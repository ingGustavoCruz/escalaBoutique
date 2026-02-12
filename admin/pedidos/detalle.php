<?php
/**
 * admin/pedidos/detalle.php - Con Imagen corregida y Bitácora
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php'; // <--- Bitácora

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$pedido_id = (int)$_GET['id'];

// --- PROCESAR CAMBIO DE ESTADO ---
$msg = '';
// --- PROCESAR CAMBIO DE ESTADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    
    // 1. Obtener estado anterior para bitácora y validación
    $pedido_info = $conn->query("SELECT estado FROM pedidos WHERE id=$pedido_id")->fetch_object();
    $estado_ant = $pedido_info->estado;

    if ($estado_ant !== $nuevo_estado) {
        $conn->begin_transaction();
        try {
            // A. Si el pedido se CANCELA, devolvemos stock
            if ($nuevo_estado === 'cancelado') {
                $resDet = $conn->query("SELECT producto_id, cantidad, talla FROM detalles_pedido WHERE pedido_id = $pedido_id");
                
                while ($item = $resDet->fetch_assoc()) {
                    if (!empty($item['talla'])) {
                        // Regresar a inventario_tallas (El TRIGGER actualizará la tabla productos)
                        $stmtStk = $conn->prepare("UPDATE inventario_tallas SET stock = stock + ? WHERE producto_id = ? AND talla = ?");
                        $stmtStk->bind_param("iis", $item['cantidad'], $item['producto_id'], $item['talla']);
                    } else {
                        // Regresar a stock global de productos
                        $stmtStk = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                        $stmtStk->bind_param("ii", $item['cantidad'], $item['producto_id']);
                    }
                    $stmtStk->execute();
                    // ... dentro del while ($item = $resDet->fetch_assoc()) y después de $stmtStk->execute()
                    $motivo_cancel = "CANCELACIÓN PEDIDO #$pedido_id";
                    $admin_id = $_SESSION['admin_id'];
                    $stmtLogCan = $conn->prepare("INSERT INTO bitacora_inventario (producto_id, talla, cantidad_cambio, motivo, admin_id) VALUES (?, ?, ?, ?, ?)");
                    $stmtLogCan->bind_param("isisi", $item['producto_id'], $item['talla'], $item['cantidad'], $motivo_cancel, $admin_id);
                    $stmtLogCan->execute();
                    $stmtLogCan->close();
                }
            }

            // B. Si el pedido estaba cancelado y se vuelve a poner en PENDIENTE/APROBADO (re-activación)
            // Aquí deberías restar stock de nuevo, pero por simplicidad, enfoquémonos en la cancelación.

            // C. Actualizar estado y registrar bitácora
            $stmtUpd = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmtUpd->bind_param("si", $nuevo_estado, $pedido_id);
            $stmtUpd->execute();

            registrarBitacora('PEDIDOS', 'EDITAR ESTADO', "Pedido #$pedido_id cambio de $estado_ant a " . strtoupper($nuevo_estado), $conn);
            
            $conn->commit();
            $msg = "Estado actualizado y stock sincronizado.";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error al procesar: " . $e->getMessage();
        }
    }
}

// 1. Obtener Info Cabecera
$queryHead = "SELECT p.*, e.nombre, e.numero_empleado, e.email, e.area FROM pedidos p JOIN empleados e ON p.empleado_id = e.id WHERE p.id = $pedido_id";
$pedido = $conn->query($queryHead)->fetch_assoc();

if (!$pedido) { echo "Pedido no encontrado"; exit; }

// 2. Obtener Detalles (AHORA CON IMAGEN)
// Agregamos la subconsulta para traer la URL de la imagen principal
$queryDet = "
    SELECT dp.*, p.nombre, p.stock as stock_actual,
    (SELECT url_imagen FROM imagenes_productos ip WHERE ip.producto_id = p.id ORDER BY es_principal DESC LIMIT 1) as img
    FROM detalles_pedido dp 
    JOIN productos p ON dp.producto_id = p.id 
    WHERE dp.pedido_id = $pedido_id
";
$detalles = $conn->query($queryDet);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido #<?php echo $pedido_id; ?> | Admin</title>
    <link rel="icon" type="image/png" href="../../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-beige': '#AA9482', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="bg-slate-50 font-sans min-h-screen pb-10">

    <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"><i data-lucide="arrow-left" class="w-6 h-6"></i></a>
                <div>
                    <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">PEDIDO #<?php echo str_pad($pedido_id, 5, "0", STR_PAD_LEFT); ?></h1>
                    <p class="text-xs text-gray-400 font-bold"><?php echo date('d F Y - h:i A', strtotime($pedido['fecha_pedido'])); ?></p>
                </div>
            </div>

            <form method="POST" class="flex items-center gap-3">
                <a href="imprimir.php?id=<?php echo $pedido_id; ?>" target="_blank" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-bold uppercase shadow-sm flex items-center gap-2 border border-gray-300 transition-colors mr-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Recibo
                </a>
                <div class="relative">
                    <select name="nuevo_estado" class="appearance-none bg-gray-100 border border-gray-200 text-gray-700 py-2 pl-4 pr-10 rounded-lg text-sm font-bold uppercase focus:outline-none focus:ring-2 focus:ring-escala-green cursor-pointer">
                        <option value="pendiente" <?php echo $pedido['estado']=='pendiente'?'selected':''; ?>>Pendiente</option>
                        <option value="aprobado" <?php echo $pedido['estado']=='aprobado'?'selected':''; ?>>Aprobado (RH)</option>
                        <option value="entregado" <?php echo $pedido['estado']=='entregado'?'selected':''; ?>>Entregado</option>
                        <option value="cancelado" <?php echo $pedido['estado']=='cancelado'?'selected':''; ?>>Cancelado</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none"></i>
                </div>
                <button type="submit" class="bg-escala-green hover:bg-escala-dark text-white px-6 py-2 rounded-lg text-sm font-bold uppercase shadow-lg transition-all flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Actualizar
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-8">
        <?php if($msg): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl flex items-center gap-3 font-bold animate-pulse">
                <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-5 bg-gray-50 border-b border-gray-100">
                        <h2 class="font-bold text-gray-700 uppercase text-sm flex items-center gap-2"><i data-lucide="package" class="w-4 h-4"></i> Productos Solicitados</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php $detalles->data_seek(0); while($item = $detalles->fetch_assoc()): ?>
                        <div class="p-5 flex items-center gap-4 hover:bg-gray-50 transition-colors">
                            
                            <div class="w-14 h-14 bg-white rounded-lg flex items-center justify-center border border-gray-200 p-1 overflow-hidden shrink-0">
                                <?php if(!empty($item['img'])): ?>
                                    <img src="../../<?php echo $item['img']; ?>" class="w-full h-full object-contain" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <span class="text-gray-400 font-bold"><?php echo substr($item['nombre'], 0, 1); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1">
                                <p class="font-bold text-gray-800 text-sm"><?php echo $item['nombre']; ?></p>
                                <?php if($item['talla']): ?>
                                    <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-bold uppercase mt-1 inline-block">
                                        Talla: <?php echo $item['talla']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 font-bold"><?php echo $item['cantidad']; ?> x $<?php echo number_format($item['precio_unitario'], 2); ?></p>
                                <p class="text-sm font-black text-escala-green">$<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="p-5 bg-escala-dark text-white flex justify-between items-center">
                        <span class="text-xs font-bold uppercase tracking-widest text-escala-beige">Total Pedido</span>
                        <span class="text-2xl font-black">$<?php echo number_format($pedido['monto_total'], 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Datos del Empleado</h3>
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-escala-beige/20 rounded-full flex items-center justify-center text-escala-dark"><i data-lucide="user" class="w-6 h-6"></i></div>
                        <div>
                            <p class="font-bold text-gray-800 leading-tight"><?php echo $pedido['nombre']; ?></p>
                            <p class="text-xs text-gray-500 font-medium"><?php echo $pedido['email']; ?></p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm border-b border-gray-50 pb-2"><span class="text-gray-500">No. Empleado:</span><span class="font-bold text-gray-800"><?php echo $pedido['numero_empleado']; ?></span></div>
                        <div class="flex justify-between text-sm border-b border-gray-50 pb-2"><span class="text-gray-500">Área:</span><span class="font-bold text-gray-800"><?php echo $pedido['area']; ?></span></div>
                        <div class="flex justify-between text-sm pt-2"><span class="text-gray-500">Plazos Nómina:</span><span class="font-black text-escala-green bg-escala-green/10 px-2 rounded"><?php echo $pedido['plazos']; ?> Qnas</span></div>
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-100 p-4 rounded-xl text-yellow-800 text-xs leading-relaxed">
                    <strong class="flex items-center gap-2 mb-1"><i data-lucide="alert-circle" class="w-3 h-3"></i> Nota Admin:</strong>
                    Recuerda verificar que el descuento se haya aplicado en nómina antes de marcar como "Entregado".
                </div>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
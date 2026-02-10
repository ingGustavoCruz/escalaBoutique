<?php
/**
 * mis_pedidos.php - Historial de Compras
 * Versión Final: Footer Corporativo + Fix Imágenes + Optimización SQL
 */
require_once 'api/conexion.php';
session_start();

// --- MODO PRUEBAS ACTIVADO ---
// Comentamos la seguridad estricta para que puedas ver el diseño
// if (!isset($_SESSION['empleado_id_db'])) { header("Location: index.php"); exit; }

// FORZAMOS EL ID 1 (Que es el que tiene los 4 pedidos en tu base de datos)
$empleado_id = 1; 

// Datos "Hardcodeados" para que el encabezado se vea bien
$empleado_nombre = "Empleado Pruebas";
$empleado_num = "EMP001";

// 2. OBTENER PEDIDOS (Optimizado)
$pedidos = [];

// Traemos los pedidos principales
$stmt = $conn->prepare("
    SELECT id, fecha_pedido, monto_total, estado, plazos 
    FROM pedidos 
    WHERE empleado_id = ? 
    ORDER BY fecha_pedido DESC
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$res = $stmt->get_result();

while($row = $res->fetch_assoc()) {
    $pid = $row['id'];
    
    // Consulta de detalles (Mantenemos tu lógica de imagen, es correcta)
    // Nota: Para sistemas muy grandes esto se optimizaría con un JOIN masivo,
    // pero para este volumen tu enfoque es claro y funcional.
    $stmtDet = $conn->prepare("
        SELECT 
            dp.cantidad, 
            dp.precio_unitario, 
            dp.talla, 
            p.nombre,
            (SELECT url_imagen FROM imagenes_productos ip WHERE ip.producto_id = p.id ORDER BY ip.es_principal DESC LIMIT 1) as imagen_url
        FROM detalles_pedido dp
        JOIN productos p ON dp.producto_id = p.id
        WHERE dp.pedido_id = ?
    ");
    
    $stmtDet->bind_param("i", $pid);
    $stmtDet->execute();
    $resDet = $stmtDet->get_result();
    
    $productos_detalle = [];
    while($prod = $resDet->fetch_assoc()) {
        // Fallback de imagen
        $prod['img'] = !empty($prod['imagen_url']) ? $prod['imagen_url'] : 'imagenes/torito.png';
        $productos_detalle[] = $prod;
    }
    $stmtDet->close();
    
    $row['items'] = $productos_detalle;
    $pedidos[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es" x-data="{ openDetail: null }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos | Escala Boutique</title>
    <link rel="icon" type="image/png" href="imagenes/monito01.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'escala-green': '#00524A',
                        'escala-beige': '#AA9482',
                        'escala-dark': '#003d36',
                        'escala-blue': '#1e3a8a',
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-gray-800 font-sans flex flex-col">

    <header class="bg-escala-green py-4 shadow-lg sticky top-0 z-30 border-b border-escala-dark">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center">
            
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-white hover:text-escala-beige transition-colors p-1 rounded-full hover:bg-white/10">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <h1 class="font-black uppercase tracking-widest text-lg md:text-xl text-white">
                    MIS PEDIDOS
                </h1>
            </div>

            <div class="flex flex-col items-end text-right">
                <span class="text-[10px] font-bold text-gray-300 uppercase tracking-wider">BIENVENIDO</span>
                <span class="text-sm font-bold text-escala-beige truncate max-w-[200px]"><?php echo htmlspecialchars($empleado_nombre); ?></span>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 py-10 w-full flex-grow">
        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8 flex flex-col md:flex-row items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-escala-green/10 rounded-full flex items-center justify-center text-escala-green">
                    <i data-lucide="user" class="w-8 h-8"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-escala-green uppercase"><?php echo htmlspecialchars($empleado_nombre); ?></h2>
                    <p class="text-sm text-gray-500 font-bold">Nómina: <span class="text-gray-800"><?php echo htmlspecialchars($empleado_num); ?></span></p>
                </div>
            </div>
            <div class="text-center md:text-right">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Total Compras</p>
                <p class="text-3xl font-black text-escala-dark"><?php echo count($pedidos); ?></p>
            </div>
        </div>

        <h3 class="text-lg font-black uppercase text-gray-700 mb-4 flex items-center gap-2">
            <i data-lucide="history" class="w-5 h-5 text-escala-beige"></i> Historial Reciente
        </h3>

        <?php if(empty($pedidos)): ?>
            <div class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border-2 border-dashed border-gray-200 text-gray-400">
                <i data-lucide="shopping-bag" class="w-16 h-16 mb-4 opacity-30"></i>
                <p class="font-bold text-lg">Aún no tienes pedidos registrados.</p>
                <a href="index.php" class="mt-4 px-6 py-2 bg-escala-green text-white rounded-full text-xs font-bold uppercase hover:bg-escala-dark transition">Ir a la Tienda</a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach($pedidos as $p): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden transition-all hover:shadow-md">
                        
                        <div class="p-5 flex flex-wrap items-center justify-between gap-4 bg-gray-50/50 border-b border-gray-100 cursor-pointer" 
                             @click="openDetail === <?php echo $p['id']; ?> ? openDetail = null : openDetail = <?php echo $p['id']; ?>">
                            
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Pedido #<?php echo str_pad($p['id'], 5, "0", STR_PAD_LEFT); ?></span>
                                <span class="text-sm font-black text-gray-800"><?php echo date('d M Y, h:i A', strtotime($p['fecha_pedido'])); ?></span>
                            </div>
                            
                            <div class="flex flex-col text-right md:text-left">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Monto Total</span>
                                <span class="text-lg font-black text-escala-green">$<?php echo number_format($p['monto_total'], 2); ?></span>
                            </div>

                            <div class="flex items-center gap-3">
                                <?php 
                                    $estado = strtolower($p['estado']);
                                    $estilo = 'bg-gray-100 text-gray-500';
                                    $icono = 'circle';

                                    if($estado == 'pendiente') { $estilo = 'bg-orange-100 text-orange-700 border border-orange-200'; $icono='clock'; }
                                    if($estado == 'aprobado') { $estilo = 'bg-blue-100 text-blue-700 border border-blue-200'; $icono='check'; }
                                    if($estado == 'entregado') { $estilo = 'bg-green-100 text-green-700 border border-green-200'; $icono='package-check'; }
                                    if($estado == 'cancelado') { $estilo = 'bg-red-100 text-red-700 border border-red-200'; $icono='x-circle'; }
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold flex items-center gap-1.5 <?php echo $estilo; ?>">
                                    <i data-lucide="<?php echo $icono; ?>" class="w-3 h-3"></i> <?php echo $estado; ?>
                                </span>

                                <div class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 transition-colors">
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-600 transition-transform duration-300" 
                                       :class="openDetail === <?php echo $p['id']; ?> ? 'rotate-180' : ''"></i>
                                </div>
                            </div>
                        </div>

                        <div x-show="openDetail === <?php echo $p['id']; ?>" x-collapse x-cloak class="bg-white border-t border-gray-100">
                            <div class="p-5">
                                <p class="text-xs font-bold text-gray-400 uppercase mb-3">Productos comprados:</p>
                                <div class="space-y-3">
                                    <?php foreach($p['items'] as $item): ?>
                                        <div class="flex items-center gap-4 p-2 hover:bg-gray-50 rounded-lg transition-colors border border-transparent hover:border-gray-100">
                                            <div class="w-12 h-12 bg-white rounded-lg border border-gray-100 flex items-center justify-center shrink-0 p-1 overflow-hidden">
                                                <img src="<?php echo $item['img']; ?>" class="w-full h-full object-contain" 
                                                     onerror="this.onerror=null; this.src='imagenes/torito.png';">
                                            </div>
                                            
                                            <div class="flex-grow">
                                                <p class="text-sm font-bold text-gray-800 leading-tight"><?php echo $item['nombre']; ?></p>
                                                <?php if(!empty($item['talla'])): ?>
                                                    <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-bold mt-1 inline-block border border-gray-200 uppercase">
                                                        Talla: <?php echo $item['talla']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="text-right flex flex-col items-end">
                                                <span class="text-xs font-bold text-gray-500 mb-1">x<?php echo $item['cantidad']; ?></span>
                                                <span class="text-sm font-black text-escala-green">$<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center bg-gray-50/50 -mx-5 -mb-5 px-5 py-3">
                                    <span class="text-[10px] text-gray-500 font-bold uppercase flex items-center gap-1">
                                        <i data-lucide="calendar-clock" class="w-3 h-3"></i> 
                                        Plan de Pago: <span class="text-escala-dark"><?php echo $p['plazos']; ?> Quincena(s)</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-gray-50 py-6 border-t-2 border-escala-blue mt-auto">
        <div class="max-w-[1400px] mx-auto px-4 flex flex-col items-center justify-center">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.3em] mb-4">Powered By</p>
            <img src="imagenes/KAI_NA.png" alt="KAI Experience" class="h-10 w-auto opacity-80 grayscale hover:grayscale-0 transition-all">
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
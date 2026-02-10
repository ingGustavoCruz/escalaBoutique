<?php
/**
 * admin/cupones/index.php - Diseño Restaurado + Bitácora
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php'; // <--- Bitácora integrada

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

$msg = ''; $msgType = '';

// --- LÓGICA PHP ---

// 1. Crear Cupón
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_cupon'])) {
    $nombre = trim($_POST['nombre_interno']);
    $codigo = strtoupper(trim($_POST['codigo']));
    $tipo = $_POST['tipo_descuento'];
    $valor = (float)$_POST['valor'];
    $limite = (int)$_POST['limite_usos'];
    $vence = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : NULL;

    if(empty($nombre) || empty($codigo) || $valor <= 0) {
         $msg = "Faltan datos obligatorios."; $msgType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO cupones (nombre_interno, codigo, tipo_descuento, valor, limite_usos, fecha_vencimiento) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdis", $nombre, $codigo, $tipo, $valor, $limite, $vence);
        
        try {
            if ($stmt->execute()) {
                // Registro en Bitácora
                registrarBitacora('CUPONES', 'CREAR', "Creó cupón: $codigo ($valor $tipo)", $conn);
                
                $msg = "Cupón creado exitosamente."; $msgType = 'success';
                header("Location: index.php?msg=created"); exit;
            }
        } catch (mysqli_sql_exception $e) {
             $msg = ($e->getCode() == 1062) ? "Error: El código '$codigo' ya existe." : "Error en BD: " . $e->getMessage();
             $msgType = 'error';
        }
    }
}

// 2. Acciones GET
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $datoPrevio = $conn->query("SELECT codigo FROM cupones WHERE id=$id")->fetch_assoc();
    $codRef = $datoPrevio['codigo'] ?? 'ID '.$id;

    if ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM cupones WHERE id = $id");
        registrarBitacora('CUPONES', 'ELIMINAR', "Eliminó cupón: $codRef", $conn);
        header("Location: index.php?msg=deleted"); exit;
    } elseif ($_GET['action'] === 'toggle') {
        $conn->query("UPDATE cupones SET estado = IF(estado='activo','inactivo','activo') WHERE id = $id");
        registrarBitacora('CUPONES', 'ESTADO', "Cambió estado de cupón: $codRef", $conn);
        header("Location: index.php?msg=toggled"); exit;
    }
}

// Mensajes
if (isset($_GET['msg'])) {
    if($_GET['msg']=='created') { $msg='Cupón creado correctamente.'; $msgType='success'; }
    if($_GET['msg']=='deleted') { $msg='Cupón eliminado.'; $msgType='success'; }
    if($_GET['msg']=='toggled') { $msg='Estado actualizado.'; $msgType='success'; }
}

$cupones = $conn->query("SELECT *, (limite_usos > 0 AND usos_actuales >= limite_usos) as agotado, (fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()) as vencido FROM cupones ORDER BY fecha_creacion DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cupones | Escala Admin</title>
    <link rel="icon" type="image/png" href="../../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-beige': '#AA9482', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden" x-data="{ showModal: false, couponForImage: null }">

    <aside class="w-64 bg-escala-dark text-escala-beige flex flex-col flex-shrink-0 shadow-2xl z-20">
        <div class="p-6 flex flex-col items-center justify-center border-b border-white/10 bg-escala-green/20">
            <img src="../../imagenes/EscalaBoutique.png" alt="Escala" class="h-8 w-auto object-contain mb-2">
            <span class="font-black text-[10px] text-white uppercase tracking-widest">Administrador</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
            <a href="../dashboard.php" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all"><i data-lucide="layout-dashboard" class="w-5 h-5"></i> <span class="font-medium text-sm">Dashboard</span></a>
            <a href="../bitacora/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all"><i data-lucide="scroll-text" class="w-5 h-5"></i> <span class="font-medium text-sm">Bitácora</span></a>
            <a href="../pedidos/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all"><i data-lucide="shopping-bag" class="w-5 h-5"></i> <span class="font-medium text-sm">Pedidos</span></a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl shadow-inner transition-all"><i data-lucide="ticket" class="w-5 h-5"></i> <span class="font-bold text-sm">Cupones</span></a>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8 border-b border-gray-100 z-10">
             <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">CUPONES PROMOCIONALES</h1>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-xl font-bold flex items-center gap-2 <?php echo $msgType=='success' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200'; ?>">
                    <i data-lucide="<?php echo $msgType=='success'?'check-circle':'alert-circle'; ?>" class="w-5 h-5"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <div class="xl:col-span-1">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-0">
                        <h2 class="font-bold text-escala-dark uppercase text-sm mb-6 flex items-center gap-2">
                            <i data-lucide="plus-circle" class="w-4 h-4 text-escala-green"></i> Nuevo Cupón
                        </h2>
                        <form method="POST" class="space-y-5">
                            <input type="hidden" name="crear_cupon" value="1">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Nombre Interno (Campaña)</label>
                                <input type="text" name="nombre_interno" required placeholder="Ej. San Valentín" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-medium text-gray-700 placeholder-gray-300">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Código (Se hará mayúsculas)</label>
                                <input type="text" name="codigo" required placeholder="Ej. AMOR2026" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-black text-escala-dark uppercase tracking-wider text-lg" onkeyup="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Tipo Oferta</label>
                                    <select name="tipo_descuento" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-gray-700 text-sm font-bold">
                                        <option value="porcentaje">Porcentaje (%)</option>
                                        <option value="fijo">Monto Fijo ($)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Valor</label>
                                    <input type="number" step="0.01" name="valor" required placeholder="Ej: 20" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Límite Usos</label>
                                    <input type="number" name="limite_usos" value="0" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-gray-700">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Vence (Opcional)</label>
                                    <input type="date" name="fecha_vencimiento" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-gray-700 text-xs">
                                </div>
                            </div>
                            <button type="submit" class="w-full py-3.5 bg-escala-dark hover:bg-escala-green text-white rounded-xl font-bold uppercase text-xs tracking-widest shadow-lg hover:shadow-xl transition-all flex justify-center gap-2 mt-4">
                                <i data-lucide="save" class="w-4 h-4"></i> Crear Cupón
                            </button>
                        </form>
                    </div>
                </div>

                <div class="xl:col-span-2">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                         <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-white">
                            <h3 class="font-bold text-escala-dark uppercase text-xs tracking-widest">Listado de Cupones</h3>
                            <span class="text-[9px] font-bold text-gray-300 uppercase tracking-widest">Ordenados por fecha</span>
                        </div>
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-wider border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4">Cupón</th>
                                    <th class="px-6 py-4">Oferta</th>
                                    <th class="px-6 py-4">Estado</th>
                                    <th class="px-6 py-4">Usos</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </div>
                            </thead>
                            <tbody class="divide-y divide-gray-50 text-sm font-medium text-gray-600">
                                <?php while($row = $cupones->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-gray-800 mb-1"><?php echo $row['nombre_interno']; ?></p>
                                        <span class="text-[10px] font-black text-escala-green bg-escala-green/10 px-2 py-1 rounded uppercase tracking-wider border border-escala-green/20"><?php echo $row['codigo']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 font-black text-xl text-escala-dark">
                                        <?php echo $row['tipo_descuento'] === 'porcentaje' ? round($row['valor']).'%' : '$'.number_format($row['valor'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                            if ($row['agotado']) echo '<span class="px-2 py-1 rounded bg-gray-100 text-gray-500 text-[10px] font-bold uppercase">Agotado</span>';
                                            elseif ($row['vencido']) echo '<span class="px-2 py-1 rounded bg-red-100 text-red-500 text-[10px] font-bold uppercase">Vencido</span>';
                                            elseif ($row['estado'] === 'activo') echo '<span class="px-2 py-1 rounded bg-green-100 text-green-600 text-[10px] font-bold uppercase">Activo</span>';
                                            else echo '<span class="px-2 py-1 rounded bg-yellow-100 text-yellow-600 text-[10px] font-bold uppercase">Pausado</span>';
                                        ?>
                                        <?php if($row['fecha_vencimiento']): ?>
                                            <p class="text-[9px] text-gray-400 mt-1">Vence: <?php echo date('d/m/y', strtotime($row['fecha_vencimiento'])); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-500">
                                        <?php echo $row['usos_actuales']; ?> / <?php echo $row['limite_usos'] == 0 ? '∞' : $row['limite_usos']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="showModal = true; couponForImage = {codigo: '<?php echo $row['codigo']; ?>', oferta: '<?php echo $row['tipo_descuento'] === 'porcentaje' ? round($row['valor']).'% DTO.' : '$'.number_format($row['valor'],0).' MXN'; ?>', nombre: '<?php echo $row['nombre_interno']; ?>'}" 
                                                    class="p-2 bg-blue-50 text-blue-500 rounded-lg hover:bg-blue-100 transition-colors tooltip" title="Descargar Imagen">
                                                <i data-lucide="download" class="w-4 h-4"></i>
                                            </button>
                                            <a href="index.php?action=toggle&id=<?php echo $row['id']; ?>" class="p-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors tooltip" title="Pausar/Activar">
                                                <i data-lucide="<?php echo $row['estado']==='activo' ? 'pause' : 'play'; ?>" class="w-4 h-4"></i>
                                            </a>
                                            <a href="index.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar cupón?')" class="p-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors tooltip" title="Eliminar">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div class="bg-white rounded-2xl p-8 max-w-3xl w-full relative shadow-2xl overflow-hidden" @click.away="showModal = false">
                <button @click="showModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
                <h3 class="text-xl font-black text-escala-dark uppercase mb-6 text-center">Vista Previa del Cupón</h3>
                
                <div id="coupon-capture-area" class="bg-white rounded-[2rem] overflow-hidden relative mb-6 shadow-2xl mx-auto flex" style="width: 600px; height: 280px; border: 4px solid #AA9482;">
                    <div class="w-[65%] bg-escala-green p-6 flex flex-col justify-center items-center relative text-white text-center">
                        <div class="absolute inset-0 opacity-10 bg-[url('../../imagenes/EscalaBoutiqueCompleto.png')] bg-center bg-no-repeat bg-cover pointer-events-none mix-blend-overlay"></div>
                        <div class="h-20 mb-2 flex items-center justify-center relative z-10">
                             <img src="../../imagenes/EscalaBoutique.png" class="h-full w-auto object-contain filter drop-shadow-md">
                        </div>
                        <h2 class="font-black text-6xl uppercase leading-none mb-2 relative z-10 tracking-tighter drop-shadow-sm" x-text="couponForImage?.oferta"></h2>
                        <p class="text-escala-beige text-xl font-bold uppercase tracking-[0.3em] relative z-10" x-text="couponForImage?.nombre"></p>
                    </div>
                    <div class="relative h-full w-0 border-l-[3px] border-dashed border-escala-beige/60 bg-white">
                        <div class="absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2 w-8 h-8 bg-white rounded-full border-b-[4px] border-escala-beige z-20"></div>
                        <div class="absolute bottom-0 left-0 -translate-x-1/2 translate-y-1/2 w-8 h-8 bg-white rounded-full border-t-[4px] border-escala-beige z-20"></div>
                    </div>
                    <div class="w-[35%] bg-white p-4 flex flex-col justify-center items-center text-center relative z-10">
                        <span class="text-[10px] font-black text-escala-green/60 uppercase tracking-[0.2em] mb-2">Código de Canje</span>
                        <div class="border-[3px] border-escala-green py-4 px-2 rounded-xl bg-green-50/30 w-full mb-3 shadow-inner flex items-center justify-center">
                            <span class="font-black text-2xl text-escala-green uppercase tracking-wide whitespace-nowrap" x-text="couponForImage?.codigo"></span>
                        </div>
                        <div class="text-center">
                            <p class="text-[9px] text-gray-400 font-bold uppercase leading-tight">Válido exclusivamente en</p>
                            <p class="text-[10px] text-escala-beige font-black uppercase">Intranet Escala</p>
                        </div>
                    </div>
                </div>

                <button onclick="downloadCoupon()" class="w-full py-3 bg-escala-green hover:bg-escala-dark text-white rounded-xl font-bold uppercase shadow-md transition-all flex justify-center gap-2">
                    <i data-lucide="download" class="w-5 h-5"></i> Descargar Imagen para WhatsApp
                </button>
            </div>
        </div>

    </main>
    <script>
        lucide.createIcons();
        function downloadCoupon() {
            const element = document.getElementById('coupon-capture-area');
            html2canvas(element, { scale: 2, backgroundColor: null }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Cupon-Escala-' + document.querySelector('[x-text="couponForImage?.codigo"]').innerText + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>
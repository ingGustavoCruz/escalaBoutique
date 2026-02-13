<?php
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// Variable para el Sidebar
$ruta_base = "../../"; 

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

$msg = ''; $msgType = '';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

// --- FILTROS ---
$f_inicio = $_GET['f_inicio'] ?? '';
$f_fin    = $_GET['f_fin'] ?? '';
$motivo   = $_GET['motivo'] ?? '';

$where = "WHERE 1=1";
if ($f_inicio) $where .= " AND b.fecha >= '" . $conn->real_escape_string($f_inicio) . " 00:00:00'";
if ($f_fin)    $where .= " AND b.fecha <= '" . $conn->real_escape_string($f_fin) . " 23:59:59'";
if ($motivo)   $where .= " AND b.motivo LIKE '%" . $conn->real_escape_string($motivo) . "%'";

// Query para el contador de registros (estilo "12 Total" de tu captura)
$total_res = $conn->query("SELECT COUNT(*) as total FROM bitacora_inventario b JOIN productos p ON b.producto_id = p.id $where");
$total_registros = $total_res->fetch_assoc()['total'];

$query = "
    SELECT b.*, p.nombre as producto, u.nombre as administrador
    FROM bitacora_inventario b
    JOIN productos p ON b.producto_id = p.id
    LEFT JOIN usuarios_admin u ON b.admin_id = u.id
    $where
    ORDER BY b.fecha DESC
";
$resultado = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Cupones | Escala Admin</title>
    <link rel="icon" type="image/png" href="../../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-beige': '#AA9482', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="flex h-screen overflow-hidden">
    
    <?php include '../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-10">
        <header class="flex justify-between items-center mb-12">
            <div class="flex items-center gap-6">
                <h1 class="text-[26px] font-black text-escala-green uppercase tracking-tight">Auditoría de Inventario</h1>
                <span class="bg-slate-100 text-slate-400 text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest">
                    <?= $total_registros ?> Total
                </span>
            </div>
            
            <div class="flex items-center gap-3">
                <button onclick="window.location.reload()" class="bg-white border border-gray-200 text-slate-500 px-5 py-2 rounded-xl text-[11px] font-bold uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                    <i data-lucide="refresh-cw" class="w-3 h-3"></i> Actualizar
                </button>
            </div>
        </header>

        <section class="mb-10 bg-white p-7 rounded-[2.5rem] border border-gray-100 shadow-[0_10px_30px_-15px_rgba(0,0,0,0.05)]">
            <form method="GET" class="flex flex-wrap items-end gap-6">
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-[9px] font-black text-slate-300 uppercase mb-3 ml-1 tracking-[0.2em]">Desde</label>
                    <input type="date" name="f_inicio" value="<?= $f_inicio ?>" class="w-full bg-slate-50 border-none rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-600 focus:ring-2 focus:ring-escala-beige/30 transition-all outline-none">
                </div>
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-[9px] font-black text-slate-300 uppercase mb-3 ml-1 tracking-[0.2em]">Hasta</label>
                    <input type="date" name="f_fin" value="<?= $f_fin ?>" class="w-full bg-slate-50 border-none rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-600 focus:ring-2 focus:ring-escala-beige/30 transition-all outline-none">
                </div>
                <div class="flex-[2] min-w-[240px]">
                    <label class="block text-[9px] font-black text-slate-300 uppercase mb-3 ml-1 tracking-[0.2em]">Buscar por Motivo</label>
                    <input type="text" name="motivo" value="<?= $motivo ?>" placeholder="Ej: VENTA PEDIDO #..." 
                           class="w-full bg-slate-50 border-none rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-600 focus:ring-2 focus:ring-escala-beige/30 transition-all outline-none">
                </div>
                <button type="submit" class="bg-escala-green text-white p-4 rounded-2xl hover:bg-escala-dark transition-all shadow-lg shadow-escala-green/20">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </button>
            </form>
        </section>

        <div class="bg-white rounded-[2.5rem] shadow-[0_20px_50px_-20px_rgba(0,0,0,0.02)] border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/40 border-b border-gray-100">
                    <tr>
                        <th class="px-12 py-7 text-[10px] uppercase font-black text-slate-300 tracking-[0.15em]">Fecha y Hora</th>
                        <th class="px-8 py-7 text-[10px] uppercase font-black text-slate-300 tracking-[0.15em]">Producto / Detalle</th>
                        <th class="px-8 py-7 text-[10px] uppercase font-black text-slate-300 tracking-[0.15em] text-center">Cambio</th>
                        <th class="px-12 py-7 text-[10px] uppercase font-black text-slate-300 tracking-[0.15em]">Motivo y Responsable</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if($resultado->num_rows > 0): ?>
                        <?php while($row = $resultado->fetch_assoc()): 
                            $is_positive = $row['cantidad_cambio'] > 0;
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-12 py-8">
                                <div class="font-bold text-slate-800 text-[15px]"><?= date('d/m/Y', strtotime($row['fecha'])) ?></div>
                                <div class="text-[11px] text-slate-400 font-medium mt-1 uppercase"><?= date('h:i A', strtotime($row['fecha'])) ?></div>
                            </td>
                            <td class="px-8 py-8">
                                <div class="font-extrabold text-escala-green text-[15px] uppercase tracking-tight"><?= $row['producto'] ?></div>
                                <?php if($row['talla']): ?>
                                    <span class="inline-block mt-2 text-[9px] bg-slate-100 text-slate-500 px-2 py-1 rounded font-black uppercase">Talla: <?= $row['talla'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-8 text-center">
                                <span class="inline-block px-4 py-1.5 rounded-full font-black text-xs <?= $is_positive ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' ?>">
                                    <?= ($is_positive ? '+' : '') . $row['cantidad_cambio'] ?>
                                </span>
                            </td>
                            <td class="px-12 py-8">
                                <div class="text-[13px] font-bold text-slate-600 uppercase mb-1"><?= $row['motivo'] ?></div>
                                <div class="text-[10px] text-escala-beige font-black uppercase tracking-wider">Por: <?= $row['administrador'] ?? 'Sistema Escala' ?></div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="py-32 text-center">
                                <div class="bg-slate-50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i data-lucide="package-search" class="w-10 h-10 text-slate-200"></i>
                                </div>
                                <p class="text-slate-300 font-black uppercase text-[11px] tracking-[0.3em]">Sin registros que mostrar</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
<?php
session_start();
require_once '../../api/conexion.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

// --- 1. CAPTURA DE FILTROS ---
$f_inicio = $_GET['f_inicio'] ?? '';
$f_fin    = $_GET['f_fin'] ?? '';
$area_sel = $_GET['area'] ?? '';

// --- 2. CONSTRUCCIÓN DE QUERY DINÁMICA CON PRIORIDAD ---
// Añadimos la subquery: Solo mostrar el ID mínimo (cuota más vieja) de cada pedido que esté pendiente
$where = "WHERE pn.estado = 'pendiente' 
          AND p.estado = 'aprobado' 
          AND pn.id IN (
              SELECT MIN(id) 
              FROM pagos_nomina 
              WHERE estado = 'pendiente' 
              GROUP BY pedido_id
          )";

if (!empty($f_inicio)) {
    $where .= " AND p.fecha_pedido >= '" . $conn->real_escape_string($f_inicio) . " 00:00:00'";
}
if (!empty($f_fin)) {
    $where .= " AND p.fecha_pedido <= '" . $conn->real_escape_string($f_fin) . " 23:59:59'";
}
if (!empty($area_sel)) {
    $where .= " AND e.area = '" . $conn->real_escape_string($area_sel) . "'";
}

$query = "
    SELECT 
        pn.id as pago_id, pn.numero_cuota, pn.total_cuotas, pn.monto_cuota,
        p.id as pedido_id, p.monto_total, p.plazos, p.fecha_pedido,
        e.nombre as empleado, e.numero_empleado, e.area
    FROM pagos_nomina pn
    JOIN pedidos p ON pn.pedido_id = p.id
    JOIN empleados e ON pn.empleado_id = e.id
    $where
    ORDER BY p.fecha_pedido ASC
";
$resultado = $conn->query($query);

// Obtener áreas únicas para el dropdown
$areas_query = $conn->query("SELECT DISTINCT area FROM empleados WHERE area IS NOT NULL AND area != '' ORDER BY area ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Quincena | Escala Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'escala-green': '#00524A',
                        'escala-dark': '#003d36',
                        'escala-beige': '#AA9482',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

    <main class="flex-1 overflow-y-auto p-8">
        <header class="flex justify-between items-start mb-10">
            <div class="flex items-center gap-4">
                <a href="index.php" class="p-2 hover:bg-white rounded-xl transition-all shadow-sm border border-transparent hover:border-gray-200 group"><i data-lucide="arrow-left" class="w-6 h-6 text-gray-400 group-hover:text-escala-green"></i></a>
                <div>
                    <h1 class="text-2xl font-black text-escala-green uppercase tracking-tighter">Corte de Quincena</h1>
                    <p class="text-gray-400 text-xs font-medium uppercase tracking-wider">Pedidos aprobados pendientes de procesar en nómina.</p>
                </div>
            </div>

            <?php if($resultado->num_rows > 0): ?>
            <a href="exportar_csv.php?f_inicio=<?php echo $f_inicio; ?>&f_fin=<?php echo $f_fin; ?>&area=<?php echo urlencode($area_sel); ?>" 
               class="bg-escala-green hover:bg-escala-dark text-white px-6 py-3.5 rounded-xl font-bold uppercase text-[11px] shadow-lg shadow-escala-green/20 flex items-center gap-3 transition-all hover:-translate-y-0.5 active:translate-y-0">
                <i data-lucide="download" class="w-4 h-4"></i> Descargar Layout y Cerrar Corte
            </a>
            <?php endif; ?>
        </header>

        <section class="mb-8 bg-white p-6 rounded-[2rem] border border-gray-100 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-6">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Desde</label>
                    <input type="date" name="f_inicio" value="<?php echo $f_inicio; ?>" 
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-escala-beige transition-all">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Hasta</label>
                    <input type="date" name="f_fin" value="<?php echo $f_fin; ?>" 
                           class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-escala-beige transition-all">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Área / Departamento</label>
                    <select name="area" class="w-full bg-slate-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-escala-beige transition-all appearance-none">
                        <option value="">TODAS LAS ÁREAS</option>
                        <?php while($area = $areas_query->fetch_assoc()): ?>
                            <option value="<?php echo $area['area']; ?>" <?php echo $area_sel == $area['area'] ? 'selected' : ''; ?>>
                                <?php echo strtoupper($area['area']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-slate-800 text-white p-3.5 rounded-xl hover:bg-black transition-colors shadow-md">
                        <i data-lucide="filter" class="w-5 h-5"></i>
                    </button>
                    <?php if($f_inicio || $f_fin || $area_sel): ?>
                        <a href="corte.php" class="bg-gray-100 text-gray-500 p-3.5 rounded-xl hover:bg-gray-200 transition-colors shadow-sm" title="Limpiar Filtros">
                            <i data-lucide="refresh-ccw" class="w-5 h-5"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-10 py-6 text-[10px] uppercase font-black text-gray-400 tracking-widest">Empleado</th>
                        <th class="px-6 py-6 text-[10px] uppercase font-black text-gray-400 tracking-widest text-center">Cuota / Plazos</th>
                        <th class="px-6 py-6 text-[10px] uppercase font-black text-gray-400 tracking-widest text-right">Total Pedido</th>
                        <th class="px-10 py-6 text-[10px] uppercase font-black text-escala-green tracking-widest text-right">Descuento Qna.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php 
                    $total_corte = 0;
                    while($row = $resultado->fetch_assoc()): 
                        $total_corte += $row['monto_cuota'];
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <td class="px-10 py-7">
                            <div class="font-bold text-slate-800 text-base"><?php echo $row['empleado']; ?></div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-gray-400 font-bold tracking-tight">ID #<?php echo $row['numero_empleado']; ?></span>
                                <span class="text-[9px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $row['area']; ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-7 text-center">
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-black bg-slate-100 text-slate-600 uppercase">
                                <?php echo $row['numero_cuota']; ?> de <?php echo $row['total_cuotas']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-7 text-right">
                            <span class="text-sm font-bold text-slate-400">$<?php echo number_format($row['monto_total'], 2); ?></span>
                        </td>
                        <td class="px-10 py-7 text-right">
                            <span class="text-lg font-black text-escala-green">$<?php echo number_format($row['monto_cuota'], 2); ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                
                <?php if($resultado->num_rows > 0): ?>
                <tfoot class="bg-slate-50/30 border-t-2 border-slate-100">
                    <tr>
                        <td colspan="3" class="px-10 py-8 text-right">
                            <span class="text-[11px] font-black text-escala-green uppercase tracking-[0.2em]">Total a descontar esta quincena:</span>
                        </td>
                        <td class="px-10 py-8 text-right">
                            <div class="inline-block relative">
                                <span class="text-3xl font-black text-escala-green tracking-tighter">$<?php echo number_format($total_corte, 2); ?></span>
                                <div class="absolute -bottom-1 left-0 w-full h-1.5 bg-escala-green/10 rounded-full"></div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>

            <?php if($resultado->num_rows === 0): ?>
            <div class="py-32 flex flex-col items-center justify-center">
                <div class="bg-slate-50 p-6 rounded-full mb-4">
                    <i data-lucide="shield-check" class="w-12 h-12 text-slate-200"></i>
                </div>
                <p class="text-slate-400 font-bold uppercase text-[10px] tracking-[0.3em]">No hay pagos pendientes con estos filtros</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
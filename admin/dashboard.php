<?php
/**
 * admin/dashboard.php - Dashboard Pro con Reportes, Gráficos y Rendimiento de Cupones
 */
session_start();
require_once '../api/conexion.php';

// Variable para el Sidebar incluido
$ruta_base = "../"; 

// Seguridad
if (!isset($_SESSION['admin_id'])) { header("Location: index.php"); exit; }

// CIRUGÍA: Validar escudo CSRF
validar_csrf(); 

// --- 1. LÓGICA DE EXPORTACIÓN A EXCEL (CSV) ---
if (isset($_GET['export']) && $_GET['export'] == 'true') {
    $filename = "Reporte_General_Escala_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    
    fputcsv($output, ['ID Pedido', 'Fecha', 'Empleado', 'Area', 'Total', 'Estado']);
    
    $res = $conn->query("SELECT p.id, p.fecha_pedido, e.nombre, e.area, p.monto_total, p.estado FROM pedidos p JOIN empleados e ON p.empleado_id = e.id ORDER BY p.fecha_pedido DESC");
    while($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// --- 2. FILTROS DE FECHA ---
$fecha_inicio = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$fecha_fin = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

// --- 3. CONSULTAS KPI ---
$sqlVentas = "SELECT COALESCE(SUM(monto_total), 0) FROM pedidos WHERE estado != 'cancelado' AND date(fecha_pedido) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$ventasPeriodo = $conn->query($sqlVentas)->fetch_row()[0];

$sqlPedidos = "SELECT COUNT(*) FROM pedidos WHERE date(fecha_pedido) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$pedidosPeriodo = $conn->query($sqlPedidos)->fetch_row()[0];

$sqlNuevos = "SELECT COUNT(*) FROM empleados WHERE date(fecha_ultimo_acceso) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$nuevosPeriodo = $conn->query($sqlNuevos)->fetch_row()[0];

$sqlCritico = "SELECT COUNT(*) FROM productos WHERE stock <= 5";
$critico = $conn->query($sqlCritico)->fetch_row()[0];


// --- 4. DATOS PARA GRÁFICAS (Chart.js) ---
$chartLabels = []; $chartData = [];
$sqlTrend = "SELECT DATE_FORMAT(fecha_pedido, '%Y-%m') as mes, SUM(monto_total) as total 
             FROM pedidos WHERE estado != 'cancelado' 
             GROUP BY mes ORDER BY mes DESC LIMIT 6";
$resTrend = $conn->query($sqlTrend);
while($row = $resTrend->fetch_assoc()) {
    array_unshift($chartLabels, date('M Y', strtotime($row['mes'].'-01'))); 
    array_unshift($chartData, $row['total']);
}

$areaLabels = []; $areaData = [];
$sqlArea = "SELECT e.area, COUNT(p.id) as compras 
            FROM pedidos p JOIN empleados e ON p.empleado_id = e.id 
            WHERE p.estado != 'cancelado' 
            GROUP BY e.area ORDER BY compras DESC LIMIT 5";
$resArea = $conn->query($sqlArea);
while($row = $resArea->fetch_assoc()) {
    $areaLabels[] = $row['area'];
    $areaData[] = $row['compras'];
}

$topProductos = $conn->query("
    SELECT p.nombre, SUM(dp.cantidad) as vendidos, p.stock 
    FROM detalles_pedido dp JOIN productos p ON dp.producto_id = p.id 
    JOIN pedidos ped ON dp.pedido_id = ped.id
    WHERE ped.estado != 'cancelado'
    GROUP BY p.id ORDER BY vendidos DESC LIMIT 5
");

$topEmpleados = $conn->query("
    SELECT e.nombre, e.area, COUNT(p.id) as ordenes, SUM(p.monto_total) as total
    FROM pedidos p JOIN empleados e ON p.empleado_id = e.id
    WHERE p.estado != 'cancelado'
    GROUP BY e.id ORDER BY total DESC LIMIT 5
");

// CIRUGÍA: Consulta de Rendimiento de Cupones
$topCupones = $conn->query("
    SELECT codigo, usos_actuales 
    FROM cupones 
    WHERE usos_actuales > 0 
    ORDER BY usos_actuales DESC LIMIT 3
");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Dashboard Pro | Escala Admin</title>
    <link rel="icon" type="image/png" href="../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { 'escala-green': '#00524A', 'escala-beige': '#AA9482', 'escala-dark': '#003d36' } }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="flex h-screen overflow-hidden">
        
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
            
            <div class="md:hidden bg-white h-16 shadow-sm flex items-center justify-between px-4 z-20 border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="text-gray-600 hover:text-escala-green focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <span class="font-black text-escala-green uppercase tracking-wide">Escala Admin</span>
                </div>
                <div class="w-8 h-8 bg-escala-green/10 rounded-full flex items-center justify-center text-escala-green font-bold text-xs">
                    <?php echo substr($_SESSION['admin_nombre'] ?? 'A', 0, 1); ?>
                </div>
            </div>

            <header class="bg-white shadow-sm px-8 py-4 flex flex-col md:flex-row items-center justify-between gap-4 z-10 border-b border-gray-100 flex-shrink-0">
                <div class="w-full md:w-auto text-center md:text-left">
                    <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">Resumen Ejecutivo</h1>
                    <p class="text-xs text-gray-400">Visión general del negocio</p>
                </div>
                
                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                    <form class="flex items-center gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200 w-full md:w-auto justify-center">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <span class="text-[10px] font-bold text-gray-400 uppercase ml-2 hidden md:inline">Periodo:</span>
                        <input type="date" name="desde" value="<?php echo $fecha_inicio; ?>" class="bg-white border border-gray-200 text-xs rounded px-2 py-1 text-gray-600 focus:outline-none focus:border-escala-green">
                        <span class="text-gray-300">-</span>
                        <input type="date" name="hasta" value="<?php echo $fecha_fin; ?>" class="bg-white border border-gray-200 text-xs rounded px-2 py-1 text-gray-600 focus:outline-none focus:border-escala-green">
                        <button type="submit" class="bg-escala-dark hover:bg-escala-green text-white p-1.5 rounded transition-colors"><i data-lucide="search" class="w-3 h-3"></i></button>
                    </form>

                    <a href="dashboard.php?export=true&desde=<?php echo $fecha_inicio; ?>&hasta=<?php echo $fecha_fin; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase shadow-md transition-all flex items-center justify-center gap-2 w-full md:w-auto">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> <span class="md:hidden lg:inline">Exportar Excel</span>
                    </a>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">
                
                <?php if($critico > 0): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-4 text-center md:text-left">
                    <div class="flex items-center gap-3">
                        <div class="bg-red-100 p-2 rounded-full text-red-500"><i data-lucide="alert-triangle" class="w-5 h-5"></i></div>
                        <div>
                            <h3 class="text-sm font-black text-red-800 uppercase">Atención: Inventario Crítico</h3>
                            <p class="text-xs text-red-600">Hay <strong><?php echo $critico; ?> productos</strong> con stock bajo (menos de 5 unidades).</p>
                        </div>
                    </div>
                    <a href="productos/" class="text-xs font-bold text-red-700 underline hover:text-red-900 whitespace-nowrap">Ver inventario</a>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-br from-escala-green to-escala-dark rounded-2xl p-6 text-white shadow-lg relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-white/10 rounded-full translate-x-10 -translate-y-10 group-hover:scale-110 transition-transform"></div>
                        <p class="text-escala-beige text-xs font-bold uppercase tracking-widest mb-1">Ingresos (Periodo)</p>
                        <h2 class="text-4xl font-black">$<?php echo number_format($ventasPeriodo, 2); ?></h2>
                    </div>
                    
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 relative overflow-hidden group">
                        <div class="absolute right-4 top-4 bg-purple-50 p-3 rounded-xl text-purple-600 group-hover:rotate-12 transition-transform"><i data-lucide="shopping-bag" class="w-6 h-6"></i></div>
                        <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">Pedidos (Periodo)</p>
                        <h2 class="text-4xl font-black text-gray-800"><?php echo $pedidosPeriodo; ?></h2>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 relative overflow-hidden group">
                        <div class="absolute right-4 top-4 bg-green-50 p-3 rounded-xl text-green-600 group-hover:rotate-12 transition-transform"><i data-lucide="users" class="w-6 h-6"></i></div>
                        <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">Usuarios Activos</p>
                        <h2 class="text-4xl font-black text-gray-800"><?php echo $nuevosPeriodo; ?></h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-700 text-sm mb-6 flex items-center gap-2">
                            <i data-lucide="bar-chart-2" class="w-4 h-4 text-escala-green"></i> Tendencia de Ventas (6 Meses)
                        </h3>
                        <div class="h-64">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-700 text-sm mb-6 flex items-center gap-2">
                            <i data-lucide="pie-chart" class="w-4 h-4 text-escala-green"></i> Compras por Área
                        </h3>
                        <div class="h-64 flex justify-center">
                            <canvas id="areaChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-bold text-gray-700 text-sm mb-4 flex items-center gap-2">
                            <i data-lucide="star" class="w-4 h-4 text-yellow-500"></i> Top Productos
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-[10px] text-gray-400 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 rounded-l-lg">Producto</th>
                                        <th class="px-4 py-2">Stock</th>
                                        <th class="px-4 py-2 text-right rounded-r-lg">Vendidos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php while($row = $topProductos->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-gray-700"><?php echo $row['nombre']; ?></td>
                                        <td class="px-4 py-3 text-xs">
                                            <span class="<?php echo $row['stock']<5?'text-red-500 font-bold':'text-gray-500'; ?>">
                                                <?php echo $row['stock']; ?> unds.
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-escala-green"><?php echo $row['vendidos']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="font-bold text-gray-700 text-sm mb-4 flex items-center gap-2">
                                <i data-lucide="crown" class="w-4 h-4 text-purple-500"></i> Empleados VIP
                            </h3>
                            <div class="space-y-4">
                                <?php while($row = $topEmpleados->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-escala-green/10 text-escala-green rounded-full flex items-center justify-center font-bold text-xs"><?php echo substr($row['nombre'],0,1); ?></div>
                                        <div>
                                            <p class="text-xs font-bold text-gray-800 line-clamp-1"><?php echo $row['nombre']; ?></p>
                                            <p class="text-[9px] text-gray-400 uppercase"><?php echo $row['area']; ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-black text-escala-dark">$<?php echo number_format($row['total']); ?></p>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="font-bold text-gray-700 text-sm mb-4 flex items-center gap-2">
                                <i data-lucide="ticket" class="w-4 h-4 text-escala-beige"></i> Cupones más usados
                            </h3>
                            <div class="space-y-4">
                                <?php while($c = $topCupones->fetch_assoc()): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-escala-green bg-escala-green/5 px-2 py-1 rounded uppercase tracking-wider"><?php echo $c['codigo']; ?></span>
                                    <span class="text-xs font-bold text-gray-600"><?php echo $c['usos_actuales']; ?> usos</span>
                                </div>
                                <?php endwhile; ?>
                                <?php if($topCupones->num_rows === 0): ?>
                                    <p class="text-[10px] text-gray-400 italic text-center">Sin actividad en cupones.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Ventas ($)',
                    data: <?php echo json_encode($chartData); ?>,
                    borderColor: '#00524A',
                    backgroundColor: 'rgba(0, 82, 74, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#AA9482'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });

        const ctxArea = document.getElementById('areaChart').getContext('2d');
        new Chart(ctxArea, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($areaLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($areaData); ?>,
                    backgroundColor: ['#00524A', '#AA9482', '#1e3a8a', '#FF9900', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'right', labels: { usePointStyle: true, font: { size: 10 } } } }
            }
        });
    </script>
</body>
</html>
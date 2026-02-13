<?php
/**
 * admin/dashboard.php - Dashboard Pro con Auditoría Financiera Real
 */
session_start();
require_once '../api/conexion.php';

$ruta_base = "../"; 

if (!isset($_SESSION['admin_id'])) { header("Location: index.php"); exit; }

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

// --- 3. CONSULTAS KPI (PRUEBA DEL CENTAVO) ---

// A. Ingresos Recaudados (Solo cuotas ya enviadas a nómina en el periodo)
$sqlRecaudado = "SELECT COALESCE(SUM(monto_cuota), 0) FROM pagos_nomina WHERE estado = 'enviado' AND date(fecha_corte) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$recaudadoPeriodo = $conn->query($sqlRecaudado)->fetch_row()[0];

// B. Cuentas por Cobrar (Todo lo que está pendiente en el sistema globalmente)
$sqlPendiente = "SELECT COALESCE(SUM(monto_cuota), 0) FROM pagos_nomina WHERE estado = 'pendiente'";
$porRecaudarTotal = $conn->query($sqlPendiente)->fetch_row()[0];

// Pedidos y Usuarios (Se mantienen por volumen de operación)
$sqlPedidos = "SELECT COUNT(*) FROM pedidos WHERE date(fecha_pedido) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$pedidosPeriodo = $conn->query($sqlPedidos)->fetch_row()[0];

$sqlNuevos = "SELECT COUNT(*) FROM empleados WHERE date(fecha_ultimo_acceso) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$nuevosPeriodo = $conn->query($sqlNuevos)->fetch_row()[0];

$sqlCritico = "SELECT COUNT(*) FROM productos WHERE stock <= 5";
$critico = $conn->query($sqlCritico)->fetch_row()[0];


// --- 4. DATOS PARA GRÁFICAS ---
$chartLabels = []; $chartData = [];
// La tendencia ahora muestra ingresos recaudados por mes para mayor precisión financiera
$sqlTrend = "SELECT DATE_FORMAT(fecha_corte, '%Y-%m') as mes, SUM(monto_cuota) as total 
             FROM pagos_nomina WHERE estado = 'enviado' 
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Resumen Ejecutivo | Escala Boutique</title>
    <link rel="icon" type="image/png" href="../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: { 
                    colors: { 
                        'escala-green': '#00524A', 
                        'escala-beige': '#AA9482', 
                        'escala-dark': '#003d36' 
                    } 
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false }">

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
            </div>

            <header class="bg-white shadow-sm px-8 py-6 flex flex-col md:flex-row items-center justify-between gap-4 z-10 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h1 class="text-2xl font-black text-escala-green uppercase tracking-tighter">Resumen Ejecutivo</h1>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Visión financiera y operativa</p>
                </div>
                
                <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                    <form class="flex items-center gap-2 bg-slate-50 p-1.5 rounded-xl border border-gray-100">
                        <span class="text-[9px] font-black text-gray-400 uppercase ml-2 hidden md:inline tracking-widest">Periodo:</span>
                        <input type="date" name="desde" value="<?php echo $fecha_inicio; ?>" class="bg-transparent border-none text-xs font-bold text-gray-600 focus:ring-0">
                        <span class="text-gray-300">-</span>
                        <input type="date" name="hasta" value="<?php echo $fecha_fin; ?>" class="bg-transparent border-none text-xs font-bold text-gray-600 focus:ring-0">
                        <button type="submit" class="bg-escala-dark hover:bg-escala-green text-white p-2 rounded-lg transition-all shadow-md"><i data-lucide="search" class="w-4 h-4"></i></button>
                    </form>

                    <a href="dashboard.php?export=true&desde=<?php echo $fecha_inicio; ?>&hasta=<?php echo $fecha_fin; ?>" class="bg-escala-green hover:bg-escala-dark text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-escala-green/20 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4 text-escala-beige"></i> Exportar Datos
                    </a>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 space-y-8">
                
                <?php if($critico > 0): ?>
                <div class="bg-red-50 border border-red-100 p-5 rounded-[1.5rem] flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm animate-pulse">
                    <div class="flex items-center gap-4">
                        <div class="bg-red-500 text-white p-3 rounded-2xl shadow-lg shadow-red-500/20"><i data-lucide="package-search" class="w-6 h-6"></i></div>
                        <div>
                            <h3 class="text-xs font-black text-red-800 uppercase tracking-widest">Atención: Inventario Crítico</h3>
                            <p class="text-xs text-red-600 font-medium">Hay <strong><?php echo $critico; ?> productos</strong> con menos de 5 unidades.</p>
                        </div>
                    </div>
                    <a href="inventario/index.php" class="bg-white text-red-600 px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-red-100 hover:bg-red-50 transition-all">Gestionar Stock</a>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-escala-green p-8 rounded-[2.5rem] text-white shadow-xl relative overflow-hidden group">
                        <div class="relative z-10">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60 mb-2">Ingresos Recaudados</p>
                            <h2 class="text-4xl font-black tracking-tighter mb-4">$<?php echo number_format($recaudadoPeriodo, 2); ?></h2>
                            
                            <div class="flex items-center gap-2 pt-4 border-t border-white/10">
                                <i data-lucide="trending-up" class="w-4 h-4 text-escala-beige"></i>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-white/80">
                                    +$<?php echo number_format($porRecaudarTotal, 2); ?> por recaudar
                                </p>
                            </div>
                        </div>
                        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                            <i data-lucide="banknote" class="w-32 h-32"></i>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-gray-100 relative overflow-hidden group">
                        <div class="absolute right-6 top-6 bg-slate-50 p-4 rounded-2xl text-slate-400 group-hover:bg-escala-beige group-hover:text-white transition-all"><i data-lucide="shopping-bag" class="w-6 h-6"></i></div>
                        <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-2">Pedidos Realizados</p>
                        <h2 class="text-4xl font-black text-slate-800 tracking-tighter"><?php echo $pedidosPeriodo; ?></h2>
                        <p class="text-[9px] text-gray-400 font-bold uppercase mt-4 tracking-widest italic">Actividad en el periodo</p>
                    </div>

                    <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-gray-100 relative overflow-hidden group">
                        <div class="absolute right-6 top-6 bg-slate-50 p-4 rounded-2xl text-slate-400 group-hover:bg-escala-dark group-hover:text-white transition-all"><i data-lucide="users" class="w-6 h-6"></i></div>
                        <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-2">Usuarios Activos</p>
                        <h2 class="text-4xl font-black text-slate-800 tracking-tighter"><?php echo $nuevosPeriodo; ?></h2>
                        <p class="text-[9px] text-gray-400 font-bold uppercase mt-4 tracking-widest italic">Colaboradores operando</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                        <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-[0.2em] mb-8 flex items-center gap-3">
                            <i data-lucide="line-chart" class="w-4 h-4 text-escala-green"></i> Tendencia de Recaudación (6 Meses)
                        </h3>
                        <div class="h-72">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                        <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-[0.2em] mb-8 flex items-center gap-3">
                            <i data-lucide="pie-chart" class="w-4 h-4 text-escala-green"></i> Participación por Área
                        </h3>
                        <div class="h-72 flex justify-center">
                            <canvas id="areaChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-8">
                        <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                            <i data-lucide="award" class="w-4 h-4 text-yellow-500"></i> Productos de Mayor Desplazamiento
                        </h3>
                        <table class="w-full text-sm text-left border-collapse">
                            <thead>
                                <tr class="text-[9px] text-slate-300 uppercase tracking-widest border-b border-gray-50">
                                    <th class="pb-4">Producto</th>
                                    <th class="pb-4">Stock Actual</th>
                                    <th class="pb-4 text-right">Vendidos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php while($row = $topProductos->fetch_assoc()): ?>
                                <tr class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 font-bold text-slate-700 uppercase text-xs"><?php echo $row['nombre']; ?></td>
                                    <td class="py-4">
                                        <span class="px-2 py-1 rounded-lg text-[9px] font-black uppercase <?php echo $row['stock']<5?'bg-red-50 text-red-500':'bg-slate-50 text-slate-400'; ?>">
                                            <?php echo $row['stock']; ?> unds.
                                        </span>
                                    </td>
                                    <td class="py-4 text-right font-black text-escala-green text-base tracking-tighter"><?php echo $row['vendidos']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-8">
                        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-8">
                            <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                                <i data-lucide="crown" class="w-4 h-4 text-escala-beige"></i> Colaboradores VIP
                            </h3>
                            <div class="space-y-6">
                                <?php while($row = $topEmpleados->fetch_assoc()): ?>
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-slate-50 text-slate-400 group-hover:bg-escala-green group-hover:text-white rounded-2xl flex items-center justify-center font-black text-xs transition-all shadow-sm"><?php echo substr($row['nombre'],0,1); ?></div>
                                        <div>
                                            <p class="text-xs font-black text-slate-700 uppercase tracking-tight line-clamp-1"><?php echo $row['nombre']; ?></p>
                                            <p class="text-[8px] text-gray-400 font-bold uppercase tracking-widest"><?php echo $row['area']; ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-black text-escala-dark tracking-tighter">$<?php echo number_format($row['total']); ?></p>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-8">
                            <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                                <i data-lucide="ticket" class="w-4 h-4 text-escala-beige"></i> Rendimiento de Cupones
                            </h3>
                            <div class="space-y-5">
                                <?php while($c = $topCupones->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100 transition-transform hover:scale-105">
                                    <span class="text-[10px] font-black text-escala-green uppercase tracking-widest"><?php echo $c['codigo']; ?></span>
                                    <span class="text-[10px] font-black text-slate-400 uppercase"><?php echo $c['usos_actuales']; ?> USOS</span>
                                </div>
                                <?php endwhile; ?>
                                <?php if($topCupones->num_rows === 0): ?>
                                    <div class="text-center py-4">
                                        <p class="text-[9px] text-gray-300 font-black uppercase tracking-widest italic">Sin actividad promocional</p>
                                    </div>
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
        
        // Configuración de Gráfica de Tendencia (Precisión Financiera)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Recaudado ($)',
                    data: <?php echo json_encode($chartData); ?>,
                    borderColor: '#00524A',
                    backgroundColor: 'rgba(0, 82, 74, 0.05)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#AA9482',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#f1f5f9', borderDash: [5, 5] },
                        ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8' }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8' }
                    }
                }
            }
        });

        // Configuración de Gráfica de Áreas
        const ctxArea = document.getElementById('areaChart').getContext('2d');
        new Chart(ctxArea, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($areaLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($areaData); ?>,
                    backgroundColor: ['#00524A', '#AA9482', '#1e3a8a', '#FF9900', '#EF4444'],
                    hoverOffset: 15,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { 
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            usePointStyle: true, 
                            padding: 20,
                            font: { size: 9, weight: '900' },
                            color: '#94a3b8'
                        } 
                    } 
                }
            }
        });
    </script>
</body>
</html>
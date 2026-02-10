<?php
/**
 * admin/dashboard.php - Panel Principal (Re-branding Escala)
 */
session_start();
require_once '../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// --- CONSULTAS KPI ---
// 1. Pedidos Pendientes
$sqlPendientes = "SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pendiente'";
$pendientes = $conn->query($sqlPendientes)->fetch_assoc()['total'];

// 2. Ventas del Mes (aprobado/entregado/pendiente)
$sqlVentas = "SELECT COALESCE(SUM(monto_total), 0) as total FROM pedidos WHERE MONTH(fecha_pedido) = MONTH(CURRENT_DATE()) AND YEAR(fecha_pedido) = YEAR(CURRENT_DATE()) AND estado != 'cancelado'";
$ventas = $conn->query($sqlVentas)->fetch_assoc()['total'];

// 3. Stock Bajo
$sqlStockBajo = "SELECT COUNT(*) as total FROM productos WHERE stock < 10";
$stockBajo = $conn->query($sqlStockBajo)->fetch_assoc()['total'];

// 4. Últimos Pedidos
$ultimosPedidos = $conn->query("
    SELECT p.id, p.monto_total, p.estado, p.fecha_pedido, e.nombre as empleado 
    FROM pedidos p 
    JOIN empleados e ON p.empleado_id = e.id 
    ORDER BY p.fecha_pedido DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Escala Admin</title>
    <link rel="icon" type="image/png" href="../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden">

    <aside class="w-64 bg-escala-dark text-escala-beige flex flex-col flex-shrink-0 transition-all duration-300 shadow-2xl relative z-20">
        
        <div class="p-6 flex flex-col items-center justify-center border-b border-white/10 bg-escala-green/20">
            <img src="../imagenes/EscalaBoutique.png" alt="Escala Boutique" class="h-10 w-auto object-contain mb-3 drop-shadow-md">
            <span class="font-black tracking-[0.2em] text-xs text-white uppercase">Administrador</span>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-6">
            <ul class="space-y-2 px-4">
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-gradient-to-r from-escala-green to-escala-dark border-l-4 border-escala-beige rounded-r-xl text-white shadow-lg transition-all">
                        <i data-lucide="layout-dashboard" class="w-5 h-5 text-escala-beige"></i> 
                        <span class="font-bold text-sm">Dashboard</span>
                    </a>
                </li>
                
                <li class="pt-6 pb-2 px-4 text-[10px] font-black text-escala-beige/50 uppercase tracking-widest">Gestión</li>
                
                <li>
                    <a href="productos/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all group">
                        <i data-lucide="package" class="w-5 h-5 group-hover:text-white transition-colors"></i> 
                        <span class="font-medium text-sm">Productos</span>
                    </a>
                </li>
                <li>
                    <a href="pedidos/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all group">
                        <i data-lucide="shopping-bag" class="w-5 h-5 group-hover:text-white transition-colors"></i> 
                        <span class="font-medium text-sm">Pedidos</span>
                        <?php if($pendientes > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold shadow-sm animate-pulse"><?php echo $pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all group">
                        <i data-lucide="users" class="w-5 h-5 group-hover:text-white transition-colors"></i> 
                        <span class="font-medium text-sm">Empleados</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="p-4 border-t border-white/10 bg-black/20">
            <a href="logout.php" class="flex items-center gap-2 text-escala-beige hover:text-red-400 transition-colors text-xs font-bold uppercase tracking-wider justify-center">
                <i data-lucide="log-out" class="w-4 h-4"></i> Cerrar Sesión
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8 z-10 border-b border-gray-100">
            <h2 class="text-lg font-black text-escala-green uppercase tracking-wide">Resumen General</h2>
            <div class="flex items-center gap-4">
                <div class="text-right hidden md:block">
                    <p class="text-xs font-bold text-gray-400 uppercase">Usuario</p>
                    <p class="text-sm font-bold text-escala-dark"><?php echo $_SESSION['admin_nombre']; ?></p>
                </div>
                <div class="w-10 h-10 bg-escala-beige/20 rounded-full flex items-center justify-center text-escala-dark border border-escala-beige/30">
                    <i data-lucide="user" class="w-5 h-5"></i>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 bg-slate-50">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-[0_4px_20px_-10px_rgba(0,0,0,0.1)] border border-gray-100 flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-14 h-14 rounded-2xl bg-orange-50 flex items-center justify-center text-orange-500 shadow-sm">
                        <i data-lucide="clock" class="w-7 h-7"></i>
                    </div>
                    <div>
                        <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest">Pedidos Pendientes</p>
                        <p class="text-3xl font-black text-gray-800"><?php echo $pendientes; ?></p>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-2xl shadow-[0_4px_20px_-10px_rgba(0,0,0,0.1)] border border-gray-100 flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-14 h-14 rounded-2xl bg-escala-green/10 flex items-center justify-center text-escala-green shadow-sm">
                        <i data-lucide="dollar-sign" class="w-7 h-7"></i>
                    </div>
                    <div>
                        <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest">Ventas del Mes</p>
                        <p class="text-3xl font-black text-gray-800">$<?php echo number_format($ventas, 2); ?></p>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-2xl shadow-[0_4px_20px_-10px_rgba(0,0,0,0.1)] border border-gray-100 flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-14 h-14 rounded-2xl bg-red-50 flex items-center justify-center text-red-500 shadow-sm">
                        <i data-lucide="alert-triangle" class="w-7 h-7"></i>
                    </div>
                    <div>
                        <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest">Stock Crítico</p>
                        <p class="text-3xl font-black text-gray-800"><?php echo $stockBajo; ?> <span class="text-xs font-bold text-gray-300">Items</span></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <i data-lucide="list" class="w-4 h-4 text-escala-beige"></i> Últimos Pedidos
                    </h3>
                    <a href="pedidos/" class="text-escala-green text-xs font-bold hover:text-escala-dark hover:underline uppercase tracking-wide">Ver todos</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-wider">
                            <tr>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Empleado</th>
                                <th class="px-6 py-4">Fecha</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm font-medium text-gray-600">
                            <?php while($row = $ultimosPedidos->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4 font-bold text-escala-green">#<?php echo str_pad($row['id'], 5, "0", STR_PAD_LEFT); ?></td>
                                <td class="px-6 py-4 text-gray-800"><?php echo $row['empleado']; ?></td>
                                <td class="px-6 py-4 text-xs"><?php echo date('d M Y, h:i a', strtotime($row['fecha_pedido'])); ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800">$<?php echo number_format($row['monto_total'], 2); ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $st = strtolower($row['estado']);
                                        $color = 'gray'; $bg = 'bg-gray-100'; $txt = 'text-gray-600';
                                        if($st=='pendiente') { $bg='bg-yellow-100'; $txt='text-yellow-700'; }
                                        if($st=='aprobado') { $bg='bg-blue-100'; $txt='text-blue-700'; }
                                        if($st=='entregado') { $bg='bg-green-100'; $txt='text-green-700'; }
                                        if($st=='cancelado') { $bg='bg-red-100'; $txt='text-red-700'; }
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo $bg . ' ' . $txt; ?>">
                                        <?php echo $row['estado']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="text-gray-400 hover:text-escala-green transition-colors bg-gray-100 hover:bg-escala-green/10 p-2 rounded-lg">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
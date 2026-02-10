<?php
/**
 * admin/pedidos/index.php - Bandeja de Entrada de Pedidos
 */
session_start();
require_once '../../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Obtener pedidos con datos del empleado
$query = "
    SELECT p.*, e.nombre as empleado, e.numero_empleado, e.area
    FROM pedidos p
    JOIN empleados e ON p.empleado_id = e.id
    ORDER BY p.fecha_pedido DESC
";
$resultado = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos | Escala Admin</title>
    <link rel="icon" type="image/png" href="../../imagenes/monito01.png">
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

    <aside class="w-64 bg-escala-dark text-escala-beige flex flex-col flex-shrink-0 shadow-2xl z-20">
        <div class="p-6 flex flex-col items-center justify-center border-b border-white/10 bg-escala-green/20">
            <img src="../../imagenes/EscalaBoutique.png" alt="Escala" class="h-8 w-auto object-contain mb-2">
            <span class="font-black text-[10px] text-white uppercase tracking-widest">Administrador</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
            <a href="../dashboard.php" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> <span class="font-medium text-sm">Dashboard</span>
            </a>
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl shadow-inner transition-all">
                <i data-lucide="shopping-bag" class="w-5 h-5"></i> <span class="font-bold text-sm">Pedidos</span>
            </a>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8 border-b border-gray-100 z-10">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-black text-escala-green uppercase tracking-tighter">Bandeja de Pedidos</h1>
                <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-full text-xs font-bold"><?php echo $resultado->num_rows; ?> Total</span>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">ID Pedido</th>
                            <th class="px-6 py-4">Empleado</th>
                            <th class="px-6 py-4">Área</th>
                            <th class="px-6 py-4">Fecha</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4 text-right">Gestión</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm font-medium text-gray-600">
                        <?php while($row = $resultado->fetch_assoc()): ?>
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <td class="px-6 py-4 font-bold text-escala-green">
                                #<?php echo str_pad($row['id'], 5, "0", STR_PAD_LEFT); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-gray-800 font-bold"><?php echo $row['empleado']; ?></span>
                                    <span class="text-[10px] text-gray-400">#<?php echo $row['numero_empleado']; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs"><?php echo $row['area']; ?></td>
                            <td class="px-6 py-4 text-xs text-gray-500">
                                <?php echo date('d/m/Y', strtotime($row['fecha_pedido'])); ?>
                                <br><span class="text-[10px] opacity-70"><?php echo date('h:i A', strtotime($row['fecha_pedido'])); ?></span>
                            </td>
                            <td class="px-6 py-4 font-black text-gray-800">$<?php echo number_format($row['monto_total'], 2); ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                    $st = strtolower($row['estado']);
                                    $bg='bg-gray-100'; $txt='text-gray-600'; $icon='circle';
                                    
                                    if($st=='pendiente') { $bg='bg-yellow-100'; $txt='text-yellow-700'; $icon='clock'; }
                                    if($st=='aprobado') { $bg='bg-blue-100'; $txt='text-blue-700'; $icon='check-circle'; }
                                    if($st=='entregado') { $bg='bg-green-100'; $txt='text-green-700'; $icon='package-check'; }
                                    if($st=='cancelado') { $bg='bg-red-100'; $txt='text-red-700'; $icon='x-circle'; }
                                ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo $bg . ' ' . $txt; ?>">
                                    <i data-lucide="<?php echo $icon; ?>" class="w-3 h-3"></i> <?php echo $st; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="detalle.php?id=<?php echo $row['id']; ?>" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-escala-green hover:text-escala-green text-gray-500 px-3 py-1.5 rounded-lg text-xs font-bold uppercase transition-all shadow-sm">
                                    Ver Detalle <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if($resultado->num_rows === 0): ?>
                    <div class="p-10 text-center text-gray-400">
                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                        <p class="text-sm font-bold uppercase">No hay pedidos registrados</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
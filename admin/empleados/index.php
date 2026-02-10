<?php
/**
 * admin/empleados/index.php - Directorio de Usuarios
 */
session_start();
require_once '../../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Consulta Avanzada:
// Trae datos del empleado Y cuenta cuántos pedidos ha hecho (historial)
$query = "
    SELECT e.*, COUNT(p.id) as total_compras, SUM(COALESCE(p.monto_total, 0)) as gasto_total
    FROM empleados e
    LEFT JOIN pedidos p ON e.id = p.empleado_id
    GROUP BY e.id
    ORDER BY e.fecha_ultimo_acceso DESC
";
$empleados = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Empleados | Escala Admin</title>
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
            <a href="../productos/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all">
                <i data-lucide="package" class="w-5 h-5"></i> <span class="font-medium text-sm">Productos</span>
            </a>
            <a href="../pedidos/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all">
                <i data-lucide="shopping-bag" class="w-5 h-5"></i> <span class="font-medium text-sm">Pedidos</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl shadow-inner transition-all">
                <i data-lucide="users" class="w-5 h-5"></i> <span class="font-bold text-sm">Empleados</span>
            </a>
        </nav>
        <div class="p-4 border-t border-white/10 bg-black/20">
            <a href="../logout.php" class="flex items-center gap-2 text-escala-beige hover:text-red-400 transition-colors text-xs font-bold uppercase tracking-wider justify-center">
                <i data-lucide="log-out" class="w-4 h-4"></i> Cerrar Sesión
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8 border-b border-gray-100 z-10">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">Directorio de Empleados</h1>
                <span class="bg-escala-green/10 text-escala-green px-3 py-1 rounded-full text-xs font-bold"><?php echo $empleados->num_rows; ?> Registrados</span>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">Empleado</th>
                            <th class="px-6 py-4">Área / Depto</th>
                            <th class="px-6 py-4">Contacto</th>
                            <th class="px-6 py-4 text-center">Historial</th>
                            <th class="px-6 py-4 text-right">Último Acceso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm font-medium text-gray-600">
                        <?php while($row = $empleados->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/80 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-escala-beige/20 text-escala-dark rounded-full flex items-center justify-center font-bold text-xs border border-escala-beige/30">
                                        <?php 
                                            // Iniciales
                                            $parts = explode(' ', $row['nombre']);
                                            echo substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : '');
                                        ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?php echo $row['nombre']; ?></p>
                                        <p class="text-[10px] text-gray-400 font-bold bg-gray-100 px-1.5 rounded inline-block mt-0.5">
                                            #<?php echo $row['numero_empleado']; ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wide">
                                    <?php echo $row['area']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="flex items-center gap-2 text-gray-500">
                                    <i data-lucide="mail" class="w-3 h-3"></i> <?php echo $row['email']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($row['total_compras'] > 0): ?>
                                    <div class="inline-flex flex-col items-center">
                                        <span class="font-black text-escala-green text-lg"><?php echo $row['total_compras']; ?></span>
                                        <span class="text-[9px] uppercase text-gray-400 font-bold">Pedidos</span>
                                        <span class="text-[9px] text-escala-green font-bold">($<?php echo number_format($row['gasto_total'], 0); ?>)</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-300 text-xs italic">Sin compras</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($row['fecha_ultimo_acceso']): ?>
                                    <span class="text-xs font-bold text-gray-700"><?php echo date('d M Y', strtotime($row['fecha_ultimo_acceso'])); ?></span>
                                    <br>
                                    <span class="text-[10px] text-gray-400"><?php echo date('h:i A', strtotime($row['fecha_ultimo_acceso'])); ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-300">Nunca</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if($empleados->num_rows === 0): ?>
                    <div class="p-10 text-center text-gray-400">
                        <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                        <p class="text-sm font-bold uppercase">No hay empleados registrados aún.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
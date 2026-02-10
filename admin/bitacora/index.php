<?php
session_start();
require_once '../../api/conexion.php';
if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

// Paginación simple o límite
$logs = $conn->query("SELECT * FROM bitacora ORDER BY fecha DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora | Escala Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden">
    
    <aside class="w-64 bg-escala-dark text-white flex flex-col shadow-2xl z-20">
        <div class="p-6 text-center font-black text-xl border-b border-white/10">ADMINISTRADOR</div>
        <nav class="flex-1 py-6 px-4 space-y-2">
            <a href="../dashboard.php" class="flex gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl"><i data-lucide="layout-dashboard"></i> Dashboard</a>
            <a href="#" class="flex gap-3 px-4 py-3 bg-white/20 text-white rounded-xl font-bold shadow-inner"><i data-lucide="scroll-text"></i> Bitácora</a>
            </nav>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center px-8 border-b border-gray-100">
            <h1 class="text-xl font-black text-slate-800 uppercase">Bitácora de Actividad</h1>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">Fecha / Hora</th>
                            <th class="px-6 py-4">Usuario</th>
                            <th class="px-6 py-4">Módulo</th>
                            <th class="px-6 py-4">Acción</th>
                            <th class="px-6 py-4">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php while($row = $logs->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-700">
                                <?php echo date('d M Y', strtotime($row['fecha'])); ?>
                                <span class="block text-xs font-normal text-gray-400"><?php echo date('H:i:s', strtotime($row['fecha'])); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-2 font-bold text-escala-green">
                                    <span class="w-6 h-6 rounded-full bg-escala-green/10 flex items-center justify-center text-xs"><?php echo substr($row['usuario'],0,1); ?></span>
                                    <?php echo $row['usuario']; ?>
                                </span>
                                <span class="text-[10px] text-gray-400 uppercase"><?php echo $row['rol']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-500">
                                    <?php echo $row['modulo']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-bold uppercase <?php echo $row['accion']=='ELIMINAR'?'text-red-500':'text-blue-600'; ?>">
                                <?php echo $row['accion']; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500 max-w-md truncate" title="<?php echo $row['detalle']; ?>">
                                <?php echo $row['detalle']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
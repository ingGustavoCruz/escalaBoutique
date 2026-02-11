<?php
/**
 * admin/bitacora/index.php - Corregido (Color Beige Agregado)
 */
session_start();
require_once '../../api/conexion.php';

// Variable para que los links del sidebar sepan dónde están
$ruta_base = "../../"; 

// Seguridad
if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

// Consulta
$logs = $conn->query("SELECT * FROM bitacora ORDER BY fecha DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora | Escala Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = { 
            theme: { 
                extend: { 
                    colors: { 
                        'escala-green': '#00524A', 
                        'escala-dark': '#003d36',
                        'escala-beige': '#AA9482' // <--- ¡ESTE FALTABA!
                    } 
                } 
            } 
        }
    </script>
</head>
<body class="bg-slate-50 font-sans" x-data="{ sidebarOpen: false }">
    
    <div class="flex h-screen overflow-hidden">
        
        <?php include '../includes/sidebar.php'; ?>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
            
            <div class="md:hidden bg-white h-16 shadow-sm flex items-center justify-between px-4 z-10 border-b border-gray-200 flex-shrink-0">
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

            <header class="hidden md:flex h-16 bg-white shadow-sm items-center px-8 border-b border-gray-100 flex-shrink-0">
                <h1 class="text-xl font-black text-slate-800 uppercase">Bitácora de Actividad</h1>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 min-w-[800px]">
                            <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-400 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 whitespace-nowrap">Fecha / Hora</th>
                                    <th class="px-6 py-4 whitespace-nowrap">Usuario</th>
                                    <th class="px-6 py-4 whitespace-nowrap">Módulo</th>
                                    <th class="px-6 py-4 whitespace-nowrap">Acción</th>
                                    <th class="px-6 py-4 min-w-[300px]">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php while($row = $logs->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-700 whitespace-nowrap">
                                        <?php echo date('d M Y', strtotime($row['fecha'])); ?>
                                        <span class="block text-xs font-normal text-gray-400"><?php echo date('H:i:s', strtotime($row['fecha'])); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2 font-bold text-escala-green whitespace-nowrap">
                                            <span class="w-6 h-6 rounded-full bg-escala-green/10 flex items-center justify-center text-xs shrink-0">
                                                <?php echo substr($row['usuario'],0,1); ?>
                                            </span>
                                            <?php echo $row['usuario']; ?>
                                        </div>
                                        <span class="text-[10px] text-gray-400 uppercase block ml-8"><?php echo $row['rol']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-500 border border-gray-200">
                                            <?php echo $row['modulo']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold uppercase whitespace-nowrap <?php echo $row['accion']=='ELIMINAR'?'text-red-500':'text-blue-600'; ?>">
                                        <?php echo $row['accion']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 text-xs leading-relaxed">
                                        <?php echo $row['detalle']; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
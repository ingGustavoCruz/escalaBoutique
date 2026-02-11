<?php
/**
 * admin/productos/index.php - Listado de Inventario Responsivo
 */
session_start();
require_once '../../api/conexion.php';

// Variable para el Sidebar
$ruta_base = "../../"; 

// Seguridad
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Lógica de Eliminación Rápida
if (isset($_GET['delete'])) {
    $idDelete = (int)$_GET['delete'];
    $conn->query("DELETE FROM productos WHERE id = $idDelete");
    header("Location: index.php?msg=deleted");
    exit;
}

// Consulta de Productos (Con subconsulta para la imagen principal)
$query = "
    SELECT p.*, 
    (SELECT url_imagen FROM imagenes_productos ip WHERE ip.producto_id = p.id ORDER BY es_principal DESC LIMIT 1) as img_principal
    FROM productos p 
    ORDER BY p.id DESC
";
$productos = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Productos | Escala Admin</title>
    <link rel="icon" type="image/png" href="../../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
<body class="bg-slate-50 font-sans" x-data="{ sidebarOpen: false }">

    <div class="flex h-screen overflow-hidden">
        
        <?php include '../includes/sidebar.php'; ?>

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

            <header class="bg-white shadow-sm px-6 py-4 flex flex-col md:flex-row items-center justify-between gap-4 z-10 border-b border-gray-100 flex-shrink-0">
                <h1 class="text-xl font-black text-escala-green uppercase tracking-wide w-full md:w-auto text-center md:text-left">
                    Inventario General
                </h1>
                
                <a href="crear.php" class="bg-escala-green hover:bg-escala-dark text-white px-5 py-2.5 rounded-lg text-sm font-bold uppercase shadow-lg transition-all flex items-center gap-2 transform hover:-translate-y-0.5 w-full md:w-auto justify-center">
                    <i data-lucide="plus" class="w-4 h-4"></i> Nuevo Producto
                </a>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                
                <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
                    <div class="mb-6 p-4 bg-red-100 border border-red-200 text-red-700 rounded-xl flex items-center gap-3 font-bold">
                        <i data-lucide="trash-2" class="w-5 h-5"></i> Producto eliminado correctamente.
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-wider border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4">Producto</th>
                                    <th class="px-6 py-4">Categoría</th>
                                    <th class="px-6 py-4">Precio</th>
                                    <th class="px-6 py-4">Stock Total</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm font-medium text-gray-600">
                                <?php while($p = $productos->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white border border-gray-200 rounded-lg p-1 flex items-center justify-center overflow-hidden shrink-0">
                                                <img src="../../<?php echo $p['img_principal'] ?? 'imagenes/torito.png'; ?>" class="max-w-full max-h-full object-contain">
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-800 line-clamp-1"><?php echo $p['nombre']; ?></p>
                                                <p class="text-[10px] text-gray-400">ID: <?php echo $p['id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-gray-100 text-gray-500 px-2 py-1 rounded text-xs font-bold uppercase tracking-wider">
                                            <?php echo $p['categoria'] ?? 'General'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-escala-dark">$<?php echo number_format($p['precio'], 2); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if($p['stock'] == 0): ?>
                                            <span class="text-red-500 font-black text-xs uppercase bg-red-50 px-2 py-1 rounded">Agotado</span>
                                        <?php elseif($p['stock'] < 10): ?>
                                            <span class="text-orange-500 font-bold"><?php echo $p['stock']; ?> Unidades</span>
                                        <?php else: ?>
                                            <span class="text-green-600 font-bold"><?php echo $p['stock']; ?> Unidades</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="editar.php?id=<?php echo $p['id']; ?>" class="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors" title="Editar">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </a>
                                            <a href="index.php?delete=<?php echo $p['id']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este producto? Esta acción no se puede deshacer.')" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors" title="Eliminar">
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
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
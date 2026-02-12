<?php
/**
 * admin/productos/crear.php - Alta de Productos (Protegida con CSRF)
 */
session_start();
require_once '../../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CIRUGÍA: Validar escudo CSRF antes de procesar el alta
    validar_csrf(); 
    
    $nombre = $_POST['nombre'];
    $cat = $_POST['categoria'];
    $precio = (float)$_POST['precio'];
    $desc_c = $_POST['descripcion_corta'];
    $desc_l = $_POST['descripcion_larga'];
    $stock_inicial = (int)$_POST['stock_simple'];
    
    // Recibimos cuál índice eligió el usuario como portada (0, 1, 2...)
    $indice_portada = isset($_POST['indice_portada']) ? (int)$_POST['indice_portada'] : 0;
    
    // 1. Insertar Producto
    $stmt = $conn->prepare("INSERT INTO productos (nombre, categoria, precio, descripcion_corta, descripcion_larga, stock, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssdssi", $nombre, $cat, $precio, $desc_c, $desc_l, $stock_inicial);
    
    if ($stmt->execute()) {
        $producto_id = $conn->insert_id;
        
        // 2. Procesar Tallas
        if (isset($_POST['tallas']) && is_array($_POST['tallas'])) {
            $tallas = $_POST['tallas'];
            $stocks = $_POST['stocks'];
            $stmtVar = $conn->prepare("INSERT INTO inventario_tallas (producto_id, talla, stock) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($tallas); $i++) {
                $t = trim($tallas[$i]);
                $s = (int)$stocks[$i];
                if (!empty($t)) {
                    $stmtVar->bind_param("isi", $producto_id, $t, $s);
                    $stmtVar->execute();
                }
            }
        }

        // 3. Procesar IMÁGENES (Con lógica de Portada)
        if (isset($_FILES['imagenes'])) {
            $archivos = $_FILES['imagenes'];
            $total_archivos = count($archivos['name']);
            
            for ($i = 0; $i < $total_archivos; $i++) {
                if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($archivos['name'][$i], PATHINFO_EXTENSION);
                    $nombre_archivo = "prod_" . $producto_id . "_" . time() . "_" . $i . "." . $ext;
                    $ruta_destino = "../../imagenes/" . $nombre_archivo;
                    $ruta_bd = "imagenes/" . $nombre_archivo;
                    
                    if (move_uploaded_file($archivos['tmp_name'][$i], $ruta_destino)) {
                        $es_principal = ($i === $indice_portada) ? 1 : 0;
                        $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal) VALUES ($producto_id, '$ruta_bd', $es_principal)");
                    }
                }
            }
        }
        
        header("Location: index.php?msg=created");
        exit;
    } else {
        $msg = "Error al crear producto: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Producto | Escala Admin</title>
    <link rel="icon" type="image/png" href="../../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { 'escala-green': '#00524A', 'escala-beige': '#AA9482', 'escala-dark': '#003d36' } }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans min-h-screen pb-10">

    <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">Nuevo Producto</h1>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
        <?php if($msg): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-200 text-red-700 rounded-xl font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-8" x-data="{ hasVariants: false }">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="font-bold text-gray-700 uppercase text-xs mb-4 border-b pb-2">Información Básica</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre</label>
                            <input type="text" name="nombre" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Categoría</label>
                            <select name="categoria" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-gray-700">
                                <option value="ropa">Ropa</option>
                                <option value="souvenirs">Souvenirs</option>
                                <option value="accesorios">Accesorios</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción Corta</label>
                        <textarea name="descripcion_corta" rows="2" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-sm"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción Larga</label>
                        <textarea name="descripcion_larga" rows="4" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio ($)</label>
                        <input type="number" step="0.01" name="precio" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h2 class="font-bold text-gray-700 uppercase text-xs">Inventario</h2>
                        <label class="flex items-center cursor-pointer">
                            <span class="mr-2 text-xs font-bold text-gray-500">¿Tiene Tallas?</span>
                            <div class="relative">
                                <input type="checkbox" x-model="hasVariants" class="sr-only">
                                <div class="w-10 h-6 bg-gray-200 rounded-full shadow-inner transition-colors" :class="hasVariants ? 'bg-escala-green' : 'bg-gray-200'"></div>
                                <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full shadow transition-transform" :class="hasVariants ? 'translate-x-full' : ''"></div>
                            </div>
                        </label>
                    </div>

                    <div x-show="!hasVariants" x-transition>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stock Global</label>
                        <input type="number" name="stock_simple" value="0" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                    </div>

                    <div x-show="hasVariants" x-cloak>
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-4" x-data="{ rows: [{t:'', s:0}] }">
                            <template x-for="(row, index) in rows" :key="index">
                                <div class="flex gap-2 mb-2">
                                    <input type="text" name="tallas[]" x-model="row.t" placeholder="Talla" class="w-1/2 px-3 py-2 border rounded-lg text-sm uppercase font-bold focus:outline-none">
                                    <input type="number" name="stocks[]" x-model="row.s" placeholder="Cant." class="w-1/3 px-3 py-2 border rounded-lg text-sm font-bold focus:outline-none">
                                    <button type="button" @click="rows.splice(index, 1)" class="text-red-400 hover:text-red-600 px-2" x-show="rows.length > 1"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({t:'', s:0})" class="mt-2 text-xs font-bold text-escala-green hover:underline flex items-center gap-1">
                                <i data-lucide="plus-circle" class="w-3 h-3"></i> Agregar otra talla
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="font-bold text-gray-700 uppercase text-xs mb-4 border-b pb-2">Galería de Imágenes</h2>
                    
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:bg-gray-50 transition-colors relative" 
                         x-data="{ images: [], activeIndex: 0 }">
                        
                        <input type="hidden" name="indice_portada" x-model="activeIndex">

                        <input type="file" name="imagenes[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" 
                               accept="image/*" 
                               @change="images = []; activeIndex = 0; for(let i=0; i<$event.target.files.length; i++) { images.push(URL.createObjectURL($event.target.files[i])) }">
                        
                        <div x-show="images.length === 0">
                            <i data-lucide="images" class="w-10 h-10 mx-auto text-gray-300 mb-2"></i>
                            <p class="text-xs text-gray-400 font-bold">Arrastra o selecciona varias fotos</p>
                            <p class="text-[9px] text-gray-300 mt-1">Podrás elegir cuál es la portada</p>
                        </div>
                        
                        <div x-show="images.length > 0">
                            <p class="text-[10px] text-escala-green font-bold mb-2 uppercase">Haz clic en la foto para elegir la portada:</p>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="(img, index) in images" :key="index">
                                    <div class="relative group cursor-pointer" @click="activeIndex = index">
                                        <img :src="img" class="h-24 w-full object-cover rounded-lg border-2 transition-all"
                                             :class="activeIndex === index ? 'border-escala-green ring-2 ring-escala-green ring-offset-1' : 'border-gray-100 hover:border-gray-300'">
                                        
                                        <div x-show="activeIndex === index" class="absolute top-1 left-1 bg-escala-green text-white text-[8px] px-2 py-0.5 rounded font-bold uppercase shadow-sm flex items-center gap-1">
                                            <i data-lucide="check" class="w-2 h-2"></i> Portada
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-escala-green hover:bg-escala-dark text-white rounded-xl font-bold uppercase shadow-lg hover:shadow-xl transition-all flex justify-center gap-2 transform hover:-translate-y-1">
                    <i data-lucide="save" class="w-5 h-5"></i> Guardar Producto
                </button>
            </div>

        </form>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
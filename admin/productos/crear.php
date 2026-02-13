<?php
/**
 * admin/productos/crear.php - Alta de Productos con Optimización WebP
 * Versión: Full Optimization (Quirúrgico)
 */
session_start();
require_once '../../api/conexion.php';

// 1. Seguridad: Solo admins logueados
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

$msg = '';

/**
 * Función de Optimización: Convierte JPG/PNG a WebP
 * Reduce el peso drásticamente manteniendo la calidad visual
 */
function optimizarImagenWebP($rutaOriginal, $rutaDestino, $calidad = 80) {
    $info = getimagesize($rutaOriginal);
    if (!$info) return false;
    
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($rutaOriginal); break;
        case 'image/png':  $image = imagecreatefrompng($rutaOriginal); break;
        default: return false; 
    }

    // Preparación técnica para el formato WebP
    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    // Generación del archivo optimizado
    $exito = imagewebp($image, $rutaDestino, $calidad);
    imagedestroy($image);
    
    return $exito;
}

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar escudo CSRF
    validar_csrf(); 
    
    $nombre = $_POST['nombre'];
    $cat    = $_POST['categoria'];
    $precio = (float)$_POST['precio'];
    $desc_c = $_POST['descripcion_corta'];
    $desc_l = $_POST['descripcion_larga'];
    $stock_simple = (int)$_POST['stock_simple'];
    $indice_portada = isset($_POST['indice_portada']) ? (int)$_POST['indice_portada'] : 0;
    
    // Iniciamos Transacción para asegurar integridad total
    $conn->begin_transaction();

    try {
        // A. Insertar Producto Base
        $stmt = $conn->prepare("INSERT INTO productos (nombre, categoria, precio, descripcion_corta, descripcion_larga, stock, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdssi", $nombre, $cat, $precio, $desc_c, $desc_l, $stock_simple);
        
        if (!$stmt->execute()) throw new Exception("Error en BD al crear producto.");
        $producto_id = $conn->insert_id;

        // B. Procesar Tallas y Stocks (Variantes)
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

        // C. Procesar y Optimizar IMÁGENES
        if (isset($_FILES['imagenes'])) {
            $archivos = $_FILES['imagenes'];
            $total_archivos = count($archivos['name']);
            
            for ($i = 0; $i < $total_archivos; $i++) {
                if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
                    
                    // Nombre único con extensión .webp
                    $nombre_base = "prod_" . $producto_id . "_" . time() . "_" . $i;
                    $ruta_destino = "../../imagenes/" . $nombre_base . ".webp";
                    $ruta_bd      = "imagenes/" . $nombre_base . ".webp";
                    
                    $tmp_name = $archivos['tmp_name'][$i];

                    // Intentar conversión a WebP
                    if (optimizarImagenWebP($tmp_name, $ruta_destino, 80)) {
                        $es_principal = ($i === $indice_portada) ? 1 : 0;
                        $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal) VALUES ($producto_id, '$ruta_bd', $es_principal)");
                    } else {
                        // Fallback: Si no se puede convertir, subir original (ej: SVG o GIF)
                        $ext = pathinfo($archivos['name'][$i], PATHINFO_EXTENSION);
                        $nombre_original = $nombre_base . "." . $ext;
                        $ruta_original   = "../../imagenes/" . $nombre_original;
                        $ruta_bd_orig    = "imagenes/" . $nombre_original;

                        if (move_uploaded_file($tmp_name, $ruta_original)) {
                            $es_principal = ($i === $indice_portada) ? 1 : 0;
                            $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal) VALUES ($producto_id, '$ruta_bd_orig', $es_principal)");
                        }
                    }
                }
            }
        }

        $conn->commit();
        header("Location: index.php?msg=created");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Producto | Escala Boutique</title>
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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen pb-10">

    <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">Nuevo Producto</h1>
            </div>
            <img src="../../imagenes/EscalaBoutique.png" alt="Logo" class="h-8 opacity-20">
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-8">
        <?php if($msg): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-xl font-bold flex items-center gap-2 shadow-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-8" x-data="{ hasVariants: false }">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                    <h2 class="font-black text-gray-400 uppercase text-[10px] tracking-widest mb-6 border-b pb-2">Información del Producto</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Nombre</label>
                            <input type="text" name="nombre" required class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-escala-beige outline-none font-bold text-slate-700 transition-all">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Categoría</label>
                            <select name="categoria" class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-escala-beige outline-none font-bold text-slate-700 transition-all appearance-none">
                                <option value="ropa">ROPA</option>
                                <option value="souvenirs">SOUVENIRS</option>
                                <option value="accesorios">ACCESORIOS</option>
                                <option value="general">GENERAL</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-6 space-y-1">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Descripción Corta</label>
                        <textarea name="descripcion_corta" rows="2" class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-escala-beige outline-none text-sm font-medium text-slate-600"></textarea>
                    </div>

                    <div class="mb-6 space-y-1">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Descripción Detallada</label>
                        <textarea name="descripcion_larga" rows="4" class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-escala-beige outline-none text-sm font-medium text-slate-600"></textarea>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Precio Unitario ($)</label>
                        <input type="number" step="0.01" name="precio" required class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-escala-beige outline-none font-black text-slate-700 text-lg">
                    </div>
                </div>

                <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6 border-b pb-2">
                        <h2 class="font-black text-gray-400 uppercase text-[10px] tracking-widest">Gestión de Stock</h2>
                        <label class="flex items-center cursor-pointer group">
                            <span class="mr-3 text-[10px] font-black text-gray-400 uppercase tracking-widest group-hover:text-escala-green transition-colors">¿Maneja Tallas?</span>
                            <div class="relative">
                                <input type="checkbox" x-model="hasVariants" class="sr-only">
                                <div class="w-10 h-5 bg-slate-100 rounded-full shadow-inner transition-colors" :class="hasVariants ? 'bg-escala-green' : 'bg-slate-100'"></div>
                                <div class="absolute left-1 top-1 bg-white w-3 h-3 rounded-full shadow transition-transform" :class="hasVariants ? 'translate-x-5' : ''"></div>
                            </div>
                        </label>
                    </div>

                    <div x-show="!hasVariants" x-transition>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Stock Inicial Disponible</label>
                        <input type="number" name="stock_simple" value="0" class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-escala-beige outline-none font-bold text-slate-700">
                    </div>

                    <div x-show="hasVariants" x-cloak x-transition>
                        <div class="bg-slate-50 rounded-2xl p-6" x-data="{ rows: [{t:'', s:0}] }">
                            <template x-for="(row, index) in rows" :key="index">
                                <div class="flex gap-3 mb-3 items-center">
                                    <input type="text" name="tallas[]" x-model="row.t" placeholder="TALLA (Ej: M, G, 32)" class="flex-1 px-4 py-2.5 rounded-xl border-none text-sm uppercase font-black text-slate-700 focus:ring-2 focus:ring-escala-beige">
                                    <input type="number" name="stocks[]" x-model="row.s" placeholder="CANT." class="w-24 px-4 py-2.5 rounded-xl border-none text-sm font-black text-slate-700 focus:ring-2 focus:ring-escala-beige text-center">
                                    <button type="button" @click="rows.splice(index, 1)" class="p-2 text-red-400 hover:bg-red-50 rounded-lg transition-colors" x-show="rows.length > 1">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({t:'', s:0})" class="mt-4 text-[10px] font-black text-escala-green hover:text-escala-dark flex items-center gap-2 uppercase tracking-widest">
                                <i data-lucide="plus-circle" class="w-4 h-4"></i> Añadir Variante de Talla
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                    <h2 class="font-black text-gray-400 uppercase text-[10px] tracking-widest mb-6 border-b pb-2">Imágenes</h2>
                    
                    <div class="border-2 border-dashed border-slate-100 rounded-[1.5rem] p-8 text-center hover:bg-slate-50 transition-all relative group" 
                         x-data="{ images: [], activeIndex: 0 }">
                        
                        <input type="hidden" name="indice_portada" x-model="activeIndex">

                        <input type="file" name="imagenes[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                               accept="image/*" 
                               @change="images = []; activeIndex = 0; for(let i=0; i<$event.target.files.length; i++) { images.push(URL.createObjectURL($event.target.files[i])) }">
                        
                        <div x-show="images.length === 0">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                                <i data-lucide="image-plus" class="w-8 h-8 text-slate-300"></i>
                            </div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Subir Fotos</p>
                        </div>
                        
                        <div x-show="images.length > 0" class="relative z-20">
                            <p class="text-[9px] text-escala-beige font-black mb-4 uppercase tracking-widest">Toca la imagen de portada:</p>
                            <div class="grid grid-cols-2 gap-3">
                                <template x-for="(img, index) in images" :key="index">
                                    <div class="relative group cursor-pointer" @click="activeIndex = index">
                                        <img :src="img" class="h-24 w-full object-cover rounded-xl border-4 transition-all shadow-sm"
                                             :class="activeIndex === index ? 'border-escala-green' : 'border-white opacity-60 hover:opacity-100'">
                                        
                                        <div x-show="activeIndex === index" class="absolute -top-2 -left-2 bg-escala-green text-white p-1 rounded-full shadow-lg">
                                            <i data-lucide="check" class="w-3 h-3"></i>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <p class="text-[9px] text-gray-300 mt-4 leading-relaxed font-medium">Nota: Las imágenes serán convertidas automáticamente a formato WebP para mejorar la velocidad de la tienda.</p>
                </div>

                <button type="submit" class="w-full py-5 bg-escala-green hover:bg-escala-dark text-white rounded-2xl font-black uppercase tracking-[0.2em] shadow-xl shadow-escala-green/20 transition-all hover:-translate-y-1 active:translate-y-0 flex justify-center items-center gap-3">
                    <i data-lucide="save" class="w-5 h-5 text-escala-beige"></i> Publicar Producto
                </button>
            </div>

        </form>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
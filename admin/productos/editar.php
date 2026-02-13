<?php
/**
 * admin/productos/editar.php - Edición Avanzada con Optimización WebP
 * Versión: Full Performance & UI (Quirúrgico)
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// 1. Seguridad y Validación de ID
if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) { 
    header("Location: index.php"); 
    exit; 
}

$id = (int)$_GET['id'];
$msg = '';
$error = '';

/**
 * Función de Optimización: Convierte JPG/PNG a WebP
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

    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $exito = imagewebp($image, $rutaDestino, $calidad);
    imagedestroy($image);
    return $exito;
}

// --- 2. PROCESAR ACTUALIZACIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf(); 

    // Inicio de Transacción para integridad total
    $conn->begin_transaction();

    try {
        // A. Datos Básicos y Toggles
        $nombre = $_POST['nombre'];
        $cat = $_POST['categoria'];
        $desc_c = $_POST['descripcion_corta'];
        $desc_l = $_POST['descripcion_larga'];
        $stock_simple = (int)$_POST['stock_simple'];
        $calif = (float)$_POST['calificacion'];
        
        $precio_anterior = !empty($_POST['precio_regular']) ? (float)$_POST['precio_regular'] : NULL;
        $precio_actual = (float)$_POST['nuevo_precio'];

        $estado = isset($_POST['estado']) ? 1 : 0;
        $es_top = isset($_POST['es_top']) ? 1 : 0;
        $en_oferta = isset($_POST['en_oferta']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE productos SET nombre=?, categoria=?, precio=?, precio_anterior=?, descripcion_corta=?, descripcion_larga=?, stock=?, es_top=?, en_oferta=?, calificacion=?, activo=? WHERE id=?");
        $stmt->bind_param("ssddssiiidii", $nombre, $cat, $precio_actual, $precio_anterior, $desc_c, $desc_l, $stock_simple, $es_top, $en_oferta, $calif, $estado, $id);
        $stmt->execute();

        // B. Re-insertar Variantes (Tallas)
        $conn->query("DELETE FROM inventario_tallas WHERE producto_id = $id");
        if (isset($_POST['tallas']) && is_array($_POST['tallas'])) {
            $tallas = $_POST['tallas'];
            $stocks = $_POST['stocks'];
            $stmtVar = $conn->prepare("INSERT INTO inventario_tallas (producto_id, talla, stock) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($tallas); $i++) {
                $t = trim($tallas[$i]);
                $s = (int)$stocks[$i];
                if (!empty($t)) {
                    $stmtVar->bind_param("isi", $id, $t, $s);
                    $stmtVar->execute();
                }
            }
        }

        // C. Gestión de Imágenes Existentes (Portada / Borrar)
        if (isset($_POST['accion_imagen'])) {
            if ($_POST['accion_imagen'] === 'cambiar_portada' && isset($_POST['id_portada'])) {
                $idPortada = (int)$_POST['id_portada'];
                $conn->query("UPDATE imagenes_productos SET es_principal = 0 WHERE producto_id = $id");
                $conn->query("UPDATE imagenes_productos SET es_principal = 1 WHERE id = $idPortada");
            } elseif ($_POST['accion_imagen'] === 'borrar' && isset($_POST['id_borrar'])) {
                $idBorrar = (int)$_POST['id_borrar'];
                $resImg = $conn->query("SELECT url_imagen FROM imagenes_productos WHERE id = $idBorrar");
                if ($rowImg = $resImg->fetch_assoc()) {
                    $rutaFisica = "../../" . $rowImg['url_imagen'];
                    if (file_exists($rutaFisica)) { unlink($rutaFisica); }
                }
                $conn->query("DELETE FROM imagenes_productos WHERE id = $idBorrar");
            }
        }

        // D. Procesar NUEVAS IMÁGENES con Optimización WebP
        if (isset($_FILES['nuevas_imagenes'])) {
            $archivos = $_FILES['nuevas_imagenes'];
            $total = count($archivos['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
                    $nombre_base = "prod_" . $id . "_" . time() . "_" . $i;
                    $ruta_destino = "../../imagenes/" . $nombre_base . ".webp";
                    $ruta_bd = "imagenes/" . $nombre_base . ".webp";
                    $tmp_name = $archivos['tmp_name'][$i];

                    if (optimizarImagenWebP($tmp_name, $ruta_destino, 80)) {
                        $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal) VALUES ($id, '$ruta_bd', 0)");
                    } else {
                        // Fallback original si falla WebP
                        $ext = pathinfo($archivos['name'][$i], PATHINFO_EXTENSION);
                        $ruta_orig = "../../imagenes/" . $nombre_base . "." . $ext;
                        $ruta_bd_orig = "imagenes/" . $nombre_base . "." . $ext;
                        if (move_uploaded_file($tmp_name, $ruta_orig)) {
                            $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal) VALUES ($id, '$ruta_bd_orig', 0)");
                        }
                    }
                }
            }
        }

        $conn->commit();
        $msg = "Producto actualizado correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// --- 3. OBTENER DATOS ACTUALES ---
$prod = $conn->query("SELECT * FROM productos WHERE id = $id")->fetch_assoc();
if (!$prod) { echo "Producto no encontrado"; exit; }

$resVar = $conn->query("SELECT talla, stock FROM inventario_tallas WHERE producto_id = $id ORDER BY id ASC");
$variantes = [];
while ($row = $resVar->fetch_assoc()) { $variantes[] = ['t' => $row['talla'], 's' => $row['stock']]; }
$jsonVariantes = json_encode($variantes);
$tieneVariantes = count($variantes) > 0 ? 'true' : 'false';

$resImg = $conn->query("SELECT * FROM imagenes_productos WHERE producto_id = $id ORDER BY es_principal DESC, id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto | Escala Boutique</title>
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
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <div>
                    <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">Editar Producto</h1>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">ID: #<?php echo $id; ?></p>
                </div>
            </div>
            <a href="../../index.php" target="_blank" class="bg-slate-50 border border-slate-200 px-4 py-2 rounded-xl text-[10px] font-black text-slate-500 hover:bg-white hover:text-blue-500 transition-all flex items-center gap-2 uppercase tracking-widest shadow-sm">
                Ver en Tienda <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <?php if($msg): ?>
            <div class="mb-8 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-xl font-bold flex items-center gap-3 shadow-sm animate-pulse">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="mb-8 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-xl font-bold flex items-center gap-3 shadow-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" 
              x-data="{ 
                  hasVariants: <?php echo $tieneVariantes; ?>,
                  precioRegular: <?php echo $prod['precio_anterior'] > 0 ? $prod['precio_anterior'] : $prod['precio']; ?>,
                  precioNuevo: <?php echo $prod['precio']; ?>,
                  activo: <?php echo $prod['activo'] == 1 ? 'true' : 'false'; ?>,
                  esTop: <?php echo $prod['es_top'] == 1 ? 'true' : 'false'; ?>,
                  enOferta: <?php echo $prod['en_oferta'] == 1 ? 'true' : 'false'; ?>,
                  calcDescuento() {
                      if(this.enOferta) {
                          this.precioNuevo = (this.precioRegular * 0.8).toFixed(2);
                      } else {
                          this.precioNuevo = this.precioRegular;
                      }
                  }
              }">
            
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 mb-8 flex flex-wrap items-center justify-between gap-8">
                <div class="flex items-center gap-4">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Estado:</span>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="estado" class="sr-only" x-model="activo">
                        <div class="w-12 h-6 bg-slate-100 rounded-full shadow-inner transition-colors duration-300" :class="activo ? 'bg-green-500' : 'bg-slate-200'"></div>
                        <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-transform duration-300 ml-1" :class="activo ? 'translate-x-6' : 'translate-x-0'"></div>
                        <span class="ml-14 text-sm font-black uppercase tracking-tight" :class="activo ? 'text-green-600' : 'text-slate-400'" x-text="activo ? 'Visible' : 'Oculto'"></span>
                    </label>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Destacado:</span>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="es_top" class="sr-only" x-model="esTop">
                        <div class="w-12 h-6 bg-slate-100 rounded-full shadow-inner transition-colors duration-300" :class="esTop ? 'bg-escala-green' : 'bg-slate-200'"></div>
                        <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-transform duration-300 ml-1" :class="esTop ? 'translate-x-6' : 'translate-x-0'"></div>
                        <span class="ml-14 text-sm font-black uppercase tracking-tight text-slate-700">Top Ventas</span>
                    </label>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Promoción:</span>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="en_oferta" class="sr-only" x-model="enOferta" @change="calcDescuento()">
                        <div class="w-12 h-6 bg-slate-100 rounded-full shadow-inner transition-colors duration-300" :class="enOferta ? 'bg-blue-600' : 'bg-slate-200'"></div>
                        <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-transform duration-300 ml-1" :class="enOferta ? 'translate-x-6' : 'translate-x-0'"></div>
                        <span class="ml-14 text-sm font-black uppercase tracking-tight text-slate-700">En Oferta</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                        <h2 class="font-black text-gray-400 uppercase text-[10px] tracking-widest mb-8 border-b pb-2">Información Básica</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Nombre del Producto</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required 
                                       class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-escala-beige outline-none font-bold text-slate-700 transition-all">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Categoría</label>
                                    <select name="categoria" class="w-full px-4 py-4 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-escala-beige font-bold text-slate-700 outline-none appearance-none">
                                        <?php $cats = ['ropa','souvenirs','accesorios','general']; ?>
                                        <?php foreach($cats as $c): ?>
                                            <option value="<?php echo $c; ?>" <?php echo $prod['categoria']==$c?'selected':''; ?>><?php echo strtoupper($c); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Calificación</label>
                                    <input type="number" step="0.1" max="5" min="0" name="calificacion" value="<?php echo $prod['calificacion']; ?>" 
                                           class="w-full px-4 py-4 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-escala-beige font-black text-yellow-600 outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-8 rounded-[2rem] mb-8 border border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Precio Regular</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 font-bold">$</span>
                                    <input type="number" step="0.01" name="precio_regular" x-model="precioRegular" @input="if(enOferta) calcDescuento()" 
                                           class="w-full pl-8 pr-4 py-4 bg-white border-none rounded-2xl focus:ring-2 focus:ring-escala-beige font-bold text-slate-400 text-sm shadow-sm outline-none">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    <span x-show="enOferta" class="text-blue-600 animate-pulse">Nuevo Precio (-20%)</span>
                                    <span x-show="!enOferta">Precio Actual</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-escala-green font-black">$</span>
                                    <input type="number" step="0.01" name="nuevo_precio" x-model="precioNuevo" 
                                           class="w-full pl-9 pr-4 py-4 bg-white border-none rounded-2xl focus:ring-2 focus:ring-escala-green font-black text-2xl text-escala-green shadow-sm outline-none">
                                </div>
                            </div>
                            <div x-show="!hasVariants" class="space-y-2" x-transition>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Stock Disponible</label>
                                <input type="number" name="stock_simple" value="<?php echo $prod['stock']; ?>" 
                                       class="w-full px-5 py-4 bg-white border-none rounded-2xl focus:ring-2 focus:ring-escala-beige font-black text-slate-700 shadow-sm outline-none">
                            </div>
                        </div>

                        <div class="mb-6 space-y-2">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Descripción Corta</label>
                            <textarea name="descripcion_corta" rows="2" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-escala-beige outline-none text-sm font-medium text-slate-600"><?php echo htmlspecialchars($prod['descripcion_corta']); ?></textarea>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Descripción Detallada</label>
                            <textarea name="descripcion_larga" rows="4" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-escala-beige outline-none text-sm font-medium text-slate-600"><?php echo htmlspecialchars($prod['descripcion_larga']); ?></textarea>
                        </div>
                    </div>

                    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-8 border-b pb-2">
                            <h2 class="font-black text-gray-400 uppercase text-[10px] tracking-widest">Inventario por Tallas</h2>
                            <label class="flex items-center cursor-pointer group">
                                <span class="mr-3 text-[10px] font-black text-gray-400 uppercase tracking-widest group-hover:text-escala-green transition-colors">¿Tiene Tallas?</span>
                                <div class="relative">
                                    <input type="checkbox" x-model="hasVariants" class="sr-only">
                                    <div class="w-10 h-5 bg-slate-100 rounded-full transition-colors duration-300" :class="hasVariants ? 'bg-escala-green' : 'bg-slate-200'"></div>
                                    <div class="absolute left-1 top-1 bg-white w-3 h-3 rounded-full shadow transition-transform duration-300" :class="hasVariants ? 'translate-x-5' : ''"></div>
                                </div>
                            </label>
                        </div>
                        <div x-show="hasVariants" x-cloak x-transition>
                            <div class="bg-slate-50 rounded-[1.5rem] p-6" x-data="{ rows: <?php echo empty($variantes) ? "[{t:'', s:0}]" : $jsonVariantes; ?> }">
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="flex gap-4 mb-4 items-center">
                                        <input type="text" name="tallas[]" x-model="row.t" placeholder="TALLA" class="flex-1 px-5 py-3 border-none rounded-xl text-sm font-black uppercase text-slate-700 focus:ring-2 focus:ring-escala-beige shadow-sm">
                                        <input type="number" name="stocks[]" x-model="row.s" placeholder="CANT." class="w-28 px-5 py-3 border-none rounded-xl text-sm font-black text-slate-700 focus:ring-2 focus:ring-escala-beige text-center shadow-sm">
                                        <button type="button" @click="rows.splice(index, 1)" class="p-3 text-red-400 hover:bg-red-50 rounded-xl transition-all" x-show="rows.length > 1">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="rows.push({t:'', s:0})" class="mt-4 w-full py-3 border-2 border-dashed border-slate-200 rounded-2xl text-[10px] font-black text-slate-400 hover:border-escala-green hover:text-escala-green transition-all flex items-center justify-center gap-2 uppercase tracking-widest">
                                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Agregar Variante
                                </button>
                            </div>
                        </div>
                        <div x-show="!hasVariants" class="text-center py-6 text-slate-300 text-[10px] font-black uppercase tracking-[0.2em]">
                            Gestión de stock global activa.
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                        <h2 class="font-black text-gray-400 uppercase text-[10px] tracking-widest mb-6 border-b pb-2">Galería</h2>
                        <div class="grid grid-cols-2 gap-4 mb-8">
                            <?php if ($resImg && $resImg->num_rows > 0): $resImg->data_seek(0); while($img = $resImg->fetch_assoc()): ?>
                                <div class="relative group rounded-2xl overflow-hidden border-4 transition-all shadow-sm <?php echo $img['es_principal']?'border-escala-green':'border-white'; ?>">
                                    <img src="../../<?php echo $img['url_imagen']; ?>" class="w-full h-28 object-cover">
                                    <?php if($img['es_principal']): ?>
                                        <div class="absolute top-2 left-2 bg-escala-green text-white text-[8px] px-2 py-1 rounded-full font-black uppercase shadow-lg">Portada</div>
                                    <?php else: ?>
                                        <div class="absolute inset-0 bg-slate-900/80 flex flex-col items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-all duration-300">
                                            <button type="submit" name="accion_imagen" value="cambiar_portada" onclick="this.form.appendChild(crearInput('id_portada', <?php echo $img['id']; ?>))" 
                                                    class="text-[9px] bg-white text-slate-900 px-3 py-1.5 rounded-lg font-black uppercase w-24 hover:bg-escala-green hover:text-white transition-all shadow-xl">Hacer Portada</button>
                                            <button type="submit" name="accion_imagen" value="borrar" onclick="if(confirm('¿Seguro que quieres eliminar esta foto?')) { this.form.appendChild(crearInput('id_borrar', <?php echo $img['id']; ?>)); } else { return false; }" 
                                                    class="text-[9px] bg-red-500 text-white px-3 py-1.5 rounded-lg font-black uppercase w-24 hover:bg-red-600 transition-all shadow-xl">Eliminar</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="col-span-2 py-10 text-center">
                                    <i data-lucide="image-off" class="w-8 h-8 text-slate-200 mx-auto mb-2"></i>
                                    <p class="text-[9px] text-slate-300 font-black uppercase tracking-widest">Sin imágenes</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="border-2 border-dashed border-slate-100 rounded-2xl p-8 text-center hover:bg-slate-50 transition-all relative group">
                             <input type="file" name="nuevas_imagenes[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" accept="image/*">
                             <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i data-lucide="image-plus" class="w-6 h-6 text-slate-300"></i>
                             </div>
                             <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest leading-relaxed">Añadir Fotos<br><span class="text-slate-300 font-bold">(WebP Auto-Optimize)</span></p>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-5 bg-escala-green hover:bg-escala-dark text-white rounded-2xl font-black uppercase tracking-[0.25em] shadow-xl shadow-escala-green/20 transition-all hover:-translate-y-1 active:translate-y-0 flex justify-center items-center gap-3">
                        <i data-lucide="save" class="w-6 h-6 text-escala-beige"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
    </main>
    
    <script>
        lucide.createIcons();
        function crearInput(nombre, valor) {
            let i = document.createElement('input');
            i.type = 'hidden';
            i.name = nombre;
            i.value = valor;
            return i;
        }
    </script>
</body>
</html>
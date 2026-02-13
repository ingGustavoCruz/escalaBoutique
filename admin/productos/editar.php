<?php
/**
 * admin/productos/editar.php - Edición Avanzada
 * Versión: UI Match (image_5.png) + Full Backend Logic
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// 1. Seguridad y Validación
if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) { header("Location: index.php"); exit; }
$id = (int)$_GET['id'];
$msg = ''; $error = '';

// Rutina WebP (Backend intacto)
function optimizarImagenWebP($rutaOriginal, $rutaDestino, $calidad = 80) {
    $info = getimagesize($rutaOriginal); if (!$info) return false;
    $mime = $info['mime'];
    switch ($mime) { case 'image/jpeg': $image = imagecreatefromjpeg($rutaOriginal); break; case 'image/png': $image = imagecreatefrompng($rutaOriginal); break; default: return false; }
    imagepalettetotruecolor($image); imagealphablending($image, true); imagesavealpha($image, true);
    $exito = imagewebp($image, $rutaDestino, $calidad); imagedestroy($image); return $exito;
}

// Cargar datos ANTES del POST para auditoría
$prod = $conn->query("SELECT * FROM productos WHERE id = $id")->fetch_assoc();
if (!$prod) { echo "Producto no encontrado"; exit; }

// 2. Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $conn->begin_transaction();
    try {
        // Auditoría de Precio
        $precio_antiguo = (float)$prod['precio']; $precio_nuevo = (float)$_POST['nuevo_precio'];
        if (abs($precio_antiguo - $precio_nuevo) > 0.01) {
            $admin = $_SESSION['admin_nombre'] ?? 'Admin';
            registrarBitacora('PRODUCTOS', 'CAMBIO PRECIO', "$admin cambió precio de ID $id de $$precio_antiguo a $$precio_nuevo", $conn);
        }

        // Datos
        $precio_ant = !empty($_POST['precio_regular']) ? (float)$_POST['precio_regular'] : NULL;
        $estado = isset($_POST['estado']) ? 1 : 0;
        $es_top = isset($_POST['es_top']) ? 1 : 0;
        $en_oferta = isset($_POST['en_oferta']) ? 1 : 0;

        // Update
        $stmt = $conn->prepare("UPDATE productos SET nombre=?, categoria=?, precio=?, precio_anterior=?, descripcion_corta=?, descripcion_larga=?, stock=?, es_top=?, en_oferta=?, calificacion=?, activo=? WHERE id=?");
        $stmt->bind_param("ssddssiiidii", $_POST['nombre'], $_POST['categoria'], $precio_nuevo, $precio_ant, $_POST['descripcion_corta'], $_POST['descripcion_larga'], $_POST['stock_simple'], $es_top, $en_oferta, $_POST['calificacion'], $estado, $id);
        $stmt->execute();

        // Variantes
        $conn->query("DELETE FROM inventario_tallas WHERE producto_id = $id");
        if (isset($_POST['tallas'])) {
            $stmtV = $conn->prepare("INSERT INTO inventario_tallas (producto_id, talla, stock) VALUES (?, ?, ?)");
            foreach ($_POST['tallas'] as $i => $t) { if(!empty($t)) $stmtV->execute([$id, $t, $_POST['stocks'][$i]]); }
        }

        // Imágenes
        if (isset($_POST['accion_imagen'])) {
            if($_POST['accion_imagen'] == 'borrar') {
                $r = $conn->query("SELECT url_imagen FROM imagenes_productos WHERE id=".(int)$_POST['id_borrar'])->fetch_assoc();
                if($r) @unlink("../../".$r['url_imagen']);
                $conn->query("DELETE FROM imagenes_productos WHERE id=".(int)$_POST['id_borrar']);
            } elseif ($_POST['accion_imagen'] == 'portada') {
                $conn->query("UPDATE imagenes_productos SET es_principal=0 WHERE producto_id=$id");
                $conn->query("UPDATE imagenes_productos SET es_principal=1 WHERE id=".(int)$_POST['id_portada']);
            }
        }
        if (!empty($_FILES['nuevas_imagenes']['name'][0])) {
            foreach ($_FILES['nuevas_imagenes']['tmp_name'] as $i => $tmp) {
                $dest = "../../imagenes/prod_{$id}_".time()."_{$i}.webp";
                if(optimizarImagenWebP($tmp, $dest)) $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen) VALUES ($id, 'imagenes/".basename($dest)."')");
            }
        }

        $conn->commit(); $msg = "Producto guardado correctamente.";
        $prod = $conn->query("SELECT * FROM productos WHERE id = $id")->fetch_assoc(); // Recargar
    } catch (Exception $e) { $conn->rollback(); $error = $e->getMessage(); }
}

// Datos UI
$resV = $conn->query("SELECT talla as t, stock as s FROM inventario_tallas WHERE producto_id=$id");
$variantes = $resV->fetch_all(MYSQLI_ASSOC);
$resI = $conn->query("SELECT * FROM imagenes_productos WHERE producto_id=$id ORDER BY es_principal DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto | Escala Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-dark': '#003d36' } } } }</script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap'); body{font-family:'Inter',sans-serif} [x-cloak]{display:none}</style>
</head>
<body class="bg-slate-50 pb-12">
    <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-slate-400 hover:text-escala-green transition-colors"><i data-lucide="arrow-left" class="w-6 h-6"></i></a>
                <div>
                    <h1 class="text-xl font-black text-escala-green uppercase tracking-tighter">Editar Producto</h1>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">ID: #<?php echo $id; ?></p>
                </div>
            </div>
            <a href="../../detalle.php?id=<?php echo $id; ?>" target="_blank" class="text-escala-green font-boldtext-sm flex items-center gap-2 hover:underline">
                Ver en Tienda <i data-lucide="external-link" class="w-4 h-4"></i>
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-8 py-10" x-data="productEditor()">
        <?php if($msg): ?><div class="mb-6 p-4 bg-green-100 text-green-700 rounded-xl font-bold flex items-center gap-2"><i data-lucide="check-circle"></i><?=$msg?></div><?php endif; ?>
        <?php if($error): ?><div class="mb-6 p-4 bg-red-100 text-red-700 rounded-xl font-bold flex items-center gap-2"><i data-lucide="alert-circle"></i><?=$error?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
            
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-8 flex flex-wrap items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Estado:</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="estado" x-model="activo" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-escala-green"></div>
                        <span class="ml-3 text-xs font-bold uppercase" :class="activo?'text-escala-green':'text-slate-400'" x-text="activo?'Visible':'Oculto'"></span>
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Destacado:</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="es_top" x-model="esTop" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-escala-green"></div>
                        <span class="ml-3 text-xs font-bold uppercase" :class="esTop?'text-escala-green':'text-slate-400'">Top Ventas</span>
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Promoción:</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="en_oferta" x-model="enOferta" @change="calcDescuento()" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-xs font-bold uppercase" :class="enOferta?'text-blue-600':'text-slate-400'">En Oferta</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
                        <h2 class="text-sm font-black text-escala-green uppercase tracking-widest mb-6 pb-2 border-b border-slate-100">Información Básica</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Nombre del Producto</label>
                                <input type="text" name="nombre" value="<?=htmlspecialchars($prod['nombre'])?>" class="w-full px-4 py-3 bg-slate-100 border-2 border-transparent rounded-xl focus:outline-none focus:border-escala-green font-semibold text-slate-700 transition-all">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Categoría</label>
                                    <select name="categoria" class="w-full px-4 py-3 bg-slate-100 border-2 border-transparent rounded-xl focus:outline-none focus:border-escala-green font-semibold text-slate-700 transition-all appearance-none">
                                        <?php foreach(['ropa','souvenirs','accesorios','general'] as $c): ?>
                                            <option value="<?=$c?>" <?=$prod['categoria']==$c?'selected':''?>><?=strtoupper($c)?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Calificación (0-5)</label>
                                    <input type="number" step="0.1" min="0" max="5" name="calificacion" value="<?=$prod['calificacion']?>" class="w-full px-4 py-3 bg-slate-100 border-2 border-transparent rounded-xl focus:outline-none focus:border-escala-green font-bold text-amber-500 transition-all text-center">
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 grid grid-cols-3 gap-6 mb-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Precio Regular (Antes)</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">$</span>
                                    <input type="number" step="0.01" x-model="precioRegular" name="precio_regular" @input="if(enOferta) calcDescuento()" class="w-full pl-8 pr-4 py-3 bg-white border-2 border-slate-200 rounded-xl focus:outline-none focus:border-escala-green font-bold text-slate-500 transition-all">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Precio Actual</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-escala-green font-black text-lg">$</span>
                                    <input type="number" step="0.01" x-model="precioNuevo" name="nuevo_precio" class="w-full pl-10 pr-4 py-3 bg-white border-2 border-escala-green rounded-xl focus:outline-none font-black text-2xl text-escala-green transition-all">
                                </div>
                            </div>
                             <div class="space-y-2" x-show="!hasVariants">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Stock Disponible</label>
                                <input type="number" name="stock_simple" value="<?=$prod['stock']?>" class="w-full px-4 py-3 bg-white border-2 border-slate-200 rounded-xl focus:outline-none focus:border-escala-green font-bold text-slate-700 transition-all text-center">
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Descripción Corta</label>
                                <textarea name="descripcion_corta" rows="2" class="w-full px-4 py-3 bg-slate-100 border-2 border-transparent rounded-xl focus:outline-none focus:border-escala-green font-medium text-slate-600 transition-all resize-none"><?=htmlspecialchars($prod['descripcion_corta'])?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Descripción Larga</label>
                                <textarea name="descripcion_larga" rows="5" class="w-full px-4 py-3 bg-slate-100 border-2 border-transparent rounded-xl focus:outline-none focus:border-escala-green font-medium text-slate-600 transition-all resize-none"><?=htmlspecialchars($prod['descripcion_larga'])?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
                         <div class="flex justify-between items-center mb-6 pb-2 border-b border-slate-100">
                            <h2 class="text-sm font-black text-escala-green uppercase tracking-widest">Inventario por Variantes</h2>
                            <label class="relative inline-flex items-center cursor-pointer gap-3">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">¿Tiene Tallas?</span>
                                <input type="checkbox" x-model="hasVariants" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-escala-green"></div>
                            </label>
                        </div>
                        
                        <div x-show="hasVariants" x-cloak class="space-y-4 bg-slate-50 p-6 rounded-2xl border border-slate-200">
                            <template x-for="(row, i) in variantes" :key="i">
                                <div class="flex gap-3">
                                    <input type="text" name="tallas[]" x-model="row.t" placeholder="Talla (Ej: CH)" class="flex-1 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl focus:outline-none focus:border-escala-green font-bold uppercase text-sm">
                                    <input type="number" name="stocks[]" x-model="row.s" placeholder="Stock" class="w-28 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl focus:outline-none focus:border-escala-green font-bold text-center text-sm">
                                    <button type="button" @click="variantes.splice(i,1)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
                                </div>
                            </template>
                            <button type="button" @click="variantes.push({t:'',s:0})" class="w-full py-3 border-2 border-dashed border-escala-green/30 text-escala-green font-bold rounded-xl hover:bg-escala-green/5 transition-all uppercase text-xs tracking-widest flex items-center justify-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Agregar Variante</button>
                        </div>
                         <div x-show="!hasVariants" class="text-center py-8 text-slate-400 text-xs font-bold uppercase tracking-widest italic">
                            Gestión de stock simple activa.
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
                        <h2 class="text-sm font-black text-escala-green uppercase tracking-widest mb-6 pb-2 border-b border-slate-100">Galería</h2>
                        
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <?php while($img = $resI->fetch_assoc()): ?>
                            <div class="relative group rounded-xl overflow-hidden border-2 <?=$img['es_principal']?'border-escala-green':'border-slate-200'?> aspect-video bg-slate-100">
                                <img src="../../<?=$img['url_imagen']?>" class="w-full h-full object-cover">
                                <?php if($img['es_principal']): ?>
                                    <div class="absolute top-2 left-2 bg-escala-green text-white text-[9px] px-2 py-1 rounded-md font-black uppercase tracking-wider shadow-sm">Portada</div>
                                <?php else: ?>
                                    <div class="absolute inset-0 bg-escala-dark/80 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-2 p-2">
                                        <button type="button" @click="submitAction('portada', <?=$img['id']?>)" class="bg-white text-escala-green text-[9px] font-black uppercase px-3 py-2 rounded-lg w-full hover:bg-escala-green hover:text-white transition-colors">Portada</button>
                                        <button type="button" @click="submitAction('borrar', <?=$img['id']?>)" class="bg-red-500 text-white text-[9px] font-black uppercase px-3 py-2 rounded-lg w-full hover:bg-red-600 transition-colors">Borrar</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        </div>

                        <div class="border-2 border-dashed border-slate-300 rounded-2xl h-36 flex flex-col justify-center items-center text-slate-400 hover:border-escala-green hover:text-escala-green hover:bg-escala-green/5 transition-all relative cursor-pointer group">
                            <input type="file" name="nuevas_imagenes[]" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <i data-lucide="image-plus" class="w-8 h-8 mb-2 text-slate-300 group-hover:text-escala-green transition-colors"></i>
                            <span class="text-xs font-bold uppercase tracking-wide">Subir más fotos</span>
                            <span class="text-[9px] font-semibold opacity-60 mt-1">(Optimización WebP Auto)</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 bg-escala-green hover:bg-escala-dark text-white rounded-xl font-black uppercase tracking-[0.2em] shadow-lg shadow-escala-green/20 transition-all hover:-translate-y-1 flex items-center justify-center gap-3 text-sm">
                        <i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios
                    </button>
                </div>
            </div>
            <input type="hidden" name="accion_imagen" id="accionInput">
            <input type="hidden" name="id_portada" id="portadaInput">
            <input type="hidden" name="id_borrar" id="borrarInput">
        </form>
    </main>

    <script>
        lucide.createIcons();
        function productEditor() {
            return {
                activo: <?=$prod['activo']?'true':'false'?>,
                esTop: <?=$prod['es_top']?'true':'false'?>,
                enOferta: <?=$prod['en_oferta']?'true':'false'?>,
                precioRegular: <?=$prod['precio_anterior']?:$prod['precio']?>,
                precioNuevo: <?=$prod['precio']?>,
                hasVariants: <?=count($variantes)>0?'true':'false'?>,
                variantes: <?=json_encode($variantes)?>,
                calcDescuento() { if(this.enOferta && this.precioRegular) { this.precioNuevo = (this.precioRegular * 0.8).toFixed(2); } else { this.precioNuevo = this.precioRegular; } },
                submitAction(action, id) {
                    if(action === 'borrar' && !confirm('¿Eliminar imagen?')) return;
                    document.getElementById('accionInput').value = action;
                    if(action === 'portada') document.getElementById('portadaInput').value = id;
                    if(action === 'borrar') document.getElementById('borrarInput').value = id;
                    document.querySelector('form').submit();
                }
            }
        }
    </script>
</body>
</html>
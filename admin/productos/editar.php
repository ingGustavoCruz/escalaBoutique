<?php
/**
 * admin/productos/editar.php - Edición Avanzada con Control de Precios y Estado
 */
session_start();
require_once '../../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) { header("Location: index.php"); exit; }

$id = (int)$_GET['id'];
$msg = '';

// --- LÓGICA DE PROCESAMIENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Datos Básicos y Control
    $nombre = $_POST['nombre'];
    $cat = $_POST['categoria'];
    $desc_c = $_POST['descripcion_corta'];
    $desc_l = $_POST['descripcion_larga'];
    $stock_simple = (int)$_POST['stock_simple'];
    $calif = (float)$_POST['calificacion'];

    // 2. Lógica de Precios
    // 'precio_regular' es lo que antes costaba (precio_anterior en BD)
    // 'nuevo_precio' es lo que cuesta ahora (precio en BD)
    $precio_anterior = !empty($_POST['precio_regular']) ? (float)$_POST['precio_regular'] : NULL;
    $precio_actual = (float)$_POST['nuevo_precio'];

    // 3. Toggles (Si vienen en POST es 1, si no es 0)
    $estado = isset($_POST['estado']) ? 1 : 0; // Columna 'activo' (necesitas crearla si no existe, o usar lógica inversa)
    // NOTA: Como en tu BD no vi columna 'activo', asumo que usaremos 'stock = 0' para ocultar o crearemos la columna. 
    // Para este ejemplo, usaré una lógica visual: Si está desactivado, ponemos stock -1 (convención) o simplemente lo guardamos en un campo nuevo si lo agregas.
    // Viendo tu estructura [image_d51fb9.png], NO TIENES columna 'activo'. 
    // SUGERENCIA: Agrega `ALTER TABLE productos ADD COLUMN activo TINYINT(1) DEFAULT 1;`
    // Por ahora, lo guardaré en una variable, pero asegúrate de tener el campo en la BD.
    
    $es_top = isset($_POST['es_top']) ? 1 : 0;
    $en_oferta = isset($_POST['en_oferta']) ? 1 : 0;

    // Actualizamos tabla productos
    // NOTA: Agregué 'precio_anterior', 'es_top', 'en_oferta', 'calificacion'
    // Si agregaste la columna 'activo', añádela al query.
    $stmt = $conn->prepare("UPDATE productos SET nombre=?, categoria=?, precio=?, precio_anterior=?, descripcion_corta=?, descripcion_larga=?, stock=?, es_top=?, en_oferta=?, calificacion=? WHERE id=?");
    $stmt->bind_param("ssddssiiidi", $nombre, $cat, $precio_actual, $precio_anterior, $desc_c, $desc_l, $stock_simple, $es_top, $en_oferta, $calif, $id);
    
    if ($stmt->execute()) {
        
        // 4. Variantes (Igual que antes)
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

        // 5. Imágenes (Igual que antes)
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

        if (isset($_FILES['nuevas_imagenes'])) {
            $archivos = $_FILES['nuevas_imagenes'];
            $total = count($archivos['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($archivos['name'][$i], PATHINFO_EXTENSION);
                    $nombre_archivo = "prod_" . $id . "_" . time() . "_" . $i . "." . $ext;
                    $ruta_destino = "../../imagenes/" . $nombre_archivo;
                    $ruta_bd = "imagenes/" . $nombre_archivo;
                    if (move_uploaded_file($archivos['tmp_name'][$i], $ruta_destino)) {
                        $conn->query("INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal) VALUES ($id, '$ruta_bd', 0)");
                    }
                }
            }
        }

        $msg = "Producto actualizado correctamente.";
    } else {
        $msg = "Error al actualizar: " . $conn->error;
    }
}

// --- CONSULTA DE DATOS ---
$prod = $conn->query("SELECT * FROM productos WHERE id = $id")->fetch_assoc();
if (!$prod) { echo "Producto no encontrado"; exit; }

// Variantes
$resVar = $conn->query("SELECT talla, stock FROM inventario_tallas WHERE producto_id = $id ORDER BY id ASC");
$variantes = [];
while ($row = $resVar->fetch_assoc()) { $variantes[] = ['t' => $row['talla'], 's' => $row['stock']]; }
$jsonVariantes = json_encode($variantes);
$tieneVariantes = count($variantes) > 0 ? 'true' : 'false';

// Imágenes
$resImg = $conn->query("SELECT * FROM imagenes_productos WHERE producto_id = $id ORDER BY es_principal DESC, id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto | Escala Admin</title>
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
        /* Toggles Switch CSS */
        .toggle-checkbox:checked {
            right: 0;
            border-color: #00524A;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #00524A;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans min-h-screen pb-10">

    <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <div>
                    <h1 class="text-xl font-black text-escala-green uppercase tracking-wide">Editar Producto</h1>
                    <p class="text-xs text-gray-400 font-bold">ID: <?php echo $id; ?></p>
                </div>
            </div>
            <a href="../../index.php" target="_blank" class="text-xs font-bold text-blue-500 hover:underline flex items-center gap-1">
                Ver en Tienda <i data-lucide="external-link" class="w-3 h-3"></i>
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <?php if($msg): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl font-bold flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" 
              x-data="{ 
                  hasVariants: <?php echo $tieneVariantes; ?>,
                  precioRegular: <?php echo $prod['precio_anterior'] > 0 ? $prod['precio_anterior'] : $prod['precio']; ?>,
                  precioNuevo: <?php echo $prod['precio']; ?>,
                  enOferta: <?php echo $prod['en_oferta'] == 1 ? 'true' : 'false'; ?>,
                  calcDescuento() {
                      if(this.enOferta) {
                          // Sugerir 20% descuento si oferta se activa
                          this.precioNuevo = (this.precioRegular * 0.8).toFixed(2);
                      } else {
                          // Si se apaga oferta, igualar precios
                          this.precioNuevo = this.precioRegular;
                      }
                  }
              }">
            
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-6 flex flex-wrap items-center justify-between gap-6">
                
                <div class="flex items-center gap-3">
                    <span class="text-xs font-black text-gray-500 uppercase">ESTADO:</span>
                    <label for="estado" class="flex items-center cursor-pointer relative">
                        <input type="checkbox" name="estado" id="estado" class="sr-only" checked> <div class="w-11 h-6 bg-gray-200 rounded-full border border-gray-200 toggle-label transition-colors duration-200 ease-in-out"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition shadow-sm transform translate-x-0"></div>
                        <span class="ml-3 text-sm font-bold text-slate-700">Activado</span>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-black text-gray-500 uppercase">DESTACADO:</span>
                    <label for="es_top" class="flex items-center cursor-pointer relative">
                        <input type="checkbox" name="es_top" id="es_top" class="sr-only" <?php echo $prod['es_top'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 rounded-full border border-gray-200 toggle-label transition-colors duration-200 ease-in-out" :class="$el.previousElementSibling.checked ? '!bg-escala-dark' : ''"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition shadow-sm transform" :class="$el.previousElementSibling.previousElementSibling.checked ? 'translate-x-5' : 'translate-x-0'"></div>
                        <span class="ml-3 text-sm font-bold text-slate-700">Top Ventas</span>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-black text-gray-500 uppercase">PROMOCIÓN:</span>
                    <label class="flex items-center cursor-pointer relative">
                        <input type="checkbox" name="en_oferta" class="sr-only" x-model="enOferta" @change="calcDescuento()">
                        <div class="w-11 h-6 bg-gray-200 rounded-full border border-gray-200 transition-colors duration-200 ease-in-out" :class="enOferta ? '!bg-blue-600' : ''"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition shadow-sm transform" :class="enOferta ? 'translate-x-5' : 'translate-x-0'"></div>
                        <span class="ml-3 text-sm font-bold text-slate-700">En Oferta</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="font-bold text-gray-700 uppercase text-xs mb-6 border-b pb-2">Información Básica</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nombre del Producto</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-800">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Categoría</label>
                                    <select name="categoria" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none text-gray-700">
                                        <?php $cats = ['ropa','souvenirs','accesorios','general']; ?>
                                        <?php foreach($cats as $c): ?>
                                            <option value="<?php echo $c; ?>" <?php echo $prod['categoria']==$c?'selected':''; ?>><?php echo ucfirst($c); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Calificación (0-5)</label>
                                    <input type="number" step="0.1" max="5" min="0" name="calificacion" value="<?php echo $prod['calificacion']; ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-yellow-600">
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50/50 p-6 rounded-xl border border-blue-100 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase mb-1">Precio Regular (Antes)</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                                        <input type="number" step="0.01" name="precio_regular" x-model="precioRegular" @input="if(enOferta) calcDescuento()" class="w-full pl-8 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green font-bold text-gray-500">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase mb-1">
                                        <span x-show="enOferta" class="text-blue-600">Nuevo Precio (-20% Sugerido)</span>
                                        <span x-show="!enOferta">Precio Actual</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-escala-dark font-bold">$</span>
                                        <input type="number" step="0.01" name="nuevo_precio" x-model="precioNuevo" class="w-full pl-8 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green font-black text-xl text-escala-dark">
                                    </div>
                                </div>

                                <div x-show="!hasVariants">
                                    <label class="block text-xs font-black text-slate-500 uppercase mb-1">Stock Disponible</label>
                                    <input type="number" name="stock_simple" value="<?php echo $prod['stock']; ?>" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green font-bold text-gray-700">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Descripción Corta</label>
                            <textarea name="descripcion_corta" rows="2" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none text-sm"><?php echo htmlspecialchars($prod['descripcion_corta']); ?></textarea>
                        </div>

                        <div class="mb-0">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Descripción Larga</label>
                            <textarea name="descripcion_larga" rows="4" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none text-sm"><?php echo htmlspecialchars($prod['descripcion_larga']); ?></textarea>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h2 class="font-bold text-gray-700 uppercase text-xs">Inventario por Variantes</h2>
                            <label class="flex items-center cursor-pointer">
                                <span class="mr-2 text-xs font-bold text-gray-500">¿Tiene Tallas?</span>
                                <div class="relative">
                                    <input type="checkbox" x-model="hasVariants" class="sr-only">
                                    <div class="w-10 h-6 bg-gray-200 rounded-full shadow-inner transition-colors" :class="hasVariants ? 'bg-escala-green' : 'bg-gray-200'"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full shadow transition-transform" :class="hasVariants ? 'translate-x-full' : ''"></div>
                                </div>
                            </label>
                        </div>

                        <div x-show="hasVariants" x-cloak>
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4" 
                                 x-data="{ rows: <?php echo empty($variantes) ? "[{t:'', s:0}]" : $jsonVariantes; ?> }">
                                
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="flex gap-3 mb-3">
                                        <input type="text" name="tallas[]" x-model="row.t" placeholder="Ej: M, G, 28, 30" class="w-1/2 px-4 py-2 border rounded-lg text-sm uppercase font-bold focus:outline-none shadow-sm">
                                        <input type="number" name="stocks[]" x-model="row.s" placeholder="Cant." class="w-1/3 px-4 py-2 border rounded-lg text-sm font-bold focus:outline-none shadow-sm">
                                        <button type="button" @click="rows.splice(index, 1)" class="text-red-400 hover:text-red-600 px-2 bg-white rounded shadow-sm border border-gray-200"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    </div>
                                </template>
                                
                                <button type="button" @click="rows.push({t:'', s:0})" class="mt-2 w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-xs font-bold text-gray-400 hover:border-escala-green hover:text-escala-green transition-colors flex items-center justify-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Agregar Variantes
                                </button>
                            </div>
                        </div>
                        <div x-show="!hasVariants" class="text-center py-4 text-gray-400 text-xs italic">
                            Gestión de stock simple activa (usar campo superior).
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="font-bold text-gray-700 uppercase text-xs mb-4 border-b pb-2">Galería de Imágenes</h2>
                        
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <?php 
                            if ($resImg && $resImg->num_rows > 0): 
                                $resImg->data_seek(0);
                                while($img = $resImg->fetch_assoc()):
                            ?>
                                <div class="relative group border rounded-lg overflow-hidden <?php echo $img['es_principal']?'ring-2 ring-escala-green':''; ?>">
                                    <img src="../../<?php echo $img['url_imagen']; ?>" class="w-full h-24 object-cover">
                                    <?php if($img['es_principal']): ?>
                                        <div class="absolute top-0 left-0 bg-escala-green text-white text-[8px] px-2 py-1 font-bold uppercase w-full text-center">Portada</div>
                                    <?php else: ?>
                                        <div class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button type="submit" name="accion_imagen" value="cambiar_portada" onclick="this.form.appendChild(crearInput('id_portada', <?php echo $img['id']; ?>))" class="text-[9px] bg-white text-gray-900 px-2 py-1 rounded font-bold uppercase w-20 hover:bg-escala-green hover:text-white transition-colors">Portada</button>
                                            <button type="submit" name="accion_imagen" value="borrar" onclick="if(confirm('¿Borrar?')) { this.form.appendChild(crearInput('id_borrar', <?php echo $img['id']; ?>)); } else { return false; }" class="text-[9px] bg-red-500 text-white px-2 py-1 rounded font-bold uppercase w-20 hover:bg-red-600 transition-colors">Borrar</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; else: ?>
                                <p class="text-xs text-gray-400 col-span-2 text-center py-4">Sin imágenes.</p>
                            <?php endif; ?>
                        </div>

                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:bg-gray-50 transition-colors relative">
                             <input type="file" name="nuevas_imagenes[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*">
                             <i data-lucide="image-plus" class="w-8 h-8 mx-auto text-gray-300 mb-2"></i>
                             <p class="text-xs text-gray-400 font-bold">Subir más fotos</p>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 bg-escala-green hover:bg-escala-dark text-white rounded-xl font-black uppercase shadow-lg hover:shadow-xl transition-all flex justify-center gap-2 transform hover:-translate-y-1 text-sm tracking-widest">
                        <i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios
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
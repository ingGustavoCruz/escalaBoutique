<?php
/**
 * admin/productos/editar.php - Edición Completa
 */
session_start();
require_once '../../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$msg = '';

// --- LÓGICA DE PROCESAMIENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Actualizar Datos Básicos
    $nombre = $_POST['nombre'];
    $cat = $_POST['categoria'];
    $precio = (float)$_POST['precio'];
    $desc_c = $_POST['descripcion_corta'];
    $desc_l = $_POST['descripcion_larga'];
    $stock_simple = (int)$_POST['stock_simple'];

    // Actualizamos tabla productos
    $stmt = $conn->prepare("UPDATE productos SET nombre=?, categoria=?, precio=?, descripcion_corta=?, descripcion_larga=?, stock=? WHERE id=?");
    $stmt->bind_param("ssdssii", $nombre, $cat, $precio, $desc_c, $desc_l, $stock_simple, $id);
    
    if ($stmt->execute()) {
        
        // 2. Actualizar Variantes (Borrar y Reinsertar es más seguro para evitar conflictos)
        // Primero borramos las actuales
        $conn->query("DELETE FROM inventario_tallas WHERE producto_id = $id");
        
        // Si se enviaron tallas nuevas/editadas
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

        // 3. Gestionar Imágenes Existentes (Cambio de Portada o Borrado)
        if (isset($_POST['accion_imagen'])) {
            // A) Cambio de Portada
            if ($_POST['accion_imagen'] === 'cambiar_portada' && isset($_POST['id_portada'])) {
                $idPortada = (int)$_POST['id_portada'];
                // Resetear todas a 0
                $conn->query("UPDATE imagenes_productos SET es_principal = 0 WHERE producto_id = $id");
                // Poner la elegida a 1
                $conn->query("UPDATE imagenes_productos SET es_principal = 1 WHERE id = $idPortada");
            }
            // B) Borrar Imagen
            elseif ($_POST['accion_imagen'] === 'borrar' && isset($_POST['id_borrar'])) {
                $idBorrar = (int)$_POST['id_borrar'];
                // Obtener ruta para borrar archivo
                $resImg = $conn->query("SELECT url_imagen FROM imagenes_productos WHERE id = $idBorrar");
                if ($rowImg = $resImg->fetch_assoc()) {
                    $rutaFisica = "../../" . $rowImg['url_imagen'];
                    if (file_exists($rutaFisica)) { unlink($rutaFisica); }
                }
                $conn->query("DELETE FROM imagenes_productos WHERE id = $idBorrar");
            }
        }

        // 4. Subir Nuevas Imágenes
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
                        // Insertamos como NO principal (0), el usuario la puede cambiar después
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

// --- CONSULTA DE DATOS PARA MOSTRAR ---
// 1. Producto
$prod = $conn->query("SELECT * FROM productos WHERE id = $id")->fetch_assoc();
if (!$prod) { echo "Producto no encontrado"; exit; }

// 2. Variantes
$resVar = $conn->query("SELECT talla, stock FROM inventario_tallas WHERE producto_id = $id ORDER BY id ASC");
$variantes = [];
while ($row = $resVar->fetch_assoc()) { $variantes[] = ['t' => $row['talla'], 's' => $row['stock']]; }
// Variable JS para Alpine
$jsonVariantes = json_encode($variantes);
$tieneVariantes = count($variantes) > 0 ? 'true' : 'false';

// 3. Imágenes
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
</head>
<body class="bg-slate-50 font-sans min-h-screen pb-10">

    <header class="bg-white shadow-sm sticky top-0 z-20 border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-6 h-20 flex items-center justify-between">
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

    <main class="max-w-6xl mx-auto px-6 py-8">
        
        <?php if($msg): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl font-bold flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-data="{ hasVariants: <?php echo $tieneVariantes; ?> }">
            
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="font-bold text-gray-700 uppercase text-xs mb-4 border-b pb-2">Información Básica</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Categoría</label>
                            <select name="categoria" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-gray-700">
                                <?php $cats = ['ropa','souvenirs','accesorios','general']; ?>
                                <?php foreach($cats as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $prod['categoria']==$c?'selected':''; ?>><?php echo ucfirst($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción Corta</label>
                        <textarea name="descripcion_corta" rows="2" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-sm"><?php echo htmlspecialchars($prod['descripcion_corta']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción Larga</label>
                        <textarea name="descripcion_larga" rows="4" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none text-sm"><?php echo htmlspecialchars($prod['descripcion_larga']); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio ($)</label>
                        <input type="number" step="0.01" name="precio" value="<?php echo $prod['precio']; ?>" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
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
                        <input type="number" name="stock_simple" value="<?php echo $prod['stock']; ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                    </div>

                    <div x-show="hasVariants" x-cloak>
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-4" 
                             x-data="{ rows: <?php echo empty($variantes) ? "[{t:'', s:0}]" : $jsonVariantes; ?> }">
                            
                            <template x-for="(row, index) in rows" :key="index">
                                <div class="flex gap-2 mb-2">
                                    <input type="text" name="tallas[]" x-model="row.t" placeholder="Talla" class="w-1/2 px-3 py-2 border rounded-lg text-sm uppercase font-bold focus:outline-none">
                                    <input type="number" name="stocks[]" x-model="row.s" placeholder="Cant." class="w-1/3 px-3 py-2 border rounded-lg text-sm font-bold focus:outline-none">
                                    <button type="button" @click="rows.splice(index, 1)" class="text-red-400 hover:text-red-600 px-2" x-show="rows.length > 0"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
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
                    <h2 class="font-bold text-gray-700 uppercase text-xs mb-4 border-b pb-2">Imágenes Actuales</h2>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <?php 
                        if ($resImg && $resImg->num_rows > 0): 
                            // Reiniciamos puntero para iterar
                            $resImg->data_seek(0);
                            while($img = $resImg->fetch_assoc()):
                        ?>
                            <div class="relative group border rounded-lg overflow-hidden <?php echo $img['es_principal']?'ring-2 ring-escala-green':''; ?>">
                                <img src="../../<?php echo $img['url_imagen']; ?>" class="w-full h-24 object-cover">
                                
                                <?php if($img['es_principal']): ?>
                                    <div class="absolute top-0 left-0 bg-escala-green text-white text-[8px] px-2 py-1 font-bold uppercase w-full text-center">Portada Actual</div>
                                <?php else: ?>
                                    <div class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="submit" name="accion_imagen" value="cambiar_portada" onclick="this.form.appendChild(crearInput('id_portada', <?php echo $img['id']; ?>))" class="text-[9px] bg-white text-gray-800 px-2 py-1 rounded hover:bg-escala-green hover:text-white font-bold uppercase w-20">
                                            Hacer Portada
                                        </button>
                                        <button type="submit" name="accion_imagen" value="borrar" onclick="if(confirm('¿Borrar foto?')) { this.form.appendChild(crearInput('id_borrar', <?php echo $img['id']; ?>)); } else { return false; }" class="text-[9px] bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 font-bold uppercase w-20">
                                            Borrar
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <p class="text-xs text-gray-400 col-span-2 text-center py-4">Sin imágenes.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="font-bold text-gray-700 uppercase text-xs mb-4 border-b pb-2">Agregar Imágenes</h2>
                    
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition-colors relative">
                         <input type="file" name="nuevas_imagenes[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*">
                         <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-gray-300 mb-2"></i>
                         <p class="text-xs text-gray-400 font-bold">Subir más fotos</p>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-escala-green hover:bg-escala-dark text-white rounded-xl font-bold uppercase shadow-lg hover:shadow-xl transition-all flex justify-center gap-2 transform hover:-translate-y-1">
                    <i data-lucide="refresh-cw" class="w-5 h-5"></i> Actualizar Producto
                </button>
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
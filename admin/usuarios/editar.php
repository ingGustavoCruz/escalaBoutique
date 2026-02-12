<?php
/**
 * admin/usuarios/editar.php - Versión Consistente con Diseño General
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// 1. SEGURIDAD: Solo Super Admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_rol'] !== 'superadmin') {
    header("Location: ../dashboard.php");
    exit;
}

// 2. VALIDAR ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$msg = '';

// 3. PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    $pass = $_POST['password']; 

    if (empty($nombre) || empty($email)) {
        $msg = "Nombre y correo son obligatorios.";
    } else {
        if (!empty($pass)) {
            $stmt = $conn->prepare("UPDATE usuarios_admin SET nombre=?, email=?, rol=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $nombre, $email, $rol, $pass, $id);
            $logDetalle = "Editó usuario $email (con cambio de pass)";
        } else {
            $stmt = $conn->prepare("UPDATE usuarios_admin SET nombre=?, email=?, rol=? WHERE id=?");
            $stmt->bind_param("sssi", $nombre, $email, $rol, $id);
            $logDetalle = "Editó usuario $email (sin cambio pass)";
        }

        if ($stmt->execute()) {
            registrarBitacora('SEGURIDAD', 'EDITAR USUARIO', $logDetalle, $conn);
            header("Location: index.php?msg=updated");
            exit;
        } else {
            $msg = "Error al actualizar (quizás el correo ya existe).";
        }
    }
}

// 4. OBTENER DATOS ACTUALES
$stmtUser = $conn->prepare("SELECT * FROM usuarios_admin WHERE id = ?");
$stmtUser->bind_param("i", $id);
$stmtUser->execute();
$usuario = $stmtUser->get_result()->fetch_assoc();

if (!$usuario) { die("Usuario no encontrado."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario | Escala Admin</title>
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
                        'escala-blue': '#1e3a8a',
                        'escala-alert': '#FF9900'
                    } 
                } 
            } 
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-gray-900">

    <header class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center gap-4">
            <a href="index.php" class="p-2 text-gray-400 hover:text-escala-green hover:bg-gray-100 rounded-full transition-all">
                <i data-lucide="arrow-left" class="w-6 h-6"></i>
            </a>
            <h1 class="text-2xl font-black text-escala-dark uppercase tracking-tight">Editar Usuario</h1>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <?php if($msg): ?>
            <div class="mb-8 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm flex items-center gap-3">
                <i data-lucide="alert-circle" class="w-6 h-6 text-red-500"></i>
                <p class="text-red-700 font-bold"><?php echo $msg; ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <div class="flex items-center gap-3 mb-8 pb-4 border-b border-gray-100">
                        <div class="bg-escala-green/10 p-3 rounded-xl text-escala-green">
                            <i data-lucide="user" class="w-6 h-6"></i>
                        </div>
                        <h2 class="text-xl font-black text-escala-dark">Información Básica</h2>
                    </div>
                    
                    <form id="form-editar-usuario" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">Nombre Completo <span class="text-red-500">*</span></label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required 
                                       class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:bg-white focus:border-transparent transition-all font-bold text-gray-800 placeholder-gray-400">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">Email Corporativo <span class="text-red-500">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required 
                                       class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:bg-white focus:border-transparent transition-all font-medium text-gray-700 placeholder-gray-400">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">Rol de Acceso <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select name="rol" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:bg-white focus:border-transparent appearance-none font-bold text-gray-700 cursor-pointer transition-all">
                                        <option value="admin" <?php echo $usuario['rol']=='admin'?'selected':''; ?>>Admin (Limitado)</option>
                                        <option value="superadmin" <?php echo $usuario['rol']=='superadmin'?'selected':''; ?>>Super Admin (Total)</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-escala-green uppercase mb-2 tracking-wider">Nueva Contraseña</label>
                                <div class="relative">
                                    <input type="password" name="password" placeholder="Dejar en blanco para mantener" 
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:bg-white focus:border-transparent transition-all text-sm placeholder-gray-400">
                                    <i data-lucide="lock" class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"></i>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1 ml-1">Solo llénalo si deseas cambiar la clave actual.</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-28">
                    <h2 class="text-lg font-black text-escala-dark mb-6">Acciones</h2>
                    
                    <button type="submit" form="form-editar-usuario" class="w-full py-4 bg-escala-green hover:bg-escala-dark text-white font-black rounded-xl shadow-lg shadow-escala-green/20 transition-all transform active:scale-95 text-sm uppercase tracking-widest flex items-center justify-center gap-2 mb-4">
                        <i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios
                    </button>
                    
                    <a href="index.php" class="w-full py-4 text-center text-gray-500 font-bold hover:text-gray-700 hover:bg-gray-50 rounded-xl border border-gray-200 transition-all text-xs uppercase tracking-widest flex items-center justify-center gap-2">
                        <i data-lucide="x" class="w-4 h-4"></i> Cancelar
                    </a>
                </div>
                
                <div class="bg-blue-50 rounded-2xl border border-blue-100 p-6 flex items-start gap-4">
                    <i data-lucide="info" class="w-6 h-6 text-blue-500 shrink-0 mt-0.5"></i>
                    <div>
                        <h3 class="font-bold text-blue-700 mb-1">Nota de Seguridad</h3>
                        <p class="text-xs text-blue-600 leading-relaxed">
                            Los cambios en roles y contraseñas se reflejarán inmediatamente. Asegúrate de notificar al usuario si se han modificado sus credenciales de acceso.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
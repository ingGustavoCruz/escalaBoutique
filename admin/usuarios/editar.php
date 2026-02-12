<?php
/**
 * admin/usuarios/editar.php - Modificar usuario Staff
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
        // Configuración Quirúrgica: Inyección de la paleta oficial Escala
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
<body class="bg-slate-900/60 flex items-center justify-center min-h-screen p-4 backdrop-blur-md">

    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden transform transition-all scale-100 border border-white/20">
        
        <div class="px-8 py-6 border-b border-gray-100 flex items-center gap-3">
            <div class="bg-escala-blue/10 p-2 rounded-lg text-escala-blue">
                <i data-lucide="edit-3" class="w-6 h-6"></i>
            </div>
            <h1 class="text-xl font-black text-slate-800 tracking-tight">Editar Usuario</h1>
        </div>

        <div class="p-8">
            <?php if($msg): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm font-bold text-center">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 tracking-wider">Nombre Completo</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required 
                           class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-escala-blue focus:border-escala-blue focus:outline-none transition-all font-bold text-gray-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 tracking-wider">Email Corporativo</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required 
                           class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-escala-blue focus:border-escala-blue focus:outline-none transition-all font-medium text-gray-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 tracking-wider">Rol de Acceso</label>
                    <div class="relative">
                        <select name="rol" class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-escala-blue focus:outline-none appearance-none font-bold text-slate-700">
                            <option value="admin" <?php echo $usuario['rol']=='admin'?'selected':''; ?>>Admin (Limitado)</option>
                            <option value="superadmin" <?php echo $usuario['rol']=='superadmin'?'selected':''; ?>>Super Admin (Total)</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-escala-blue uppercase mb-1 tracking-wider">Nueva Contraseña</label>
                    <input type="password" name="password" placeholder="Dejar en blanco para mantener la actual" 
                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-escala-blue focus:outline-none transition-all placeholder-gray-400 text-sm">
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <a href="index.php" class="w-1/2 py-3.5 text-center text-gray-500 font-bold hover:bg-gray-100 rounded-xl transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" class="w-1/2 py-3.5 bg-escala-blue hover:bg-escala-blue/90 text-white font-bold rounded-xl shadow-lg shadow-escala-blue/20 transition-all transform active:scale-95">
                        Guardar Cambios
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
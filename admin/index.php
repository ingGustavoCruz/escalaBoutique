<?php
/**
 * admin/index.php - Login Administrativo
 */
session_start();
require_once '../api/conexion.php'; // Nota la ruta '../'

// Si ya está logueado, mandar al dashboard directo
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, nombre, password FROM usuarios_admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // Verificar hash de contraseña
        if (password_verify($pass, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_nombre'] = $row['nombre'];
            
            // Actualizar último acceso
            $conn->query("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = " . $row['id']);
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Escala Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md border border-gray-700 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 to-cyan-400"></div>
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 mb-4">
                <i data-lucide="shield-check" class="w-8 h-8 text-blue-600"></i>
            </div>
            <h1 class="text-2xl font-black text-gray-800 uppercase tracking-wide">Acceso Administrativo</h1>
            <p class="text-sm text-gray-400">Escala Boutique Intranet</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-bold mb-4 flex items-center gap-2 border border-red-100">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Correo Electrónico</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="email" name="email" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all text-sm" placeholder="admin@escala.com">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Contraseña</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="password" name="password" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all text-sm" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-bold py-3 rounded-xl hover:bg-gray-800 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex justify-center items-center gap-2">
                INGRESAR <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
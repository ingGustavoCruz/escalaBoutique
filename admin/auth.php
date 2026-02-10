<?php
/**
 * admin/auth.php - Login de Administradores
 */
session_start();
require_once '../api/conexion.php';
require_once '../api/logger.php'; // <--- INTEGRACIÓN BITÁCORA

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validar contra tabla 'admins' (Asegúrate de tener esta tabla o ajusta a tu lógica)
    // Antes: SELECT id, nombre, password FROM admins...
// Ahora:
$stmt = $conn->prepare("SELECT id, nombre, password, rol FROM admins WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // En producción usa: password_verify($password, $row['password'])
        // Para este ejemplo asumo texto plano o md5 según tu configuración actual
        if ($password === $row['password']) { 
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_nombre'] = $row['nombre'];
            $_SESSION['admin_rol'] = $row['rol']; // <--- NUEVO: Guardamos el rol

            registrarBitacora('SEGURIDAD', 'LOGIN', "Acceso: " . $row['nombre'] . " (" . $row['rol'] . ")", $conn);
            header("Location: dashboard.php");
            exit;
        }else {
            $error = "Contraseña incorrecta.";
            // Opcional: Registrar intentos fallidos
            // registrarBitacora('SEGURIDAD', 'LOGIN FALLIDO', "Pass incorrecto para: $email", $conn);
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
    <title>Login Admin | Escala Boutique</title>
    <link rel="icon" type="image/png" href="../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border-t-4 border-escala-green">
        <div class="text-center mb-8">
            <img src="../imagenes/EscalaBoutique.png" alt="Escala" class="h-12 mx-auto mb-4 object-contain">
            <h1 class="text-xl font-black text-escala-dark uppercase tracking-widest">Acceso Administrativo</h1>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm font-bold text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Correo Electrónico</label>
                <input type="email" name="email" required class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:border-escala-green focus:ring-1 focus:ring-escala-green transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Contraseña</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:border-escala-green focus:ring-1 focus:ring-escala-green transition-colors">
            </div>
            <button type="submit" class="w-full bg-escala-green hover:bg-escala-dark text-white font-bold py-3 rounded-xl transition-all shadow-lg uppercase text-sm tracking-wide">
                Ingresar al Sistema
            </button>
        </form>
        <p class="mt-8 text-center text-xs text-gray-400">Sistema de Gestión Interna &copy; <?php echo date('Y'); ?></p>
    </div>
</body>
</html>
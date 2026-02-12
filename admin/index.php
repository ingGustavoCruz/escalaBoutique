<?php
/**
 * admin/index.php - Login Administrativo Protegido con CSRF
 */
session_start();
require_once '../api/conexion.php'; 

// Si ya está logueado, mandar al dashboard directo
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CIRUGÍA: Validar escudo CSRF antes de procesar credenciales
    validar_csrf(); 

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Escala Boutique</title>
    <link rel="icon" type="image/png" href="../imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        // CIRUGÍA: Inyección de paleta oficial para consistencia visual
        tailwind.config = {
            theme: {
                extend: { 
                    colors: { 
                        'escala-green': '#00524A', 
                        'escala-beige': '#AA9482', 
                        'escala-dark': '#003d36' 
                    } 
                }
            }
        }
    </script>
</head>
<body class="bg-escala-dark min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-md border border-white/10 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-escala-green to-escala-beige"></div>
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-escala-green/10 mb-4 text-escala-green">
                <i data-lucide="shield-check" class="w-10 h-10"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Acceso Staff</h1>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Escala Boutique Intranet</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-xs font-black mb-6 flex items-center gap-3 border border-red-100 uppercase tracking-tighter">
                <i data-lucide="alert-circle" class="w-5 h-5"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Correo Electrónico</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="email" name="email" required 
                           class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-escala-green focus:bg-white transition-all text-sm font-bold text-gray-700" 
                           placeholder="admin@escala.com">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Contraseña</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="password" name="password" required 
                           class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-escala-green focus:bg-white transition-all text-sm font-bold text-gray-700" 
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full bg-escala-green text-white font-black py-4 rounded-2xl hover:bg-escala-dark transition-all shadow-xl shadow-escala-green/20 transform active:scale-95 flex justify-center items-center gap-3 uppercase text-xs tracking-widest">
                INGRESAR AL PANEL <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>

        <p class="mt-8 text-center text-[10px] text-gray-400 font-bold uppercase tracking-[0.2em]">
            &copy; <?php echo date('Y'); ?> Escala Business Management
        </p>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
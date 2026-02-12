<?php
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// Seguridad: Solo admins logueados
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

$mensaje = "";

// --- PROCESAR ACTUALIZACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CIRUGÍA: Validar escudo CSRF antes de guardar cambios
    validar_csrf(); 

    $configuraciones = [
        'email_nomina' => $_POST['email_nomina'],
        'email_almacen' => $_POST['email_almacen'],
        'limite_quincenas' => $_POST['limite_quincenas']
    ];

    $conn->begin_transaction();
    try {
        foreach ($configuraciones as $clave => $valor) {
            $stmt = $conn->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
            $stmt->bind_param("ss", $valor, $clave);
            $stmt->execute();
        }
        
        registrarBitacora('SISTEMA', 'ACTUALIZAR CONFIG', "Se actualizaron los correos y límites del sistema", $conn);
        $conn->commit();
        $mensaje = "Configuración guardada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al guardar: " . $e->getMessage();
    }
}

// --- OBTENER VALORES ACTUALES ---
$email_nomina = get_config('email_nomina', $conn);
$email_almacen = get_config('email_almacen', $conn);
$limite_qnas = get_config('limite_quincenas', $conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración | Escala Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden">

    <?php include '../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-black text-escala-green uppercase">Ajustes del Sistema</h1>
            <p class="text-gray-500 text-sm">Gestiona los correos de notificación y parámetros globales.</p>
        </header>

        <?php if($mensaje): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl flex items-center gap-3 font-bold">
                <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="max-w-2xl bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <form method="POST" class="p-8 space-y-6">
                
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="space-y-2">
                    <label class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i data-lucide="mail" class="w-3 h-3"></i> Correo Directora de Nómina
                    </label>
                    <input type="email" name="email_nomina" value="<?php echo $email_nomina; ?>" required
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-escala-green outline-none transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i data-lucide="truck" class="w-3 h-3"></i> Correo Encargado de Almacén
                    </label>
                    <input type="email" name="email_almacen" value="<?php echo $email_almacen; ?>" required
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-escala-green outline-none transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i data-lucide="calendar" class="w-3 h-3"></i> Máximo de Quincenas Permitidas
                    </label>
                    <select name="limite_quincenas" 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-escala-green outline-none appearance-none">
                        <option value="1" <?php echo $limite_qnas == 1 ? 'selected' : ''; ?>>1 Quincena</option>
                        <option value="2" <?php echo $limite_qnas == 2 ? 'selected' : ''; ?>>2 Quincenas</option>
                        <option value="3" <?php echo $limite_qnas == 3 ? 'selected' : ''; ?>>3 Quincenas</option>
                        <option value="4" <?php echo $limite_qnas == 4 ? 'selected' : ''; ?>>4 Quincenas</option>
                    </select>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-escala-green hover:bg-escala-dark text-white font-bold py-4 rounded-xl shadow-lg shadow-escala-green/20 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-5 h-5"></i> GUARDAR CAMBIOS
                    </button>
                </div>

            </form>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
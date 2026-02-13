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
$error = "";

// --- PROCESAR ACTUALIZACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $error = "Error al guardar: " . $e->getMessage();
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
    <title>Ajustes del Sistema | Escala Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'escala-green': '#00524A',
                        'escala-dark': '#003d36',
                        'escala-beige': '#AA9482',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center py-12 px-4">

    <div class="w-full max-w-3xl">
        
        <header class="flex justify-between items-start mb-10">
            <div class="flex items-center gap-4">
                <a href="../index.php" class="p-2 hover:bg-white rounded-xl transition-all shadow-sm border border-transparent hover:border-gray-200 group">
                    <i data-lucide="arrow-left" class="w-6 h-6 text-gray-400 group-hover:text-escala-green"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-black text-escala-green uppercase tracking-tighter leading-none">Ajustes del Sistema</h1>
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-[0.15em] mt-1">Parámetros globales de Escala Boutique</p>
                </div>
            </div>
            <img src="../../imagenes/EscalaBoutique.png" alt="Logo" class="h-8 opacity-20 grayscale">
        </header>

        <?php if($mensaje): ?>
            <div class="mb-8 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-xl flex items-center gap-3 font-bold text-sm shadow-sm animate-pulse">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="mb-8 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-xl flex items-center gap-3 font-bold text-sm shadow-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2.5rem] shadow-[0_20px_50px_-20px_rgba(0,0,0,0.05)] border border-gray-100 overflow-hidden">
            <div class="bg-gray-50/50 px-10 py-6 border-b border-gray-100 flex justify-between items-center">
                <span class="text-[10px] uppercase font-black text-gray-400 tracking-[0.2em]">Configuración General</span>
                <i data-lucide="settings" class="w-4 h-4 text-gray-300"></i>
            </div>

            <form method="POST" class="p-10 space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-3">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2 ml-1">
                            Correo Directora de Nómina
                        </label>
                        <div class="relative group">
                            <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 group-focus-within:text-escala-beige transition-colors"></i>
                            <input type="email" name="email_nomina" value="<?php echo $email_nomina; ?>" required
                                   class="w-full bg-slate-50 border-none rounded-2xl pl-12 pr-4 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-escala-beige transition-all outline-none">
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2 ml-1">
                            Correo Encargado de Almacén
                        </label>
                        <div class="relative group">
                            <i data-lucide="truck" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 group-focus-within:text-escala-beige transition-colors"></i>
                            <input type="email" name="email_almacen" value="<?php echo $email_almacen; ?>" required
                                   class="w-full bg-slate-50 border-none rounded-2xl pl-12 pr-4 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-escala-beige transition-all outline-none">
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2 ml-1">
                        Máximo de Quincenas Permitidas
                    </label>
                    <div class="relative group">
                        <i data-lucide="calendar" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 group-focus-within:text-escala-beige transition-colors"></i>
                        <select name="limite_quincenas" 
                                class="w-full bg-slate-50 border-none rounded-2xl pl-12 pr-4 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-escala-beige transition-all appearance-none outline-none cursor-pointer">
                            <option value="1" <?php echo $limite_qnas == 1 ? 'selected' : ''; ?>>1 QUINCENA</option>
                            <option value="2" <?php echo $limite_qnas == 2 ? 'selected' : ''; ?>>2 QUINCENAS</option>
                            <option value="3" <?php echo $limite_qnas == 3 ? 'selected' : ''; ?>>3 QUINCENAS</option>
                            <option value="4" <?php echo $limite_qnas == 4 ? 'selected' : ''; ?>>4 QUINCENAS</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-50">
                    <button type="submit" 
                            class="w-full bg-escala-green hover:bg-escala-dark text-white font-black py-5 rounded-2xl shadow-xl shadow-escala-green/20 transition-all hover:-translate-y-1 active:translate-y-0 flex items-center justify-center gap-3 uppercase text-xs tracking-[0.25em]">
                        <i data-lucide="save" class="w-5 h-5 text-escala-beige"></i> Guardar Configuración
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-12 text-center">
            <p class="text-[9px] font-black text-gray-300 uppercase tracking-[0.4em]">Powered by Escala TI</p>
        </footer>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
<?php
/**
 * admin/usuarios/index.php - Gestión de Staff Protegida con CSRF
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

$ruta_base = "../../"; 

// 1. SEGURIDAD EXTREMA
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_rol'] !== 'superadmin') {
    header("Location: ../dashboard.php");
    exit;
}

$msg = '';

// 2. LOGICA: Procesar Acciones (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CIRUGÍA: Validar escudo CSRF para cualquier acción POST
    validar_csrf(); 

    // A. CREAR USUARIO
    if ($_POST['action'] === 'create') {
        $nombre = $_POST['nombre'];
        $email = $_POST['email'];
        $pass = $_POST['password']; 
        $rol = $_POST['rol'];

        $stmt = $conn->prepare("INSERT INTO usuarios_admin (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre, $email, $pass, $rol);
        
        if ($stmt->execute()) {
            registrarBitacora('SEGURIDAD', 'CREAR USUARIO', "Nuevo staff: $email ($rol)", $conn);
            $msg = "Usuario creado correctamente.";
        } else {
            $msg = "Error: El correo ya existe.";
        }
    }

    // B. ELIMINAR USUARIO (Ahora por POST para mayor seguridad)
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $idDel = (int)$_POST['id'];
        if ($idDel !== $_SESSION['admin_id']) { 
            $conn->query("DELETE FROM usuarios_admin WHERE id = $idDel");
            registrarBitacora('SEGURIDAD', 'ELIMINAR USUARIO', "Eliminó al admin ID: $idDel", $conn);
            $msg = "Usuario eliminado correctamente.";
        }
    }
}

$usuarios_admin = $conn->query("SELECT * FROM usuarios_admin ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Gestión Staff | Escala Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'escala-green': '#00524A',
                        'escala-dark': '#003d36',
                        'escala-beige': '#AA9482'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans" x-data="{ sidebarOpen: false, showForm: false }">

    <div class="flex h-screen overflow-hidden">
        
        <?php include '../includes/sidebar.php'; ?>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
            
            <div class="md:hidden bg-white h-16 shadow-sm flex items-center justify-between px-4 z-10 border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="text-gray-600 hover:text-escala-green focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <span class="font-black text-escala-green uppercase tracking-wide">Escala Admin</span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                
                <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Gestión de Staff</h1>
                        <p class="text-sm text-gray-500">Control de accesos y roles administrativos</p>
                    </div>
                </header>

                <?php if($msg): ?>
                    <div class="bg-green-100 text-green-800 p-4 rounded-xl mb-6 font-bold flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                    <button @click="showForm = !showForm" class="flex items-center gap-2 text-escala-green font-bold hover:text-escala-dark transition-colors">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i> <span x-text="showForm ? 'Cerrar Formulario' : 'Nuevo Usuario'"></span>
                    </button>

                    <div x-show="showForm" x-collapse class="mt-6 border-t border-gray-100 pt-6">
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nombre Completo</label>
                                <input type="text" name="nombre" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none font-bold text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Correo Electrónico</label>
                                <input type="email" name="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Contraseña</label>
                                <input type="password" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Rol de Acceso</label>
                                <select name="rol" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none font-bold">
                                    <option value="admin">Admin (Limitado)</option>
                                    <option value="superadmin">Super Admin (Total)</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="w-full py-4 bg-escala-dark text-white font-black uppercase tracking-widest rounded-xl hover:bg-escala-green transition-all shadow-lg">
                                    Guardar Usuario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[600px]">
                            <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-wider border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Usuario</th>
                                    <th class="px-6 py-4">Rol</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php while($user = $usuarios_admin->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-6 py-4 text-gray-400 font-mono text-xs">#<?php echo $user['id']; ?></td>
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-slate-800"><?php echo $user['nombre']; ?></p>
                                        <p class="text-xs text-gray-400"><?php echo $user['email']; ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="<?php echo $user['rol'] === 'superadmin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'; ?> px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wide">
                                            <?php echo $user['rol'] === 'superadmin' ? 'Super Admin' : 'Admin'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="editar.php?id=<?php echo $user['id']; ?>" class="text-gray-300 hover:text-escala-green transition-colors">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </a>
                                            
                                            <?php if($user['id'] != $_SESSION['admin_id']): ?>
                                                <form method="POST" onsubmit="return confirm('¿Eliminar acceso definitivamente?')" class="inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
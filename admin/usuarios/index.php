<?php
/**
 * admin/usuarios/index.php - Gestión de Staff (Solo Super Admin)
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// 1. SEGURIDAD EXTRAMA: Solo Super Admin entra aquí
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_rol'] !== 'superadmin') {
    // Si es un admin normal intentando entrar, lo sacamos
    header("Location: ../dashboard.php");
    exit;
}

$msg = '';

// 2. LOGICA: Crear / Eliminar Usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $nombre = $_POST['nombre'];
        $email = $_POST['email'];
        $pass = $_POST['password']; // En prod usar password_hash()
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
}

if (isset($_GET['delete'])) {
    $idDel = (int)$_GET['delete'];
    if ($idDel !== $_SESSION['admin_id']) { // No te puedes borrar a ti mismo
        $conn->query("DELETE FROM usuarios_admin WHERE id = $idDel");
        registrarBitacora('SEGURIDAD', 'ELIMINAR USUARIO', "Eliminó al admin ID: $idDel", $conn);
        header("Location: index.php"); exit;
    }
}

$usuarios_admin = $conn->query("SELECT * FROM usuarios_admin ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión Staff | Escala Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-dark': '#003d36', 'escala-beige': '#AA9482' } } } }
    </script>
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden">

    <aside class="w-64 bg-escala-dark text-escala-beige flex flex-col flex-shrink-0 shadow-2xl z-20">
        <div class="p-6 flex flex-col items-center justify-center border-b border-white/10 bg-escala-green/20">
            <img src="../../imagenes/EscalaBoutique.png" class="h-8 w-auto mb-2">
            <span class="font-black text-[10px] text-white uppercase tracking-widest">Super Admin</span>
        </div>
        <nav class="flex-1 py-6 px-4 space-y-2">
            <a href="../dashboard.php" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl"><i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard</a>
            
            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Administración</div>
            <a href="#" class="flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl shadow-inner"><i data-lucide="shield" class="w-5 h-5"></i> Usuarios Staff</a>
            <a href="../bitacora/" class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl"><i data-lucide="scroll-text" class="w-5 h-5"></i> Bitácora</a>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden p-8" x-data="{ showForm: false }">
        
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">Gestión de Staff</h1>
                <p class="text-gray-500">Control de accesos y roles administrativos</p>
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
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nombre Completo</label>
                        <input type="text" name="nombre" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none">
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
                        <select name="rol" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-escala-green focus:outline-none">
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
            <table class="w-full text-left">
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
                            <?php if($user['rol'] === 'superadmin'): ?>
                                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wide">Super Admin</span>
                            <?php else: ?>
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wide">Admin</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="editar.php?id=<?php echo $user['id']; ?>" class="text-gray-300 hover:text-blue-600 transition-colors">
    <i data-lucide="edit-3" class="w-4 h-4"></i>
</a>
                                <?php if($user['id'] != $_SESSION['admin_id']): ?>
                                    <a href="index.php?delete=<?php echo $user['id']; ?>" onclick="return confirm('¿Eliminar acceso?')" class="text-gray-300 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
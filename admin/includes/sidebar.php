<div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity 
     class="fixed inset-0 z-20 bg-black/50 backdrop-blur-sm md:hidden"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-30 w-64 bg-escala-dark text-escala-beige transition-transform duration-300 transform md:translate-x-0 md:static md:inset-auto flex flex-col shadow-2xl h-full">
    
    <div class="p-6 flex flex-col items-center justify-center border-b border-white/10 bg-escala-green/20 relative shrink-0">
        <button @click="sidebarOpen = false" class="absolute top-4 right-4 text-white/50 hover:text-white md:hidden">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <img src="<?php echo $ruta_base; ?>imagenes/EscalaBoutique.png" alt="Escala" class="h-10 w-auto object-contain mb-2">
        
        <span class="font-black text-[10px] text-white uppercase tracking-widest bg-white/10 px-2 py-0.5 rounded">
            <?php 
                if(isset($_SESSION['admin_rol'])) {
                    echo $_SESSION['admin_rol'] === 'superadmin' ? 'SUPER ADMIN' : 'ADMINISTRADOR'; 
                } else {
                    echo 'ADMIN';
                }
            ?>
        </span>
    </div>

    <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
        
        <a href="<?php echo $ruta_base; ?>admin/dashboard.php" 
           class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i> <span class="font-medium text-sm">Dashboard</span>
        </a>
        
        <a href="<?php echo $ruta_base; ?>admin/pedidos/" 
           class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'pedidos') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
            <i data-lucide="shopping-bag" class="w-5 h-5"></i> <span class="font-medium text-sm">Pedidos</span>
        </a>

        <a href="<?php echo $ruta_base; ?>admin/productos/" 
           class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'productos') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
            <i data-lucide="box" class="w-5 h-5"></i> <span class="font-medium text-sm">Productos</span>
        </a>

        <a href="<?php echo $ruta_base; ?>admin/empleados/" 
           class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'empleados') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
            <i data-lucide="users" class="w-5 h-5"></i> <span class="font-medium text-sm">Empleados</span>
        </a>

        <a href="<?php echo $ruta_base; ?>admin/cupones/" 
        class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'cupones') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
            <i data-lucide="ticket" class="w-5 h-5"></i> <span class="font-medium text-sm">Cupones</span>
        </a>

        <a href="<?php echo $ruta_base; ?>admin/inventario/" 
        class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'inventario') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
            <i data-lucide="package-search" class="w-5 h-5"></i><span class="font-medium text-sm">Inventario</span>
        </a>

        <?php if(isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] === 'superadmin'): ?>
            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest opacity-50 mt-2 border-t border-white/5">
                Seguridad
            </div>
            
            <a href="<?php echo $ruta_base; ?>admin/usuarios/" 
               class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'usuarios') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
                <i data-lucide="shield" class="w-5 h-5"></i> <span class="font-medium text-sm">Staff</span>
            </a>
            
            <a href="<?php echo $ruta_base; ?>admin/bitacora/" 
               class="flex items-center gap-3 px-4 py-3 text-escala-beige hover:text-white hover:bg-white/5 rounded-xl transition-all <?php echo strpos($_SERVER['PHP_SELF'], 'bitacora') ? 'bg-white/10 text-white shadow-inner' : ''; ?>">
                <i data-lucide="scroll-text" class="w-5 h-5"></i> <span class="font-medium text-sm">Bitácora</span>
            </a>
        <?php endif; ?>

    </nav>

    <div class="p-4 border-t border-white/10 shrink-0">
        <a href="<?php echo $ruta_base; ?>admin/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500/10 rounded-xl transition-all group">
            <i data-lucide="log-out" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i> 
            <span class="font-bold text-sm">Cerrar Sesión</span>
        </a>
    </div>
</aside>
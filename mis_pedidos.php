<?php
/**
 * mis_pedidos.php - Historial de Compras con Desglose Quincenal
 * Versión: Frontend Optimizado (Lazy Loading)
 */
require_once 'api/conexion.php';
session_start();

// --- 1. SEGURIDAD DE SESIÓN ---
if (!isset($_SESSION['empleado_id_db'])) {
    header("Location: index.php");
    exit;
}

$empleado_id = $_SESSION['empleado_id_db'];
$empleado_nombre = $_SESSION['usuario_empleado']['nombre'] ?? "Colaborador";
$empleado_num = $_SESSION['usuario_empleado']['numero'] ?? "S/N";

// --- 2. OBTENER PEDIDOS (Estructura Optimizada) ---
$pedidos = [];
$stmt = $conn->prepare("
    SELECT id, fecha_pedido, monto_total, estado, plazos 
    FROM pedidos 
    WHERE empleado_id = ? 
    ORDER BY fecha_pedido DESC
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$res = $stmt->get_result();

while($row = $res->fetch_assoc()) {
    $pid = $row['id'];
    
    // Consulta de detalles para cada pedido
    $stmtDet = $conn->prepare("
        SELECT 
            dp.cantidad, 
            dp.precio_unitario, 
            dp.talla, 
            p.nombre,
            (SELECT url_imagen FROM imagenes_productos ip WHERE ip.producto_id = p.id ORDER BY ip.es_principal DESC LIMIT 1) as imagen_url
        FROM detalles_pedido dp
        JOIN productos p ON dp.producto_id = p.id
        WHERE dp.pedido_id = ?
    ");
    
    $stmtDet->bind_param("i", $pid);
    $stmtDet->execute();
    $resDet = $stmtDet->get_result();
    
    $productos_detalle = [];
    while($prod = $resDet->fetch_assoc()) {
        $prod['img'] = !empty($prod['imagen_url']) ? $prod['imagen_url'] : 'imagenes/torito.png';
        $productos_detalle[] = $prod;
    }
    $stmtDet->close();
    
    $row['items'] = $productos_detalle;
    $pedidos[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es" x-data="{ openDetail: null }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos | Escala Boutique</title>
    <link rel="icon" type="image/png" href="imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'escala-green': '#00524A',
                        'escala-beige': '#AA9482',
                        'escala-dark': '#003d36',
                        'escala-blue': '#1e3a8a',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-gray-800 flex flex-col">

    <header class="bg-escala-green py-4 shadow-lg sticky top-0 z-30 border-b border-escala-dark">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-white hover:text-escala-beige transition-colors p-1 rounded-full hover:bg-white/10">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <h1 class="font-black uppercase tracking-widest text-lg md:text-xl text-white leading-none">MIS PEDIDOS</h1>
            </div>
            <div class="flex flex-col items-end text-right">
                <span class="text-[9px] font-black text-white/50 uppercase tracking-widest">COLABORADOR</span>
                <span class="text-sm font-bold text-escala-beige truncate max-w-[200px]"><?php echo htmlspecialchars($empleado_nombre); ?></span>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 py-10 w-full flex-grow">
        
        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-8 mb-10 flex flex-col md:flex-row items-center md:justify-between gap-6 transition-all hover:shadow-md">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 bg-escala-green/5 rounded-full flex items-center justify-center text-escala-green shadow-inner">
                    <i data-lucide="user" class="w-10 h-10"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-escala-green uppercase tracking-tight"><?php echo htmlspecialchars($empleado_nombre); ?></h2>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mt-1">Número de Nómina: <span class="text-escala-beige"><?php echo htmlspecialchars($empleado_num); ?></span></p>
                </div>
            </div>
            <div class="text-center md:text-right bg-slate-50 px-8 py-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] text-gray-400 uppercase font-black tracking-widest mb-1">Total de Pedidos</p>
                <p class="text-4xl font-black text-escala-green tracking-tighter"><?php echo count($pedidos); ?></p>
            </div>
        </div>

        <h3 class="text-[11px] font-black uppercase text-gray-400 mb-6 flex items-center gap-3 tracking-[0.3em] ml-2">
            <i data-lucide="calendar-days" class="w-4 h-4 text-escala-beige"></i> Historial de Adquisiciones
        </h3>

        <?php if(empty($pedidos)): ?>
            <div class="flex flex-col items-center justify-center py-24 bg-white rounded-[2.5rem] border-2 border-dashed border-slate-100 text-slate-300">
                <div class="bg-slate-50 p-6 rounded-full mb-6">
                    <i data-lucide="shopping-bag" class="w-12 h-12 opacity-20"></i>
                </div>
                <p class="font-black uppercase text-xs tracking-widest">Tu historial está vacío por ahora</p>
                <a href="index.php" class="mt-6 px-8 py-3 bg-escala-green text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-escala-dark transition-all shadow-lg shadow-escala-green/20">Explorar Boutique</a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach($pedidos as $p): ?>
                    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden transition-all hover:shadow-md group">
                        
                        <div class="p-6 flex flex-wrap items-center justify-between gap-6 bg-white cursor-pointer select-none" 
                             @click="openDetail === <?php echo $p['id']; ?> ? openDetail = null : openDetail = <?php echo $p['id']; ?>">
                            
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-slate-300 uppercase tracking-[0.2em] mb-1">Folio #<?php echo str_pad($p['id'], 5, "0", STR_PAD_LEFT); ?></span>
                                <span class="text-base font-black text-slate-700 uppercase tracking-tight"><?php echo date('d M, Y', strtotime($p['fecha_pedido'])); ?></span>
                                <span class="text-[10px] text-slate-400 font-bold"><?php echo date('h:i A', strtotime($p['fecha_pedido'])); ?></span>
                            </div>
                            
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-slate-300 uppercase tracking-[0.2em] mb-1">Inversión Total</span>
                                <span class="text-xl font-black text-escala-green tracking-tighter">$<?php echo number_format($p['monto_total'], 2); ?></span>
                            </div>

                            <div class="flex items-center gap-6">
                                <?php 
                                    $estado = strtolower($p['estado']);
                                    $estilo = 'bg-slate-100 text-slate-400'; $icono = 'circle';
                                    if($estado == 'pendiente') { $estilo = 'bg-amber-50 text-amber-600 border border-amber-100'; $icono='clock'; }
                                    if($estado == 'aprobado') { $estilo = 'bg-blue-50 text-blue-600 border border-blue-100'; $icono='check-circle'; }
                                    if($estado == 'entregado') { $estilo = 'bg-emerald-50 text-emerald-600 border border-emerald-100'; $icono='package'; }
                                    if($estado == 'cancelado') { $estilo = 'bg-rose-50 text-rose-600 border border-rose-100'; $icono='x-circle'; }
                                ?>
                                <span class="px-4 py-1.5 rounded-full text-[10px] uppercase font-black flex items-center gap-2 tracking-widest <?php echo $estilo; ?>">
                                    <i data-lucide="<?php echo $icono; ?>" class="w-3.5 h-3.5"></i> <?php echo $estado; ?>
                                </span>

                                <div class="w-10 h-10 flex items-center justify-center rounded-2xl bg-slate-50 text-slate-400 group-hover:bg-escala-green group-hover:text-white transition-all duration-300 shadow-sm"
                                     :class="openDetail === <?php echo $p['id']; ?> ? '!bg-escala-beige !text-white rotate-180' : ''">
                                    <i data-lucide="chevron-down" class="w-5 h-5"></i>
                                </div>
                            </div>
                        </div>

                        <div x-show="openDetail === <?php echo $p['id']; ?>" x-collapse x-cloak class="bg-slate-50/50 border-t border-slate-100">
                            <div class="p-8">
                                <div class="mb-10 p-6 bg-white rounded-[1.5rem] border border-slate-100 shadow-sm">
                                    <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-[0.3em] mb-4 flex items-center gap-3">
                                        <i data-lucide="calendar-range" class="w-4 h-4 text-escala-beige"></i> Proyección de Descuento Quincenal
                                    </h4>
                                    <div class="flex gap-2.5 h-2 mb-4">
                                        <?php for($i=1; $i<=$p['plazos']; $i++): ?>
                                            <div class="flex-1 rounded-full <?php echo ($estado == 'entregado' || $estado == 'aprobado') ? 'bg-escala-green' : 'bg-slate-200'; ?> opacity-40 shadow-sm"></div>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="flex justify-between items-end text-[11px] font-black uppercase tracking-widest">
                                        <span class="text-slate-400">Plan: <span class="text-slate-700"><?php echo $p['plazos']; ?> quincena(s)</span></span>
                                        <span class="text-escala-green text-sm">$<?php echo number_format($p['monto_total'] / $p['plazos'], 2); ?> por quincena</span>
                                    </div>
                                </div>

                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-5 ml-2">Artículos Solicitados:</p>
                                <div class="space-y-4">
                                    <?php foreach($p['items'] as $item): ?>
                                        <div class="flex items-center gap-6 p-4 bg-white rounded-2xl border border-transparent hover:border-slate-200 transition-all shadow-sm group/item">
                                            <div class="w-16 h-16 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center shrink-0 p-2 overflow-hidden group-hover/item:scale-105 transition-transform">
                                                <img src="<?php echo $item['img']; ?>" 
                                                     loading="lazy"
                                                     class="w-full h-full object-contain" 
                                                     onerror="this.onerror=null; this.src='imagenes/torito.png';">
                                            </div>
                                            <div class="flex-grow">
                                                <p class="text-sm font-black text-slate-700 uppercase tracking-tight leading-none mb-1"><?php echo $item['nombre']; ?></p>
                                                <?php if(!empty($item['talla'])): ?>
                                                    <span class="text-[9px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md font-black uppercase tracking-tighter">
                                                        Talla: <?php echo $item['talla']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-right flex flex-col items-end">
                                                <span class="text-[10px] font-black text-slate-300 uppercase mb-1 tracking-widest">Cant. <?php echo $item['cantidad']; ?></span>
                                                <span class="text-base font-black text-escala-green tracking-tighter">$<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-gray-50 py-6 border-t-2 border-escala-blue mt-auto">
        <div class="max-w-[1400px] mx-auto px-4 flex flex-col items-center justify-center">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.3em] mb-4">Powered By</p>
            <img src="imagenes/KAI_NA.png" alt="KAI Experience" class="h-10 w-auto escala-blue">
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
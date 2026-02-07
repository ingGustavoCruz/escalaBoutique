<?php
/**
 * index.php - EscalaBoutique (Intranet)
 * Versión: Final Visual (Animaciones, Footer KAI, Tarjetas Estilo Boutique)
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); 

require_once 'api/conexion.php';

// --- 1. LOGIN SILENCIOSO ---
$es_local = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');

if ($es_local) {
    if (!isset($_SESSION['usuario_empleado'])) {
        $_SESSION['usuario_empleado'] = [
            'numero' => '12345',
            'nombre' => 'Empleado Pruebas Local',
            'email'  => 'demo@escala.com',
            'area'   => 'TI'
        ];
    }
}

// Registro en BD
if (isset($_SESSION['usuario_empleado'])) {
    $emp = $_SESSION['usuario_empleado'];
    $stmt = $conn->prepare("SELECT id FROM empleados WHERE numero_empleado = ?");
    $stmt->bind_param("s", $emp['numero']);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $conn->query("UPDATE empleados SET fecha_ultimo_acceso = NOW() WHERE id = " . $row['id']);
        $_SESSION['empleado_id_db'] = $row['id'];
    } else {
        $stmtIns = $conn->prepare("INSERT INTO empleados (numero_empleado, nombre, email, area, fecha_ultimo_acceso) VALUES (?, ?, ?, ?, NOW())");
        $stmtIns->bind_param("ssss", $emp['numero'], $emp['nombre'], $emp['email'], $emp['area']);
        $stmtIns->execute();
        $_SESSION['empleado_id_db'] = $conn->insert_id;
    }
}

// --- 2. CARGA PRODUCTOS ---
$conn->query("SET SESSION group_concat_max_len = 10000;");
$query = "SELECT p.*, GROUP_CONCAT(i.url_imagen ORDER BY i.es_principal DESC, i.id ASC) as lista_imagenes 
          FROM productos p 
          LEFT JOIN imagenes_productos i ON p.id = i.producto_id 
          GROUP BY p.id";

$resultado = $conn->query($query);
$productos = [];
$categorias = ['Todos']; 

if ($resultado && $resultado->num_rows > 0) {
    while($row = $resultado->fetch_assoc()) {
        $row['imagenes'] = $row['lista_imagenes'] ? explode(',', $row['lista_imagenes']) : ['https://via.placeholder.com/400'];
        $row['precio'] = (float)$row['precio'];
        $row['stock'] = (int)$row['stock'];
        $row['en_oferta'] = (int)$row['en_oferta'];
        $row['es_top'] = (int)$row['es_top'];
        $row['categoria'] = $row['categoria'] ?? 'General';
        $row['calificacion'] = (float)($row['calificacion'] ?? 5.0);
        
        if (!in_array($row['categoria'], $categorias)) {
            $categorias[] = $row['categoria'];
        }
        $productos[] = $row;
    }
}

$nombreCompleto = isset($_SESSION['usuario_empleado']) ? $_SESSION['usuario_empleado']['nombre'] : 'Invitado';
$partesNombre = explode(' ', $nombreCompleto);
$nombreCorto = $partesNombre[0];
?>

<!DOCTYPE html>
<html lang="es" x-data="appData()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escala Boutique</title>
    <link rel="icon" type="image/png" href="imagenes/monito01.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'escala-blue': '#1e3a8a',
                        'escala-green': '#00524A', 
                        'escala-dark': '#003d36',
                        'escala-alert': '#FF9900',
                    }
                }
            }
        }

        function appData() {
            return {
                currentCategory: 'Todos',
                searchQuery: '',
                cartOpen: false,
                selectedProduct: null,
                showToast: false,
                isPaying: false, 
                showSuccess: false,
                showPayrollModal: false,
                plazos: 1,
                cart: JSON.parse(localStorage.getItem('cart_escala')) || [],
                
                init() {
                    this.$watch('selectedProduct', (val) => { if (val) setTimeout(() => lucide.createIcons(), 50); });
                    this.$watch('cartOpen', (val) => { if (val) setTimeout(() => lucide.createIcons(), 50); });
                    lucide.createIcons();
                },
                openModal(p) { this.selectedProduct = p; },
                addToCart(p, qty = 1) {
                    const qtyNum = parseInt(qty);
                    const itemInCart = this.cart.find(i => i.id === p.id);
                    if (itemInCart && (itemInCart.qty + qtyNum) > p.stock) { alert('Stock insuficiente'); return; }
                    if (itemInCart) { itemInCart.qty += qtyNum; } 
                    else { this.cart.push({ id: p.id, nombre: p.nombre, precio: p.precio, img: p.imagenes[0], qty: qtyNum, stock: p.stock }); }
                    this.saveCart();
                    this.showToast = true;
                    setTimeout(() => { this.showToast = false; }, 3000);
                },
                updateQty(id, delta) {
                    const item = this.cart.find(i => i.id === id);
                    if (item && delta > 0 && item.qty >= item.stock) { alert('Límite de stock alcanzado'); return; }
                    if (item) { item.qty += delta; if (item.qty <= 0) this.cart = this.cart.filter(i => i.id !== id); }
                    this.saveCart();
                },
                saveCart() { localStorage.setItem('cart_escala', JSON.stringify(this.cart)); setTimeout(() => lucide.createIcons(), 50); },
                totalPrice() { return this.cart.reduce((s, i) => s + (i.precio * i.qty), 0).toFixed(2); },
                iniciarTramite() { this.cartOpen = false; this.showPayrollModal = true; },
                confirmarPedidoNomina() {
                    this.isPaying = true;
                    fetch('api/procesar_pedido_nomina.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cart: this.cart, total: this.totalPrice(), plazos: this.plazos })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.isPaying = false;
                        this.showPayrollModal = false;
                        if (data.status === 'success') {
                            this.showSuccess = true;
                            this.cart = [];
                            this.saveCart();
                            setTimeout(() => { this.showSuccess = false; }, 4000);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => { this.isPaying = false; alert('Error de conexión.'); });
                }
            }
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: system-ui, -apple-system, sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        /* Badges */
        .badge-top { background: linear-gradient(90deg, #00524A 0%, #16ed48 100%); color: white; font-weight: 800; padding: 5px 15px; clip-path: polygon(0 0, 100% 0, 90% 50%, 100% 100%, 0 100%); z-index: 10; font-size: 10px; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); }
        .badge-right { color: white; font-weight: 900; padding: 4px 10px 4px 18px; clip-path: polygon(10px 0, 100% 0, 100% 100%, 10px 100%, 0 50%); z-index: 10; font-size: 10px; margin-bottom: 4px; text-transform: uppercase; box-shadow: -2px 2px 5px rgba(0,0,0,0.1); }
        .bg-sale { background-color: #FF9900; }
        .bg-last { background-color: #EF4444; }

        /* Botón Estilo Boutique */
        .btn-add { background-color: #00524A; color: white; transition: 0.3s; border-radius: 8px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; font-size: 11px; }
        .btn-add:hover { background-color: #003d36; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,82,74,0.3); }
        
        .btn-outline { background-color: white; border: 1px solid #e5e7eb; color: #6b7280; font-weight: 700; font-size: 10px; text-transform: uppercase; border-radius: 8px; transition: 0.3s; }
        .btn-outline:hover { border-color: #00524A; color: #00524A; }
    </style>
</head>

<body class="bg-slate-50 text-gray-900 text-slate-800 min-h-screen flex flex-col">

    <div x-show="showToast" x-cloak x-transition class="fixed top-5 right-5 z-[100] px-6 py-4 bg-white rounded-lg shadow-xl flex items-center gap-3 border-l-4 border-escala-green">
        <i data-lucide="check" class="w-5 h-5 text-escala-green"></i>
        <p class="text-sm font-bold">Agregado al carrito</p>
    </div>

    <header class="bg-white pt-4 pb-2 sticky top-0 z-40 border-b border-gray-100 shadow-sm">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6">
            
            <div class="md:hidden flex flex-col gap-3 pb-2">
                <div class="flex justify-center mb-1">
                    <img src="imagenes/EscalaBoutique.png" class="h-24 w-auto object-contain">
                </div>
                <div class="flex justify-end items-baseline gap-1">
                    <span class="text-[10px] text-gray-400 font-bold uppercase">HOLA,</span>
                    <span class="text-xs font-bold text-escala-green"><?php echo $nombreCorto; ?></span>
                </div>
                <div class="relative w-full">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="text" x-model="searchQuery" placeholder="Buscar productos..."
                           class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:outline-none focus:border-escala-green focus:ring-1 focus:ring-escala-green transition-all text-sm">
                </div>
                <nav class="flex gap-2 overflow-x-auto no-scrollbar">
                    <?php foreach($categorias as $cat): ?>
                    <button @click="currentCategory = '<?php echo $cat; ?>'" :class="currentCategory === '<?php echo $cat; ?>' ? 'bg-escala-green text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200'" class="px-6 py-2 rounded-full text-[11px] font-black uppercase tracking-widest whitespace-nowrap transition-all">
                        <?php echo $cat; ?>
                    </button>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="hidden md:block">
                <div class="flex flex-row items-center justify-between gap-6 mb-6">
                    <div class="flex-shrink-0">
                        <img src="imagenes/EscalaBoutiqueCompleto.png" class="h-20 w-auto object-contain">
                    </div>
                    <div class="relative w-full max-w-xl mx-auto">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" x-model="searchQuery" placeholder="Buscar productos..."
                               class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:outline-none focus:border-escala-green focus:ring-1 focus:ring-escala-green transition-all text-sm">
                    </div>
                    <div class="flex flex-col items-end text-right">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">BIENVENIDO</span>
                        <span class="text-sm font-bold text-escala-green truncate max-w-[200px]"><?php echo $nombreCompleto; ?></span>
                    </div>
                </div>
                <nav class="flex gap-3 overflow-x-auto no-scrollbar pb-2">
                    <?php foreach($categorias as $cat): ?>
                    <button @click="currentCategory = '<?php echo $cat; ?>'" :class="currentCategory === '<?php echo $cat; ?>' ? 'bg-escala-green text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:border-escala-green hover:text-escala-green'" class="px-6 py-2 rounded-full text-[11px] font-black uppercase tracking-widest whitespace-nowrap transition-all">
                        <?php echo $cat; ?>
                    </button>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </header>

    <main class="max-w-[1400px] mx-auto px-4 sm:px-6 py-10 flex-grow w-full">
        <?php if(empty($productos)): ?>
            <div class="flex flex-col items-center justify-center h-64 text-gray-300">
                <i data-lucide="package" class="w-16 h-16 mb-4 opacity-50"></i>
                <p class="font-medium text-lg">No hay productos disponibles.</p>
            </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($productos as $p): ?>
            <div x-show="(currentCategory === 'Todos' || currentCategory === '<?php echo $p['categoria']; ?>') && ('<?php echo strtolower($p['nombre']); ?>'.includes(searchQuery.toLowerCase()) || '<?php echo $p['precio']; ?>'.includes(searchQuery.replace('$','').trim()))"
                 class="bg-white rounded-3xl p-8 shadow-[0_10px_40px_-15px_rgba(0,0,0,0.1)] hover:shadow-[0_20px_50px_-20px_rgba(0,0,0,0.15)] transition-all duration-300 border border-escala-dark flex flex-col relative group"
                 x-data="{ activeImg: 0, imgs: <?php echo htmlspecialchars(json_encode($p['imagenes']), ENT_QUOTES, 'UTF-8'); ?>, qty: 1 }">
                
                <div class="absolute top-4 left-0"><?php if ($p['es_top'] == 1): ?><div class="badge-top">TOP VENTAS</div><?php endif; ?></div>
                <div class="absolute top-4 right-0 flex flex-col items-end space-y-2">
                     <?php if ($p['stock'] <= 5): ?><div class="badge-right bg-last">¡ÚLTIMAS PIEZAS!</div><?php endif; ?>
                     <?php if ($p['en_oferta'] == 1): ?><div class="badge-right bg-sale">EN OFERTA</div><?php endif; ?>
                </div>

                <div class="h-72 flex items-center justify-center mb-8 relative p-4">
                    <img :src="imgs[activeImg]" class="max-h-full max-w-full object-contain drop-shadow-xl group-hover:scale-105 transition-transform duration-500">
                    <template x-if="imgs.length > 1">
                        <div class="absolute inset-0 flex items-center justify-between px-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="activeImg = (activeImg === 0) ? imgs.length - 1 : activeImg - 1" class="p-2 bg-white rounded-full shadow-md hover:bg-blue-50 text-blue-900 transition-colors"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                            <button @click.stop="activeImg = (activeImg === imgs.length - 1) ? 0 : activeImg + 1" class="p-2 bg-white rounded-full shadow-md hover:bg-blue-50 text-blue-900 transition-colors"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                        </div>
                    </template>
                </div>

                <div class="flex flex-col flex-grow items-center text-center">
                    <h3 class="font-black text-xl text-slate-900 mb-3 uppercase leading-tight line-clamp-2"><?php echo $p['nombre']; ?></h3>
                    
                    <div class="flex items-center gap-1 mb-4 justify-center">
                        <?php for($i=1; $i<=5; $i++): $color = ($i <= (int)$p['calificacion']) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200'; ?>
                        <i data-lucide="star" class="w-4 h-4 <?php echo $color; ?>"></i><?php endfor; ?>
                    </div>

                    <p class="text-xs text-gray-500 mb-6 font-bold uppercase tracking-wider">STOCK: <span class="text-blue-600"><?php echo $p['stock']; ?></span></p>

                    <div class="mt-auto w-full">
                        <div class="flex items-center justify-center gap-4 mb-8">
                             <div class="flex items-center bg-gray-100 rounded-full p-1 shadow-inner">
                                <button @click="if(qty > 1) qty--" class="p-2 hover:text-blue-600 transition-colors"><i data-lucide="minus" class="w-4 h-4"></i></button>
                                <span class="w-10 text-center font-black text-lg" x-text="qty"></span>
                                <button @click="if(qty < <?php echo $p['stock']; ?>) qty++; else alert('Max stock')" class="p-2 hover:text-blue-600 transition-colors"><i data-lucide="plus" class="w-4 h-4"></i></button>
                            </div>
                            <div class="flex flex-col items-start">
                                <span class="text-sm text-gray-400 line-through font-medium">$<?php echo number_format($p['precio'] * 1.3, 2); ?></span>
                                <span class="text-3xl font-black text-escala-green">$<?php echo number_format($p['precio'], 2); ?></span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 w-full">
                            <button @click="addToCart(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>, qty); qty = 1" 
                                    class="btn-add w-full py-4 rounded-xl flex items-center justify-center gap-2 shadow-lg">
                                <i data-lucide="shopping-cart" class="w-5 h-5"></i> AÑADIR AL CARRITO
                            </button>
                            
                            <button @click="openModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" 
                                    class="w-full py-2.5 flex items-center justify-center gap-2 bg-white border border-escala-dark text-escala-dark rounded-xl font-bold uppercase text-[10px] hover:bg-gray-50 transition-all">
                                <i data-lucide="info" class="w-3 h-3"></i> MÁS INFORMACIÓN
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-60 py-6 border-t-2 border-escala-blue mt-auto">
        <div class="max-w-[1400px] mx-auto px-4 flex flex-col items-center justify-center">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.3em] mb-4">Powered By</p>
            <img src="imagenes/KAI_NA.png" alt="KAI Experience" class="h-10 w-auto escala-blue">
        </div>
    </footer>

    <div class="fixed bottom-8 right-8 z-50">
        <button @click="cartOpen = !cartOpen" class="w-16 h-16 bg-escala-green text-white rounded-full shadow-2xl flex items-center justify-center relative hover:scale-110 transition-transform border-4 border-white">
            <i data-lucide="shopping-cart" class="w-7 h-7"></i>
            <template x-if="cart.reduce((s, i) => s + i.qty, 0) > 0">
                <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] w-6 h-6 rounded-full flex items-center justify-center font-bold border-2 border-white shadow-sm animate-bounce" x-text="cart.reduce((s, i) => s + i.qty, 0)"></span>
            </template>
        </button>
    </div>

    <div x-show="cartOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm" @click="cartOpen = false" x-cloak x-transition.opacity></div>
    
    <div x-show="cartOpen" 
         class="fixed bottom-28 right-4 z-50 w-[92vw] md:w-full max-w-md bg-white shadow-2xl rounded-3xl overflow-hidden flex flex-col transition-all duration-300 border border-gray-100 max-h-[80vh] h-auto origin-bottom"
         :class="cartOpen ? 'translate-x-0 opacity-100 scale-100' : 'translate-x-10 opacity-0 scale-95 pointer-events-none'"
         x-cloak>
        
        <div class="p-6 bg-escala-dark flex justify-between items-center shadow-lg shrink-0">
            <h2 class="font-black text-2xl text-white tracking-wide">TU CARRITO</h2>
            <button @click="cartOpen = false" class="p-2 bg-white/20 hover:bg-white/30 rounded-full transition-all duration-300 hover:rotate-90 text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>

        <div class="flex-grow overflow-y-auto p-6 space-y-6 bg-gray-50">
            <template x-for="item in cart" :key="item.id">
                <div class="flex gap-4 items-center bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
                    <div class="w-16 h-16 bg-gray-50 rounded-xl flex items-center justify-center shrink-0 p-1 border border-gray-100">
                        <img :src="item.img" class="max-h-full max-w-full object-contain">
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-xs text-slate-800 uppercase leading-tight mb-1 truncate" x-text="item.nombre"></h4>
                        <p class="text-escala-green font-black text-sm" x-text="'$' + (item.precio * item.qty).toFixed(2)"></p>
                    </div>
                    <div class="flex items-center bg-gray-100 rounded-full px-2 py-1 border border-gray-200">
                        <button @click="updateQty(item.id, -1)" class="p-1 text-gray-500 hover:text-red-500 transition-colors"><i data-lucide="minus" class="w-3 h-3"></i></button>
                        <span class="text-xs font-black w-6 text-center text-slate-800" x-text="item.qty"></span>
                        <button @click="updateQty(item.id, 1)" class="p-1 text-gray-500 hover:text-escala-green transition-colors"><i data-lucide="plus" class="w-3 h-3"></i></button>
                    </div>
                </div>
            </template>
            <div x-show="cart.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400">
                <i data-lucide="shopping-cart" class="w-16 h-16 mb-4 opacity-20"></i>
                <p class="font-bold text-lg">Tu carrito está vacío</p>
            </div>
        </div>

        <div class="p-8 bg-white border-t border-gray-100 shadow-[0_-10px_30px_-15px_rgba(0,0,0,0.1.5)] shrink-0" x-show="cart.length > 0">
            <div class="flex justify-between items-end mb-6">
                <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Total a Descontar</span>
                <span class="font-black text-3xl text-escala-green" x-text="'$' + totalPrice()"></span>
            </div>
            <button @click="iniciarTramite()" class="w-full py-4 rounded-xl font-black uppercase tracking-[0.15em] text-sm shadow-xl flex items-center justify-center gap-3 text-white bg-escala-dark hover:bg-escala-green hover:shadow-2xl hover:-translate-y-1 transition-all">
                <i data-lucide="credit-card" class="w-5 h-5"></i> SOLICITAR PEDIDO
            </button>
        </div>
    </div>

    <template x-if="selectedProduct">
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-cloak x-transition.opacity>
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="selectedProduct = null"></div>
            
            <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row max-h-[90vh]" 
                x-data="{ activeImgModal: 0, modalQty: 1 }">
                
                <div class="w-full md:w-1/2 bg-gray-50 p-12 flex items-center justify-center relative group">
                    <img :src="selectedProduct.imagenes[activeImgModal]" class="max-h-[400px] w-auto object-contain drop-shadow-2xl transition-all duration-300 mix-blend-multiply">
                    <button @click="selectedProduct = null" class="absolute top-6 left-6 md:hidden p-3 bg-white rounded-full shadow-md text-gray-800 hover:bg-gray-100"><i data-lucide="arrow-left" class="w-6 h-6"></i></button>
                    <template x-if="selectedProduct.imagenes.length > 1">
                        <div class="absolute inset-0 flex items-center justify-between px-6 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="activeImgModal = (activeImgModal === 0) ? selectedProduct.imagenes.length - 1 : activeImgModal - 1" class="p-3 bg-white rounded-full shadow-lg hover:bg-blue-50 text-blue-900 transition-colors"><i data-lucide="chevron-left" class="w-6 h-6"></i></button>
                            <button @click="activeImgModal = (activeImgModal === selectedProduct.imagenes.length - 1) ? 0 : activeImgModal + 1" class="p-3 bg-white rounded-full shadow-lg hover:bg-blue-50 text-blue-900 transition-colors"><i data-lucide="chevron-right" class="w-6 h-6"></i></button>
                        </div>
                    </template>
                </div>

                <div class="w-full md:w-1/2 p-10 overflow-y-auto flex flex-col">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="font-black text-3xl uppercase leading-none text-slate-900" x-text="selectedProduct.nombre"></h2>
                        <button @click="selectedProduct = null" class="hidden md:block p-2 hover:bg-gray-100 rounded-full transition-all duration-300 hover:rotate-90 text-gray-500"><i data-lucide="x" class="w-7 h-7"></i></button>
                    </div>
                    
                    <div class="flex items-center gap-2 mb-6">
                        <div class="flex text-yellow-400"><i data-lucide="star" class="w-4 h-4 fill-current"></i><i data-lucide="star" class="w-4 h-4 fill-current"></i><i data-lucide="star" class="w-4 h-4 fill-current"></i><i data-lucide="star" class="w-4 h-4 fill-current"></i><i data-lucide="star" class="w-4 h-4 fill-current"></i></div>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide" x-text="'Stock: ' + selectedProduct.stock"></span>
                    </div>
                    
                    <p class="text-gray-600 text-base mb-8 leading-relaxed font-medium" x-text="selectedProduct.descripcion_larga || selectedProduct.descripcion_corta"></p>
                    
                    <div class="mt-auto pt-8 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-8">
                            
                            <div class="flex items-center bg-gray-100 rounded-full px-4 py-2 border border-gray-200">
                                <button @click="if(modalQty > 1) modalQty--" class="text-slate-600 hover:text-escala-green transition-colors font-bold text-lg px-2">−</button>
                                <span class="mx-4 font-black text-xl text-slate-800 w-6 text-center" x-text="modalQty"></span>
                                <button @click="if(modalQty < selectedProduct.stock) modalQty++; else alert('Stock máximo alcanzado')" class="text-slate-600 hover:text-escala-green transition-colors font-bold text-lg px-2">+</button>
                            </div>

                            <div class="text-right">
                                <span class="text-sm text-gray-400 line-through font-medium block" x-text="'$' + (selectedProduct.precio * 1.3).toFixed(2)"></span>
                                <span class="text-4xl font-black text-escala-green" x-text="'$' + selectedProduct.precio.toFixed(2)"></span>
                            </div>
                        </div>

                        <button @click="addToCart(selectedProduct, modalQty); selectedProduct = null" class="btn-add w-full py-5 rounded-2xl font-black text-white uppercase shadow-xl hover:shadow-2xl transition-all flex justify-center gap-3 text-base tracking-wider">
                            <i data-lucide="shopping-cart" class="w-6 h-6"></i> AGREGAR AL CARRITO
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <div x-show="showPayrollModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4" x-cloak>
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="!isPaying && (showPayrollModal = false)"></div>
        <div class="relative bg-white w-full max-w-sm rounded-2xl p-8 shadow-2xl text-center">
            <div x-show="isPaying">
                <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-200 border-t-escala-green mx-auto mb-4"></div>
                <h3 class="font-bold text-lg text-slate-800">Procesando...</h3>
                <p class="text-xs text-gray-500">Enviando solicitud a Recursos Humanos</p>
            </div>
            <div x-show="!isPaying">
                <h3 class="font-black text-xl uppercase mb-2 text-slate-900">Confirmar Descuento</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Monto total a descontar: <br>
                    <span class="font-black text-2xl text-escala-green" x-text="'$' + totalPrice()"></span>
                </p>
                <p class="text-xs font-bold text-gray-400 uppercase mb-3">Selecciona plazos de pago:</p>
                <div class="flex gap-3 justify-center mb-8">
                    <button @click="plazos = 1" :class="plazos === 1 ? 'bg-escala-green text-white ring-2 ring-offset-2 ring-escala-green' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'" class="flex-1 py-3 rounded-lg flex flex-col items-center justify-center transition-all">
                        <span class="font-black text-lg">1</span><span class="text-[9px] uppercase font-bold">Qna</span>
                    </button>
                    <button @click="plazos = 2" :class="plazos === 2 ? 'bg-escala-green text-white ring-2 ring-offset-2 ring-escala-green' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'" class="flex-1 py-3 rounded-lg flex flex-col items-center justify-center transition-all">
                        <span class="font-black text-lg">2</span><span class="text-[9px] uppercase font-bold">Qnas</span>
                    </button>
                    <button @click="plazos = 3" :class="plazos === 3 ? 'bg-escala-green text-white ring-2 ring-offset-2 ring-escala-green' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'" class="flex-1 py-3 rounded-lg flex flex-col items-center justify-center transition-all">
                        <span class="font-black text-lg">3</span><span class="text-[9px] uppercase font-bold">Qnas</span>
                    </button>
                </div>
                <div class="flex gap-3">
                    <button @click="showPayrollModal = false" class="flex-1 py-3 rounded-lg font-bold text-gray-500 hover:bg-gray-100 text-xs uppercase">Cancelar</button>
                    <button @click="confirmarPedidoNomina()" class="flex-1 py-3 rounded-lg font-bold bg-escala-green text-white shadow-lg hover:bg-escala-dark transition-all text-xs uppercase">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showSuccess" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-white/90" x-cloak>
        <div class="text-center animate-bounce">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check" class="w-10 h-10 text-green-600"></i>
            </div>
            <h2 class="text-3xl font-black uppercase text-slate-800 mb-2">¡Pedido Exitoso!</h2>
            <p class="text-gray-500 font-medium">Recibirás un correo con los detalles.</p>
        </div>
    </div>

</body>
</html>
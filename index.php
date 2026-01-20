<?php
/**
 * LÓGICA DE DATOS OPTIMIZADA
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'api/conexion.php'; 

$conn->query("SET SESSION group_concat_max_len = 10000;");

$query = "SELECT p.*, GROUP_CONCAT(i.url_imagen) as lista_imagenes 
          FROM kaiexper_perpetualife.productos p 
          LEFT JOIN kaiexper_perpetualife.imagenes_productos i ON p.id = i.producto_id 
          GROUP BY p.id";

$resultado = $conn->query($query);
$productos = [];
$categorias = ['Todos']; 

if ($resultado && $resultado->num_rows > 0) {
    while($row = $resultado->fetch_assoc()) {
        $row['imagenes'] = $row['lista_imagenes'] ? explode(',', $row['lista_imagenes']) : ['https://via.placeholder.com/400'];
        $row['precio'] = (float)$row['precio'];
        $row['precio_anterior'] = $row['precio_anterior'] ? (float)$row['precio_anterior'] : null;
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
?>

<!DOCTYPE html>
<html lang="es" x-data="appData()" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpetua Life | Store Elite</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://www.paypal.com/sdk/js?client-id=test&currency=USD"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'perpetua-blue': '#1e3a8a',
                        'perpetua-aqua': '#22d3ee',
                        'perpetua-orange': '#FF9900',
                    }
                }
            }
        }

        function appData() {
            return {
                darkMode: localStorage.getItem('darkMode') === 'true',
                lang: localStorage.getItem('lang') || 'es',
                currentCategory: 'Todos',
                searchQuery: '',
                cartOpen: false,
                selectedProduct: null,
                isScanning: false,
                showToast: false,
                isPaying: false, 
                payProgress: 0,
                showSuccess: false,
                cart: JSON.parse(localStorage.getItem('cart')) || [],
                
                init() {
                    this.$watch('selectedProduct', (val) => { if (val) setTimeout(() => lucide.createIcons(), 50); });
                    this.$watch('showSuccess', (val) => { if (val) setTimeout(() => lucide.createIcons(), 50); });
                    this.$watch('cartOpen', (val) => { if (val) setTimeout(() => lucide.createIcons(), 50); });
                    lucide.createIcons();
                },

                t: {
                    es: { 
                        added: 'Producto añadido', add: 'Añadir al carrito', cart: 'Tu Carrito', empty: 'Vacío', 
                        total: 'Total', pay: 'Finalizar Compra', stock: 'STOCK', price: 'Precio', 
                        langBtn: 'English', scan: 'ESCANEANDO...', powered: 'POWERED BY',
                        encrypting: 'Procesando pago seguro...', success: '¡Compra Exitosa!', qty: 'CANT.',
                        units: 'unidades', noStock: 'Límite alcanzado', sale: 'EN OFERTA', top: 'TOP VENTAS',
                        lastUnits: '¡ÚLTIMAS PIEZAS!', info: 'MÁS INFORMACIÓN',
                        searchPlaceholder: 'Buscar productos...',
                        cat: { 'Todos': 'Todos', 'Suplementos': 'Suplementos', 'Equipamiento': 'Equipamiento' }
                    },
                    en: { 
                        added: 'Added', add: 'Add to cart', cart: 'Your Cart', empty: 'Empty', 
                        total: 'Total', pay: 'Checkout', stock: 'STOCK', price: 'Price', 
                        langBtn: 'Español', scan: 'SCANNING...', powered: 'POWERED BY',
                        encrypting: 'Processing secure payment...', success: 'Success!', qty: 'QTY',
                        units: 'units', noStock: 'Limit reached', sale: 'ON SALE', top: 'TOP SELLER',
                        lastUnits: 'LAST UNITS!', info: 'MORE INFORMATION',
                        searchPlaceholder: 'Search products...',
                        cat: { 'Todos': 'All', 'Suplementos': 'Supplements', 'Equipamiento': 'Equipment' }
                    }
                },

                switchLang() { this.lang = (this.lang === 'es' ? 'en' : 'es'); localStorage.setItem('lang', this.lang); },
                toggleDarkMode() { this.darkMode = !this.darkMode; localStorage.setItem('darkMode', this.darkMode); },
                
                openModal(p) {
                    this.selectedProduct = p;
                    this.isScanning = true;
                    setTimeout(() => { this.isScanning = false; }, 1500);
                },

                addToCart(p, qty = 1) {
                    const qtyNum = parseInt(qty);
                    const itemInCart = this.cart.find(i => i.id === p.id);
                    if (itemInCart && (itemInCart.qty + qtyNum) > p.stock) { alert(this.t[this.lang].noStock); return; }
                    if (itemInCart) { itemInCart.qty += qtyNum; } 
                    else { this.cart.push({ id: p.id, nombre: p.nombre, precio: p.precio, img: p.imagenes[0], qty: qtyNum, stock: p.stock }); }
                    this.saveCart();
                    this.showToast = true;
                    setTimeout(() => { this.showToast = false; }, 3000);
                },

                updateQty(id, delta) {
                    const item = this.cart.find(i => i.id === id);
                    if (item && delta > 0 && item.qty >= item.stock) { alert(this.t[this.lang].noStock); return; }
                    if (item) { item.qty += delta; if (item.qty <= 0) this.cart = this.cart.filter(i => i.id !== id); }
                    this.saveCart();
                },

                initPayPal() {
                    this.isPaying = true;
                    this.payProgress = 15;
                    
                    const container = document.getElementById('paypal-button-container');
                    if (container) container.innerHTML = '';

                    paypal.Buttons({
                        createOrder: (data, actions) => {
                            this.payProgress = 40;
                            return actions.order.create({ purchase_units: [{ amount: { value: this.totalPrice() } }] });
                        },
                        onApprove: (data, actions) => {
                            this.payProgress = 75;
                            return actions.order.capture().then((details) => {
                                return fetch('api/confirmar_pago.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ orderID: data.orderID, cart: this.cart, total: this.totalPrice() })
                                })
                                .then(res => res.json())
                                .then(res => {
                                    if(res.status === 'success') {
                                        this.payProgress = 100;
                                        setTimeout(() => {
                                            this.isPaying = false;
                                            this.showSuccess = true;
                                            this.cart = [];
                                            this.saveCart();
                                        }, 400);
                                        setTimeout(() => { this.showSuccess = false; this.cartOpen = false; }, 4000);
                                    }
                                });
                            });
                        },
                        onCancel: () => { this.isPaying = false; this.payProgress = 0; },
                        onError: () => { this.isPaying = false; this.payProgress = 0; }
                    }).render('#paypal-button-container');
                },

                saveCart() { localStorage.setItem('cart', JSON.stringify(this.cart)); setTimeout(() => lucide.createIcons(), 50); },
                totalPrice() { return this.cart.reduce((s, i) => s + (i.precio * i.qty), 0).toFixed(2); }
            }
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .btn-gradient { background: linear-gradient(135deg, #1e3a8a 0%, #22d3ee 100%); transition: all 0.3s ease; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(34, 211, 238, 0.4); }
        .glass { backdrop-blur: 14px; background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(30, 58, 138, 0.1); }
        .dark .glass { background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Animación Scanner */
        @keyframes scan { 0% { top: 0%; opacity: 0; } 50% { opacity: 1; } 100% { top: 100%; opacity: 0; } }
        .scanner-line { height: 4px; background: #22d3ee; box-shadow: 0 0 20px #22d3ee; position: absolute; width: 100%; z-index: 40; animation: scan 2s linear infinite; }
        .grid-overlay { background-image: linear-gradient(rgba(34, 211, 238, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(34, 211, 238, 0.1) 1px, transparent 1px); background-size: 20px 20px; }
        
        /* Badges */
        .sale-badge { background-color: #FF9900; color: white; font-weight: 800; padding: 5px 12px; clip-path: polygon(0 0, 100% 0, 90% 50%, 100% 100%, 0 100%); z-index: 40; }
        .last-units-badge { background-color: #EF4444; color: white; font-weight: 800; padding: 5px 12px; clip-path: polygon(0 0, 100% 0, 90% 50%, 100% 100%, 0 100%); z-index: 40; }
        .top-badge { background: linear-gradient(to right, #1e3a8a, #22d3ee); color: white; font-weight: 800; padding: 5px 12px; clip-path: polygon(0 0, 90% 0, 100% 50%, 90% 100%, 0 100%); z-index: 40; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>

<body class="min-h-screen transition-all duration-500 flex flex-col" :class="darkMode ? 'bg-gray-900 text-white' : 'bg-slate-50 text-gray-900'">

    <div x-show="showToast" x-cloak x-transition class="fixed top-5 right-5 z-[100] px-6 py-4 glass rounded-2xl shadow-2xl flex items-center gap-4 border-l-4 border-perpetua-aqua">
        <i data-lucide="check-circle" class="w-6 h-6 text-perpetua-aqua"></i>
        <p class="text-sm font-bold" x-text="t[lang].added"></p>
    </div>

    <header class="sticky top-0 z-40 glass border-b border-perpetua-blue/10">
        <div class="max-w-7xl mx-auto px-4 py-2 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center">
                <img src="imagenes/Perpetua.png" class="block md:hidden w-20 h-auto object-contain">
                <img :src="darkMode ? 'imagenes/designWhite.png' : 'imagenes/Perpetua_Life.png'" 
                     class="hidden md:block w-72 h-auto md:w-[450px] object-contain transition-all duration-500">
            </div>

            <div class="relative w-full md:max-w-xs">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="text" x-model="searchQuery" :placeholder="t[lang].searchPlaceholder"
                       class="w-full pl-10 pr-4 py-2 bg-white/50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-perpetua-aqua text-sm font-medium transition-all">
            </div>

            <div class="flex items-center gap-3">
                <button @click="switchLang()" class="px-3 py-1 rounded-full text-[10px] font-bold border-2 border-perpetua-blue text-perpetua-blue dark:text-perpetua-aqua transition-all">
                    <span x-text="t[lang].langBtn"></span>
                </button>
                <button @click="toggleDarkMode()" class="p-2 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/40 text-perpetua-blue dark:text-perpetua-aqua">
                    <i data-lucide="sun" x-show="darkMode"></i>
                    <i data-lucide="moon" x-show="!darkMode"></i>
                </button>
            </div>
        </div>

        <nav class="max-w-7xl mx-auto px-4 py-3 flex gap-2 overflow-x-auto no-scrollbar">
            <?php foreach($categorias as $cat): ?>
            <button @click="currentCategory = '<?php echo $cat; ?>'"
                    :class="currentCategory === '<?php echo $cat; ?>' ? 'btn-gradient text-white' : 'glass text-gray-500'"
                    class="px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all shadow-sm">
                <span x-text="t[lang].cat['<?php echo $cat; ?>'] || '<?php echo $cat; ?>'"></span>
            </button>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6 flex-grow">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($productos as $p): ?>
            <div x-show="(currentCategory === 'Todos' || currentCategory === '<?php echo $p['categoria']; ?>') && '<?php echo strtolower($p['nombre']); ?>'.includes(searchQuery.toLowerCase())"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 class="group rounded-3xl overflow-hidden shadow-sm border bg-white dark:bg-gray-800 dark:border-gray-700 flex flex-col transition-all hover:shadow-xl relative"
                 x-data="{ activeImg: 0, imgs: <?php echo htmlspecialchars(json_encode($p['imagenes']), ENT_QUOTES, 'UTF-8'); ?>, selectedQty: 1 }">
                
                <div class="absolute top-4 right-0 flex flex-col gap-1 z-10 pointer-events-none items-end text-[9px]">
                    <?php if ($p['stock'] < 10): ?><div class="last-units-badge shadow-md"><span x-text="t[lang].lastUnits"></span></div><?php endif; ?>
                    <?php if ($p['en_oferta'] == 1): ?><div class="sale-badge shadow-md"><span x-text="t[lang].sale"></span></div><?php endif; ?>
                </div>
                <?php if ($p['es_top'] == 1): ?>
                    <div class="absolute top-4 left-0 z-10 top-badge text-[9px] shadow-md uppercase tracking-widest"><span x-text="t[lang].top"></span></div>
                <?php endif; ?>

                <div class="relative h-72 w-full p-4 bg-gray-50 dark:bg-gray-700/20 flex items-center justify-center shrink-0 overflow-hidden">
                    <template x-for="(img, index) in imgs" :key="index">
                        <img :src="img" x-show="activeImg === index" class="max-h-full max-w-full object-contain drop-shadow-xl transition-all duration-500">
                    </template>
                    <template x-if="imgs.length > 1">
                        <div class="absolute inset-0 flex items-center justify-between px-2 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                            <button @click="activeImg = (activeImg === 0) ? imgs.length - 1 : activeImg - 1" class="p-1 glass rounded-full text-blue-600 shadow-md"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
                            <button @click="activeImg = (activeImg === imgs.length - 1) ? 0 : activeImg + 1" class="p-1 glass rounded-full text-blue-600 shadow-md"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
                        </div>
                    </template>
                </div>

                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="font-bold mb-1 leading-tight text-lg dark:text-white uppercase"><?php echo $p['nombre']; ?></h3>
                    
                    <div class="flex items-center gap-1 mb-4">
                        <?php $calif = (int)$p['calificacion']; for($i=1; $i<=5; $i++): $color = ($i <= $calif) ? 'text-perpetua-orange fill-perpetua-orange' : 'text-gray-300 dark:text-gray-600'; ?>
                        <i data-lucide="star" class="w-3 h-3 <?php echo $color; ?>"></i><?php endfor; ?>
                    </div>

                    <p class="text-[10px] font-bold text-gray-400 mb-4 uppercase">
                        <span x-text="t[lang].stock"></span> : <span class="text-perpetua-blue dark:text-perpetua-aqua font-black"><?php echo $p['stock']; ?></span>
                    </p>
                    
                    <div class="flex items-center gap-2 mb-5">
                        <div class="flex items-center bg-gray-100 dark:bg-gray-100 rounded-full p-1 shadow-inner border border-gray-200">
                            <button @click="if(selectedQty > 1) selectedQty--" class="w-8 h-8 flex items-center justify-center bg-transparent text-blue-600 active:scale-90 transition-all"><i data-lucide="minus" class="w-4 h-4"></i></button>
                            <span class="text-sm font-black w-10 text-center text-gray-900" x-text="selectedQty"></span>
                            <button @click="if(selectedQty < <?php echo $p['stock']; ?>) selectedQty++; else alert(t[lang].noStock)" class="w-8 h-8 flex items-center justify-center bg-transparent text-blue-600 active:scale-90 transition-all"><i data-lucide="plus" class="w-4 h-4"></i></button>
                        </div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase" x-text="t[lang].qty"></span>
                    </div>

                    <div class="mt-auto pt-4 border-t dark:border-gray-700 flex flex-col gap-2">
                        <span class="text-2xl font-black text-perpetua-blue dark:text-perpetua-aqua">$<?php echo number_format($p['precio'], 2); ?></span>
                        <button @click="addToCart(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>, selectedQty); selectedQty = 1" 
                                class="btn-gradient text-white w-full py-3.5 rounded-xl font-black uppercase shadow-lg text-xs flex items-center justify-center gap-2">
                            <i data-lucide="shopping-cart" class="w-4 h-4"></i><span x-text="t[lang].add"></span>
                        </button>
                        <button @click="openModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" 
                                class="btn-ghost w-full py-2.5 rounded-xl font-bold uppercase text-[10px] flex items-center justify-center gap-2 opacity-80 hover:opacity-100 border-2 border-perpetua-blue text-perpetua-blue dark:border-perpetua-aqua dark:text-perpetua-aqua">
                            <i data-lucide="info" class="w-3.5 h-3.5"></i><span x-text="t[lang].info"></span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div class="fixed bottom-6 right-6 z-50" x-cloak>
        <button @click="cartOpen = !cartOpen" class="w-16 h-16 btn-gradient text-white rounded-full shadow-2xl flex items-center justify-center relative hover:scale-110 transition-all">
            <i data-lucide="shopping-cart" class="w-7 h-7"></i>
            <template x-if="cart.reduce((s, i) => s + i.qty, 0) > 0">
                <span class="absolute -top-1 -right-1 bg-red-500 text-[10px] w-6 h-6 rounded-full flex items-center justify-center font-bold border-2 border-white animate-bounce" x-text="cart.reduce((s, i) => s + i.qty, 0)"></span>
            </template>
        </button>
        
        <div x-show="cartOpen" @click.away="!isPaying && (cartOpen = false)" x-transition class="absolute bottom-20 right-0 w-80 md:w-96 rounded-3xl shadow-2xl glass border-2 border-perpetua-blue/20 overflow-hidden min-h-[200px]">
            <div x-show="!isPaying && !showSuccess">
                <div class="p-4 bg-perpetua-blue text-white flex justify-between items-center font-bold uppercase text-sm">
                    <span x-text="t[lang].cart"></span>
                    <button @click="cartOpen = false" class="hover:rotate-180 transition-transform duration-500 p-1"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="max-h-80 overflow-y-auto p-4 space-y-4 no-scrollbar font-bold uppercase text-xs">
                    <template x-for="item in cart" :key="item.id">
                        <div class="flex gap-3 items-center border-b dark:border-gray-700 pb-3 last:border-0">
                            <img :src="item.img" class="w-10 h-10 rounded-lg object-contain bg-white p-1 shadow-sm">
                            <div class="flex-1 min-w-0 text-gray-800 dark:text-gray-100">
                                <div class="truncate" x-text="item.nombre"></div>
                                <div class="text-perpetua-aqua" x-text="'$' + parseFloat(item.precio * item.qty).toFixed(2)"></div>
                            </div>
                            <div class="flex items-center gap-1 bg-blue-50 dark:bg-gray-800 rounded-lg p-1 border">
                                <button @click="updateQty(item.id, -1)" class="p-1 text-blue-600 bg-transparent transition-all active:scale-75"><i data-lucide="minus" class="w-3 h-3"></i></button>
                                <span class="w-4 text-center dark:text-white" x-text="item.qty"></span>
                                <button @click="updateQty(item.id, 1)" class="p-1 text-blue-600 bg-transparent transition-all active:scale-75"><i data-lucide="plus" class="w-3 h-3"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700" x-show="cart.length > 0">
                    <div class="flex justify-between font-bold mb-4 dark:text-white uppercase"><span x-text="t[lang].total"></span><span class="text-perpetua-aqua" x-text="'$' + totalPrice()"></span></div>
                    <button @click="initPayPal()" class="w-full btn-gradient text-white py-3 rounded-2xl font-bold uppercase tracking-widest shadow-lg transition-all" x-text="t[lang].pay"></button>
                </div>
            </div>

            <div x-show="isPaying" class="p-8 flex flex-col gap-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-bold text-xs uppercase dark:text-white">Pago Seguro</span>
                    <button @click="isPaying = false" class="hover:rotate-180 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                
                <div class="w-full bg-gray-200 dark:bg-gray-700 h-2 rounded-full overflow-hidden mb-2">
                    <div class="bg-perpetua-aqua h-full transition-all duration-500 shadow-[0_0_10px_#22d3ee]" :style="`width: ${payProgress}%`"></div >
                </div>

                <div id="paypal-button-container" class="min-h-[150px]"></div>
                <div class="text-center text-[10px] text-gray-400 font-bold uppercase animate-pulse" x-text="t[lang].encrypting"></div>
            </div>

            <div x-show="showSuccess" x-cloak class="p-12 flex flex-col items-center gap-4 text-center">
                <i data-lucide="party-popper" class="w-16 h-16 text-perpetua-orange animate-bounce"></i>
                <h2 class="text-2xl font-black text-perpetua-blue dark:text-perpetua-aqua uppercase" x-text="t[lang].success"></h2>
            </div>
        </div>
    </div>

    <template x-if="selectedProduct">
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-cloak>
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="selectedProduct = null"></div>
            <div class="relative w-full max-w-xl rounded-[2.5rem] glass overflow-hidden shadow-2xl transition-all" x-data="{ activeImgModal: 0, selectedQtyModal: 1 }">
                <div class="p-5 bg-perpetua-blue text-white flex justify-between items-center font-bold uppercase text-sm">
                    <span x-text="selectedProduct.nombre"></span>
                    <button @click="selectedProduct = null" class="hover:rotate-180 transition-transform duration-500 p-1"><i data-lucide="x" class="w-6 h-6"></i></button>
                </div>
                <div class="p-8">
                    <div class="relative w-full h-64 bg-white rounded-3xl mb-6 flex items-center justify-center p-4 border overflow-hidden">
                        <div x-show="isScanning" class="absolute inset-0 z-50 flex items-center justify-center bg-perpetua-blue/20 backdrop-blur-sm">
                            <div class="scanner-line"></div>
                            <div class="grid-overlay absolute inset-0"></div>
                        </div>
                        
                        <img :src="selectedProduct.imagenes[activeImgModal]" class="max-h-full object-contain transition-all duration-500">
                        <template x-if="selectedProduct.imagenes.length > 1">
                            <div class="absolute inset-0 flex items-center justify-between px-3 z-30">
                                <button @click="activeImgModal = (activeImgModal === 0) ? selectedProduct.imagenes.length - 1 : activeImgModal - 1" class="p-2 glass rounded-full text-blue-600 shadow-lg active:scale-90"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                                <button @click="activeImgModal = (activeImgModal === selectedProduct.imagenes.length - 1) ? 0 : activeImgModal + 1" class="p-2 glass rounded-full text-blue-600 shadow-lg active:scale-90"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                            </div>
                        </template>
                    </div>
                    
                    <p class="text-xs text-gray-500 dark:text-gray-300 mb-6 italic leading-relaxed" x-text="selectedProduct.descripcion_larga || 'No hay descripción disponible.'"></p>
                    
                    <div class="flex flex-wrap justify-between items-center p-4 rounded-2xl mb-6 bg-gray-100 dark:bg-gray-800 border dark:border-gray-600 gap-4">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 block mb-1 uppercase" x-text="t[lang].price"></span>
                            <span class="text-2xl font-black text-blue-600 dark:text-perpetua-aqua" x-text="'$' + selectedProduct.precio.toFixed(2)"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center bg-gray-100 dark:bg-gray-100 rounded-full p-1 border shadow-inner border-gray-200">
                                <button @click="if(selectedQtyModal > 1) selectedQtyModal--" class="w-8 h-8 flex items-center justify-center bg-transparent text-blue-600 transition-all"><i data-lucide="minus" class="w-4 h-4"></i></button>
                                <span class="text-lg font-black w-10 text-center text-gray-900" x-text="selectedQtyModal"></span>
                                <button @click="if(selectedQtyModal < selectedProduct.stock) selectedQtyModal++; else alert(t[lang].noStock)" class="w-8 h-8 flex items-center justify-center bg-transparent text-blue-600 transition-all"><i data-lucide="plus" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                    </div>
                    <button @click="addToCart(selectedProduct, selectedQtyModal); selectedProduct = null" class="w-full btn-gradient text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">
                        <span x-text="t[lang].add"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <footer class="py-12 border-t border-perpetua-blue/10 flex flex-col items-center justify-center bg-white dark:bg-gray-900 transition-colors">
        <span class="text-[10px] font-medium tracking-[0.4em] text-gray-400 mb-6 uppercase" x-text="t[lang].powered"></span>
        <img src="imagenes/KAI_NA.png" alt="KAI" class="h-10 object-contain dark:invert opacity-80">
    </footer>

    <script>window.addEventListener('load', () => lucide.createIcons());</script>
</body>
</html>
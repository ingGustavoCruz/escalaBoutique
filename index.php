<?php
/**
 * index.php - EscalaBoutique (Intranet)
 * Versión: Gold Master (Frontend + Backend Sync + Realtime Stock)
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

// --- 2. CARGA INICIAL DE PRODUCTOS ---
$conn->query("SET SESSION group_concat_max_len = 10000;");
$query = "SELECT p.*, GROUP_CONCAT(i.url_imagen ORDER BY i.es_principal DESC, i.id ASC) as lista_imagenes 
          FROM productos p 
          LEFT JOIN imagenes_productos i ON p.id = i.producto_id 
          GROUP BY p.id";

$resultado = $conn->query($query);
$productos = [];
$categorias = ['todos'];

if ($resultado && $resultado->num_rows > 0) {
    while($row = $resultado->fetch_assoc()) {
        // Filtrar rutas vacías
        $imgsRaw = $row['lista_imagenes'] ? explode(',', $row['lista_imagenes']) : [];
        $row['imagenes'] = array_values(array_filter($imgsRaw, function($value) {
            return !is_null($value) && $value !== ''; 
        }));
        
        if (empty($row['imagenes'])) { $row['imagenes'] = []; }

        $row['precio'] = (float)$row['precio'];
        $row['precio_anterior'] = $row['precio_anterior'] ? (float)$row['precio_anterior'] : 0;
        $row['stock'] = (int)$row['stock']; // Stock inicial (foto del momento)
        $row['en_oferta'] = (int)$row['en_oferta'];
        $row['es_top'] = (int)$row['es_top'];
        $row['tallas'] = $row['tallas'] ?? '';
        
        $catClean = isset($row['categoria']) ? strtolower(trim($row['categoria'])) : 'general';
        $catClean = empty($catClean) ? 'general' : $catClean;
        $row['categoria_normalizada'] = $catClean;
        $row['calificacion'] = (float)($row['calificacion'] ?? 5.0);
        
        if (!in_array($catClean, $categorias)) {
            $categorias[] = $catClean;
        }
        $productos[] = $row;
    }
}

$nombreCompleto = isset($_SESSION['usuario_empleado']) ? $_SESSION['usuario_empleado']['nombre'] : 'Invitado';
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
                        'escala-green': '#00524A',
                        'escala-beige': '#AA9482',
                        'escala-dark': '#003d36',
                        'escala-blue': '#1e3a8a',
                        'escala-alert': '#FF9900',
                    }
                }
            }
        }

        function appData() {
            return {
                products: <?php echo json_encode($productos, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>,
                isLoading: true,
                currentCategory: 'todos',
                searchQuery: '',
                cartOpen: false,
                selectedProduct: null,
                showToast: false,
                isPaying: false, 
                showSuccess: false,
                showPayrollModal: false,
                plazos: 1,
                userMenuOpen: false,
                cart: JSON.parse(localStorage.getItem('cart_escala')) || [],
                
                init() {
                    // Loader inicial
                    setTimeout(() => {
                        this.isLoading = false;
                        setTimeout(() => lucide.createIcons(), 50);
                    }, 800);

                    // Watchers visuales
                    this.$watch('selectedProduct', () => setTimeout(() => lucide.createIcons(), 50));
                    this.$watch('cartOpen', () => setTimeout(() => lucide.createIcons(), 50));
                    this.$watch('currentCategory', () => setTimeout(() => lucide.createIcons(), 50));
                    this.$watch('searchQuery', () => setTimeout(() => lucide.createIcons(), 100));

                    // --- NUEVO: POLLING DE STOCK (CADA 10 SEGUNDOS) ---
                    setInterval(() => {
                        this.sincronizarStock();
                    }, 10000);
                },

                // --- NUEVO: FUNCION DE SINCRONIZACIÓN SILENCIOSA ---
                sincronizarStock() {
                    fetch('api/obtener_stock.php')
                        .then(res => {
                            if (!res.ok) throw new Error('API no disponible');
                            return res.json();
                        })
                        .then(data => {
                            data.forEach(itemFresco => {
                                // Buscamos el producto en memoria local
                                let productoLocal = this.products.find(p => p.id === itemFresco.id);
                                if (productoLocal) {
                                    // Si el stock cambió en BD, actualizamos la vista
                                    if (productoLocal.stock !== itemFresco.stock) {
                                        productoLocal.stock = itemFresco.stock;
                                    }
                                }
                            });
                        })
                        .catch(err => {
                            // Silencioso: no molestamos al usuario si falla el polling
                            // console.warn("Sincronización de stock pausada:", err);
                        });
                },

                get filteredProducts() {
                    const q = this.searchQuery.toLowerCase().trim();
                    const cat = this.currentCategory;

                    return this.products.filter(p => {
                        const categoryMatch = (cat === 'todos' || p.categoria_normalizada === cat);
                        const nameMatch = p.nombre.toLowerCase().includes(q);
                        const priceMatch = p.precio.toString().includes(q);
                        return categoryMatch && (q === '' || nameMatch || priceMatch);
                    });
                },

                openModal(p) { 
                    this.selectedProduct = JSON.parse(JSON.stringify(p)); 
                    this.selectedProduct.sizeSelected = ''; 
                    this.activeImgModal = 0; 
                },

                addToCart(p, qty = 1, size = null) {
                    if (p.stock === 0) return;
                    
                    if (p.tallas && p.tallas.length > 0 && !size) {
                        alert('Por favor selecciona una talla.');
                        return;
                    }
                    const qtyNum = parseInt(qty);
                    const itemIndex = this.cart.findIndex(i => i.id === p.id && i.talla === size);

                    if (itemIndex > -1) {
                        if ((this.cart[itemIndex].qty + qtyNum) > p.stock) {
                            alert('Stock insuficiente.');
                            return;
                        }
                        this.cart[itemIndex].qty += qtyNum;
                    } else {
                        let imgUrl = (p.imagenes && p.imagenes.length > 0 && p.imagenes[0]) ? p.imagenes[0] : 'imagenes/torito.png';
                        
                        this.cart.push({
                            id: p.id,
                            nombre: p.nombre,
                            precio: p.precio,
                            img: imgUrl,
                            qty: qtyNum,
                            stock: p.stock,
                            talla: size
                        });
                    }
                    this.saveCart();
                    this.showToast = true;
                    if(this.selectedProduct) this.selectedProduct = null;
                    setTimeout(() => { this.showToast = false; }, 3000);
                },

                updateQty(index, delta) {
                    const item = this.cart[index];
                    if (!item) return;
                    const newQty = item.qty + delta;
                    if (delta > 0 && newQty > item.stock) { alert('Límite de stock alcanzado'); return; }
                    if (newQty <= 0) { this.cart.splice(index, 1); } else { item.qty = newQty; }
                    this.saveCart();
                },

                saveCart() { 
                    localStorage.setItem('cart_escala', JSON.stringify(this.cart)); 
                    setTimeout(() => lucide.createIcons(), 50); 
                },

                totalPrice() { 
                    return this.cart.reduce((s, i) => s + (i.precio * i.qty), 0).toFixed(2); 
                },

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
                            
                            // --- NUEVO: ACTUALIZACIÓN OPTIMISTA DEL STOCK LOCAL ---
                            // Restamos inmediatamente lo que se acaba de comprar para no esperar al polling
                            this.cart.forEach(itemCart => {
                                let productEnTienda = this.products.find(p => p.id === itemCart.id);
                                if (productEnTienda) {
                                    // Restamos stock, asegurando que no baje de 0
                                    productEnTienda.stock = Math.max(0, productEnTienda.stock - itemCart.qty);
                                }
                            });
                            // -----------------------------------------------------

                            this.cart = [];
                            this.saveCart();
                            setTimeout(() => { this.showSuccess = false; }, 4000);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => { 
                        this.isPaying = false; 
                        alert('Error de conexión con el servidor.'); 
                        console.error(err);
                    });
                }
            }
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: system-ui, -apple-system, sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .badge-top { background: linear-gradient(90deg, #00524A 0%, #16ed48 100%); color: white; font-weight: 800; padding: 5px 15px; clip-path: polygon(0 0, 100% 0, 90% 50%, 100% 100%, 0 100%); z-index: 10; font-size: 10px; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); }
        .badge-right { color: white; font-weight: 900; padding: 4px 10px 4px 18px; clip-path: polygon(10px 0, 100% 0, 100% 100%, 10px 100%, 0 50%); z-index: 10; font-size: 10px; margin-bottom: 4px; text-transform: uppercase; box-shadow: -2px 2px 5px rgba(0,0,0,0.1); }
        .bg-sale { background-color: #FF9900; }
        .bg-last { background-color: #EF4444; }
        .btn-add { background-color: #00524A; color: white; transition: 0.3s; border-radius: 8px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; font-size: 11px; }
        .btn-add:hover { background-color: #003d36; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,82,74,0.3); }
        .size-btn-active { background-color: #00524A; color: white; border-color: #00524A; }
    </style>
</head>

<body class="bg-slate-50 text-gray-900 min-h-screen flex flex-col">

    <div x-show="showToast" x-cloak x-transition class="fixed top-5 right-5 z-[100] px-6 py-4 bg-white rounded-lg shadow-xl flex items-center gap-3 border-l-4 border-escala-green">
        <i data-lucide="check" class="w-5 h-5 text-escala-green"></i>
        <p class="text-sm font-bold">Agregado al carrito</p>
    </div>

    <header class="bg-escala-green pt-4 pb-2 sticky top-0 z-40 shadow-lg border-b border-escala-dark">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-4 md:mb-6">
                
                <div class="flex justify-between items-center w-full md:w-auto md:justify-start gap-4">
                    <div class="flex-shrink-0 bg-white/95 p-2 md:p-3 rounded-xl shadow-md transition-all hover:scale-105">
                        <img src="imagenes/EscalaBoutique.png" alt="Logo Mobile" class="h-10 w-auto object-contain block md:hidden">
                        <img src="imagenes/EscalaBoutiqueCompleto.png" alt="Logo Desktop" class="h-12 md:h-16 w-auto object-contain hidden md:block">
                    </div>

                    <div class="relative md:hidden" @click.away="userMenuOpen = false">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex flex-col items-end text-right focus:outline-none">
                            <span class="text-[9px] font-bold text-gray-300 uppercase tracking-wider">HOLA,</span>
                            <div class="flex items-center gap-1">
                                <span class="text-xs font-bold text-escala-beige truncate max-w-[120px]"><?php echo explode(' ', $nombreCompleto)[0]; ?></span>
                                <i data-lucide="chevron-down" class="w-3 h-3 text-escala-beige"></i>
                            </div>
                        </button>
                        <div x-show="userMenuOpen" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50 border border-gray-100">
                            <a href="mis_pedidos.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-escala-green font-bold"><i data-lucide="package" class="inline w-4 h-4 mr-2"></i> Mis Pedidos</a>
                        </div>
                    </div>
                </div>

                <div class="relative w-full md:max-w-xl mx-auto order-2 md:order-1">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 md:w-5 md:h-5 text-gray-400"></i>
                    <input type="text" x-model="searchQuery" placeholder="Buscar..." class="w-full pl-10 md:pl-12 pr-4 py-2.5 md:py-3 bg-white border-none rounded-full focus:outline-none focus:ring-2 focus:ring-escala-beige shadow-lg transition-all text-xs md:text-sm placeholder-gray-400 text-gray-800">
                </div>

                <div class="hidden md:block relative order-3" @click.away="userMenuOpen = false">
                    <button @click="userMenuOpen = !userMenuOpen" class="flex flex-col items-end text-right focus:outline-none group">
                        <span class="text-[10px] font-bold text-gray-300 uppercase tracking-wider group-hover:text-white transition-colors">BIENVENIDO</span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-escala-beige truncate max-w-[200px]"><?php echo $nombreCompleto; ?></span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-escala-beige"></i>
                        </div>
                    </button>
                     <div x-show="userMenuOpen" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50 border border-gray-100">
                        <a href="mis_pedidos.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-escala-green font-bold"><i data-lucide="package" class="inline w-4 h-4 mr-2"></i> Mis Pedidos</a>
                    </div>
                </div>

            </div>
            
            <nav class="flex gap-2 md:gap-3 overflow-x-auto no-scrollbar pb-2 pt-1">
                <?php foreach($categorias as $cat): ?>
                <button @click="currentCategory = '<?php echo $cat; ?>'" 
                        :class="currentCategory === '<?php echo $cat; ?>' ? 'bg-escala-beige text-white shadow-md transform -translate-y-0.5' : 'bg-escala-dark/40 text-gray-300 border border-white/10 hover:bg-white/10 hover:text-white'" 
                        class="px-4 md:px-6 py-1.5 md:py-2 rounded-full text-[10px] md:text-[11px] font-black uppercase tracking-widest whitespace-nowrap transition-all duration-300 flex-shrink-0">
                    <?php echo strtoupper($cat); ?>
                </button>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main class="max-w-[1400px] mx-auto px-4 sm:px-6 py-10 flex-grow w-full">
        
        <div x-show="isLoading" class="flex flex-col items-center justify-center min-h-[50vh]" x-transition>
            <div class="relative w-24 h-24 mb-4">
                <div class="absolute inset-0 bg-escala-green/20 rounded-full animate-ping"></div>
                <img src="imagenes/torito.png" class="w-full h-full object-contain relative z-10 animate-bounce" alt="Cargando...">
            </div>
            <p class="text-escala-green font-bold text-sm tracking-widest animate-pulse">CARGANDO BOUTIQUE...</p>
        </div>

        <div x-show="!isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" x-cloak x-transition.opacity>
            
            <template x-for="p in filteredProducts" :key="p.id">
                <div class="bg-white rounded-3xl p-8 shadow-[0_10px_40px_-15px_rgba(0,0,0,0.1)] hover:shadow-[0_20px_50px_-20px_rgba(0,0,0,0.15)] transition-all duration-300 border border-escala-dark flex flex-col relative group"
                     :class="{'opacity-60 grayscale pointer-events-none select-none': p.stock === 0}" 
                     x-data="{ activeImg: 0, qty: 1 }">
                
                    <div class="absolute top-4 left-0" x-show="p.es_top == 1 && p.stock > 0">
                        <div class="badge-top">TOP VENTAS</div>
                    </div>
                    <div class="absolute top-4 right-0 flex flex-col items-end space-y-2 z-20">
                         <div x-show="p.stock <= 5 && p.stock > 0" class="badge-right bg-last">¡ÚLTIMAS PIEZAS!</div>
                         <div x-show="p.en_oferta == 1 && p.stock > 0" class="badge-right bg-sale">EN OFERTA</div>
                         <div x-show="p.stock === 0" class="badge-right bg-gray-500">AGOTADO</div>
                    </div>

                    <div class="h-72 flex items-center justify-center mb-8 relative p-4">
                        <div class="w-full h-full flex items-center justify-center">
                            <img :src="(p.imagenes && p.imagenes.length > 0 && p.imagenes[activeImg]) ? p.imagenes[activeImg] : 'imagenes/torito.png'" 
                                 onerror="this.onerror=null; this.src='imagenes/torito.png';"
                                 class="max-h-full max-w-full object-contain drop-shadow-xl group-hover:scale-105 transition-transform duration-500">
                        </div>

                        <template x-if="p.imagenes && p.imagenes.length > 1">
                            <div class="absolute inset-0 flex items-center justify-between px-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <button @click.stop="activeImg = (activeImg === 0) ? p.imagenes.length - 1 : activeImg - 1" 
                                        class="pointer-events-auto p-2 bg-white/80 hover:bg-white rounded-full shadow-md text-escala-dark hover:text-escala-green transition-all transform hover:scale-110">
                                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                </button>
                                <button @click.stop="activeImg = (activeImg === p.imagenes.length - 1) ? 0 : activeImg + 1" 
                                        class="pointer-events-auto p-2 bg-white/80 hover:bg-white rounded-full shadow-md text-escala-dark hover:text-escala-green transition-all transform hover:scale-110">
                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-col flex-grow items-center text-center">
                        <span class="text-[9px] bg-gray-200 text-gray-500 px-2 py-1 rounded mb-2" x-text="'[CAT: ' + p.categoria_normalizada.toUpperCase() + ']'"></span>

                        <h3 class="font-black text-xl text-slate-900 mb-2 uppercase leading-tight line-clamp-2" x-text="p.nombre"></h3>
                        <p class="text-xs text-gray-500 font-medium mb-3 px-2 line-clamp-2 min-h-[2.5em] leading-snug" x-text="p.descripcion_corta || ''"></p>
                        
                        <div class="flex items-center gap-1 mb-4 justify-center">
                            <template x-for="i in 5">
                                <i data-lucide="star" class="w-4 h-4" :class="i <= p.calificacion ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200'"></i>
                            </template>
                        </div>

                        <p class="text-xs text-gray-500 mb-6 font-bold uppercase tracking-wider">
                            STOCK: 
                            <span :class="p.stock > 0 ? 'text-blue-600' : 'text-red-500'" x-text="p.stock > 0 ? p.stock : 'AGOTADO'"></span>
                        </p>

                        <div class="mt-auto w-full">
                            <div class="flex items-center justify-center gap-4 mb-8">
                                 <div class="flex items-center bg-gray-100 rounded-full p-1 shadow-inner" :class="{'opacity-50': p.stock === 0}">
                                    <button @click="if(qty > 1) qty--" :disabled="p.stock===0" class="p-2 hover:text-blue-600 transition-colors"><i data-lucide="minus" class="w-4 h-4"></i></button>
                                    <span class="w-10 text-center font-black text-lg" x-text="qty"></span>
                                    <button @click="if(qty < p.stock) qty++; else if(p.stock>0) alert('Max stock')" :disabled="p.stock===0" class="p-2 hover:text-blue-600 transition-colors"><i data-lucide="plus" class="w-4 h-4"></i></button>
                                </div>
                                
                                <div class="flex flex-col items-start">
                                    <span class="text-sm text-gray-400 line-through font-medium" 
                                          x-show="p.en_oferta == 1 || p.precio_anterior > 0"
                                          x-text="'$' + (p.precio_anterior > 0 ? p.precio_anterior.toFixed(2) : (p.precio * 1.3).toFixed(2))">
                                    </span>
                                    <span class="text-3xl font-black text-escala-green" x-text="'$' + p.precio.toFixed(2)"></span>
                                    <span class="text-[10px] font-bold text-escala-beige bg-escala-beige/10 px-2 py-0.5 rounded mt-1">
                                        Desde $<span x-text="(p.precio/3).toFixed(2)"></span> /qna
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 w-full">
                                <button @click="if(p.stock > 0) { (!p.tallas || p.tallas.length === 0) ? addToCart(p, qty) : openModal(p) }" 
                                        class="w-full py-4 rounded-xl flex items-center justify-center gap-2 shadow-lg transition-all"
                                        :class="p.stock === 0 ? 'bg-gray-400 cursor-not-allowed text-white' : 'btn-add'"
                                        :disabled="p.stock === 0">
                                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                                    <span x-text="p.stock === 0 ? 'AGOTADO' : (p.tallas && p.tallas.length > 0 ? 'SELECCIONAR TALLA' : 'AÑADIR AL CARRITO')"></span>
                                </button>
                                
                                <button @click="openModal(p)" class="w-full py-2.5 flex items-center justify-center gap-2 bg-white border border-escala-dark text-escala-dark rounded-xl font-bold uppercase text-[10px] hover:bg-gray-50 transition-all">
                                    <i data-lucide="info" class="w-3 h-3"></i> MÁS INFORMACIÓN
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="filteredProducts.length === 0" class="col-span-1 md:col-span-2 lg:col-span-3 flex flex-col items-center justify-center h-64 text-gray-300">
                <i data-lucide="package" class="w-16 h-16 mb-4 opacity-50"></i>
                <p class="font-medium text-lg">No se encontraron productos.</p>
            </div>
        </div>
    </main>

    <footer class="bg-gray-50 py-6 border-t-2 border-escala-blue mt-auto">
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
         class="fixed bottom-28 right-4 z-50 w-72 bg-white shadow-2xl rounded-2xl overflow-hidden flex flex-col transition-all duration-300 border border-gray-100 max-h-[60vh] h-auto origin-bottom"
         :class="cartOpen ? 'translate-x-0 opacity-100 scale-100' : 'translate-x-10 opacity-0 scale-95 pointer-events-none'"
         x-cloak>
        <div class="p-3 bg-escala-dark flex justify-between items-center shadow-md shrink-0">
            <h2 class="font-bold text-xs text-white tracking-widest uppercase">Tu Carrito</h2>
            <button @click="cartOpen = false" class="p-1 bg-white/20 hover:bg-white/30 rounded-full transition-all duration-300 hover:rotate-90 text-white"><i data-lucide="x" class="w-3 h-3"></i></button>
        </div>
        <div class="flex-grow overflow-y-auto p-2 space-y-2 bg-gray-50">
            <template x-for="(item, index) in cart" :key="index">
                <div class="flex gap-2 items-center bg-white p-2 rounded-lg shadow-sm border border-gray-100 relative group">
                    <div class="w-10 h-10 bg-white rounded flex items-center justify-center shrink-0 p-0.5 border border-gray-100">
                        <img :src="item.img" class="max-h-full max-w-full object-contain" onerror="this.onerror=null; this.src='imagenes/torito.png';">
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-[10px] text-slate-800 uppercase leading-none mb-1 truncate" x-text="item.nombre"></h4>
                        <template x-if="item.talla">
                             <span class="text-[9px] font-bold text-gray-500 bg-gray-100 px-1 rounded" x-text="'Talla: ' + item.talla"></span>
                        </template>
                        <p class="text-escala-green font-black text-xs" x-text="'$' + (item.precio * item.qty).toFixed(2)"></p>
                    </div>
                    <div class="flex items-center bg-gray-50 rounded-md px-1 py-0.5 border border-gray-100">
                        <button @click="updateQty(index, -1)" class="p-1 text-gray-400 hover:text-red-500 transition-colors"><i data-lucide="minus" class="w-3 h-3"></i></button>
                        <span class="text-[10px] font-black w-5 text-center text-slate-800" x-text="item.qty"></span>
                        <button @click="updateQty(index, 1)" class="p-1 text-gray-400 hover:text-escala-green transition-colors"><i data-lucide="plus" class="w-3 h-3"></i></button>
                    </div>
                </div>
            </template>
            <div x-show="cart.length === 0" class="flex flex-col items-center justify-center py-8 text-gray-300">
                <i data-lucide="shopping-bag" class="w-8 h-8 mb-2 opacity-50"></i>
                <p class="font-bold text-[10px] uppercase">Carrito vacío</p>
            </div>
        </div>
        <div class="p-3 bg-white border-t border-gray-100 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0" x-show="cart.length > 0">
            <div class="flex justify-between items-end mb-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total</span>
                <span class="font-black text-base text-escala-green" x-text="'$' + totalPrice()"></span>
            </div>
            <button @click="iniciarTramite()" class="w-full py-2 rounded-lg font-bold uppercase tracking-widest text-[10px] shadow-sm flex items-center justify-center gap-2 text-white bg-escala-dark hover:bg-escala-green hover:shadow-md hover:-translate-y-0.5 transition-all">
                <i data-lucide="credit-card" class="w-3 h-3"></i> Finalizar Compra
            </button>
        </div>
    </div>

    <template x-if="selectedProduct">
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-cloak x-transition.opacity>
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="selectedProduct = null"></div>
            
            <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row max-h-[90vh]" 
                x-data="{ activeImgModal: 0, modalQty: 1 }">
                
                <div class="w-full md:w-1/2 bg-gray-50 p-12 flex items-center justify-center relative group">
                    <img :src="(selectedProduct.imagenes && selectedProduct.imagenes.length > 0 && selectedProduct.imagenes[activeImgModal]) 
                                ? selectedProduct.imagenes[activeImgModal] 
                                : 'imagenes/torito.png'" 
                         onerror="this.onerror=null; this.src='imagenes/torito.png';"
                         class="max-h-[400px] w-auto object-contain drop-shadow-2xl transition-all duration-300 mix-blend-multiply">
                    
                    <button @click="selectedProduct = null" class="absolute top-6 left-6 md:hidden p-3 bg-white rounded-full shadow-md text-gray-800 hover:bg-gray-100"><i data-lucide="arrow-left" class="w-6 h-6"></i></button>
                    
                    <template x-if="selectedProduct.imagenes && selectedProduct.imagenes.length > 1">
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
                    
                    <div class="mb-6 space-y-4">
                        <div class="flex items-center gap-2">
                            <div class="flex text-yellow-400">
                                <template x-for="i in 5">
                                    <i data-lucide="star" class="w-4 h-4" :class="i <= selectedProduct.calificacion ? 'fill-current' : 'text-gray-200'"></i>
                                </template>
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wide" 
                                  :class="selectedProduct.stock > 0 ? 'text-gray-400' : 'text-red-500'"
                                  x-text="selectedProduct.stock > 0 ? 'Stock: ' + selectedProduct.stock : '¡AGOTADO!'"></span>
                        </div>

                        <template x-if="selectedProduct.tallas && selectedProduct.tallas.length > 0">
                            <div>
                                <p class="text-xs font-bold text-slate-800 uppercase mb-2">Selecciona Talla:</p>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="talla in selectedProduct.tallas.split(',')" :key="talla">
                                        <button @click="selectedProduct.sizeSelected = talla"
                                                class="px-4 py-2 rounded-lg border font-bold text-sm transition-all"
                                                :class="selectedProduct.sizeSelected === talla ? 'size-btn-active shadow-md' : 'bg-white text-gray-500 border-gray-200 hover:border-escala-green'"
                                                x-text="talla">
                                        </button>
                                    </template>
                                </div>
                                <p x-show="!selectedProduct.sizeSelected" class="text-[10px] text-red-500 font-bold mt-1">* Requerido</p>
                            </div>
                        </template>
                    </div>
                    
                    <p class="text-gray-600 text-base mb-8 leading-relaxed font-medium" x-text="selectedProduct.descripcion_larga || selectedProduct.descripcion_corta"></p>
                    
                    <div class="mt-auto pt-8 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center bg-gray-100 rounded-full px-4 py-2 border border-gray-200" :class="{'opacity-50 pointer-events-none': selectedProduct.stock === 0}">
                                <button @click="if(modalQty > 1) modalQty--" class="text-slate-600 hover:text-escala-green transition-colors font-bold text-lg px-2">−</button>
                                <span class="mx-4 font-black text-xl text-slate-800 w-6 text-center" x-text="modalQty"></span>
                                <button @click="if(modalQty < selectedProduct.stock) modalQty++; else alert('Stock máximo alcanzado')" class="text-slate-600 hover:text-escala-green transition-colors font-bold text-lg px-2">+</button>
                            </div>
                            <div class="text-right">
                                <span x-show="selectedProduct.en_oferta == 1 || selectedProduct.precio_anterior > 0" 
                                      class="text-sm text-gray-400 line-through font-medium block" 
                                      x-text="'$' + (selectedProduct.precio_anterior > 0 ? selectedProduct.precio_anterior.toFixed(2) : (selectedProduct.precio * 1.3).toFixed(2))"></span>
                                <span class="text-4xl font-black text-escala-green" x-text="'$' + selectedProduct.precio.toFixed(2)"></span>
                                <span class="text-xs font-bold text-escala-beige block mt-1">
                                    Desde $<span x-text="(selectedProduct.precio/3).toFixed(2)"></span> /qna
                                </span>
                            </div>
                        </div>

                        <button @click="addToCart(selectedProduct, modalQty, selectedProduct.sizeSelected)" 
                                class="w-full py-5 rounded-2xl font-black text-white uppercase shadow-xl hover:shadow-2xl transition-all flex justify-center gap-3 text-base tracking-wider"
                                :class="(selectedProduct.stock === 0 || (selectedProduct.tallas && !selectedProduct.sizeSelected)) ? 'bg-gray-400 cursor-not-allowed' : 'btn-add'"
                                :disabled="selectedProduct.stock === 0 || (selectedProduct.tallas && !selectedProduct.sizeSelected)">
                            <i data-lucide="shopping-cart" class="w-6 h-6"></i> 
                            <span x-text="selectedProduct.stock === 0 ? 'AGOTADO' : 'AGREGAR AL CARRITO'"></span>
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
                <p class="text-xs text-gray-500">Enviando solicitud a Escala Boutique</p>
            </div>

            <div x-show="!isPaying">
                <h3 class="font-black text-xl uppercase mb-1 text-slate-900">Confirmar Descuento</h3>
                
                <p class="text-xs text-gray-400 mb-2 uppercase tracking-wide font-bold">Monto total de la compra</p>
                <span class="font-black text-4xl text-escala-green block mb-6" x-text="'$' + totalPrice()"></span>

                <p class="text-xs text-gray-800 mb-3 font-bold">Selecciona los plazos a diferir:</p>
                <div class="flex gap-3 justify-center mb-6">
                    <button @click="plazos = 1" :class="plazos === 1 ? 'bg-escala-green text-white ring-2 ring-offset-2 ring-escala-green shadow-lg scale-105' : 'bg-gray-50 text-gray-400 border border-gray-200 hover:bg-gray-100'" class="flex-1 py-3 rounded-xl flex flex-col items-center justify-center transition-all duration-200">
                        <span class="font-black text-lg">1</span><span class="text-[9px] uppercase font-bold">Qna</span>
                    </button>
                    <button @click="plazos = 2" :class="plazos === 2 ? 'bg-escala-green text-white ring-2 ring-offset-2 ring-escala-green shadow-lg scale-105' : 'bg-gray-50 text-gray-400 border border-gray-200 hover:bg-gray-100'" class="flex-1 py-3 rounded-xl flex flex-col items-center justify-center transition-all duration-200">
                        <span class="font-black text-lg">2</span><span class="text-[9px] uppercase font-bold">Qnas</span>
                    </button>
                    <button @click="plazos = 3" :class="plazos === 3 ? 'bg-escala-green text-white ring-2 ring-offset-2 ring-escala-green shadow-lg scale-105' : 'bg-gray-50 text-gray-400 border border-gray-200 hover:bg-gray-100'" class="flex-1 py-3 rounded-xl flex flex-col items-center justify-center transition-all duration-200">
                        <span class="font-black text-lg">3</span><span class="text-[9px] uppercase font-bold">Qnas</span>
                    </button>
                </div>

                <div class="bg-blue-50/60 rounded-xl p-4 mb-8 border border-blue-100">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-gray-500 uppercase">Tu descuento quincenal:</span>
                        <span class="font-black text-xl text-escala-blue" x-text="'$' + (parseFloat(totalPrice()) / plazos).toFixed(2)"></span>
                    </div>
                    <p class="text-[10px] text-gray-400 leading-tight text-right">
                        <span x-show="plazos === 1">Se descontará en una sola exhibición.</span>
                        <span x-show="plazos > 1">Durante las próximas <span x-text="plazos"></span> quincenas.</span>
                    </p>
                </div>

                <div class="flex gap-3">
                    <button @click="showPayrollModal = false" class="flex-1 py-3.5 rounded-xl font-bold text-gray-500 hover:bg-gray-100 text-xs uppercase transition-colors">
                        Cancelar
                    </button>
                    <button @click="confirmarPedidoNomina()" class="flex-1 py-3.5 rounded-xl font-bold bg-escala-green text-white shadow-lg hover:bg-escala-dark hover:shadow-xl transition-all text-xs uppercase transform active:scale-95">
                        Confirmar
                    </button>
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
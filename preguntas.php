<?php
session_start();
// No requiere conexión a BD a menos que quieras jalar FAQs dinámicas, 
// pero para velocidad, las dejaremos estáticas.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Preguntas Frecuentes | Escala Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'escala-green': '#00524A', 'escala-beige': '#AA9482', 'escala-dark': '#003d36' }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <header class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-gray-400 hover:text-escala-green transition-colors">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <h1 class="text-xl font-black text-escala-green uppercase tracking-tighter">Ayuda y FAQ</h1>
            </div>
            <img src="imagenes/monito01.png" class="h-10 w-auto" alt="Logo">
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-6">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-black text-escala-dark mb-4">¿Tienes dudas?</h2>
            <p class="text-gray-500 font-medium">Todo lo que necesitas saber sobre tus beneficios y compras en Escala Boutique.</p>
        </div>

        <div class="space-y-4" x-data="{ active: null }">
            
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <button @click="active = (active === 1 ? null : 1)" class="w-full px-8 py-3 text-left flex justify-between items-center group">
                    <span class="font-bold text-slate-700 group-hover:text-escala-green transition-colors">¿Cómo funciona el descuento por nómina?</span>
                    <i data-lucide="chevron-down" class="w-5 h-5 text-gray-300 transition-transform" :class="active === 1 ? 'rotate-180 text-escala-green' : ''"></i>
                </button>
                <div x-show="active === 1" x-collapse x-cloak class="px-8 pb-6 text-sm text-gray-500 leading-relaxed">
                    Al realizar tu compra, el monto total se dividirá según el plan que elijas (1, 2 o 3 quincenas). El primer descuento se aplicará en la quincena inmediata posterior a la aprobación de tu pedido por Recursos Humanos.
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <button @click="active = (active === 2 ? null : 2)" class="w-full px-8 py-3 text-left flex justify-between items-center group">
                    <span class="font-bold text-slate-700 group-hover:text-escala-green transition-colors">¿Por qué mi pedido sigue "Pendiente"?</span>
                    <i data-lucide="chevron-down" class="w-5 h-5 text-gray-300 transition-transform" :class="active === 2 ? 'rotate-180 text-escala-green' : ''"></i>
                </button>
                <div x-show="active === 2" x-collapse x-cloak class="px-8 pb-6 text-sm text-gray-500 leading-relaxed">
                    Todos los pedidos pasan por una validación de Recursos Humanos para asegurar que cuentas con saldo disponible en tu nómina. Una vez que RH lo marque como "Aprobado", procederemos con la entrega de tus productos.
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <button @click="active = (active === 3 ? null : 3)" class="w-full px-8 py-3 text-left flex justify-between items-center group">
                    <span class="font-bold text-slate-700 group-hover:text-escala-green transition-colors">¿Puedo elegir cuántas quincenas pagar?</span>
                    <i data-lucide="chevron-down" class="w-5 h-5 text-gray-300 transition-transform" :class="active === 3 ? 'rotate-180 text-escala-green' : ''"></i>
                </button>
                <div x-show="active === 3" x-collapse x-cloak class="px-8 pb-6 text-sm text-gray-500 leading-relaxed">
                    ¡Sí! Al momento de finalizar tu compra en el carrito, podrás seleccionar si deseas liquidar en 1, 2 o 3 quincenas sin intereses adicionales.
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <button @click="active = (active === 4 ? null : 4)" class="w-full px-8 py-3 text-left flex justify-between items-center group">
                    <span class="font-bold text-slate-700 group-hover:text-escala-green transition-colors">¿Qué pasa si un producto no me queda?</span>
                    <i data-lucide="chevron-down" class="w-5 h-5 text-gray-300 transition-transform" :class="active === 4 ? 'rotate-180 text-escala-green' : ''"></i>
                </button>
                <div x-show="active === 4" x-collapse x-cloak class="px-8 pb-6 text-sm text-gray-500 leading-relaxed">
                    Contamos con un sistema de inventario auditado. Si necesitas un cambio de talla, acude a la boutique física. El sistema ajustará el stock automáticamente para mantener la precisión del almacén.
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <button @click="active = (active === 5 ? null : 5)" class="w-full px-8 py-3 text-left flex justify-between items-center group">
                    <span class="font-bold text-slate-700 group-hover:text-escala-green transition-colors">¿Es segura mi información?</span>
                    <i data-lucide="chevron-down" class="w-5 h-5 text-gray-300 transition-transform" :class="active === 5 ? 'rotate-180 text-escala-green' : ''"></i>
                </button>
                <div x-show="active === 5" x-collapse x-cloak class="px-8 pb-6 text-sm text-gray-500 leading-relaxed">
                    Totalmente. Utilizamos cifrado corporativo y protección contra ataques externos para asegurar que tus transacciones y datos de empleado estén blindados en todo momento.
                </div>
            </div>
        </div>

        <div class="mt-6 p-3 bg-escala-green rounded-[2.5rem] text-center text-white shadow-xl shadow-escala-green/20">
            <h3 class="font-black uppercase tracking-widest text-xs mb-2">¿Aún tienes dudas?</h3>
            <p class="text-sm opacity-80 mb-2">Contáctanos directamente.</p>
        </div>
    </main>

    <footer class="bg-gray-50 py-6 border-t-2 border-escala-blue mt-auto">
        <div class="max-w-[1400px] mx-auto px-4 flex flex-col items-center justify-center">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.3em] mb-4">Powered By</p>
            <img src="imagenes/KAI_NA.png" alt="KAI Experience" class="h-10 w-auto escala-blue">
        </div>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>
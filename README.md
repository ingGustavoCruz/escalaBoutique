🛍️ Escala Boutique - Intranet E-commerce (V. 1.2)
Sistema de gestión de pedidos internos para empleados mediante descuento por nómina con Inteligencia de Negocio (BI), blindaje de seguridad de grado bancario y optimización de activos.

🚀 Características Avanzadas (Nuevas en V. 1.2)
👮‍♂️ Backend Administrativo (BI & Operaciones)
Inteligencia Financiera (Prueba del Centavo): Dashboard de BI que diferencia entre Ingresos Recaudados (efectivo real en nómina) y Cuentas por Cobrar (proyección de cuotas pendientes), eliminando "dinero fantasma" en los reportes.

Auditoría Anti-Fraude de Precios: Registro automático en bitácora ante cualquier modificación de precios, identificando al administrador responsable y el monto exacto del cambio para prevenir manipulaciones internas.

Corte de Nómina Masivo: Exportación de layouts CSV con rigor contable que incluye desglose de montos recaudados vs. saldos pendientes por pedido.

Optimización de Assets (WebP): Rutina automática que convierte imágenes pesadas (JPG/PNG) al formato WebP al subir o editar productos, reduciendo el peso de la galería hasta un 70% sin perder calidad visual.

🛡️ Seguridad Corporativa de "Doble Cerrojo"
Session Timeout & Inactivity Lock: El sistema expulsa automáticamente a los administradores tras 20 minutos de inactividad, protegiendo la sesión en computadoras desatendidas mediante validación en servidor y monitor en cliente.

Escudo Global CSRF: Protección criptográfica en todos los formularios críticos para evitar ejecuciones maliciosas externas.

🛒 Frontend (Experiencia del Empleado)
Performance Ultra-Rápido: Implementación de Lazy Loading en todo el catálogo y el historial de pedidos; las imágenes solo se descargan cuando el usuario hace scroll hacia ellas.

Interfaz Premium Unificada: Diseño consistente basado en tipografía Inter, pesos visuales fuertes (font-black) y radios de borde redondeados (2.5rem) para una experiencia de marca de alta gama.

🛠️ Stack Técnico Actualizado
Rendimiento: Conversión dinámica WebP (PHP GD) y Carga Perezosa (Native Lazy Loading).

BI: Chart.js con lógica de flujo de caja real y exportador CSV con auditoría financiera.

Frontend: Tailwind CSS, Alpine.js, Lucide Icons e Inter Font.

⚙️ Configuración de Seguridad (Importante)
Gestión de Sesiones: El monitor de inactividad requiere que sidebar.php esté incluido en todas las vistas administrativas.

Integridad de Auditoría: Se debe verificar que la función registrarBitacora() en api/logger.php tenga permisos de escritura para capturar los cambios de precio y movimientos de stock.

📋 Guía de Operación Estratégica

1. Gestión Financiera (Dashboard)
   El nuevo Resumen Ejecutivo permite a la dirección tomar decisiones basadas en Recaudación Real. Al visualizar el monto "Por recaudar", la boutique puede proyectar sus compras de stock futuro basándose en la deuda actual de los colaboradores.

2. Control de Inventario y Precios
   Cualquier discrepancia en el almacén o manipulación de precios queda grabada con fecha y responsable en la Bitácora de Seguridad, garantizando una rendición de cuentas total ante auditorías externas.

🛍️ Escala Boutique - Intranet E-commerce (V. 1.1)
Sistema de gestión de pedidos internos para empleados mediante descuento por nómina con blindaje de seguridad y auditoría de inventario.

🚀 Características Avanzadas
👮‍♂️ Backend Administrativo (BI & Operaciones)
Corte de Nómina Masivo: Módulo para exportar layouts CSV listos para sistemas contables, evitando duplicidad de cargos mediante estados de envío.

Dashboard de Business Intelligence: Análisis de KPIs, tendencias de ventas de 6 meses y rendimiento de campañas de cupones en tiempo real.

Seguridad Corporativa: Protección global contra ataques CSRF en todos los formularios críticos y validación de tokens de sesión.

Auditoría de Inventario: Bitácora automatizada que rastrea cada movimiento de stock (ventas, cancelaciones y ajustes manuales) con ID de responsable.

🛒 Frontend (Experiencia del Empleado)
Transparencia Quincenal: Proyección visual de descuentos en Mis Pedidos para que el colaborador sepa exactamente cuánto se le descontará cada quincena según su plan.

Gestión Inteligente: Bloqueo de transacciones por stock insuficiente a nivel de talla mediante transacciones SQL.

🛠️ Stack Técnico Actualizado
Seguridad: Motor de validación CSRF nativo.

Base de Datos: Triggers automáticos para sincronización de stock global y tablas de auditoría.

Frontend: Tailwind CSS, Alpine.js, Lucide Icons y Chart.js.

⚙️ Configuración de Seguridad (Importante)
El sistema requiere que el servidor soporte sesiones activas para la generación de tokens criptográficos:

Asegurar que api/conexion.php esté incluido en todos los procesos que usen POST.

Verificar que la tabla bitacora_inventario exista para evitar errores en el flujo de pedidos.

📋 Guía de Operación para Recursos Humanos y Nómina
Este sistema está diseñado para automatizar el ciclo de cobro y asegurar la integridad del inventario.

1. Gestión del Ciclo de Nómina (Corte Quincenal)
   Para evitar la saturación de correos y errores manuales, el proceso de cobro se centraliza en el módulo de Corte de Quincena:

Revisión: El sistema filtra automáticamente todos los pedidos con estado "Aprobado (RH)" que aún no han sido descontados.

Exportación Masiva: Al hacer clic en "Descargar Layout", se genera un archivo CSV compatible con Excel que contiene el número de empleado y el monto exacto a descontar según el plazo elegido (1, 2 o 3 quincenas).

Cierre de Corte: Una vez descargado el archivo, el sistema marca estos pedidos como "Enviados a Nómina" para que no se vuelvan a cobrar en la siguiente quincena.

2. Flujo de Pedidos e Inventario
   La administración de la boutique debe seguir este flujo para mantener el stock auditado:

Aprobación: Un pedido entra como "Pendiente" y debe ser validado por RH.

Cancelaciones: Si un pedido se cancela, el sistema devuelve automáticamente las prendas al stock (por talla o global) y genera un registro en la Bitácora de Inventario.

Auditoría: Cualquier movimiento de mercancía queda registrado con la fecha, el motivo y el ID del administrador responsable, permitiendo rastrear discrepancias en el almacén.

3. Estrategia de Marketing Interno
   El módulo de Cupones permite incentivar el consumo de los colaboradores:

Generación de Imagen: Una vez creado un cupón, se puede generar una tarjeta visual personalizada para compartir por WhatsApp o canales internos.

Monitoreo: El Dashboard BI muestra en tiempo real qué cupones están teniendo mayor éxito, permitiendo medir el retorno de las campañas de beneficios.

🛡️ Notas de Seguridad para el Administrador
Acceso Seguro: El panel administrativo está protegido contra ataques de sesión y falsificación de peticiones (CSRF).

Bitácora de Seguridad: Cada inicio de sesión y modificación sensible (cambio de precios o eliminación de usuarios) queda grabado de forma permanente para fines de auditoría.

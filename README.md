# 🛍️ Escala Boutique - Intranet E-commerce

Sistema de gestión de pedidos internos para empleados mediante descuento por nómina (Payroll Deduction).

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1)

## 🚀 Características Principales

### 🛒 Frontend (Empleados)

- **Catálogo Visual:** Vista de productos con tallas y stock en tiempo real.
- **Carrito de Compras:** Gestión de items antes de confirmar.
- **Descuento por Nómina:** Cálculo automático de plazos quincenales.
- **Mis Pedidos:** Historial de compras y estado de entrega.

### 👮‍♂️ Backend (Administrador)

- **Dashboard BI:** Gráficas de ventas, KPIs, inventario crítico y exportación a Excel (CSV).
- **Gestión de Pedidos:** Flujo de aprobación (Pendiente -> Entregado).
- **Inventario:** CRUD de productos con gestión de **Tallas** y **Galería de Imágenes**.
- **Marketing:** Módulo de Cupones con generador de imágenes para WhatsApp.
- **Directorio:** Listado de empleados y análisis de consumo interno.

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP Nativo (Sin frameworks pesados).
- **Base de Datos:** MySQL / MariaDB.
- **Frontend:** HTML5, Tailwind CSS (CDN).
- **Interactividad:** Alpine.js (Manejo de estados y modales).
- **Gráficos:** Chart.js.
- **Reportes:** Librería `html2canvas` para cupones.

## ⚙️ Instalación

1. **Base de Datos:**
   - Crear una base de datos llamada `escala_boutique`.
   - Importar el archivo `database/schema.sql` (o la estructura proporcionada).

2. **Conexión:**
   - Configurar credenciales en `api/conexion.php`.

3. **Permisos:**
   - Asegurar que la carpeta `imagenes/` tenga permisos de escritura.

## 🔑 Credenciales por Defecto (Entorno Local)

**Administrador:**

- URL: `/admin/`
- Usuario: (Configurado en base de datos, tabla `admins`)

**Empleado de Prueba:**

- Auto-login configurado para desarrollo local (`$_SESSION['empleado_id'] = 1`).

## 📂 Estructura del Proyecto

- `/admin` - Panel de control protegido.
- `/api` - Lógica de conexión y endpoints.
- `/imagenes` - Carga de fotos de productos.
- `index.php` - Tienda principal.

---

Desarrollado para uso interno de Escala.

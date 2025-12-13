# Sistema de Facturación Electrónica DIAN

Sistema completo de facturación electrónica desarrollado en PHP vanilla, cumpliendo con los lineamientos y estándares de la Dirección de Impuestos y Aduanas Nacionales (DIAN) de Colombia.

## Características Principales

- Generación de facturas electrónicas según estándares UBL 2.1
- Integración con API DIAN (simulador y producción)
- Validación XSD de documentos XML
- Firma digital de documentos
- Sistema de webhooks para notificaciones
- Plantillas personalizables de facturas
- Cálculo automático de impuestos según régimen tributario
- Determinación automática del tipo de documento según sector CIIU
- Dashboard con estadísticas en tiempo real
- Exportación de facturas a PDF y PNG
- Gestión de productos, servicios y clientes
- Notas crédito y débito

## Requisitos del Sistema

- PHP 7.4 o superior
- MySQL 5.7 o superior / MariaDB 10.3 o superior
- Apache con mod_rewrite habilitado o servidor PHP integrado
- Extensiones PHP requeridas:
  - PDO
  - PDO_MySQL
  - JSON
  - mbstring
  - OpenSSL (para firma digital)
  - libxml (para validación XSD)
  - cURL (para integración DIAN)

## Instalación

### 1. Clonar o descargar el proyecto

```bash
git clone <repository-url>
cd facturacion-electronica
```

### 2. Configurar base de datos

Editar el archivo `config/database.php` con las credenciales de su base de datos:

```php
return [
    'host' => 'localhost',
    'database' => 'facturacion_electronica',
    'username' => 'usuario',
    'password' => 'contraseña',
    'charset' => 'utf8mb4'
];
```

### 3. Crear base de datos

Ejecutar el script SQL:

```bash
mysql -u usuario -p < database.sql
```

O usar el script de instalación:

```bash
php install.php
```

### 4. Configurar servidor web

#### Opción A: Servidor PHP integrado (desarrollo)

```bash
php -S localhost:8000 -t .
```

#### Opción B: Apache

Configurar VirtualHost apuntando al directorio del proyecto y asegurar que mod_rewrite esté habilitado.

### 5. Configurar DIAN (opcional)

Para usar el servicio real de DIAN, editar `config/dian.php`:

```php
define('USE_REAL_DIAN', true);
define('DIAN_ENVIRONMENT', 'HABILITACION'); // o 'PRODUCCION'
define('DIAN_CERTIFICATE_PATH', '/ruta/al/certificado.p12');
define('DIAN_CERTIFICATE_PASSWORD', 'contraseña_certificado');
define('DIAN_USERNAME', 'usuario_dian');
define('DIAN_PASSWORD', 'contraseña_dian');
```

## Estructura del Proyecto

```
/
├── config/
│   ├── database.php          # Configuración de base de datos
│   └── dian.php              # Configuración DIAN
├── core/
│   ├── Database.php          # Clase para manejo de base de datos (PDO)
│   ├── Model.php             # Clase base para modelos
│   ├── Router.php            # Sistema de enrutamiento
│   ├── Response.php          # Manejo de respuestas JSON
│   └── Auth.php              # Sistema de autenticación
├── models/                   # Modelos de datos
│   ├── User.php
│   ├── Company.php
│   ├── ElectronicInvoice.php
│   └── ...
├── controllers/              # Controladores de API
│   ├── AuthController.php
│   ├── ElectronicInvoiceController.php
│   └── ...
├── services/                 # Servicios de negocio
│   ├── InvoiceService.php
│   ├── DianSimulatorService.php
│   ├── DianRealService.php
│   ├── TaxCalculationService.php
│   ├── XsdValidationService.php
│   ├── WebhookService.php
│   └── TemplateService.php
├── middleware/               # Middleware
│   └── auth.php
├── templates/                # Plantillas de facturas
│   └── invoices/
│       └── default.html
├── public/                   # Archivos públicos
│   ├── index.html            # Interfaz de usuario
│   ├── js/
│   │   ├── api.js            # Cliente API
│   │   └── app.js            # Lógica de aplicación
│   └── docs.html             # Documentación
├── index.php                 # Punto de entrada
├── database.sql              # Esquema de base de datos
└── README.md                 # Este archivo
```

## API Endpoints

### Autenticación

- `POST /api/register` - Registro de empresa y usuario
- `POST /api/login` - Inicio de sesión
- `POST /api/logout` - Cerrar sesión
- `GET /api/me` - Obtener usuario actual
- `PUT /api/completeRegistration` - Completar registro

### Facturas Electrónicas

- `GET /api/invoices` - Listar facturas
- `POST /api/invoices` - Crear factura
- `GET /api/invoices/{id}` - Obtener factura
- `PUT /api/invoices/{id}` - Actualizar factura
- `DELETE /api/invoices/{id}` - Eliminar factura
- `POST /api/invoices/{id}/send-dian` - Enviar factura a DIAN
- `GET /api/invoices/{id}/status` - Consultar estado en DIAN
- `POST /api/invoices/{id}/cancel` - Cancelar factura
- `GET /api/invoices/{id}/qr` - Obtener URL del código QR
- `GET /api/invoices/{id}/download/xml` - Descargar XML
- `GET /api/invoices/{id}/preview/template` - Vista previa con plantilla
- `GET /api/invoices/create/data` - Datos para crear factura
- `GET /api/invoices/clients` - Listar clientes
- `GET /api/invoices/stats/summary` - Estadísticas

### Notas Crédito y Débito

- `POST /api/invoices/{id}/notes` - Crear nota
- `GET /api/invoices/{id}/notes` - Listar notas
- `POST /api/invoices/{id}/notes/annul` - Anular con nota crédito

### Productos y Servicios

- `GET /api/products` - Listar productos
- `POST /api/products` - Crear producto
- `GET /api/products/{id}` - Obtener producto
- `PUT /api/products/{id}` - Actualizar producto
- `DELETE /api/products/{id}` - Eliminar producto
- `GET /api/products/active` - Productos activos

- `GET /api/services` - Listar servicios
- `POST /api/services` - Crear servicio
- `GET /api/services/{id}` - Obtener servicio
- `PUT /api/services/{id}` - Actualizar servicio
- `DELETE /api/services/{id}` - Eliminar servicio
- `GET /api/services/active` - Servicios activos

### Tipos de Factura y Regímenes

- `GET /api/invoice-types` - Lista tipos de factura DIAN
- `GET /api/tax-regimes` - Lista regímenes tributarios
- `POST /api/tax-preview` - Vista previa de cálculo de impuestos
- `POST /api/validate-invoice-type` - Validar configuración
- `GET /api/sector-info` - Información del sector según CIIU
- `GET /api/validate-sector` - Validar configuración del sector

### Webhooks

- `GET /api/webhooks` - Listar webhooks
- `POST /api/webhooks` - Crear webhook
- `PUT /api/webhooks/{id}` - Actualizar webhook
- `DELETE /api/webhooks/{id}` - Eliminar webhook
- `GET /api/webhooks/{id}/logs` - Ver logs de webhook

### Plantillas

- `GET /api/templates` - Listar plantillas
- `POST /api/templates` - Crear plantilla
- `PUT /api/templates/{id}` - Actualizar plantilla
- `DELETE /api/templates/{id}` - Eliminar plantilla
- `GET /api/templates/{id}/preview?invoice_id={id}` - Preview de plantilla

## Tipos de Documento por Sector

El sistema determina automáticamente el tipo de documento según el código CIIU de la empresa:

- Comercio al por Menor (47): Tiquete POS
- Restaurantes (56): Tiquete de alimentos
- Transporte (49-51): Documento equivalente de transporte
- Servicios Públicos (35-37): Documento equivalente de servicios
- Telecomunicaciones (60): Documento equivalente
- Otros sectores: Factura electrónica de venta estándar

## Cálculo de Impuestos

El sistema calcula automáticamente los impuestos según:

- Régimen tributario de la empresa (Simplificado, Común, Gran Contribuyente, etc.)
- Tipo de factura (exportación no aplica IVA)
- Sector económico (CIIU)
- Tipo de comprador (persona natural, jurídica, no responsable)
- Productos o servicios con impuestos asociados

## Validación XSD

El sistema valida todos los documentos XML contra los esquemas XSD oficiales de UBL 2.1 antes de enviarlos a DIAN, asegurando conformidad con los estándares.

## Webhooks

Sistema de notificaciones HTTP POST para eventos:

- `invoice.created` - Factura creada
- `invoice.accepted` - Factura aceptada por DIAN
- `invoice.rejected` - Factura rechazada por DIAN
- `invoice.cancelled` - Factura cancelada
- `certificate.expiring` - Certificado próximo a vencer
- `certificate.expired` - Certificado vencido

## Plantillas de Factura

Sistema de plantillas HTML personalizables con:

- Variables dinámicas para datos de factura
- CSS personalizado por plantilla
- Plantilla predeterminada por empresa
- Preview en tiempo real

## Autenticación

El sistema utiliza autenticación por tokens Bearer. Todas las peticiones a endpoints protegidos deben incluir el header:

```
Authorization: Bearer {token}
```

El token se obtiene mediante el endpoint `/api/login` y tiene una duración configurable.

## Respuestas de la API

Todas las respuestas son en formato JSON:

**Éxito:**
```json
{
    "success": true,
    "message": "Operación exitosa",
    "data": { ... }
}
```

**Error:**
```json
{
    "success": false,
    "message": "Descripción del error",
    "data": { ... }
}
```

## Credenciales de Prueba

Después de ejecutar `database.sql` o `install.php`, se crean usuarios de prueba:

**Administrador:**
- Email: `admin@test.com`
- Password: `password`

**Cliente:**
- Email: `cliente@test.com`
- Password: `password`

## Pruebas

Ejecutar el script de pruebas:

```bash
php test_api.php
```

Este script prueba:
- Login
- Obtención de datos para crear factura
- Creación de factura
- Envío a DIAN
- Obtención de estadísticas

## Documentación Completa

Para documentación detallada, acceder a:

```
http://localhost:8000/docs.html
```

## Licencia

Este proyecto es de uso interno. Todos los derechos reservados.

## Soporte

Para consultas técnicas o problemas, contactar al equipo de desarrollo.

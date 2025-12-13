<?php

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__ . '/public';

// Si es una ruta de API, procesar API directamente
if (strpos($requestUri, '/api/') === 0) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Model.php';
    require_once __DIR__ . '/core/Response.php';
    require_once __DIR__ . '/core/Auth.php';
    require_once __DIR__ . '/core/Router.php';
    
    require_once __DIR__ . '/models/User.php';
    require_once __DIR__ . '/models/Company.php';
    require_once __DIR__ . '/models/Role.php';
    require_once __DIR__ . '/models/ElectronicInvoice.php';
    require_once __DIR__ . '/models/InvoiceDetail.php';
    require_once __DIR__ . '/models/Product.php';
    require_once __DIR__ . '/models/Service.php';
    require_once __DIR__ . '/models/Tax.php';
    require_once __DIR__ . '/models/MeasurementUnit.php';
    require_once __DIR__ . '/models/DianNumbering.php';
    require_once __DIR__ . '/models/DigitalCertificate.php';
    require_once __DIR__ . '/models/ElectronicDocument.php';
    require_once __DIR__ . '/models/CreditDebitNote.php';
    
    require_once __DIR__ . '/controllers/AuthController.php';
    require_once __DIR__ . '/controllers/ElectronicInvoiceController.php';
    require_once __DIR__ . '/controllers/ProductController.php';
    require_once __DIR__ . '/controllers/ServiceController.php';
    require_once __DIR__ . '/controllers/InvoiceTypeController.php';
    require_once __DIR__ . '/controllers/WebhookController.php';
    require_once __DIR__ . '/controllers/TemplateController.php';
    
    require_once __DIR__ . '/middleware/auth.php';
    
    $router = new Router();
    
    $router->post('/api/register', 'AuthController@register');
    $router->post('/api/login', 'AuthController@login');
    
    $router->group('/api', function($router) {
        $router->post('/logout', 'AuthController@logout', ['authMiddleware']);
        $router->get('/me', 'AuthController@me', ['authMiddleware']);
        $router->put('/completeRegistration', 'AuthController@completeRegistration', ['authMiddleware']);
        
        $router->group('/invoices', function($router) {
            $router->get('/', 'ElectronicInvoiceController@index');
            $router->post('/', 'ElectronicInvoiceController@store');
            $router->get('/create/data', 'ElectronicInvoiceController@createData');
            $router->get('/clients', 'ElectronicInvoiceController@getClients');
            $router->get('/stats/summary', 'ElectronicInvoiceController@stats');
            $router->get('/{id}', 'ElectronicInvoiceController@show');
            $router->put('/{id}', 'ElectronicInvoiceController@update');
            $router->delete('/{id}', 'ElectronicInvoiceController@destroy');
            $router->post('/{id}/send-dian', 'ElectronicInvoiceController@sendToDian');
            $router->get('/{id}/status', 'ElectronicInvoiceController@checkStatus');
            $router->post('/{id}/cancel', 'ElectronicInvoiceController@cancel');
            $router->get('/{id}/qr', 'ElectronicInvoiceController@generateQR');
            $router->get('/{id}/download/xml', 'ElectronicInvoiceController@downloadXML');
            $router->get('/{id}/preview/template', 'ElectronicInvoiceController@previewTemplate');
            $router->post('/{id}/notes', 'ElectronicInvoiceController@createNote');
            $router->get('/{id}/notes', 'ElectronicInvoiceController@listNotes');
            $router->post('/{id}/notes/annul', 'ElectronicInvoiceController@annulWithCreditNote');
        }, ['authMiddleware']);
        
        $router->get('/products/active', 'ProductController@active', ['authMiddleware']);
        $router->get('/products', 'ProductController@index', ['authMiddleware']);
        $router->post('/products', 'ProductController@store', ['authMiddleware']);
        $router->get('/products/{id}', 'ProductController@show', ['authMiddleware']);
        $router->put('/products/{id}', 'ProductController@update', ['authMiddleware']);
        $router->delete('/products/{id}', 'ProductController@destroy', ['authMiddleware']);
        
        $router->get('/services/active', 'ServiceController@active', ['authMiddleware']);
        $router->get('/services', 'ServiceController@index', ['authMiddleware']);
        $router->post('/services', 'ServiceController@store', ['authMiddleware']);
        $router->get('/services/{id}', 'ServiceController@show', ['authMiddleware']);
        $router->put('/services/{id}', 'ServiceController@update', ['authMiddleware']);
        $router->delete('/services/{id}', 'ServiceController@destroy', ['authMiddleware']);
        
        $router->get('/invoice-types', 'InvoiceTypeController@getInvoiceTypes', ['authMiddleware']);
        $router->get('/tax-regimes', 'InvoiceTypeController@getTaxRegimes', ['authMiddleware']);
        $router->post('/tax-preview', 'InvoiceTypeController@calculateTaxPreview', ['authMiddleware']);
        $router->post('/validate-invoice-type', 'InvoiceTypeController@validateInvoiceType', ['authMiddleware']);
        $router->get('/sector-info', 'InvoiceTypeController@getSectorInfo', ['authMiddleware']);
        $router->get('/validate-sector', 'InvoiceTypeController@validateSectorConfiguration', ['authMiddleware']);
        
        $router->group('/webhooks', function($router) {
            $router->get('/', 'WebhookController@index');
            $router->post('/', 'WebhookController@store');
            $router->put('/{id}', 'WebhookController@update');
            $router->delete('/{id}', 'WebhookController@destroy');
            $router->get('/{id}/logs', 'WebhookController@logs');
        }, ['authMiddleware']);
        
        $router->group('/templates', function($router) {
            $router->get('/', 'TemplateController@index');
            $router->post('/', 'TemplateController@store');
            $router->put('/{id}', 'TemplateController@update');
            $router->delete('/{id}', 'TemplateController@destroy');
            $router->get('/{id}/preview', 'TemplateController@preview');
        }, ['authMiddleware']);
    }, []);
    
    $router->dispatch();
    exit;
}

// Verificar si es un archivo estático (js, css, imágenes, etc.)
if (preg_match('/\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|html)$/i', $requestUri)) {
    // Intentar diferentes rutas posibles
    $possiblePaths = [
        $publicPath . $requestUri,  // /js/api.js -> public/js/api.js
        $publicPath . str_replace('/public', '', $requestUri),  // /public/js/api.js -> public/js/api.js
        __DIR__ . $requestUri  // /js/api.js -> /js/api.js (raíz)
    ];
    
    // Si la ruta no empieza con /, agregarla
    if (strpos($requestUri, '/') !== 0) {
        $possiblePaths[] = $publicPath . '/' . $requestUri;
    }
    
    $filePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            $filePath = $path;
            break;
        }
    }
    
    if ($filePath) {
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
        exit;
    }
}

// Si es la raíz o archivos HTML, servir index.html
if ($requestUri === '/' || $requestUri === '/index.html' || empty($requestUri) || $requestUri === '/public/' || $requestUri === '/public') {
    $indexPath = $publicPath . '/index.html';
    if (file_exists($indexPath)) {
        header('Content-Type: text/html');
        readfile($indexPath);
        exit;
    }
}

// Si llegamos aquí, devolver 404 JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Ruta no encontrada']);
exit;

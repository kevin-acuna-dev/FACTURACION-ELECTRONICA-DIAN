<?php
/**
 * Script de prueba para la API de Facturación Electrónica
 * Ejecutar: php test_api.php
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Company.php';
require_once __DIR__ . '/models/Role.php';
require_once __DIR__ . '/services/InvoiceService.php';

echo "=== PRUEBA DE API DE FACTURACIÓN ELECTRÓNICA ===\n\n";

try {
    // 1. Verificar conexión a base de datos
    echo "1. Verificando conexión a base de datos...\n";
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result) {
        echo "   ✓ Conexión exitosa\n\n";
    } else {
        throw new Exception("Error de conexión");
    }

    // 2. Verificar que existan roles
    echo "2. Verificando roles...\n";
    $roles = $db->fetchAll("SELECT * FROM roles");
    if (empty($roles)) {
        echo "   ⚠ No hay roles. Creando roles básicos...\n";
        $db->query("INSERT INTO roles (role_name, description) VALUES 
                    ('admin', 'Administrador del sistema'),
                    ('cliente', 'Cliente que recibe facturas'),
                    ('vendedor', 'Vendedor que emite facturas')");
        $roles = $db->fetchAll("SELECT * FROM roles");
    }
    echo "   ✓ Roles verificados: " . count($roles) . " roles\n\n";

    // 3. Verificar que exista empresa
    echo "3. Verificando empresa de prueba...\n";
    $company = $db->fetchOne("SELECT * FROM companies WHERE nit = '900123456-7'");
    if (!$company) {
        echo "   ⚠ Empresa no encontrada. Creando empresa de prueba...\n";
        $db->query("INSERT INTO companies (business_name, nit, trade_name, email, tax_regime, ciiu_code, city, department, country) 
                    VALUES ('Empresa de Prueba S.A.S', '900123456-7', 'Empresa Prueba', 'prueba@empresa.com', 'Común', '6201', 'Bogotá', 'Cundinamarca', 'Colombia')");
        $company = $db->fetchOne("SELECT * FROM companies WHERE nit = '900123456-7'");
    }
    if ($company) {
        echo "   ✓ Empresa encontrada: {$company['business_name']}\n\n";
    } else {
        throw new Exception("No se pudo crear/obtener empresa");
    }

    // 4. Buscar usuario de prueba
    echo "4. Buscando usuario de prueba...\n";
    $testUserData = $db->fetchOne("SELECT * FROM users WHERE email = 'admin@test.com'");
    
    if (!$testUserData) {
        echo "   ⚠ Usuario no encontrado. Creando usuario de prueba...\n";
        $adminRole = $db->fetchOne("SELECT * FROM roles WHERE role_name = 'admin'");
        if (!$adminRole) {
            echo "   ⚠ Rol admin no encontrado. Creando rol admin...\n";
            $db->query("INSERT INTO roles (role_name, description) VALUES ('admin', 'Administrador del sistema')");
            $adminRole = $db->fetchOne("SELECT * FROM roles WHERE role_name = 'admin'");
            if (!$adminRole) {
                throw new Exception("No se pudo crear el rol admin");
            }
        }
        echo "   Creando usuario con rol ID: {$adminRole['id']}\n";
        $db->query("INSERT INTO users (company_id, role_id, first_name, email, password, document_type, document_number, status) 
                    VALUES (?, ?, 'Admin Test', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CC', '1234567890', 'Active')",
                    [$company['id'], $adminRole['id']]);
        $testUserData = $db->fetchOne("SELECT * FROM users WHERE email = 'admin@test.com'");
    }
    
    if ($testUserData) {
        $testUser = new User();
        $testUser->hydrate($testUserData);
        echo "   ✓ Usuario encontrado: {$testUser->email}\n\n";
    } else {
        throw new Exception("No se pudo crear/obtener usuario");
    }

    // 5. Buscar cliente de prueba
    echo "5. Buscando cliente de prueba...\n";
    $buyerData = $db->fetchOne("SELECT * FROM users WHERE email = 'cliente@test.com'");
    
    if (!$buyerData) {
        echo "   ⚠ Cliente no encontrado. Creando cliente de prueba...\n";
        $clienteRole = $db->fetchOne("SELECT * FROM roles WHERE role_name = 'cliente'");
        if (!$clienteRole) {
            echo "   ⚠ Rol cliente no encontrado. Creando rol cliente...\n";
            $db->query("INSERT INTO roles (role_name, description) VALUES ('cliente', 'Cliente que recibe facturas')");
            $clienteRole = $db->fetchOne("SELECT * FROM roles WHERE role_name = 'cliente'");
            if (!$clienteRole) {
                throw new Exception("No se pudo crear el rol cliente");
            }
        }
        $db->query("INSERT INTO users (company_id, role_id, first_name, email, password, document_type, document_number, status) 
                    VALUES (?, ?, 'Cliente Test', 'cliente@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CC', '9876543210', 'Active')",
                    [$company['id'], $clienteRole['id']]);
        $buyerData = $db->fetchOne("SELECT * FROM users WHERE email = 'cliente@test.com'");
    }
    
    if ($buyerData) {
        $buyer = new User();
        $buyer->hydrate($buyerData);
        echo "   ✓ Cliente encontrado: {$buyer->email}\n\n";
    } else {
        throw new Exception("No se pudo crear/obtener cliente");
    }

    // 6. Verificar unidades de medida
    echo "6. Verificando unidades de medida...\n";
    $units = $db->fetchAll("SELECT * FROM measurement_units LIMIT 1");
    if (empty($units)) {
        echo "   ⚠ No hay unidades. Creando unidad básica...\n";
        $db->query("INSERT INTO measurement_units (code, name, description) VALUES ('C62', 'Unidad', 'Unidad de medida estándar')");
        $units = $db->fetchAll("SELECT * FROM measurement_units LIMIT 1");
    }
    echo "   ✓ Unidades verificadas\n\n";

    // 7. Verificar productos
    echo "7. Verificando productos disponibles...\n";
    $products = $db->fetchAll("SELECT * FROM products WHERE company_id = ? LIMIT 2", [$testUser->company_id]);
    if (count($products) > 0) {
        echo "   ✓ Productos encontrados: " . count($products) . "\n";
        foreach ($products as $p) {
            echo "     - {$p['name']}: \${$p['unit_price']}\n";
        }
        echo "\n";
    } else {
        echo "   ⚠ No hay productos. Creando productos de prueba...\n";
        $unit = $db->fetchOne("SELECT * FROM measurement_units WHERE code = 'C62'");
        if ($unit) {
            $db->query("INSERT INTO products (company_id, product_code, name, description, unit_price, measurement_unit_id, status) 
                        VALUES (?, 'TEST-001', 'Producto Prueba', 'Producto de prueba para facturación', 10000.00, ?, 'Active')",
                        [$testUser->company_id, $unit['id']]);
            $products = $db->fetchAll("SELECT * FROM products WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$testUser->company_id]);
            echo "   ✓ Producto creado\n\n";
        }
    }

    // 8. Verificar numeración DIAN
    echo "8. Verificando numeración DIAN...\n";
    $numbering = $db->fetchOne("SELECT * FROM dian_numberings WHERE company_id = ? AND current_status = 'Activo'", [$testUser->company_id]);
    if (!$numbering) {
        echo "   ⚠ No hay numeración DIAN. Creando numeración de prueba...\n";
        $db->query("INSERT INTO dian_numberings (company_id, document_type, prefix, start_number, end_number, validity_start_date, validity_end_date, current_status, resolution_number)
                    VALUES (?, 'Factura', 'FAC', 1, 10000, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Activo', 'RES-2024-001')",
                    [$testUser->company_id]);
        echo "   ✓ Numeración DIAN creada\n\n";
    } else {
        echo "   ✓ Numeración DIAN encontrada\n\n";
    }

    // 9. Verificar certificado digital
    echo "9. Verificando certificado digital...\n";
    $cert = $db->fetchOne("SELECT * FROM digital_certificates WHERE company_id = ? AND status = 'Vigente'", [$testUser->company_id]);
    if (!$cert) {
        echo "   ⚠ No hay certificado digital. Creando certificado de prueba...\n";
        $db->query("INSERT INTO digital_certificates (company_id, certificate_name, serial_number, issuer, start_date, end_date, status, certificate_type, signature_algorithm)
                    VALUES (?, 'Certificado Prueba', 'SN-TEST-123456', 'CN=AC Prueba, O=DIAN, C=CO', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Vigente', 'Firma Digital', 'SHA256withRSA')",
                    [$testUser->company_id]);
        echo "   ✓ Certificado digital creado\n\n";
    } else {
        echo "   ✓ Certificado digital encontrado\n\n";
    }

    // 10. Simular autenticación
    echo "10. Simulando autenticación...\n";
    $token = Auth::createToken($testUser);
    if ($token) {
        echo "   ✓ Token generado: " . substr($token, 0, 20) . "...\n\n";
    }

    // 11. Crear factura de prueba
    echo "11. Creando factura de prueba...\n";
    
    // Obtener productos
    if (empty($products)) {
        $products = $db->fetchAll("SELECT * FROM products WHERE company_id = ? LIMIT 1", [$testUser->company_id]);
    }
    
    if (empty($products)) {
        throw new Exception("No hay productos disponibles para crear la factura");
    }
    
    $product = $products[0];
    
    // Establecer usuario autenticado para el servicio
    Auth::setUser($testUser);
    
    $invoiceService = new InvoiceService();
    
    $invoiceData = [
        'user_id' => $testUser->id,
        'buyer_id' => $buyer->id,
        'items' => [
            [
                'type' => 'product',
                'id' => $product['id'],
                'quantity' => 2,
                'discount' => 0
            ]
        ],
        'invoice_type_code' => '01'
    ];
    
    $result = $invoiceService->createInvoice($invoiceData);
    
    if ($result['success']) {
        echo "   ✓ Factura creada exitosamente!\n";
        echo "     ID: {$result['data']['id']}\n";
        echo "     Número: {$result['data']['invoice_number']}\n";
        echo "     Total: \${$result['data']['payable_amount']}\n\n";
        
        $invoiceId = $result['data']['id'];
        
        // 12. Enviar a DIAN
        echo "12. Enviando factura a DIAN (simulador)...\n";
        $dianResult = $invoiceService->sendToDian($invoiceId);
        
        if ($dianResult['success']) {
            echo "   ✓ Factura enviada a DIAN exitosamente!\n";
            echo "     Estado: {$dianResult['data']['status']}\n";
            if (isset($dianResult['data']['cufe'])) {
                echo "     CUFE: {$dianResult['data']['cufe']}\n";
            }
            if (isset($dianResult['data']['protocol_number'])) {
                echo "     Protocolo: {$dianResult['data']['protocol_number']}\n";
            }
            echo "\n";
        } else {
            echo "   ⚠ Error al enviar a DIAN: {$dianResult['message']}\n";
            if (isset($dianResult['data']['errors'])) {
                foreach ($dianResult['data']['errors'] as $error) {
                    echo "     - {$error}\n";
                }
            }
            echo "\n";
        }
        
        echo "=== PRUEBA COMPLETADA ===\n";
        echo "\n✅ Sistema funcionando correctamente!\n\n";
        echo "Puedes acceder al frontend en: http://localhost:8000\n";
        echo "Credenciales:\n";
        echo "  Email: admin@test.com\n";
        echo "  Password: password\n\n";
        
    } else {
        echo "   ✗ Error al crear factura: {$result['message']}\n";
        throw new Exception("No se pudo crear la factura de prueba");
    }

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    if (isset($e->getTrace()[0])) {
        echo "Archivo: " . $e->getFile() . "\n";
        echo "Línea: " . $e->getLine() . "\n";
    }
    exit(1);
}

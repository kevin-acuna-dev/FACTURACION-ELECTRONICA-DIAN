<?php
/**
 * Script de instalación mejorado
 */

require_once __DIR__ . '/config/database.php';

$config = require __DIR__ . '/config/database.php';

echo "=== Instalación del Sistema de Facturación Electrónica ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Conexión al servidor MySQL exitosa\n";
    
    $dbName = $config['database'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    echo "✓ Base de datos '{$dbName}' lista\n";
    
    // Crear tablas en orden correcto
    $tables = [
        "CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_name VARCHAR(255) NOT NULL,
            nit VARCHAR(50) NOT NULL UNIQUE,
            trade_name VARCHAR(255),
            address TEXT,
            city VARCHAR(100),
            department VARCHAR(100),
            country VARCHAR(100) DEFAULT 'Colombia',
            phone VARCHAR(50),
            email VARCHAR(255),
            tax_regime VARCHAR(50),
            ciiu_code VARCHAR(20),
            logo_url VARCHAR(500),
            legal_representative_name VARCHAR(255),
            legal_representative_document_type VARCHAR(10),
            legal_representative_document_number VARCHAR(50),
            pos_number VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT,
            role_id INT,
            first_name VARCHAR(255) NOT NULL,
            document_type VARCHAR(10) NOT NULL,
            document_number VARCHAR(50) NOT NULL,
            address TEXT,
            country VARCHAR(100) DEFAULT 'Colombia',
            description TEXT,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            phone VARCHAR(50),
            status VARCHAR(20) DEFAULT 'Active',
            last_access TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            INDEX idx_role_id (role_id),
            INDEX idx_email (email),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS personal_access_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS measurement_units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS taxes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            percentage DECIMAL(5,2) DEFAULT 0,
            fixed_value DECIMAL(15,2) DEFAULT 0,
            application_type VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'Activo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            product_code VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            measurement_unit_id INT,
            status VARCHAR(20) DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            INDEX idx_status (status),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (measurement_unit_id) REFERENCES measurement_units(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            service_code VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            measurement_unit_id INT,
            status VARCHAR(20) DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            INDEX idx_status (status),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (measurement_unit_id) REFERENCES measurement_units(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS product_tax (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            tax_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product_tax (product_id, tax_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS service_tax (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            tax_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_service_tax (service_id, tax_id),
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS dian_numberings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            prefix VARCHAR(50) NOT NULL,
            start_number INT NOT NULL,
            end_number INT NOT NULL,
            current_number INT NOT NULL,
            validity_start_date DATE NOT NULL,
            validity_end_date DATE NOT NULL,
            resolution_number VARCHAR(100),
            current_status VARCHAR(20) DEFAULT 'Activo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            INDEX idx_status (current_status),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS digital_certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            certificate_name VARCHAR(255) NOT NULL,
            serial_number VARCHAR(255) NOT NULL,
            issuer VARCHAR(500),
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            certificate_type VARCHAR(50),
            signature_algorithm VARCHAR(50),
            status VARCHAR(20) DEFAULT 'Vigente',
            certificate_data LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            INDEX idx_status (status),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS electronic_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            buyer_id INT NOT NULL,
            invoice_number VARCHAR(100) NOT NULL,
            issue_date DATETIME NOT NULL,
            internal_status VARCHAR(20) DEFAULT 'draft',
            observation TEXT,
            ubl_version VARCHAR(10) DEFAULT '2.1',
            customization_id VARCHAR(255),
            profile_id VARCHAR(50),
            uuid VARCHAR(255),
            document_currency_code VARCHAR(3) DEFAULT 'COP',
            invoice_type_code VARCHAR(2) DEFAULT '01',
            line_extension_amount DECIMAL(15,2) DEFAULT 0,
            tax_exclusive_amount DECIMAL(15,2) DEFAULT 0,
            tax_inclusive_amount DECIMAL(15,2) DEFAULT 0,
            payable_amount DECIMAL(15,2) DEFAULT 0,
            total_discount DECIMAL(15,2) DEFAULT 0,
            dian_status VARCHAR(20) DEFAULT 'pending',
            sent_at TIMESTAMP NULL,
            received_at TIMESTAMP NULL,
            payment_means_code VARCHAR(10),
            payment_terms TEXT,
            payment_means_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_buyer_id (buyer_id),
            INDEX idx_invoice_number (invoice_number),
            INDEX idx_dian_status (dian_status),
            INDEX idx_internal_status (internal_status),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (buyer_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS invoice_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            electronic_invoice_id INT NOT NULL,
            item_id INT NOT NULL,
            item_type VARCHAR(255) NOT NULL,
            description TEXT,
            quantity DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL,
            line_extension_amount DECIMAL(15,2) NOT NULL,
            discount_amount DECIMAL(15,2) DEFAULT 0,
            tax_amount DECIMAL(15,2) DEFAULT 0,
            total_line_amount DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_invoice_id (electronic_invoice_id),
            INDEX idx_item_id (item_id),
            FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS electronic_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            electronic_invoice_id INT,
            credit_debit_note_id INT,
            dian_numbering_id INT,
            cufe VARCHAR(255),
            cude VARCHAR(255),
            xml_document LONGTEXT,
            dian_status VARCHAR(50),
            validation_date TIMESTAMP NULL,
            digital_signature TEXT,
            document_hash VARCHAR(255),
            description TEXT,
            environment VARCHAR(50),
            document_type VARCHAR(100),
            qr_code TEXT,
            cdr TEXT,
            emission_mode VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_invoice_id (electronic_invoice_id),
            INDEX idx_cufe (cufe),
            FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS dian_status_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            electronic_document_id INT,
            status_code VARCHAR(10),
            status_description VARCHAR(255),
            status_message TEXT,
            response_xml LONGTEXT,
            protocol_number VARCHAR(100),
            received_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_document_id (electronic_document_id),
            FOREIGN KEY (electronic_document_id) REFERENCES electronic_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS credit_debit_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            electronic_invoice_id INT NOT NULL,
            reason TEXT NOT NULL,
            note_type VARCHAR(20) NOT NULL,
            note_number VARCHAR(100) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            issue_date DATETIME NOT NULL,
            total_amount DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_invoice_id (electronic_invoice_id),
            INDEX idx_note_type (note_type),
            FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $i => $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Tabla " . ($i + 1) . " creada\n";
        } catch (PDOException $e) {
            echo "⚠ Error en tabla " . ($i + 1) . ": " . $e->getMessage() . "\n";
        }
    }
    
    // Insertar datos iniciales
    $pdo->exec("INSERT IGNORE INTO roles (id, role_name, description) VALUES
        (1, 'administrador', 'Administrador del sistema'),
        (2, 'usuario', 'Usuario estándar'),
        (3, 'cliente', 'Cliente de la empresa')");
    
    $pdo->exec("INSERT IGNORE INTO measurement_units (id, code, name, description) VALUES
        (1, 'C62', 'Unidad', 'Unidad de medida estándar'),
        (2, 'MTR', 'Metro', 'Unidad de longitud'),
        (3, 'KGM', 'Kilogramo', 'Unidad de masa'),
        (4, 'LTR', 'Litro', 'Unidad de volumen'),
        (5, 'HUR', 'Hora', 'Unidad de tiempo')");
    
    $pdo->exec("INSERT IGNORE INTO companies (id, business_name, nit, trade_name, address, city, department, country, phone, email, tax_regime, ciiu_code) VALUES
        (1, 'Empresa de Prueba S.A.S', '900123456-7', 'Empresa Prueba', 'Calle 123 #45-67', 'Bogotá', 'Cundinamarca', 'Colombia', '6012345678', 'prueba@empresa.com', 'Común', '6201')");
    
    $pdo->exec("INSERT IGNORE INTO users (id, company_id, role_id, first_name, document_type, document_number, email, password, status) VALUES
        (1, 1, 1, 'Admin Prueba', 'CC', '1234567890', 'admin@prueba.com', '" . password_hash('password', PASSWORD_BCRYPT) . "', 'Active'),
        (2, 1, 3, 'Cliente Prueba', 'CC', '9876543210', 'cliente@prueba.com', '" . password_hash('password', PASSWORD_BCRYPT) . "', 'Active')");
    
    $pdo->exec("INSERT IGNORE INTO dian_numberings (id, company_id, document_type, prefix, start_number, end_number, current_number, validity_start_date, validity_end_date, resolution_number, current_status) VALUES
        (1, 1, 'Factura', 'FAC', 1, 999999, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'RES-2024-001', 'Activo')");
    
    $pdo->exec("INSERT IGNORE INTO digital_certificates (id, company_id, certificate_name, serial_number, issuer, start_date, end_date, certificate_type, signature_algorithm, status) VALUES
        (1, 1, 'Certificado Prueba', 'SN123456789', 'AC Prueba', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Firma', 'SHA256withRSA', 'Vigente')");
    
    $pdo->exec("INSERT IGNORE INTO taxes (id, company_id, name, type, percentage, application_type, status) VALUES
        (1, 1, 'IVA', 'IVA', 19.00, 'Porcentaje', 'Activo')");
    
    $pdo->exec("INSERT IGNORE INTO products (id, company_id, product_code, name, description, unit_price, measurement_unit_id, status) VALUES
        (1, 1, 'PROD-001', 'Producto de Prueba', 'Descripción del producto de prueba', 100000.00, 1, 'Active')");
    
    $pdo->exec("INSERT IGNORE INTO services (id, company_id, service_code, name, description, unit_price, measurement_unit_id, status) VALUES
        (1, 1, 'SERV-001', 'Servicio de Prueba', 'Descripción del servicio de prueba', 50000.00, 1, 'Active')");
    
    echo "\n✓ Datos iniciales insertados\n";
    
    // Verificar
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
    $companyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "\n=== Resumen ===\n";
    echo "Empresas: {$companyCount}\n";
    echo "Usuarios: {$userCount}\n";
    
    echo "\n✓ Instalación completada exitosamente!\n\n";
    echo "Credenciales de prueba:\n";
    echo "  Email: admin@prueba.com\n";
    echo "  Password: password\n\n";
    echo "Cliente de prueba:\n";
    echo "  Email: cliente@prueba.com\n";
    echo "  Password: password\n\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}


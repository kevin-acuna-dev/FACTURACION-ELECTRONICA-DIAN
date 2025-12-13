-- Base de datos para Facturación Electrónica DIAN
CREATE DATABASE IF NOT EXISTS facturacion_electronica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE facturacion_electronica;

-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de empresas
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    nit VARCHAR(50) NOT NULL UNIQUE,
    trade_name VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    department VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Colombia',
    phone VARCHAR(20),
    email VARCHAR(255),
    tax_regime VARCHAR(50),
    ciiu_code VARCHAR(10),
    logo_url VARCHAR(500),
    legal_representative_name VARCHAR(255),
    legal_representative_document_type VARCHAR(10),
    legal_representative_document_number VARCHAR(50),
    pos_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    role_id INT,
    first_name VARCHAR(255) NOT NULL,
    document_type VARCHAR(10) DEFAULT 'CC',
    document_number VARCHAR(50) NOT NULL,
    address TEXT,
    country VARCHAR(100) DEFAULT 'Colombia',
    description TEXT,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    status VARCHAR(20) DEFAULT 'Active',
    last_access TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_company (company_id),
    INDEX idx_email (email)
);

-- Tabla de tokens de autenticación
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
);

-- Tabla de unidades de medida
CREATE TABLE IF NOT EXISTS measurement_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de impuestos
CREATE TABLE IF NOT EXISTS taxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    fixed_value DECIMAL(10,2) DEFAULT 0.00,
    application_type VARCHAR(50) DEFAULT 'Porcentaje',
    status VARCHAR(20) DEFAULT 'Activo',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de productos
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    product_code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    measurement_unit_id INT,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (measurement_unit_id) REFERENCES measurement_units(id),
    INDEX idx_company (company_id)
);

-- Tabla de servicios
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    service_code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    measurement_unit_id INT,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (measurement_unit_id) REFERENCES measurement_units(id),
    INDEX idx_company (company_id)
);

-- Tabla de relación producto-impuesto
CREATE TABLE IF NOT EXISTS product_tax (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    tax_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_tax (product_id, tax_id)
);

-- Tabla de relación servicio-impuesto
CREATE TABLE IF NOT EXISTS service_tax (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    tax_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_service_tax (service_id, tax_id)
);

-- Tabla de numeración DIAN
CREATE TABLE IF NOT EXISTS dian_numberings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    prefix VARCHAR(50) NOT NULL,
    start_number INT NOT NULL,
    end_number INT NOT NULL,
    current_number INT DEFAULT 0,
    validity_start_date DATE NOT NULL,
    validity_end_date DATE NOT NULL,
    current_status VARCHAR(20) DEFAULT 'Activo',
    resolution_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id)
);

-- Tabla de certificados digitales
CREATE TABLE IF NOT EXISTS digital_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    certificate_name VARCHAR(255) NOT NULL,
    serial_number VARCHAR(255) NOT NULL,
    issuer VARCHAR(500),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'Vigente',
    certificate_type VARCHAR(50),
    signature_algorithm VARCHAR(50) DEFAULT 'SHA256withRSA',
    certificate_data LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id)
);

-- Tabla de facturas electrónicas
CREATE TABLE IF NOT EXISTS electronic_invoices (
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
    document_currency_code VARCHAR(3) DEFAULT 'COP',
    invoice_type_code VARCHAR(10) DEFAULT '01',
    payment_means_code VARCHAR(10) DEFAULT '10',
    payment_means_name VARCHAR(100),
    line_extension_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_exclusive_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_inclusive_amount DECIMAL(10,2) DEFAULT 0.00,
    payable_amount DECIMAL(10,2) DEFAULT 0.00,
    dian_status VARCHAR(20) DEFAULT 'pending',
    uuid VARCHAR(255),
    sent_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_dian_status (dian_status)
);

-- Tabla de detalles de factura
CREATE TABLE IF NOT EXISTS invoice_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    electronic_invoice_id INT NOT NULL,
    item_id INT NOT NULL,
    item_type VARCHAR(255) NOT NULL,
    description TEXT,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_extension_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total_line_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (electronic_invoice_id)
);

-- Tabla de documentos electrónicos
CREATE TABLE IF NOT EXISTS electronic_documents (
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
    environment VARCHAR(50) DEFAULT 'HABILITACION',
    document_type VARCHAR(100),
    qr_code TEXT,
    cdr TEXT,
    emission_mode VARCHAR(50) DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (electronic_invoice_id),
    INDEX idx_cufe (cufe)
);

-- Tabla de respuestas de estado DIAN
CREATE TABLE IF NOT EXISTS dian_status_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    electronic_document_id INT NOT NULL,
    status_code VARCHAR(10),
    status_description VARCHAR(255),
    status_message TEXT,
    response_xml TEXT,
    protocol_number VARCHAR(100),
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (electronic_document_id) REFERENCES electronic_documents(id) ON DELETE CASCADE
);

-- Tabla para logs de comunicación con DIAN
CREATE TABLE IF NOT EXISTS dian_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    electronic_invoice_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    response_data TEXT,
    error_message TEXT,
    http_code INT,
    attempt_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (electronic_invoice_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Tabla para webhooks
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255) NOT NULL,
    headers TEXT,
    status VARCHAR(20) DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_event_type (event_type),
    INDEX idx_status (status)
);

-- Tabla para logs de webhooks
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    success TINYINT(1) DEFAULT 0,
    http_code INT,
    response_body TEXT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_id (webhook_id),
    INDEX idx_event_type (event_type),
    INDEX idx_success (success),
    INDEX idx_created_at (created_at)
);

-- Tabla para plantillas de factura
CREATE TABLE IF NOT EXISTS invoice_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    html_content TEXT NOT NULL,
    css_content TEXT,
    is_default TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_status (status),
    INDEX idx_is_default (is_default)
);

-- Tabla de notas crédito y débito
CREATE TABLE IF NOT EXISTS credit_debit_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    electronic_invoice_id INT NOT NULL,
    reason TEXT NOT NULL,
    note_type VARCHAR(20) NOT NULL,
    note_number VARCHAR(100) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    issue_date DATETIME NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (electronic_invoice_id) REFERENCES electronic_invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (electronic_invoice_id)
);

-- Insertar datos iniciales
INSERT INTO roles (role_name, description) VALUES
('admin', 'Administrador del sistema'),
('cliente', 'Cliente que recibe facturas'),
('vendedor', 'Vendedor que emite facturas')
ON DUPLICATE KEY UPDATE role_name=role_name;

INSERT INTO measurement_units (code, name, description) VALUES
('C62', 'Unidad', 'Unidad de medida estándar'),
('MTR', 'Metro', 'Unidad de longitud'),
('KGM', 'Kilogramo', 'Unidad de masa'),
('LTR', 'Litro', 'Unidad de volumen'),
('HUR', 'Hora', 'Unidad de tiempo')
ON DUPLICATE KEY UPDATE code=code;

INSERT INTO taxes (name, type, percentage, application_type, status) VALUES
('IVA', 'IVA', 19.00, 'Porcentaje', 'Activo'),
('Retención en la Fuente', 'Retención', 3.50, 'Porcentaje', 'Activo')
ON DUPLICATE KEY UPDATE name=name;

-- Crear empresa de prueba
INSERT INTO companies (business_name, nit, trade_name, email, tax_regime, ciiu_code, city, department, country) VALUES
('Empresa de Prueba S.A.S', '900123456-7', 'Empresa Prueba', 'prueba@empresa.com', 'Común', '6201', 'Bogotá', 'Cundinamarca', 'Colombia')
ON DUPLICATE KEY UPDATE business_name=business_name;

-- Crear usuario admin de prueba
INSERT INTO users (company_id, role_id, first_name, document_type, document_number, email, password, status) 
SELECT 
    c.id,
    r.id,
    'Usuario Admin',
    'CC',
    '1234567890',
    'admin@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Active'
FROM companies c, roles r
WHERE c.nit = '900123456-7' AND r.role_name = 'admin'
ON DUPLICATE KEY UPDATE email=email;

-- Crear cliente de prueba
INSERT INTO users (company_id, role_id, first_name, document_type, document_number, email, password, status) 
SELECT 
    c.id,
    r.id,
    'Cliente Prueba',
    'CC',
    '9876543210',
    'cliente@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Active'
FROM companies c, roles r
WHERE c.nit = '900123456-7' AND r.role_name = 'cliente'
ON DUPLICATE KEY UPDATE email=email;

-- Crear numeración DIAN de prueba
INSERT INTO dian_numberings (company_id, document_type, prefix, start_number, end_number, validity_start_date, validity_end_date, current_status, resolution_number)
SELECT 
    c.id,
    'Factura',
    'FAC',
    1,
    10000,
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
    'Activo',
    'RES-2024-001'
FROM companies c
WHERE c.nit = '900123456-7'
ON DUPLICATE KEY UPDATE document_type=document_type;

-- Crear certificado digital de prueba
INSERT INTO digital_certificates (company_id, certificate_name, serial_number, issuer, start_date, end_date, status, certificate_type, signature_algorithm)
SELECT 
    c.id,
    'Certificado Prueba',
    'SN-TEST-123456',
    'CN=AC Prueba, O=DIAN, C=CO',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
    'Vigente',
    'Firma Digital',
    'SHA256withRSA'
FROM companies c
WHERE c.nit = '900123456-7'
ON DUPLICATE KEY UPDATE certificate_name=certificate_name;

-- Crear productos de prueba
INSERT INTO products (company_id, product_code, name, description, unit_price, measurement_unit_id, status)
SELECT 
    c.id,
    'PROD-001',
    'Producto de Prueba 1',
    'Descripción del producto de prueba',
    10000.00,
    mu.id,
    'Active'
FROM companies c, measurement_units mu
WHERE c.nit = '900123456-7' AND mu.code = 'C62'
ON DUPLICATE KEY UPDATE product_code=product_code;

INSERT INTO products (company_id, product_code, name, description, unit_price, measurement_unit_id, status)
SELECT 
    c.id,
    'PROD-002',
    'Producto de Prueba 2',
    'Otro producto de prueba',
    25000.00,
    mu.id,
    'Active'
FROM companies c, measurement_units mu
WHERE c.nit = '900123456-7' AND mu.code = 'C62'
ON DUPLICATE KEY UPDATE product_code=product_code;

-- Crear servicios de prueba
INSERT INTO services (company_id, service_code, name, description, unit_price, measurement_unit_id, status)
SELECT 
    c.id,
    'SERV-001',
    'Servicio de Prueba 1',
    'Descripción del servicio de prueba',
    50000.00,
    mu.id,
    'Active'
FROM companies c, measurement_units mu
WHERE c.nit = '900123456-7' AND mu.code = 'HUR'
ON DUPLICATE KEY UPDATE service_code=service_code;

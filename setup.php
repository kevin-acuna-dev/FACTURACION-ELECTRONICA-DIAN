<?php
/**
 * Script de configuración inicial
 * Crea la base de datos y datos de prueba
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

$config = require __DIR__ . '/config/database.php';

echo "=== Configuración del Sistema de Facturación Electrónica ===\n\n";

// Conectar sin base de datos para crearla
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Conexión al servidor MySQL exitosa\n";
    
    // Crear base de datos
    $dbName = $config['database'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Base de datos '{$dbName}' creada o ya existe\n";
    
        // Leer y ejecutar script SQL
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            
            // Usar la base de datos
            $pdo->exec("USE `{$dbName}`");
            
            // Dividir en statements, ignorando comentarios y líneas vacías
            $lines = explode("\n", $sql);
            $currentStatement = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar comentarios y líneas vacías
                if (empty($line) || preg_match('/^--/', $line) || 
                    preg_match('/^CREATE DATABASE/i', $line) || 
                    preg_match('/^USE/i', $line)) {
                    continue;
                }
                
                $currentStatement .= $line . "\n";
                
                // Si la línea termina con ;, ejecutar el statement
                if (substr(rtrim($line), -1) === ';') {
                    $stmt = trim($currentStatement);
                    if (!empty($stmt)) {
                        try {
                            $pdo->exec($stmt);
                        } catch (PDOException $e) {
                            // Ignorar errores de "ya existe" o "duplicado"
                            $errorMsg = $e->getMessage();
                            if (strpos($errorMsg, 'already exists') === false && 
                                strpos($errorMsg, 'Duplicate') === false &&
                                strpos($errorMsg, 'Duplicate entry') === false) {
                                echo "⚠ Advertencia en: " . substr($stmt, 0, 50) . "...\n";
                                echo "   " . $errorMsg . "\n";
                            }
                        }
                    }
                    $currentStatement = '';
                }
            }
            
            echo "✓ Tablas creadas exitosamente\n";
            echo "✓ Datos de prueba insertados\n";
        } else {
            echo "⚠ Archivo database.sql no encontrado\n";
        }
    
    // Verificar datos
    $pdo->exec("USE `{$dbName}`");
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
    $companyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "\n=== Resumen ===\n";
    echo "Empresas: {$companyCount}\n";
    echo "Usuarios: {$userCount}\n";
    
    echo "\n✓ Configuración completada exitosamente!\n\n";
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


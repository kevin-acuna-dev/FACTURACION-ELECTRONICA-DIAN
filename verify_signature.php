<?php
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();
$doc = $db->fetchOne('SELECT id, digital_signature, xml_document FROM electronic_documents ORDER BY id DESC LIMIT 1');

if ($doc) {
    echo "Documento ID: {$doc['id']}\n";
    echo "Firma guardada: " . (empty($doc['digital_signature']) ? 'NO' : 'SI') . "\n";
    echo "XML tiene firma: " . (strpos($doc['xml_document'], 'Signature') !== false ? 'SI' : 'NO') . "\n";
    echo "XML tiene UBLExtensions: " . (strpos($doc['xml_document'], 'UBLExtensions') !== false ? 'SI' : 'NO') . "\n";
    echo "Longitud XML: " . strlen($doc['xml_document']) . " caracteres\n";
    
    if (!empty($doc['digital_signature'])) {
        $sigData = json_decode($doc['digital_signature'], true);
        if ($sigData) {
            echo "\nInformación de Firma:\n";
            echo "  - Fecha de firma: " . ($sigData['signed_at'] ?? 'N/A') . "\n";
            echo "  - Algoritmo: " . ($sigData['certificate_info']['algorithm'] ?? 'N/A') . "\n";
            echo "  - Serial del certificado: " . ($sigData['certificate_info']['serial_number'] ?? 'N/A') . "\n";
        }
    }
} else {
    echo "No hay documentos\n";
}


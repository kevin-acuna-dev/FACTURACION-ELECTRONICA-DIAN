<?php

require_once __DIR__ . '/../core/Database.php';

class XsdValidationService {
    private $db;
    private $xsdSchemasPath;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->xsdSchemasPath = __DIR__ . '/../schemas/xsd/';
        
        if (!is_dir($this->xsdSchemasPath)) {
            @mkdir($this->xsdSchemasPath, 0755, true);
        }
    }
    
    public function validateInvoiceXML($xmlContent) {
        $errors = [];
        $warnings = [];
        
        if (empty($xmlContent)) {
            return [
                'valid' => false,
                'errors' => ['El XML está vacío'],
                'warnings' => []
            ];
        }
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        if (!@$dom->loadXML($xmlContent)) {
            $xmlErrors = libxml_get_errors();
            foreach ($xmlErrors as $error) {
                $errors[] = "Error XML: " . trim($error->message) . " (Línea {$error->line})";
            }
            libxml_clear_errors();
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => []
            ];
        }
        
        $xsdFile = $this->getXsdSchemaPath();
        
        if ($xsdFile && file_exists($xsdFile)) {
            $valid = @$dom->schemaValidate($xsdFile);
            
            if (!$valid) {
                $xmlErrors = libxml_get_errors();
                foreach ($xmlErrors as $error) {
                    $errorMsg = trim($error->message);
                    $line = $error->line;
                    $level = $error->level;
                    
                    if ($level === LIBXML_ERR_WARNING) {
                        $warnings[] = "Advertencia (Línea {$line}): {$errorMsg}";
                    } else {
                        $errors[] = "Error XSD (Línea {$line}): {$errorMsg}";
                    }
                }
                libxml_clear_errors();
            }
        } else {
            $warnings[] = 'No se encontró el archivo XSD. Validación estructural básica únicamente.';
        }
        
        $structuralValidation = $this->validateStructure($dom);
        if (!$structuralValidation['valid']) {
            $errors = array_merge($errors, $structuralValidation['errors']);
        }
        
        libxml_clear_errors();
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'xsd_validated' => $xsdFile && file_exists($xsdFile)
        ];
    }
    
    private function validateStructure($dom) {
        $errors = [];
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        
        $requiredElements = [
            'Invoice' => '//ubl:Invoice | //Invoice',
            'UBLVersionID' => '//cbc:UBLVersionID',
            'CustomizationID' => '//cbc:CustomizationID',
            'ProfileID' => '//cbc:ProfileID',
            'ID' => '//cbc:ID',
            'UUID' => '//cbc:UUID',
            'IssueDate' => '//cbc:IssueDate',
            'IssueTime' => '//cbc:IssueTime',
            'InvoiceTypeCode' => '//cbc:InvoiceTypeCode',
            'DocumentCurrencyCode' => '//cbc:DocumentCurrencyCode',
            'AccountingSupplierParty' => '//cac:AccountingSupplierParty',
            'AccountingCustomerParty' => '//cac:AccountingCustomerParty',
            'LegalMonetaryTotal' => '//cac:LegalMonetaryTotal'
        ];
        
        foreach ($requiredElements as $element => $xpathQuery) {
            $nodes = $xpath->query($xpathQuery);
            if ($nodes->length === 0) {
                $errors[] = "Falta el elemento requerido: {$element}";
            }
        }
        
        $invoiceNodes = $xpath->query('//ubl:Invoice | //Invoice');
        if ($invoiceNodes->length > 0) {
            $invoiceNode = $invoiceNodes->item(0);
            
            $uuidNodes = $xpath->query('//cbc:UUID', $invoiceNode);
            if ($uuidNodes->length > 0) {
                $uuid = $uuidNodes->item(0)->nodeValue;
                if (empty($uuid) || strlen($uuid) < 10) {
                    $errors[] = 'El UUID/CUFE debe tener al menos 10 caracteres';
                }
            }
            
            $idNodes = $xpath->query('//cbc:ID', $invoiceNode);
            if ($idNodes->length > 0) {
                $id = $idNodes->item(0)->nodeValue;
                if (empty($id)) {
                    $errors[] = 'El ID de la factura no puede estar vacío';
                }
            }
            
            $issueDateNodes = $xpath->query('//cbc:IssueDate', $invoiceNode);
            if ($issueDateNodes->length > 0) {
                $issueDate = $issueDateNodes->item(0)->nodeValue;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
                    $errors[] = 'La fecha de emisión debe estar en formato YYYY-MM-DD';
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function getXsdSchemaPath() {
        $possiblePaths = [
            $this->xsdSchemasPath . 'UBL-Invoice-2.1.xsd',
            $this->xsdSchemasPath . 'Invoice-2.1.xsd',
            __DIR__ . '/../schemas/UBL-Invoice-2.1.xsd',
            __DIR__ . '/../schemas/Invoice-2.1.xsd'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    public function downloadXsdSchemas() {
        $schemas = [
            'UBL-Invoice-2.1.xsd' => 'https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd',
            'UBL-CommonAggregateComponents-2.1.xsd' => 'https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/common/UBL-CommonAggregateComponents-2.1.xsd',
            'UBL-CommonBasicComponents-2.1.xsd' => 'https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/common/UBL-CommonBasicComponents-2.1.xsd'
        ];
        
        $downloaded = [];
        $errors = [];
        
        foreach ($schemas as $filename => $url) {
            $filepath = $this->xsdSchemasPath . $filename;
            
            if (file_exists($filepath)) {
                $downloaded[] = $filename . ' (ya existe)';
                continue;
            }
            
            $content = @file_get_contents($url);
            if ($content !== false) {
                if (file_put_contents($filepath, $content)) {
                    $downloaded[] = $filename;
                } else {
                    $errors[] = "No se pudo guardar {$filename}";
                }
            } else {
                $errors[] = "No se pudo descargar {$filename} desde {$url}";
            }
        }
        
        return [
            'success' => empty($errors),
            'downloaded' => $downloaded,
            'errors' => $errors
        ];
    }
}


<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../models/ElectronicInvoice.php';
require_once __DIR__ . '/../models/ElectronicDocument.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/DigitalSignatureService.php';

class DianSimulatorService {
    private $signatureService;
    private $db;
    
    public function __construct() {
        $this->signatureService = new DigitalSignatureService();
        $this->db = Database::getInstance();
    }
    
    public function generateCUFE($invoice) {
        if (!($invoice instanceof ElectronicInvoice)) {
            throw new Exception('El parámetro invoice debe ser una instancia de ElectronicInvoice');
        }
        $user = $invoice->user();
        if (!$user) {
            throw new Exception('La factura no tiene usuario asociado');
        }
        $company = $user->company();
        $issueDate = is_string($invoice->issue_date) ? date('Y-m-d', strtotime($invoice->issue_date)) : date('Y-m-d', strtotime($invoice->issue_date));
        $issueTime = is_string($invoice->issue_date) ? date('H:i:s', strtotime($invoice->issue_date)) : date('H:i:s', strtotime($invoice->issue_date));
        
        $nit = preg_replace('/[^0-9]/', '', $company->nit);
        $invoiceNumber = preg_replace('/[^0-9A-Za-z-]/', '', $invoice->invoice_number);
        $dateFormatted = str_replace('-', '', $issueDate);
        $timeFormatted = str_replace(':', '', $issueTime);
        $totalAmount = number_format((float)$invoice->payable_amount, 2, '.', '');
        $taxAmount = number_format((float)($invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount), 2, '.', '');
        $invoiceTypeCode = $invoice->invoice_type_code ?? '01';
        $currencyCode = $invoice->document_currency_code ?? 'COP';
        
        $cufeData = [
            $nit,
            $invoiceNumber,
            $dateFormatted,
            $timeFormatted,
            $totalAmount,
            $taxAmount,
            $invoiceTypeCode,
            $currencyCode,
            $nit
        ];
        
        $cufeString = implode('', $cufeData);
        $hash = hash('sha384', $cufeString);
        $cufe = strtoupper($hash);
        
        return $cufe;
    }
    
    public function generateCUDE() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public function sendInvoiceToDian($invoice) {
        $validation = $this->validateBeforeSending($invoice);
        if (!$validation['valid']) {
            $invoice->update([
                'dian_status' => 'rejected',
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => false,
                'message' => 'La factura no cumple con los requisitos para ser enviada a la DIAN',
                'data' => [
                    'errors' => $validation['errors'],
                    'invoice_number' => $invoice->invoice_number,
                    'status' => 'rejected'
                ]
            ];
        }
        
        sleep(rand(1, 3));
        $success = true;
        
        if ($success) {
            $cufe = $this->generateCUFE($invoice);
            $xmlResult = $this->generateXmlUBL($invoice, $cufe);
            
            if (is_array($xmlResult)) {
                $xml = $xmlResult['xml'];
            } else {
                $xml = $xmlResult;
            }
            
            require_once __DIR__ . '/XsdValidationService.php';
            $xsdValidator = new XsdValidationService();
            $xsdValidation = $xsdValidator->validateInvoiceXML($xml);
            
            if (!$xsdValidation['valid']) {
                $invoice->update([
                    'dian_status' => 'rejected',
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => false,
                    'message' => 'El XML no cumple con el esquema XSD de DIAN',
                    'data' => [
                        'errors' => $xsdValidation['errors'],
                        'warnings' => $xsdValidation['warnings'],
                        'invoice_number' => $invoice->invoice_number,
                        'status' => 'rejected'
                    ]
                ];
            }
            
            $document = $this->createElectronicDocument($invoice, $cufe);
            $this->createDianResponse($document, 'accepted');
            
            $invoice->update([
                'dian_status' => 'accepted',
                'uuid' => $cufe,
                'sent_at' => date('Y-m-d H:i:s'),
                'received_at' => date('Y-m-d H:i:s', strtotime('+10 seconds'))
            ]);
            
            return [
                'success' => true,
                'message' => 'Factura procesada exitosamente por la DIAN',
                'data' => [
                    'cufe' => $cufe,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => 'accepted',
                    'protocol_number' => 'PRT-' . str_pad(rand(100000, 999999), 9, '0', STR_PAD_LEFT),
                    'validation_date' => date('Y-m-d H:i:s'),
                    'qr_url' => "https://catalogo-vpfe.dian.gov.co/document/{$cufe}"
                ]
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Factura rechazada por la DIAN',
            'data' => [
                'error' => 'Error simulado de validación',
                'status' => 'rejected'
            ]
        ];
    }
    
    private function createElectronicDocument($invoice, $cufe) {
        if (!($invoice instanceof ElectronicInvoice)) {
            throw new Exception('El parámetro invoice debe ser una instancia de ElectronicInvoice');
        }
        $cude = $this->generateCUDE();
        $user = $invoice->user();
        if (!$user) {
            throw new Exception('La factura no tiene usuario asociado');
        }
        $company = $user->company();
        
        $numbering = $this->db->fetchOne(
            "SELECT * FROM dian_numberings WHERE company_id = ? AND document_type = 'Factura' AND current_status = 'Activo'",
            [$company->id]
        );
        
        $xmlResult = $this->generateXmlUBL($invoice, $cufe);
        
        if (is_array($xmlResult)) {
            $xml = $xmlResult['xml'];
            $signatureData = $xmlResult['signature_data'];
        } else {
            $xml = $xmlResult;
            $signatureData = null;
        }
        
        $xmlValidation = $this->validateXMLStructure($xml);
        if (!$xmlValidation['valid']) {
            throw new Exception('El XML generado no es válido: ' . implode(', ', $xmlValidation['errors']));
        }
        
        $document = new ElectronicDocument();
        $documentData = [
            'electronic_invoice_id' => $invoice->id,
            'dian_numbering_id' => $numbering ? $numbering['id'] : null,
            'cufe' => $cufe,
            'cude' => $cude,
            'xml_document' => $xml,
            'dian_status' => 'Aprobado',
            'validation_date' => date('Y-m-d H:i:s'),
            'digital_signature' => $signatureData ? $signatureData['signature_value'] : bin2hex(random_bytes(25)),
            'document_hash' => hash('sha256', $xml),
            'description' => 'Documento electrónico generado y firmado automáticamente',
            'environment' => 'HABILITACION',
            'document_type' => 'Factura Electrónica',
            'qr_code' => $this->generateQRUrl($invoice, $cufe),
            'cdr' => $this->generateCDR($cufe),
            'emission_mode' => 'normal'
        ];
        
        $createdDocument = $document->create($documentData);
        
        if ($signatureData && isset($signatureData['certificate_info'])) {
            $this->db->query(
                "UPDATE electronic_documents SET digital_signature = ? WHERE id = ?",
                [
                    json_encode([
                        'signature_value' => $signatureData['signature_value'],
                        'digest_value' => $signatureData['digest_value'],
                        'certificate_info' => $signatureData['certificate_info'],
                        'signed_at' => date('Y-m-d H:i:s')
                    ]),
                    $createdDocument->id
                ]
            );
        }
        
        return $createdDocument;
    }
    
    private function generateXmlUBL($invoice, $cufe) {
        if (!($invoice instanceof ElectronicInvoice)) {
            throw new Exception('El parámetro invoice debe ser una instancia de ElectronicInvoice');
        }
        $user = $invoice->user();
        if (!$user) {
            throw new Exception('La factura no tiene usuario asociado');
        }
        $company = $user->company();
        $buyer = $invoice->buyer();
        $details = $invoice->invoiceDetails();
        
        if (is_string($invoice->issue_date)) {
            $dateTime = new DateTime($invoice->issue_date);
            $issueDate = $dateTime->format('Y-m-d');
            $issueTime = $dateTime->format('H:i:s');
        } else {
            $issueDate = date('Y-m-d', strtotime($invoice->issue_date));
            $issueTime = date('H:i:s', strtotime($invoice->issue_date));
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
            $issueDate = date('Y-m-d');
            $issueTime = date('H:i:s');
        }
        
        $invoiceLines = $this->generateInvoiceLines($invoice, $details);
        $taxTotal = $this->generateTaxTotal($invoice, $details);
        $customerParty = $this->generateCustomerParty($buyer);
        $detailsCount = is_array($details) ? count($details) : 0;
        
        $xmlWithoutSignature = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
         xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
    <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>DIAN 2.1: Factura Electrónica de Venta</cbc:CustomizationID>
    <cbc:ProfileID>DIAN 2.1</cbc:ProfileID>
    <cbc:ID>{$invoice->invoice_number}</cbc:ID>
    <cbc:UUID schemeID="CUFE-SHA384">{$cufe}</cbc:UUID>
    <cbc:IssueDate>{$issueDate}</cbc:IssueDate>
    <cbc:IssueTime>{$issueTime}</cbc:IssueTime>
    <cbc:InvoiceTypeCode listID="01">{$invoice->invoice_type_code}</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>COP</cbc:DocumentCurrencyCode>
    <cbc:LineCountNumeric>{$detailsCount}</cbc:LineCountNumeric>
    
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="4">{$company->nit}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>{$this->escapeXml($company->business_name)}</cbc:Name>
            </cac:PartyName>
        </cac:Party>
    </cac:AccountingSupplierParty>
    
    {$customerParty}
    {$invoiceLines}
    {$taxTotal}
    
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="COP">{$this->formatAmount($invoice->line_extension_amount)}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="COP">{$this->formatAmount($invoice->tax_exclusive_amount)}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="COP">{$this->formatAmount($invoice->tax_inclusive_amount)}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="COP">{$this->formatAmount($invoice->payable_amount)}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
XML;
        
        try {
            $signatureData = $this->signatureService->signXML($xmlWithoutSignature, $company);
            if (isset($signatureData['signature']) && !empty($signatureData['signature'])) {
                $signatureXml = $signatureData['signature'];
                $xmlWithSignature = str_replace(
                    '    <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>',
                    "    {$signatureXml}\n    <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>",
                    $xmlWithoutSignature
                );
                return [
                    'xml' => $xmlWithSignature,
                    'signature_data' => $signatureData
                ];
            } else {
                throw new Exception('No se pudo generar la firma digital');
            }
        } catch (Exception $e) {
            error_log('Error al firmar XML: ' . $e->getMessage());
            throw new Exception('Error al firmar el documento XML: ' . $e->getMessage());
        }
    }
    
    private function generateInvoiceLines($invoice, $details) {
        $lines = '';
        $lineNumber = 1;
        
        foreach ($details as $detail) {
            $item = $detail->item();
            $unitCode = 'C62';
            if ($item && ($item instanceof Product || $item instanceof Service)) {
                $unit = $item->measurementUnit();
                if ($unit) {
                    $unitCode = $unit->code ?? 'C62';
                }
            }
            
            $lineTaxAmount = $detail->tax_amount ?? 0;
            $lineTaxableAmount = $detail->line_extension_amount;
            $lineTaxPercent = $lineTaxableAmount > 0 && $lineTaxAmount > 0 ? round(($lineTaxAmount / $lineTaxableAmount) * 100, 2) : 0;
            
            $taxSubtotal = '';
            if ($lineTaxAmount > 0) {
                $taxSubtotal = <<<XML
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="COP">{$this->formatAmount($lineTaxAmount)}</cbc:TaxAmount>
            <cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID="COP">{$this->formatAmount($lineTaxableAmount)}</cbc:TaxableAmount>
                <cbc:TaxAmount currencyID="COP">{$this->formatAmount($lineTaxAmount)}</cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:Percent>{$this->formatAmount($lineTaxPercent)}</cbc:Percent>
                    <cac:TaxScheme>
                        <cbc:ID>01</cbc:ID>
                        <cbc:Name>IVA</cbc:Name>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
        </cac:TaxTotal>
XML;
            }
            
            $discountAmount = $detail->discount_amount ?? 0;
            $allowanceCharge = '';
            if ($discountAmount > 0) {
                $allowanceCharge = <<<XML
        <cac:AllowanceCharge>
            <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
            <cbc:Amount currencyID="COP">{$this->formatAmount($discountAmount)}</cbc:Amount>
        </cac:AllowanceCharge>
XML;
            }
            
            $lines .= <<<XML
    <cac:InvoiceLine>
        <cbc:ID>{$lineNumber}</cbc:ID>
        <cbc:InvoicedQuantity unitCode="{$unitCode}">{$this->formatAmount($detail->quantity)}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="COP">{$this->formatAmount($detail->line_extension_amount)}</cbc:LineExtensionAmount>
        {$allowanceCharge}
        <cac:Item>
            <cbc:Description>{$this->escapeXml($detail->description)}</cbc:Description>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="COP">{$this->formatAmount($detail->unit_price)}</cbc:PriceAmount>
        </cac:Price>
        {$taxSubtotal}
    </cac:InvoiceLine>
XML;
            $lineNumber++;
        }
        
        return $lines;
    }
    
    private function generateTaxTotal($invoice, $details) {
        $totalTax = $invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount;
        
        if ($totalTax <= 0) {
            return <<<XML
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="COP">0.00</cbc:TaxAmount>
    </cac:TaxTotal>
XML;
        }
        
        $taxableAmount = $invoice->tax_exclusive_amount;
        $taxPercent = $taxableAmount > 0 ? round(($totalTax / $taxableAmount) * 100, 2) : 19.00;
        
        return <<<XML
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="COP">{$this->formatAmount($totalTax)}</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="COP">{$this->formatAmount($taxableAmount)}</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="COP">{$this->formatAmount($totalTax)}</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:Percent>{$this->formatAmount($taxPercent)}</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>01</cbc:ID>
                    <cbc:Name>IVA</cbc:Name>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
XML;
    }
    
    private function generateCustomerParty($buyer) {
        $documentType = $buyer->document_type ?? 'CC';
        $documentNumber = $buyer->document_number ?? '';
        $name = $buyer->first_name ?? 'Cliente';
        
        return <<<XML
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="{$documentType}">{$this->escapeXml($documentNumber)}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>{$this->escapeXml($name)}</cbc:Name>
            </cac:PartyName>
        </cac:Party>
    </cac:AccountingCustomerParty>
XML;
    }
    
    private function generateCDR($cufe) {
        $protocol = 'PRT-' . str_pad(rand(100000, 999999), 9, '0', STR_PAD_LEFT);
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ApplicationResponse>
    <cbc:ResponseCode>00</cbc:ResponseCode>
    <cbc:Description>Procesado Correctamente</cbc:Description>
    <cbc:ProtocolNumber>{$protocol}</cbc:ProtocolNumber>
</ApplicationResponse>
XML;
    }
    
    public function generateQRUrl($invoice, $cufe) {
        if (!($invoice instanceof ElectronicInvoice)) {
            throw new Exception('El parámetro invoice debe ser una instancia de ElectronicInvoice');
        }
        $user = $invoice->user();
        if (!$user) {
            throw new Exception('La factura no tiene usuario asociado');
        }
        $company = $user->company();
        $issueDate = is_string($invoice->issue_date) ? date('Y-m-d', strtotime($invoice->issue_date)) : date('Y-m-d', strtotime($invoice->issue_date));
        $buyer = $invoice->buyer();
        
        $qrData = [
            'NumFac' => $invoice->invoice_number,
            'FecFac' => $issueDate,
            'NitFac' => $company->nit,
            'DocAdq' => $buyer ? $buyer->document_number : '',
            'ValFac' => number_format($invoice->payable_amount, 2, '.', ''),
            'ValIva' => number_format($invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount, 2, '.', ''),
            'ValOtroIm' => '0.00',
            'ValTotal' => number_format($invoice->payable_amount, 2, '.', ''),
            'CUFE' => $cufe
        ];
        
        return "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?" . http_build_query($qrData);
    }
    
    private function createDianResponse($document, $status) {
        $db = Database::getInstance();
        $sql = "INSERT INTO dian_status_responses (electronic_document_id, status_code, status_description, status_message, response_xml, protocol_number, received_at, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())";
        
        $statusCode = $status === 'accepted' ? '200' : '400';
        $description = $status === 'accepted' ? 'Documento recibido correctamente' : 'Error en validación';
        $message = $status === 'accepted' ? 'La factura fue validada exitosamente' : 'El documento no cumple con el esquema XML';
        $protocol = 'PRT-' . str_pad(rand(100000, 999999), 9, '0', STR_PAD_LEFT);
        
        $db->query($sql, [
            $document->id,
            $statusCode,
            $description,
            $message,
            '<ApplicationResponse>Validación DIAN completada</ApplicationResponse>',
            $protocol
        ]);
    }
    
    public function checkInvoiceStatus($cufe) {
        sleep(1);
        
        $document = $this->db->fetchOne("SELECT * FROM electronic_documents WHERE cufe = ?", [$cufe]);
        
        if (!$document) {
            return [
                'success' => false,
                'message' => 'Documento no encontrado',
                'data' => ['cufe' => $cufe, 'status' => 'not_found']
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Consulta exitosa',
            'data' => [
                'cufe' => $document['cufe'],
                'status' => $document['dian_status'],
                'validation_date' => $document['validation_date'],
                'qr_url' => $document['qr_code']
            ]
        ];
    }
    
    private function validateBeforeSending($invoice) {
        if (!($invoice instanceof ElectronicInvoice)) {
            return ['valid' => false, 'errors' => ['El parámetro invoice debe ser una instancia de ElectronicInvoice']];
        }
        $errors = [];
        $user = $invoice->user();
        if (!$user) {
            $errors[] = 'La factura no tiene usuario asociado';
            return ['valid' => false, 'errors' => $errors];
        }
        $company = $user->company();
        
        if (!$company) {
            $errors[] = 'La factura no tiene una empresa asociada';
            return ['valid' => false, 'errors' => $errors];
        }
        
        if (empty($company->nit)) {
            $errors[] = 'La empresa no tiene NIT configurado';
        }
        
        if (empty($company->business_name)) {
            $errors[] = 'La empresa no tiene razón social configurada';
        }
        
        $certificate = $this->signatureService->getActiveCertificate($company);
        if (!$certificate) {
            $errors[] = 'La empresa no tiene un certificado digital activo';
        }
        
        $numbering = $this->db->fetchOne(
            "SELECT * FROM dian_numberings WHERE company_id = ? AND document_type = 'Factura' AND current_status = 'Activo'",
            [$company->id]
        );
        
        if (!$numbering) {
            $errors[] = 'No hay numeración DIAN activa para facturas';
        } else {
            $today = date('Y-m-d');
            if ($today < $numbering['validity_start_date'] || $today > $numbering['validity_end_date']) {
                $errors[] = 'La resolución DIAN no está vigente';
            }
            
            if (empty($numbering['prefix'])) {
                $errors[] = 'La numeración DIAN no tiene prefijo configurado';
            }
        }
        
        if (empty($invoice->invoice_number)) {
            $errors[] = 'La factura no tiene número asignado';
        }
        
        if (empty($invoice->issue_date)) {
            $errors[] = 'La factura no tiene fecha de emisión';
        }
        
        $buyer = $invoice->buyer();
        if (!$buyer) {
            $errors[] = 'La factura no tiene cliente asignado';
        } else {
            if (empty($buyer->document_number)) {
                $errors[] = 'El cliente no tiene número de documento';
            }
        }
        
        $details = $invoice->invoiceDetails();
        if (empty($details)) {
            $errors[] = 'La factura debe tener al menos un detalle';
        } else {
            foreach ($details as $detail) {
                if ($detail->quantity <= 0) {
                    $errors[] = 'Todos los detalles deben tener cantidad mayor a cero';
                    break;
                }
                if ($detail->unit_price <= 0) {
                    $errors[] = 'Todos los detalles deben tener precio unitario mayor a cero';
                    break;
                }
            }
        }
        
        if ($invoice->payable_amount <= 0) {
            $errors[] = 'El total a pagar debe ser mayor a cero';
        }
        
        if ($invoice->tax_inclusive_amount < $invoice->tax_exclusive_amount) {
            $errors[] = 'El monto con impuestos no puede ser menor al monto sin impuestos';
        }
        
        if (empty($invoice->invoice_type_code)) {
            $errors[] = 'La factura debe tener código de tipo de factura';
        }
        
        if (empty($invoice->document_currency_code)) {
            $errors[] = 'La factura debe tener código de moneda';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    public function validateXMLStructure($xml) {
        $errors = [];
        
        if (empty($xml)) {
            $errors[] = 'El XML está vacío';
            return ['valid' => false, 'errors' => $errors];
        }
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        if (!@$dom->loadXML($xml)) {
            $xmlErrors = libxml_get_errors();
            foreach ($xmlErrors as $error) {
                $errors[] = "Error XML: " . trim($error->message);
            }
            libxml_clear_errors();
            return ['valid' => false, 'errors' => $errors];
        }
        
        $requiredElements = [
            'Invoice',
            'UBLVersionID',
            'CustomizationID',
            'ProfileID',
            'ID',
            'UUID',
            'IssueDate',
            'IssueTime',
            'InvoiceTypeCode',
            'DocumentCurrencyCode',
            'AccountingSupplierParty',
            'AccountingCustomerParty',
            'LegalMonetaryTotal'
        ];
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        
        // Verificar elemento Invoice (raíz) - puede estar con o sin namespace
        $invoiceNodes = $xpath->query('//ubl:Invoice | //Invoice');
        if ($invoiceNodes->length === 0) {
            $errors[] = 'Falta el elemento requerido: Invoice';
        }
        
        foreach ($requiredElements as $element) {
            if ($element === 'Invoice') {
                continue; // Ya verificado arriba
            } elseif ($element === 'UBLVersionID' || $element === 'CustomizationID' || $element === 'ProfileID' || 
                      $element === 'ID' || $element === 'UUID' || $element === 'IssueDate' || 
                      $element === 'IssueTime' || $element === 'InvoiceTypeCode' || $element === 'DocumentCurrencyCode' ||
                      $element === 'LineCountNumeric') {
                $nodes = $xpath->query("//cbc:{$element}");
            } elseif ($element === 'AccountingSupplierParty' || $element === 'AccountingCustomerParty' || 
                      $element === 'LegalMonetaryTotal') {
                $nodes = $xpath->query("//cac:{$element}");
            } else {
                $nodes = $xpath->query("//cbc:{$element} | //cac:{$element}");
            }
            
            if ($nodes->length === 0) {
                $errors[] = "Falta el elemento requerido: {$element}";
            }
        }
        
        libxml_clear_errors();
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    private function formatAmount($amount) {
        return number_format((float)$amount, 2, '.', '');
    }
    
    private function escapeXml($string) {
        return htmlspecialchars($string ?? '', ENT_XML1, 'UTF-8');
    }
}


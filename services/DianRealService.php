<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../models/ElectronicInvoice.php';
require_once __DIR__ . '/../models/ElectronicDocument.php';
require_once __DIR__ . '/DigitalSignatureService.php';

class DianRealService {
    private $signatureService;
    private $db;
    private $apiUrl;
    private $environment;
    private $certificatePath;
    private $certificatePassword;
    private $dianUsername;
    private $dianPassword;
    
    public function __construct() {
        $this->signatureService = new DigitalSignatureService();
        $this->db = Database::getInstance();
        
        // Configuración desde variables de entorno o configuración
        $this->environment = getenv('DIAN_ENVIRONMENT') ?: 'HABILITACION';
        $this->apiUrl = $this->environment === 'PRODUCCION' 
            ? 'https://api.dian.gov.co' 
            : 'https://api-hab.dian.gov.co';
        
        $this->certificatePath = getenv('DIAN_CERTIFICATE_PATH') ?: '';
        $this->certificatePassword = getenv('DIAN_CERTIFICATE_PASSWORD') ?: '';
        $this->dianUsername = getenv('DIAN_USERNAME') ?: '';
        $this->dianPassword = getenv('DIAN_PASSWORD') ?: '';
    }
    
    public function sendInvoiceToDian($invoice) {
        if (!($invoice instanceof ElectronicInvoice)) {
            return [
                'success' => false,
                'message' => 'El parámetro invoice debe ser una instancia de ElectronicInvoice',
                'data' => ['status' => 'rejected']
            ];
        }
        
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
        
        try {
            $user = $invoice->user();
            $company = $user->company();
            
            $cufe = $this->generateCUFE($invoice);
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
            
            require_once __DIR__ . '/XsdValidationService.php';
            $xsdValidator = new XsdValidationService();
            $xsdValidation = $xsdValidator->validateInvoiceXML($xml);
            
            if (!$xsdValidation['valid']) {
                throw new Exception('El XML no cumple con el esquema XSD de DIAN: ' . implode(', ', $xsdValidation['errors']));
            }
            
            if (!empty($xsdValidation['warnings'])) {
                error_log('Advertencias XSD: ' . implode(', ', $xsdValidation['warnings']));
            }
            
            $response = $this->sendToDianAPI($xml, $invoice);
            
            if ($response['success']) {
                $this->createElectronicDocument($invoice, $cufe, $xml, $signatureData, $response);
                
                $protocolNumber = $response['data']['protocol_number'] ?? null;
                $validationDate = $response['data']['validation_date'] ?? date('Y-m-d H:i:s');
                
                $invoice->update([
                    'dian_status' => 'accepted',
                    'uuid' => $cufe,
                    'sent_at' => date('Y-m-d H:i:s'),
                    'received_at' => $validationDate,
                    'protocol_number' => $protocolNumber,
                    'validation_date' => $validationDate
                ]);
                
                $this->logDianResponse($invoice->id, 'accepted', $response);
                
                return [
                    'success' => true,
                    'message' => 'Factura procesada exitosamente por la DIAN',
                    'data' => [
                        'cufe' => $cufe,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => 'accepted',
                        'protocol_number' => $protocolNumber,
                        'validation_date' => $validationDate,
                        'response_code' => $response['data']['response_code'] ?? '00',
                        'response_message' => $response['data']['response_message'] ?? 'Aceptado',
                        'qr_url' => $this->generateQRUrl($invoice, $cufe),
                        'cdr' => $response['data']['cdr'] ?? null
                    ]
                ];
            } else {
                $invoice->update([
                    'dian_status' => 'rejected',
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->logDianResponse($invoice->id, 'rejected', $response);
                
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Factura rechazada por la DIAN',
                    'data' => [
                        'errors' => $response['errors'] ?? [],
                        'status' => 'rejected',
                        'response_code' => $response['response_code'] ?? '99',
                        'invoice_number' => $invoice->invoice_number,
                        'http_code' => $response['http_code'] ?? null
                    ]
                ];
            }
        } catch (Exception $e) {
            $invoice->update([
                'dian_status' => 'rejected',
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => false,
                'message' => 'Error al enviar factura a DIAN: ' . $e->getMessage(),
                'data' => [
                    'status' => 'rejected',
                    'invoice_number' => $invoice->invoice_number
                ]
            ];
        }
    }
    
    private function sendToDianAPI($xml, $invoice, $retryAttempts = 3) {
        if (empty($this->certificatePath) || !file_exists($this->certificatePath)) {
            throw new Exception('Certificado digital no configurado o no encontrado');
        }
        
        $endpoint = $this->apiUrl . '/ubl2.1/send-bill';
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                $ch = curl_init($endpoint);
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $xml,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/xml',
                        'Accept: application/json',
                        'User-Agent: FacturacionElectronica/1.0'
                    ],
                    CURLOPT_SSLCERT => $this->certificatePath,
                    CURLOPT_SSLCERTPASSWD => $this->certificatePassword,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_VERBOSE => false,
                    CURLOPT_CAINFO => $this->getCACertPath()
                ]);
                
                if (!empty($this->dianUsername) && !empty($this->dianPassword)) {
                    curl_setopt($ch, CURLOPT_USERPWD, $this->dianUsername . ':' . $this->dianPassword);
                }
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                curl_close($ch);
                
                if ($curlError && $curlErrno) {
                    $lastError = "Error cURL ($curlErrno): $curlError";
                    if ($attempt < $retryAttempts) {
                        sleep(2 * $attempt);
                        continue;
                    }
                    throw new Exception($lastError);
                }
                
                if ($httpCode === 200 || $httpCode === 201) {
                    $data = json_decode($response, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $lastError = 'Error al decodificar respuesta JSON: ' . json_last_error_msg();
                        if ($attempt < $retryAttempts) {
                            sleep(2 * $attempt);
                            continue;
                        }
                        throw new Exception($lastError);
                    }
                    
                    if (isset($data['isValid']) && $data['isValid'] === true) {
                        return [
                            'success' => true,
                            'message' => 'Factura aceptada por DIAN',
                            'data' => [
                                'cufe' => $data['cufe'] ?? $data['uuid'] ?? null,
                                'protocol_number' => $data['number'] ?? $data['zipKey'] ?? null,
                                'validation_date' => $data['issueDate'] ?? $data['issueDateTime'] ?? date('Y-m-d H:i:s'),
                                'cdr' => $data['qrCode'] ?? $data['qr'] ?? null,
                                'response_code' => $data['statusCode'] ?? '00',
                                'response_message' => $data['statusMessage'] ?? 'Aceptado',
                                'response' => $data
                            ]
                        ];
                    } else {
                        $errors = [];
                        if (isset($data['statusDescription'])) {
                            $errors[] = $data['statusDescription'];
                        }
                        if (isset($data['errors']) && is_array($data['errors'])) {
                            $errors = array_merge($errors, $data['errors']);
                        }
                        if (isset($data['errorMessages']) && is_array($data['errorMessages'])) {
                            $errors = array_merge($errors, $data['errorMessages']);
                        }
                        
                        return [
                            'success' => false,
                            'message' => $data['statusMessage'] ?? $data['message'] ?? 'Factura rechazada por DIAN',
                            'errors' => $errors,
                            'response_code' => $data['statusCode'] ?? '99',
                            'data' => $data
                        ];
                    }
                } else {
                    $errorData = json_decode($response, true);
                    $lastError = $errorData['message'] ?? "Error HTTP $httpCode";
                    
                    if ($httpCode >= 500 && $attempt < $retryAttempts) {
                        sleep(2 * $attempt);
                        continue;
                    }
                    
                    return [
                        'success' => false,
                        'message' => $errorData['message'] ?? "Error al enviar factura a DIAN (HTTP $httpCode)",
                        'errors' => $errorData['errors'] ?? [$lastError],
                        'http_code' => $httpCode,
                        'data' => $errorData
                    ];
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                if ($attempt < $retryAttempts) {
                    sleep(2 * $attempt);
                    continue;
                }
                throw $e;
            }
        }
        
        throw new Exception('Error después de ' . $retryAttempts . ' intentos: ' . $lastError);
    }
    
    private function getCACertPath() {
        $caPaths = [
            __DIR__ . '/../certs/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/usr/local/share/certs/ca-root-nss.crt'
        ];
        
        foreach ($caPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    public function getInvoiceStatus($cufe, $retryAttempts = 2) {
        if (empty($cufe)) {
            return ['success' => false, 'message' => 'CUFE no proporcionado'];
        }
        
        $endpoint = $this->apiUrl . '/ubl2.1/get-status/' . urlencode($cufe);
        
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $ch = curl_init($endpoint);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: FacturacionElectronica/1.0'
                ],
                CURLOPT_SSLCERT => $this->certificatePath,
                CURLOPT_SSLCERTPASSWD => $this->certificatePassword,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_CAINFO => $this->getCACertPath()
            ]);
            
            if (!empty($this->dianUsername) && !empty($this->dianPassword)) {
                curl_setopt($ch, CURLOPT_USERPWD, $this->dianUsername . ':' . $this->dianPassword);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError && $attempt < $retryAttempts) {
                sleep(1 * $attempt);
                continue;
            }
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'success' => true,
                        'data' => $data,
                        'status' => $data['status'] ?? 'unknown',
                        'is_valid' => $data['isValid'] ?? false
                    ];
                }
            }
            
            if ($attempt < $retryAttempts && $httpCode >= 500) {
                sleep(1 * $attempt);
                continue;
            }
            
            return [
                'success' => false,
                'message' => $curlError ?: "Error al consultar estado en DIAN (HTTP $httpCode)",
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al consultar estado después de ' . $retryAttempts . ' intentos'
        ];
    }
    
    public function downloadCDR($cufe, $retryAttempts = 2) {
        if (empty($cufe)) {
            return ['success' => false, 'message' => 'CUFE no proporcionado'];
        }
        
        $endpoint = $this->apiUrl . '/ubl2.1/get-cdr/' . urlencode($cufe);
        
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $ch = curl_init($endpoint);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/xml',
                    'User-Agent: FacturacionElectronica/1.0'
                ],
                CURLOPT_SSLCERT => $this->certificatePath,
                CURLOPT_SSLCERTPASSWD => $this->certificatePassword,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_CAINFO => $this->getCACertPath()
            ]);
            
            if (!empty($this->dianUsername) && !empty($this->dianPassword)) {
                curl_setopt($ch, CURLOPT_USERPWD, $this->dianUsername . ':' . $this->dianPassword);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError && $attempt < $retryAttempts) {
                sleep(1 * $attempt);
                continue;
            }
            
            if ($httpCode === 200) {
                if (!empty($response) && $this->isValidXML($response)) {
                    return [
                        'success' => true,
                        'data' => $response,
                        'format' => 'xml',
                        'size' => strlen($response)
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'CDR recibido pero no es un XML válido'
                    ];
                }
            }
            
            if ($attempt < $retryAttempts && $httpCode >= 500) {
                sleep(1 * $attempt);
                continue;
            }
            
            return [
                'success' => false,
                'message' => $curlError ?: "Error al descargar CDR de DIAN (HTTP $httpCode)",
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al descargar CDR después de ' . $retryAttempts . ' intentos'
        ];
    }
    
    private function isValidXML($xml) {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return $doc !== false && empty($errors);
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
        
        if (empty($this->certificatePath) || !file_exists($this->certificatePath)) {
            $errors[] = 'Certificado digital no configurado o no encontrado';
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
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function generateCUFE($invoice) {
        if (!($invoice instanceof ElectronicInvoice)) {
            throw new Exception('El parámetro invoice debe ser una instancia de ElectronicInvoice');
        }
        
        $user = $invoice->user();
        if (!$user) {
            throw new Exception('La factura no tiene usuario asociado');
        }
        
        $company = $user->company();
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
    
    private function generateXmlUBL($invoice, $cufe) {
        require_once __DIR__ . '/DianSimulatorService.php';
        $simulator = new DianSimulatorService();
        $reflection = new ReflectionClass($simulator);
        $method = $reflection->getMethod('generateXmlUBL');
        $method->setAccessible(true);
        return $method->invoke($simulator, $invoice, $cufe);
    }
    
    private function validateXMLStructure($xml) {
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
        
        $invoiceNodes = $xpath->query('//ubl:Invoice | //Invoice');
        if ($invoiceNodes->length === 0) {
            $errors[] = 'Falta el elemento requerido: Invoice';
        }
        
        foreach ($requiredElements as $element) {
            if ($element === 'Invoice') {
                continue;
            } elseif (in_array($element, ['UBLVersionID', 'CustomizationID', 'ProfileID', 'ID', 'UUID', 'IssueDate', 'IssueTime', 'InvoiceTypeCode', 'DocumentCurrencyCode', 'LineCountNumeric'])) {
                $nodes = $xpath->query("//cbc:{$element}");
            } elseif (in_array($element, ['AccountingSupplierParty', 'AccountingCustomerParty', 'LegalMonetaryTotal'])) {
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
    
    private function createElectronicDocument($invoice, $cufe, $xml, $signatureData, $response) {
        $user = $invoice->user();
        $company = $user->company();
        
        $numbering = $this->db->fetchOne(
            "SELECT * FROM dian_numberings WHERE company_id = ? AND document_type = 'Factura' AND current_status = 'Activo'",
            [$company->id]
        );
        
        $document = new ElectronicDocument();
        $documentData = [
            'electronic_invoice_id' => $invoice->id,
            'dian_numbering_id' => $numbering ? $numbering['id'] : null,
            'cufe' => $cufe,
            'cude' => $this->generateCUDE(),
            'xml_document' => $xml,
            'dian_status' => 'Aprobado',
            'validation_date' => $response['data']['validation_date'] ?? date('Y-m-d H:i:s'),
            'digital_signature' => $signatureData ? json_encode($signatureData) : null,
            'document_hash' => hash('sha256', $xml),
            'description' => 'Documento electrónico enviado y validado por DIAN',
            'environment' => $this->environment,
            'document_type' => 'Factura Electrónica',
            'qr_code' => $this->generateQRUrl($invoice, $cufe),
            'cdr' => $response['data']['cdr'] ?? null,
            'emission_mode' => 'normal'
        ];
        
        return $document->create($documentData);
    }
    
    private function generateCUDE() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
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
            'ValFac' => $this->formatAmount($invoice->payable_amount),
            'ValIva' => $this->formatAmount($invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount),
            'ValOtroIm' => '0.00',
            'ValTotal' => $this->formatAmount($invoice->payable_amount),
            'CUFE' => $cufe
        ];
        
        $baseUrl = "https://catalogo-vpfe" . ($this->environment === 'PRODUCCION' ? '' : '-hab') . ".dian.gov.co/document/searchqr";
        return $baseUrl . "?" . http_build_query($qrData);
    }
    
    public function checkInvoiceStatus($cufe) {
        return $this->getInvoiceStatus($cufe);
    }
    
    private function formatAmount($amount) {
        return number_format((float)$amount, 2, '.', '');
    }
    
    private function escapeXml($string) {
        return htmlspecialchars($string ?? '', ENT_XML1, 'UTF-8');
    }
    
    private function logDianResponse($invoiceId, $status, $response) {
        try {
            $this->db->query(
                "INSERT INTO dian_logs (electronic_invoice_id, status, response_data, created_at) VALUES (?, ?, ?, NOW())",
                [
                    $invoiceId,
                    $status,
                    json_encode($response)
                ]
            );
        } catch (Exception $e) {
            error_log('Error al guardar log de DIAN: ' . $e->getMessage());
        }
    }
    
    public function validateCertificate() {
        if (empty($this->certificatePath) || !file_exists($this->certificatePath)) {
            return [
                'valid' => false,
                'message' => 'Certificado no encontrado'
            ];
        }
        
        if (!empty($this->certificatePassword)) {
            $cert = @openssl_x509_read(file_get_contents($this->certificatePath));
            if ($cert === false) {
                $pkcs12 = @openssl_pkcs12_read(
                    file_get_contents($this->certificatePath),
                    $certs,
                    $this->certificatePassword
                );
                
                if (!$pkcs12) {
                    return [
                        'valid' => false,
                        'message' => 'No se pudo leer el certificado. Verifique la contraseña.'
                    ];
                }
            }
        }
        
        return [
            'valid' => true,
            'message' => 'Certificado válido'
        ];
    }
}


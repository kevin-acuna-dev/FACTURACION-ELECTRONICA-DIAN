<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../models/ElectronicInvoice.php';
require_once __DIR__ . '/../models/InvoiceDetail.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Tax.php';
require_once __DIR__ . '/../models/InvoiceDetail.php';
require_once __DIR__ . '/DianSimulatorService.php';
require_once __DIR__ . '/TaxCalculationService.php';
require_once __DIR__ . '/SectorInvoiceService.php';

class InvoiceService {
    private $dianService;
    private $taxCalculationService;
    private $db;
    private $useRealDian;
    
    public function __construct() {
        $this->useRealDian = getenv('USE_REAL_DIAN') === 'true' || getenv('USE_REAL_DIAN') === '1';
        
        if ($this->useRealDian && file_exists(__DIR__ . '/DianRealService.php')) {
            require_once __DIR__ . '/DianRealService.php';
            $this->dianService = new DianRealService();
        } else {
            $this->dianService = new DianSimulatorService();
        }
        
        $this->taxCalculationService = new TaxCalculationService();
        $this->db = Database::getInstance();
    }
    
    public function createInvoice($data) {
        $this->db->beginTransaction();
        try {
            $loggedUser = Auth::user();
            if (!$loggedUser || !$loggedUser->company_id) {
                throw new Exception('Usuario no autenticado o sin empresa asociada');
            }
            
            $userModel = new User();
            $user = $userModel->find($data['user_id']);
            if (!$user || !$user->company_id) {
                throw new Exception('El usuario no tiene una empresa asociada');
            }
            
            if (!($user instanceof User)) {
                throw new Exception('Error al obtener datos del usuario');
            }
            
            if ($user->company_id !== $loggedUser->company_id) {
                throw new Exception('No puede crear facturas para usuarios de otra empresa');
            }
            
            $buyerModel = new User();
            $buyer = $buyerModel->find($data['buyer_id']);
            if (!$buyer || !$buyer->company_id) {
                throw new Exception('El cliente no tiene una empresa asociada');
            }
            
            if (!($buyer instanceof User)) {
                throw new Exception('Error al obtener datos del cliente');
            }
            
            if ($buyer->company_id !== $loggedUser->company_id) {
                throw new Exception('No puede crear facturas para clientes de otra empresa');
            }
            
            $buyerRole = $buyer->role();
            if (!$buyerRole || $buyerRole->role_name !== 'cliente') {
                throw new Exception('El usuario seleccionado no es un cliente');
            }
            
            $company = $user->company();
            
            $sectorService = new SectorInvoiceService();
            $sectorValidation = $sectorService->validateSectorConfiguration($company);
            if (!$sectorValidation['valid']) {
                throw new Exception('Error de configuración del sector: ' . implode(', ', $sectorValidation['errors']));
            }
            
            $invoiceTypeCode = $this->determineInvoiceTypeCode($data, $company, $buyer);
            
            $validation = $this->taxCalculationService->validateInvoiceConfiguration($company, $buyer, $invoiceTypeCode);
            if (!$validation['valid']) {
                throw new Exception('Error de validación: ' . implode(', ', $validation['errors']));
            }
            
            $invoiceNumber = $this->generateInvoiceNumber($user, $invoiceTypeCode);
            
            $invoice = new ElectronicInvoice();
            $invoiceData = [
                'user_id' => $data['user_id'],
                'buyer_id' => $data['buyer_id'],
                'invoice_number' => $invoiceNumber,
                'issue_date' => date('Y-m-d H:i:s'),
                'internal_status' => 'draft',
                'observation' => $data['observation'] ?? null,
                'ubl_version' => '2.1',
                'customization_id' => 'DIAN 2.1: Factura Electrónica de Venta',
                'profile_id' => 'DIAN 2.1',
                'document_currency_code' => $data['document_currency_code'] ?? 'COP',
                'invoice_type_code' => $invoiceTypeCode,
                'payment_means_code' => $data['payment_means_code'] ?? '10',
                'payment_means_name' => $data['payment_means_name'] ?? 'Contado',
                'dian_status' => 'pending'
            ];
            $invoice = $invoice->create($invoiceData);
            
            $totals = $this->createInvoiceDetails($invoice, $data['items'], $data['buyer_id'], $invoiceTypeCode);
            $invoice->update($totals);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Factura creada exitosamente',
                'data' => $invoice->toArray()
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error al crear la factura: ' . $e->getMessage()
            ];
        }
    }
    
    private function createInvoiceDetails($invoice, $items, $buyerId, $invoiceTypeCode) {
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            throw new Exception('Usuario no autenticado o sin empresa asociada');
        }
        
        $company = $loggedUser->company();
        $buyer = new User();
        $buyer = $buyer->find($buyerId);
        
        foreach ($items as $itemData) {
            if ($itemData['type'] === 'product') {
                $item = new Product();
            } else {
                $item = new Service();
            }
            $item = $item->find($itemData['id']);
            
            if (!$item) {
                throw new Exception("Item no encontrado: {$itemData['type']} ID {$itemData['id']}");
            }
            
            if ($item->company_id !== $loggedUser->company_id) {
                throw new Exception("El item no pertenece a su empresa");
            }
            
            if ($item->status !== 'Active') {
                throw new Exception("El item no está activo");
            }
            
            $quantity = $itemData['quantity'];
            $unitPrice = $item->unit_price;
            $discount = $itemData['discount'] ?? 0;
            
            $lineSubtotal = ($unitPrice * $quantity) - $discount;
            
            $taxCalculation = $this->taxCalculationService->calculateItemTaxes($item, $lineSubtotal, $company, $buyer, $invoiceTypeCode);
            $lineTax = $taxCalculation['total'];
            $lineTotal = $lineSubtotal + $lineTax;
            
            $detail = new InvoiceDetail();
            $detailData = [
                'electronic_invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'item_type' => $itemData['type'] === 'product' ? 'App\\Models\\Product' : 'App\\Models\\Service',
                'description' => $item->description ?? $item->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_extension_amount' => $lineSubtotal,
                'discount_amount' => $discount,
                'tax_amount' => $lineTax,
                'total_line_amount' => $lineTotal
            ];
            $detail->create($detailData);
            
            $subtotal += $lineSubtotal;
            $totalTax += $lineTax;
            $totalDiscount += $discount;
        }
        
        return [
            'line_extension_amount' => round($subtotal, 2),
            'tax_exclusive_amount' => round($subtotal, 2),
            'tax_inclusive_amount' => round($subtotal + $totalTax, 2),
            'payable_amount' => round($subtotal + $totalTax, 2),
            'total_discount' => round($totalDiscount, 2)
        ];
    }
    
    private function determineInvoiceTypeCode($data, $company, $buyer) {
        if (isset($data['invoice_type_code'])) {
            return $data['invoice_type_code'];
        }
        
        $isExport = isset($data['is_export']) && $data['is_export'] === true;
        $isContingency = isset($data['is_contingency']) && $data['is_contingency'] === true;
        
        if ($isExport) {
            return '02';
        }
        
        if ($isContingency) {
            return '03';
        }
        
        $sectorService = new SectorInvoiceService();
        $sectorInfo = $sectorService->getInvoiceTypeBySector($company);
        return $sectorInfo['invoice_type_code'] ?? '01';
    }
    
    private function generateInvoiceNumber($user, $invoiceTypeCode = '01') {
        $company = $user->company();
        
        $documentType = 'Factura';
        if ($invoiceTypeCode === '02') {
            $documentType = 'Factura de Exportación';
        } elseif ($invoiceTypeCode === '03') {
            $documentType = 'Factura de Contingencia';
        }
        
        $numbering = $this->db->fetchOne(
            "SELECT * FROM dian_numberings WHERE company_id = ? AND document_type = ? AND current_status = 'Activo'",
            [$company->id, $documentType]
        );
        
        if (!$numbering) {
            $numbering = $this->db->fetchOne(
                "SELECT * FROM dian_numberings WHERE company_id = ? AND document_type = 'Factura' AND current_status = 'Activo'",
                [$company->id]
            );
        }
        
        if (!$numbering) {
            throw new Exception('No hay numeración DIAN activa para facturas');
        }
        
        $today = date('Y-m-d');
        if ($today < $numbering['validity_start_date'] || $today > $numbering['validity_end_date']) {
            throw new Exception('La resolución DIAN no está vigente');
        }
        
        $lastInvoice = $this->db->fetchOne(
            "SELECT invoice_number FROM electronic_invoices ei 
             INNER JOIN users u ON u.id = ei.user_id 
             WHERE u.company_id = ? AND invoice_number LIKE ? 
             ORDER BY ei.id DESC LIMIT 1",
            [$company->id, $numbering['prefix'] . '-%']
        );
        
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice['invoice_number']);
            $lastNumber = (int)end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = $numbering['start_number'];
        }
        
        if ($nextNumber > $numbering['end_number']) {
            throw new Exception("Se ha agotado el rango de numeración DIAN");
        }
        
        return "{$numbering['prefix']}-{$company->id}-{$nextNumber}";
    }
    
    public function sendToDian($invoiceId) {
        $invoiceModel = new ElectronicInvoice();
        $invoice = $invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        if (!($invoice instanceof ElectronicInvoice)) {
            return ['success' => false, 'message' => 'Error al obtener datos de la factura'];
        }
        
        if ($invoice->internal_status !== 'draft') {
            return ['success' => false, 'message' => 'Solo se pueden enviar facturas en estado borrador'];
        }
        
        $buyer = $invoice->buyer();
        if (!$buyer) {
            return ['success' => false, 'message' => 'La factura no tiene un cliente asignado'];
        }
        
        $details = $invoice->invoiceDetails();
        if (empty($details)) {
            return ['success' => false, 'message' => 'La factura no tiene detalles'];
        }
        
        $invoice->update(['internal_status' => 'issued']);
        
        $result = $this->dianService->sendInvoiceToDian($invoice);
        
        require_once __DIR__ . '/WebhookService.php';
        $webhookService = new WebhookService();
        
        if ($result['success']) {
            $webhookService->triggerWebhook('invoice.accepted', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'cufe' => $result['data']['cufe'] ?? null,
                'status' => 'accepted'
            ], $invoice->id);
        } else {
            $webhookService->triggerWebhook('invoice.rejected', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'errors' => $result['data']['errors'] ?? [],
                'status' => 'rejected'
            ], $invoice->id);
        }
        
        return $result;
    }
    
    public function getInvoiceComplete($invoiceId) {
        $invoiceModel = new ElectronicInvoice();
        $invoice = $invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return null;
        }
        
        if (!($invoice instanceof ElectronicInvoice)) {
            return null;
        }
        
        $data = $invoice->toArray();
        $user = $invoice->user();
        $buyer = $invoice->buyer();
        $details = $invoice->invoiceDetails();
        
        $data['user'] = $user ? $user->toArray() : null;
        $data['buyer'] = $buyer ? $buyer->toArray() : null;
        $data['invoiceDetails'] = [];
        
        foreach ($details as $detail) {
            $detailData = $detail->toArray();
            $item = $detail->item();
            if ($item && ($item instanceof Product || $item instanceof Service)) {
                $detailData['item'] = $item->toArray();
                $taxes = $item->taxes();
                $detailData['item']['taxes'] = array_map(function($t) { return $t->toArray(); }, $taxes);
                $unit = $item->measurementUnit();
                $detailData['item']['measurementUnit'] = $unit ? $unit->toArray() : null;
            }
            $data['invoiceDetails'][] = $detailData;
        }
        
        // Obtener datos del documento electrónico y respuesta DIAN
        $doc = $this->db->fetchOne(
            "SELECT ed.*, dsr.protocol_number, dsr.received_at as validation_date 
             FROM electronic_documents ed 
             LEFT JOIN dian_status_responses dsr ON dsr.electronic_document_id = ed.id 
             WHERE ed.electronic_invoice_id = ? AND ed.credit_debit_note_id IS NULL 
             ORDER BY ed.id DESC LIMIT 1",
            [$invoiceId]
        );
        
        if ($doc) {
            if ($doc['cufe']) {
                $data['uuid'] = $doc['cufe'];
            }
            if ($doc['protocol_number']) {
                $data['protocol_number'] = $doc['protocol_number'];
            }
            if ($doc['validation_date']) {
                $data['validation_date'] = $doc['validation_date'];
            }
            if ($doc['qr_code']) {
                $data['qr_url'] = $doc['qr_code'];
            }
        }
        
        // Si hay CUFE, generar QR URL si no existe
        if ($data['uuid'] && !isset($data['qr_url'])) {
            try {
                $qrUrl = $this->dianService->generateQRUrl($invoice, $data['uuid']);
                $data['qr_url'] = $qrUrl;
            } catch (Exception $e) {
                // Ignorar error
            }
        }
        
        // Obtener datos de la empresa del usuario
        if ($user && $user->company_id) {
            $company = $this->db->fetchOne("SELECT * FROM companies WHERE id = ?", [$user->company_id]);
            if ($company) {
                $data['company'] = $company;
                if ($data['user']) {
                    $data['user']['company'] = $company;
                }
            }
        }
        
        return $data;
    }
    
    public function cancelInvoice($invoiceId) {
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($invoiceId);
        
        if (!$invoice) {
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        if ($invoice->dian_status === 'accepted') {
            return ['success' => false, 'message' => 'No se puede cancelar una factura aceptada por la DIAN'];
        }
        
        $invoice->update([
            'internal_status' => 'cancelled',
            'dian_status' => 'cancelled'
        ]);
        
        return ['success' => true, 'message' => 'Factura cancelada exitosamente', 'data' => $invoice->toArray()];
    }
    
    public function listInvoices($filters = []) {
        $loggedUser = Auth::user();
        $sql = "SELECT ei.* FROM electronic_invoices ei 
                INNER JOIN users u ON u.id = ei.user_id 
                WHERE u.company_id = ?";
        $params = [$loggedUser->company_id];
        
        if (isset($filters['user_id'])) {
            $sql .= " AND ei.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['dian_status'])) {
            $sql .= " AND ei.dian_status = ?";
            $params[] = $filters['dian_status'];
        }
        
        if (isset($filters['internal_status'])) {
            $sql .= " AND ei.internal_status = ?";
            $params[] = $filters['internal_status'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND ei.issue_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND ei.issue_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY ei.id DESC";
        
        if (isset($filters['per_page'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['per_page'];
        }
        
        $results = $this->db->fetchAll($sql, $params);
        return array_map(function($row) {
            $invoice = new ElectronicInvoice();
            return $invoice->hydrate($row)->toArray();
        }, $results);
    }
    
    public function getInvoiceStats($companyId = null, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                COUNT(*) as total_invoices,
                SUM(payable_amount) as total_amount,
                SUM(CASE WHEN dian_status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN dian_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN dian_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN dian_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                AVG(payable_amount) as average_amount,
                SUM(CASE WHEN internal_status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN internal_status = 'issued' THEN 1 ELSE 0 END) as issued,
                SUM(CASE WHEN internal_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_internal
                FROM electronic_invoices ei";
        
        $params = [];
        if ($companyId) {
            $sql .= " INNER JOIN users u ON u.id = ei.user_id WHERE u.company_id = ?";
            $params[] = $companyId;
        }
        
        if ($dateFrom) {
            $sql .= ($companyId ? " AND" : " WHERE") . " ei.issue_date >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= ($companyId || $dateFrom ? " AND" : " WHERE") . " ei.issue_date <= ?";
            $params[] = $dateTo;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        
        return [
            'total_invoices' => (int)$result['total_invoices'],
            'total_amount' => (float)$result['total_amount'],
            'accepted' => (int)$result['accepted'],
            'rejected' => (int)$result['rejected'],
            'pending' => (int)$result['pending'],
            'cancelled' => (int)$result['cancelled'],
            'average_amount' => (float)$result['average_amount'],
            'by_status' => [
                'draft' => (int)$result['draft'],
                'issued' => (int)$result['issued'],
                'cancelled' => (int)$result['cancelled_internal']
            ]
        ];
    }
}


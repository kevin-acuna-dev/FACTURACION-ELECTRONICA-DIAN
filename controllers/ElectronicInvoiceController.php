<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/ElectronicInvoice.php';
require_once __DIR__ . '/../models/ElectronicDocument.php';
require_once __DIR__ . '/../models/CreditDebitNote.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../services/InvoiceService.php';
require_once __DIR__ . '/../services/DianSimulatorService.php';
require_once __DIR__ . '/../config/dian.php';

class ElectronicInvoiceController {
    private $invoiceService;
    private $dianService;
    
    public function __construct() {
        $this->invoiceService = new InvoiceService();
        
        if (defined('USE_REAL_DIAN') && USE_REAL_DIAN === true && file_exists(__DIR__ . '/../services/DianRealService.php')) {
            require_once __DIR__ . '/../services/DianRealService.php';
            $this->dianService = new DianRealService();
        } else {
            $this->dianService = new DianSimulatorService();
        }
    }
    
    public function index() {
        $filters = [];
        if (isset($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
        if (isset($_GET['dian_status'])) $filters['dian_status'] = $_GET['dian_status'];
        if (isset($_GET['internal_status'])) $filters['internal_status'] = $_GET['internal_status'];
        if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
        if (isset($_GET['per_page'])) $filters['per_page'] = $_GET['per_page'];
        
        $invoices = $this->invoiceService->listInvoices($filters);
        Response::success($invoices);
    }
    
    public function store() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['buyer_id'])) {
            Response::error('buyer_id es requerido', 422);
        }
        
        if (empty($data['items']) || !is_array($data['items'])) {
            Response::error('items es requerido y debe ser un array', 422);
        }
        
        if (!isset($data['user_id'])) {
            $user = Auth::user();
            if (!$user) {
                Response::error('Usuario no autenticado', 401);
            }
            $data['user_id'] = $user->id;
        }
        
        $result = $this->invoiceService->createInvoice($data);
        
        if ($result['success']) {
            Response::success($result['data'], $result['message'], 201);
        } else {
            Response::error($result['message'], 400);
        }
    }
    
    public function show($id) {
        $invoice = $this->invoiceService->getInvoiceComplete($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        Response::success($invoice);
    }
    
    public function update($id) {
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        if ($invoice->internal_status !== 'draft') {
            Response::error('Solo se pueden editar facturas en estado borrador', 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['buyer_id'])) {
            $loggedUser = Auth::user();
            $buyerModel = new User();
            $buyer = $buyerModel->find($data['buyer_id']);
            
            if (!$buyer) {
                Response::error('Cliente no encontrado', 404);
            }
            
            if (!($buyer instanceof User)) {
                Response::error('Error al obtener datos del cliente', 400);
            }
            
            if ($buyer->company_id !== $loggedUser->company_id) {
                Response::error('El cliente no pertenece a su empresa', 400);
            }
            
            $buyerRole = $buyer->role();
            if (!$buyerRole || $buyerRole->role_name !== 'cliente') {
                Response::error('El usuario seleccionado no es un cliente', 400);
            }
        }
        
        $invoice->update($data);
        Response::success($invoice->toArray(), 'Factura actualizada exitosamente');
    }
    
    public function destroy($id) {
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        if ($invoice->internal_status !== 'draft') {
            Response::error('Solo se pueden eliminar facturas en estado borrador', 400);
        }
        
        $invoice->delete();
        Response::success(null, 'Factura eliminada exitosamente');
    }
    
    public function sendToDian($id) {
        $result = $this->invoiceService->sendToDian($id);
        
        if ($result['success']) {
            Response::success($result['data'], $result['message']);
        } else {
            Response::error($result['message'], 400, $result['data'] ?? null);
        }
    }
    
    public function checkStatus($id) {
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        if (!$invoice->uuid) {
            Response::error('Esta factura aún no ha sido enviada a la DIAN', 400);
        }
        
        $result = $this->dianService->checkInvoiceStatus($invoice->uuid);
        Response::json($result);
    }
    
    public function cancel($id) {
        $result = $this->invoiceService->cancelInvoice($id);
        
        if ($result['success']) {
            require_once __DIR__ . '/../services/WebhookService.php';
            $webhookService = new WebhookService();
            $webhookService->triggerWebhook('invoice.cancelled', [
                'invoice_id' => $id,
                'status' => 'cancelled'
            ], $id);
            
            Response::success($result['data'], $result['message']);
        } else {
            Response::error($result['message'], 400);
        }
    }
    
    public function createData() {
        $loggedUser = Auth::user();
        
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $db = Database::getInstance();
        
        $products = $db->fetchAll(
            "SELECT id, product_code, name, description, unit_price, measurement_unit_id, status 
             FROM products WHERE company_id = ? AND status = 'Active' ORDER BY name",
            [$loggedUser->company_id]
        );
        
        foreach ($products as &$product) {
            $taxes = $db->fetchAll(
                "SELECT t.* FROM taxes t 
                 INNER JOIN product_tax pt ON pt.tax_id = t.id 
                 WHERE pt.product_id = ?",
                [$product['id']]
            );
            $product['taxes'] = $taxes;
            
            $unit = $db->fetchOne("SELECT * FROM measurement_units WHERE id = ?", [$product['measurement_unit_id']]);
            $product['measurementUnit'] = $unit;
        }
        
        $services = $db->fetchAll(
            "SELECT id, service_code, name, description, unit_price, measurement_unit_id, status 
             FROM services WHERE company_id = ? AND status = 'Active' ORDER BY name",
            [$loggedUser->company_id]
        );
        
        foreach ($services as &$service) {
            $taxes = $db->fetchAll(
                "SELECT t.* FROM taxes t 
                 INNER JOIN service_tax st ON st.tax_id = t.id 
                 WHERE st.service_id = ?",
                [$service['id']]
            );
            $service['taxes'] = $taxes;
            
            $unit = $db->fetchOne("SELECT * FROM measurement_units WHERE id = ?", [$service['measurement_unit_id']]);
            $service['measurementUnit'] = $unit;
        }
        
        $clients = $db->fetchAll(
            "SELECT u.id, u.first_name, u.document_type, u.document_number, u.email, u.phone, u.address 
             FROM users u 
             INNER JOIN roles r ON r.id = u.role_id 
             WHERE u.company_id = ? AND r.role_name = 'cliente' AND u.status = 'Active' 
             ORDER BY u.first_name",
            [$loggedUser->company_id]
        );
        
        Response::success([
            'products' => $products,
            'services' => $services,
            'clients' => $clients
        ]);
    }
    
    public function getClients() {
        $loggedUser = Auth::user();
        
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $db = Database::getInstance();
        $clients = $db->fetchAll(
            "SELECT u.id, u.first_name, u.document_type, u.document_number, u.email, u.phone, u.address 
             FROM users u 
             INNER JOIN roles r ON r.id = u.role_id 
             WHERE u.company_id = ? AND r.role_name = 'cliente' AND u.status = 'Active' 
             ORDER BY u.first_name",
            [$loggedUser->company_id]
        );
        
        Response::success($clients);
    }
    
    public function stats() {
        $companyId = $_GET['company_id'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        $stats = $this->invoiceService->getInvoiceStats($companyId, $dateFrom, $dateTo);
        Response::success($stats);
    }
    
    public function generateQR($id) {
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        if (!$invoice->uuid) {
            Response::error('Esta factura no tiene CUFE generado', 400);
        }
        
        $qrUrl = $this->dianService->generateQRUrl($invoice, $invoice->uuid);
        
        Response::success([
            'qr_url' => $qrUrl,
            'cufe' => $invoice->uuid
        ]);
    }
    
    public function downloadXML($id) {
        $db = Database::getInstance();
        $doc = $db->fetchOne(
            "SELECT xml_document FROM electronic_documents WHERE electronic_invoice_id = ? AND credit_debit_note_id IS NULL ORDER BY id DESC LIMIT 1",
            [$id]
        );
        
        if (!$doc || !$doc['xml_document']) {
            Response::error('Documento electrónico no encontrado', 404);
        }
        
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="invoice-' . $id . '.xml"');
        echo $doc['xml_document'];
        exit;
    }
    
    public function previewTemplate($id) {
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        require_once __DIR__ . '/../services/TemplateService.php';
        $templateService = new TemplateService();
        
        $templateId = $_GET['template_id'] ?? null;
        
        if ($templateId) {
            $html = $templateService->getTemplate($templateId, $invoice);
        } else {
            $html = $templateService->getCompanyTemplate($user->company_id, $invoice);
        }
        
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
    
    public function createNote($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['reason'])) {
            Response::error('reason es requerido', 422);
        }
        
        if (empty($data['note_type']) || !in_array($data['note_type'], ['debit', 'credit'])) {
            Response::error('note_type debe ser credit o debit', 422);
        }
        
        if (empty($data['total_amount'])) {
            Response::error('total_amount es requerido', 422);
        }
        
        $invoiceModel = new ElectronicInvoice();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        if (!($invoice instanceof ElectronicInvoice)) {
            Response::error('Error al obtener datos de la factura', 400);
        }
        
        $amount = (float)$data['total_amount'];
        
        if ($data['note_type'] === 'credit' && $amount > (float)($invoice->payable_amount ?? 0)) {
            Response::error('El valor de la nota crédito no puede exceder el total de la factura', 400);
        }
        
        $user = $invoice->user();
        if (!$user) {
            Response::error('La factura no tiene usuario asociado', 400);
        }
        $companyId = $user->company_id;
        $prefix = $data['note_type'] === 'credit' ? 'CN' : 'DN';
        $noteNumber = $prefix . '-' . $companyId . '-' . date('YmdHis');
        
        $note = new CreditDebitNote();
        $noteData = [
            'electronic_invoice_id' => $invoice->id,
            'reason' => $data['reason'],
            'note_type' => $data['note_type'],
            'note_number' => $noteNumber,
            'status' => 'pending',
            'issue_date' => date('Y-m-d H:i:s'),
            'total_amount' => $amount
        ];
        
        $note = $note->create($noteData);
        Response::success($note->toArray(), null, 201);
    }
    
    public function listNotes($id) {
        $db = Database::getInstance();
        $notes = $db->fetchAll(
            "SELECT * FROM credit_debit_notes WHERE electronic_invoice_id = ? ORDER BY id DESC",
            [$id]
        );
        
        Response::success($notes);
    }
    
    public function annulWithCreditNote($id) {
        $invoiceModel = new ElectronicInvoice();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        if (!($invoice instanceof ElectronicInvoice)) {
            Response::error('Error al obtener datos de la factura', 400);
        }
        
        $amount = (float)($invoice->payable_amount ?? 0);
        
        if ($amount <= 0) {
            Response::error('La factura no tiene total pagadero válido para anulación', 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['reason'])) {
            Response::error('reason es requerido', 422);
        }
        
        $user = $invoice->user();
        if (!$user) {
            Response::error('La factura no tiene usuario asociado', 400);
        }
        $companyId = $user->company_id;
        $noteNumber = 'CN-' . $companyId . '-' . date('YmdHis');
        
        $note = new CreditDebitNote();
        $noteData = [
            'electronic_invoice_id' => $invoice->id,
            'reason' => $data['reason'],
            'note_type' => 'credit',
            'note_number' => $noteNumber,
            'status' => 'pending',
            'issue_date' => date('Y-m-d H:i:s'),
            'total_amount' => $amount
        ];
        
        $note = $note->create($noteData);
        Response::success($note->toArray(), null, 201);
    }
}


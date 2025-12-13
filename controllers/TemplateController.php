<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../services/TemplateService.php';

class TemplateController {
    private $templateService;
    
    public function __construct() {
        $this->templateService = new TemplateService();
    }
    
    public function index() {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $templates = $this->templateService->listTemplates($user->company_id);
        Response::success($templates);
    }
    
    public function store() {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            Response::error('name es requerido', 422);
        }
        
        if (empty($data['html_content'])) {
            Response::error('html_content es requerido', 422);
        }
        
        $data['company_id'] = $user->company_id;
        $template = $this->templateService->createTemplate($data);
        
        Response::success($template, 'Plantilla creada exitosamente', 201);
    }
    
    public function update($id) {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $template = $this->templateService->db->fetchOne(
            "SELECT * FROM invoice_templates WHERE id = ? AND company_id = ?",
            [$id, $user->company_id]
        );
        
        if (!$template) {
            Response::error('Plantilla no encontrada', 404);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $updated = $this->templateService->updateTemplate($id, $data);
        
        if ($updated) {
            Response::success($updated, 'Plantilla actualizada exitosamente');
        } else {
            Response::error('Error al actualizar plantilla', 400);
        }
    }
    
    public function destroy($id) {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $template = $this->templateService->db->fetchOne(
            "SELECT * FROM invoice_templates WHERE id = ? AND company_id = ?",
            [$id, $user->company_id]
        );
        
        if (!$template) {
            Response::error('Plantilla no encontrada', 404);
        }
        
        $this->templateService->deleteTemplate($id);
        Response::success(null, 'Plantilla eliminada exitosamente');
    }
    
    public function preview($id) {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $invoiceId = $_GET['invoice_id'] ?? null;
        
        if (!$invoiceId) {
            Response::error('invoice_id es requerido', 422);
        }
        
        require_once __DIR__ . '/../models/ElectronicInvoice.php';
        $invoice = new ElectronicInvoice();
        $invoice = $invoice->find($invoiceId);
        
        if (!$invoice) {
            Response::error('Factura no encontrada', 404);
        }
        
        $html = $this->templateService->getTemplate($id, $invoice);
        
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}


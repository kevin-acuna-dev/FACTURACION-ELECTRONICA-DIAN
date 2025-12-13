<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../services/WebhookService.php';

class WebhookController {
    private $webhookService;
    
    public function __construct() {
        $this->webhookService = new WebhookService();
    }
    
    public function index() {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $eventType = $_GET['event_type'] ?? null;
        $webhooks = $this->webhookService->listWebhooks($user->company_id, $eventType);
        
        Response::success($webhooks);
    }
    
    public function store() {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['event_type'])) {
            Response::error('event_type es requerido', 422);
        }
        
        if (empty($data['url'])) {
            Response::error('url es requerido', 422);
        }
        
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            Response::error('URL inválida', 422);
        }
        
        $validEvents = [
            'invoice.created',
            'invoice.accepted',
            'invoice.rejected',
            'invoice.cancelled',
            'certificate.expiring',
            'certificate.expired'
        ];
        
        if (!in_array($data['event_type'], $validEvents)) {
            Response::error('Tipo de evento inválido', 422);
        }
        
        $data['company_id'] = $user->company_id;
        $webhook = $this->webhookService->createWebhook($data);
        
        Response::success($webhook, 'Webhook creado exitosamente', 201);
    }
    
    public function update($id) {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $webhook = $this->webhookService->db->fetchOne(
            "SELECT * FROM webhooks WHERE id = ? AND company_id = ?",
            [$id, $user->company_id]
        );
        
        if (!$webhook) {
            Response::error('Webhook no encontrado', 404);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            Response::error('URL inválida', 422);
        }
        
        $updated = $this->webhookService->updateWebhook($id, $data);
        
        if ($updated) {
            Response::success($updated, 'Webhook actualizado exitosamente');
        } else {
            Response::error('Error al actualizar webhook', 400);
        }
    }
    
    public function destroy($id) {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $webhook = $this->webhookService->db->fetchOne(
            "SELECT * FROM webhooks WHERE id = ? AND company_id = ?",
            [$id, $user->company_id]
        );
        
        if (!$webhook) {
            Response::error('Webhook no encontrado', 404);
        }
        
        $this->webhookService->deleteWebhook($id);
        Response::success(null, 'Webhook eliminado exitosamente');
    }
    
    public function logs($id) {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            Response::error('Usuario no autenticado o sin empresa asociada', 401);
        }
        
        $webhook = $this->webhookService->db->fetchOne(
            "SELECT * FROM webhooks WHERE id = ? AND company_id = ?",
            [$id, $user->company_id]
        );
        
        if (!$webhook) {
            Response::error('Webhook no encontrado', 404);
        }
        
        $logs = $this->webhookService->db->fetchAll(
            "SELECT * FROM webhook_logs WHERE webhook_id = ? ORDER BY created_at DESC LIMIT 100",
            [$id]
        );
        
        Response::success($logs);
    }
}


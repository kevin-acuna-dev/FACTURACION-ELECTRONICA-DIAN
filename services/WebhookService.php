<?php

require_once __DIR__ . '/../core/Database.php';

class WebhookService {
    public $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function triggerWebhook($event, $data, $invoiceId = null) {
        $webhooks = $this->getActiveWebhooks($event);
        
        if (empty($webhooks)) {
            return ['triggered' => 0, 'results' => []];
        }
        
        $results = [];
        foreach ($webhooks as $webhook) {
            $result = $this->sendWebhook($webhook, $event, $data);
            $results[] = $result;
            
            $this->logWebhookCall($webhook['id'], $event, $result);
        }
        
        return [
            'triggered' => count($webhooks),
            'results' => $results
        ];
    }
    
    private function sendWebhook($webhook, $event, $data) {
        $payload = [
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: FacturacionElectronica/1.0',
                'X-Webhook-Event: ' . $event,
                'X-Webhook-Signature: ' . $this->generateSignature($payload, $webhook['secret'])
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if (!empty($webhook['headers'])) {
            $customHeaders = json_decode($webhook['headers'], true);
            if (is_array($customHeaders)) {
                foreach ($customHeaders as $key => $value) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                        curl_getinfo($ch, CURLINFO_HEADER_OUT) ?: [],
                        ["{$key}: {$value}"]
                    ));
                }
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $success = $httpCode >= 200 && $httpCode < 300 && empty($curlError);
        
        return [
            'webhook_id' => $webhook['id'],
            'url' => $webhook['url'],
            'success' => $success,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $curlError
        ];
    }
    
    private function generateSignature($payload, $secret) {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
    
    private function getActiveWebhooks($event) {
        return $this->db->fetchAll(
            "SELECT * FROM webhooks WHERE event_type = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
            [$event]
        );
    }
    
    private function logWebhookCall($webhookId, $event, $result) {
        try {
            $this->db->query(
                "INSERT INTO webhook_logs (webhook_id, event_type, success, http_code, response_body, error_message, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $webhookId,
                    $event,
                    $result['success'] ? 1 : 0,
                    $result['http_code'],
                    $result['response'],
                    $result['error']
                ]
            );
        } catch (Exception $e) {
            error_log('Error al guardar log de webhook: ' . $e->getMessage());
        }
    }
    
    public function createWebhook($data) {
        $sql = "INSERT INTO webhooks (company_id, event_type, url, secret, headers, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())";
        
        $secret = $data['secret'] ?? bin2hex(random_bytes(32));
        $headers = isset($data['headers']) ? json_encode($data['headers']) : null;
        
        $this->db->query($sql, [
            $data['company_id'],
            $data['event_type'],
            $data['url'],
            $secret,
            $headers
        ]);
        
        $webhookId = $this->db->lastInsertId();
        return $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
    }
    
    public function updateWebhook($id, $data) {
        $updates = [];
        $params = [];
        
        if (isset($data['url'])) {
            $updates[] = "url = ?";
            $params[] = $data['url'];
        }
        
        if (isset($data['secret'])) {
            $updates[] = "secret = ?";
            $params[] = $data['secret'];
        }
        
        if (isset($data['headers'])) {
            $updates[] = "headers = ?";
            $params[] = json_encode($data['headers']);
        }
        
        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['expires_at'])) {
            $updates[] = "expires_at = ?";
            $params[] = $data['expires_at'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE webhooks SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->query($sql, $params);
        
        return $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$id]);
    }
    
    public function deleteWebhook($id) {
        $this->db->query("DELETE FROM webhooks WHERE id = ?", [$id]);
        return true;
    }
    
    public function listWebhooks($companyId, $eventType = null) {
        $sql = "SELECT * FROM webhooks WHERE company_id = ?";
        $params = [$companyId];
        
        if ($eventType) {
            $sql .= " AND event_type = ?";
            $params[] = $eventType;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
}


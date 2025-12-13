<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../models/ElectronicInvoice.php';

class TemplateService {
    public $db;
    private $templatesPath;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->templatesPath = __DIR__ . '/../templates/invoices/';
        
        if (!is_dir($this->templatesPath)) {
            @mkdir($this->templatesPath, 0755, true);
        }
    }
    
    public function getTemplate($templateId, $invoice) {
        $template = $this->db->fetchOne(
            "SELECT * FROM invoice_templates WHERE id = ? AND status = 'active'",
            [$templateId]
        );
        
        if (!$template) {
            return $this->getDefaultTemplate($invoice);
        }
        
        $html = $template['html_content'];
        return $this->renderTemplate($html, $invoice, $template);
    }
    
    public function getCompanyTemplate($companyId, $invoice) {
        $template = $this->db->fetchOne(
            "SELECT * FROM invoice_templates WHERE company_id = ? AND is_default = 1 AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$companyId]
        );
        
        if (!$template) {
            return $this->getDefaultTemplate($invoice);
        }
        
        $html = $template['html_content'];
        return $this->renderTemplate($html, $invoice, $template);
    }
    
    private function getDefaultTemplate($invoice) {
        $defaultHtml = file_get_contents(__DIR__ . '/../templates/invoices/default.html');
        
        if ($defaultHtml === false) {
            return $this->generateBasicTemplate($invoice);
        }
        
        return $this->renderTemplate($defaultHtml, $invoice);
    }
    
    private function renderTemplate($html, $invoice, $template = null) {
        if (!($invoice instanceof ElectronicInvoice)) {
            throw new Exception('El parámetro invoice debe ser una instancia de ElectronicInvoice');
        }
        
        $user = $invoice->user();
        $company = $user ? $user->company() : null;
        $buyer = $invoice->buyer();
        $details = $invoice->invoiceDetails();
        
        $data = [
            'invoice' => [
                'number' => $invoice->invoice_number,
                'date' => date('d/m/Y', strtotime($invoice->issue_date)),
                'time' => date('H:i:s', strtotime($invoice->issue_date)),
                'cufe' => $invoice->uuid ?? '',
                'protocol_number' => $invoice->protocol_number ?? '',
                'validation_date' => $invoice->validation_date ? date('d/m/Y H:i:s', strtotime($invoice->validation_date)) : '',
                'type' => $invoice->invoice_type_code ?? '01',
                'currency' => $invoice->document_currency_code ?? 'COP'
            ],
            'company' => $company ? [
                'name' => $company->business_name,
                'nit' => $company->nit,
                'address' => $company->address,
                'city' => $company->city,
                'phone' => $company->phone,
                'email' => $company->email
            ] : [],
            'buyer' => $buyer ? [
                'name' => $buyer->first_name,
                'document_type' => $buyer->document_type,
                'document_number' => $buyer->document_number,
                'address' => $buyer->address,
                'phone' => $buyer->phone,
                'email' => $buyer->email
            ] : [],
            'items' => [],
            'totals' => [
                'subtotal' => number_format($invoice->tax_exclusive_amount, 2, ',', '.'),
                'tax' => number_format($invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount, 2, ',', '.'),
                'total' => number_format($invoice->payable_amount, 2, ',', '.')
            ],
            'template' => $template ? [
                'name' => $template['name'],
                'style' => $template['css_content'] ?? ''
            ] : []
        ];
        
        foreach ($details as $detail) {
            $data['items'][] = [
                'code' => $detail->item_code ?? '',
                'description' => $detail->description,
                'quantity' => number_format($detail->quantity, 2, ',', '.'),
                'unit_price' => number_format($detail->unit_price, 2, ',', '.'),
                'discount' => number_format($detail->discount_amount ?? 0, 2, ',', '.'),
                'tax' => number_format($detail->tax_amount ?? 0, 2, ',', '.'),
                'total' => number_format($detail->line_extension_amount, 2, ',', '.')
            ];
        }
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (!is_array($subValue)) {
                        $html = str_replace("{{" . strtoupper($key) . "." . strtoupper($subKey) . "}}", htmlspecialchars($subValue ?? '', ENT_QUOTES, 'UTF-8'), $html);
                    }
                }
            } else {
                $html = str_replace("{{" . strtoupper($key) . "}}", htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $html);
            }
        }
        
        $html = preg_replace_callback('/\{\{ITEMS\}\}(.*?)\{\{\/ITEMS\}\}/s', function($matches) use ($data) {
            $itemTemplate = $matches[1];
            $itemsHtml = '';
            foreach ($data['items'] as $item) {
                $itemHtml = $itemTemplate;
                foreach ($item as $key => $value) {
                    $itemHtml = str_replace("{{ITEM." . strtoupper($key) . "}}", htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $itemHtml);
                }
                $itemsHtml .= $itemHtml;
            }
            return $itemsHtml;
        }, $html);
        
        if ($template && !empty($template['css_content'])) {
            if (strpos($html, '</head>') !== false) {
                $html = str_replace('</head>', '<style>' . $template['css_content'] . '</style></head>', $html);
            } else {
                $html = '<style>' . $template['css_content'] . '</style>' . $html;
            }
        }
        
        return $html;
    }
    
    private function generateBasicTemplate($invoice) {
        $user = $invoice->user();
        $company = $user ? $user->company() : null;
        $buyer = $invoice->buyer();
        
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Factura</title></head><body>';
        $html .= '<h1>FACTURA ELECTRÓNICA</h1>';
        $html .= '<p><strong>Número:</strong> ' . htmlspecialchars($invoice->invoice_number) . '</p>';
        $html .= '<p><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($invoice->issue_date)) . '</p>';
        
        if ($company) {
            $html .= '<h2>Emisor</h2>';
            $html .= '<p>' . htmlspecialchars($company->business_name) . '</p>';
            $html .= '<p>NIT: ' . htmlspecialchars($company->nit) . '</p>';
        }
        
        if ($buyer) {
            $html .= '<h2>Cliente</h2>';
            $html .= '<p>' . htmlspecialchars($buyer->first_name) . '</p>';
            $html .= '<p>Documento: ' . htmlspecialchars($buyer->document_number) . '</p>';
        }
        
        $html .= '<h2>Total: $' . number_format($invoice->payable_amount, 2, ',', '.') . '</h2>';
        $html .= '</body></html>';
        
        return $html;
    }
    
    public function createTemplate($data) {
        $sql = "INSERT INTO invoice_templates (company_id, name, description, html_content, css_content, is_default, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
        
        $this->db->query($sql, [
            $data['company_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['html_content'],
            $data['css_content'] ?? null,
            $data['is_default'] ?? 0
        ]);
        
        $templateId = $this->db->lastInsertId();
        
        if ($data['is_default'] ?? false) {
            $this->db->query(
                "UPDATE invoice_templates SET is_default = 0 WHERE company_id = ? AND id != ?",
                [$data['company_id'], $templateId]
            );
        }
        
        return $this->db->fetchOne("SELECT * FROM invoice_templates WHERE id = ?", [$templateId]);
    }
    
    public function updateTemplate($id, $data) {
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['html_content'])) {
            $updates[] = "html_content = ?";
            $params[] = $data['html_content'];
        }
        
        if (isset($data['css_content'])) {
            $updates[] = "css_content = ?";
            $params[] = $data['css_content'];
        }
        
        if (isset($data['is_default'])) {
            $updates[] = "is_default = ?";
            $params[] = $data['is_default'];
            
            if ($data['is_default']) {
                $template = $this->db->fetchOne("SELECT company_id FROM invoice_templates WHERE id = ?", [$id]);
                if ($template) {
                    $this->db->query(
                        "UPDATE invoice_templates SET is_default = 0 WHERE company_id = ? AND id != ?",
                        [$template['company_id'], $id]
                    );
                }
            }
        }
        
        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE invoice_templates SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->query($sql, $params);
        
        return $this->db->fetchOne("SELECT * FROM invoice_templates WHERE id = ?", [$id]);
    }
    
    public function listTemplates($companyId) {
        return $this->db->fetchAll(
            "SELECT * FROM invoice_templates WHERE company_id = ? ORDER BY is_default DESC, created_at DESC",
            [$companyId]
        );
    }
    
    public function deleteTemplate($id) {
        $this->db->query("DELETE FROM invoice_templates WHERE id = ?", [$id]);
        return true;
    }
}


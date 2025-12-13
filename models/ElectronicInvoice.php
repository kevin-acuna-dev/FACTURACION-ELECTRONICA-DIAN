<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/InvoiceDetail.php';
require_once __DIR__ . '/ElectronicDocument.php';
require_once __DIR__ . '/CreditDebitNote.php';

class ElectronicInvoice extends Model {
    protected $table = 'electronic_invoices';
    protected $fillable = [
        'user_id', 'buyer_id', 'invoice_number', 'issue_date', 'internal_status', 'observation',
        'ubl_version', 'customization_id', 'profile_id', 'uuid', 'document_currency_code', 'invoice_type_code',
        'line_extension_amount', 'tax_exclusive_amount', 'tax_inclusive_amount', 'payable_amount', 'total_discount',
        'dian_status', 'sent_at', 'received_at', 'payment_means_code', 'payment_terms', 'payment_means_name'
    ];
    
    public function user() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE id = ?";
        $data = $db->fetchOne($sql, [$this->user_id]);
        if ($data) {
            $user = new User();
            return $user->hydrate($data);
        }
        return null;
    }
    
    public function buyer() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE id = ?";
        $data = $db->fetchOne($sql, [$this->buyer_id]);
        if ($data) {
            $user = new User();
            return $user->hydrate($data);
        }
        return null;
    }
    
    public function invoiceDetails() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM invoice_details WHERE electronic_invoice_id = ?";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $detail = new InvoiceDetail();
            return $detail->hydrate($data);
        }, $results);
    }
    
    public function electronicDocuments() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM electronic_documents WHERE electronic_invoice_id = ? AND credit_debit_note_id IS NULL";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $doc = new ElectronicDocument();
            return $doc->hydrate($data);
        }, $results);
    }
    
    public function creditDebitNotes() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM credit_debit_notes WHERE electronic_invoice_id = ?";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $note = new CreditDebitNote();
            return $note->hydrate($data);
        }, $results);
    }
}


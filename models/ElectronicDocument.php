<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

class ElectronicDocument extends Model {
    protected $table = 'electronic_documents';
    protected $fillable = [
        'electronic_invoice_id', 'credit_debit_note_id', 'dian_numbering_id', 'cufe', 'cude',
        'xml_document', 'dian_status', 'validation_date', 'digital_signature', 'document_hash',
        'description', 'environment', 'document_type', 'qr_code', 'cdr', 'emission_mode'
    ];
}


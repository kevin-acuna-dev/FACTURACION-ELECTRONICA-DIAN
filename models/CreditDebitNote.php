<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

class CreditDebitNote extends Model {
    protected $table = 'credit_debit_notes';
    protected $fillable = [
        'electronic_invoice_id', 'reason', 'note_type', 'note_number', 'status',
        'issue_date', 'total_amount'
    ];
}


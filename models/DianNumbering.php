<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

class DianNumbering extends Model {
    protected $table = 'dian_numberings';
    protected $fillable = [
        'company_id', 'document_type', 'prefix', 'start_number', 'end_number',
        'current_status', 'validity_start_date', 'validity_end_date', 'resolution_number'
    ];
}


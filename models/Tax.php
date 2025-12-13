<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

class Tax extends Model {
    protected $table = 'taxes';
    protected $fillable = [
        'company_id', 'name', 'type', 'percentage', 'fixed_value', 'application_type', 'status'
    ];
}


<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';

class MeasurementUnit extends Model {
    protected $table = 'measurement_units';
    protected $fillable = ['company_id', 'code', 'name', 'description'];
}


<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Tax.php';
require_once __DIR__ . '/MeasurementUnit.php';

class Service extends Model {
    protected $table = 'services';
    protected $fillable = [
        'company_id', 'service_code', 'name', 'description', 'unit_price',
        'measurement_unit_id', 'status'
    ];
    
    public function taxes() {
        $db = Database::getInstance();
        $sql = "SELECT t.* FROM taxes t 
                INNER JOIN service_tax st ON st.tax_id = t.id 
                WHERE st.service_id = ?";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $tax = new Tax();
            return $tax->hydrate($data);
        }, $results);
    }
    
    public function measurementUnit() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM measurement_units WHERE id = ?";
        $data = $db->fetchOne($sql, [$this->measurement_unit_id]);
        if ($data) {
            $unit = new MeasurementUnit();
            return $unit->hydrate($data);
        }
        return null;
    }
}


<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Tax.php';
require_once __DIR__ . '/MeasurementUnit.php';

class Product extends Model {
    protected $table = 'products';
    protected $fillable = [
        'company_id', 'product_code', 'name', 'description', 'unit_price',
        'measurement_unit_id', 'status'
    ];
    
    public function taxes() {
        $db = Database::getInstance();
        $sql = "SELECT t.* FROM taxes t 
                INNER JOIN product_tax pt ON pt.tax_id = t.id 
                WHERE pt.product_id = ?";
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


<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/DianNumbering.php';
require_once __DIR__ . '/DigitalCertificate.php';

class Company extends Model {
    protected $table = 'companies';
    protected $fillable = [
        'business_name', 'nit', 'trade_name', 'address', 'city', 'department',
        'country', 'phone', 'email', 'tax_regime', 'ciiu_code', 'logo_url',
        'legal_representative_name', 'legal_representative_document_type', 'legal_representative_document_number'
    ];
    
    public function users() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE company_id = ?";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $user = new User();
            return $user->hydrate($data);
        }, $results);
    }
    
    public function dianNumberings() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM dian_numberings WHERE company_id = ?";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $numbering = new DianNumbering();
            return $numbering->hydrate($data);
        }, $results);
    }
    
    public function digitalCertificates() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM digital_certificates WHERE company_id = ?";
        $results = $db->fetchAll($sql, [$this->id]);
        return array_map(function($data) {
            $cert = new DigitalCertificate();
            return $cert->hydrate($data);
        }, $results);
    }
}


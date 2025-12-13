<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Company.php';

class DigitalCertificate extends Model {
    protected $table = 'digital_certificates';
    protected $fillable = [
        'company_id', 'certificate_name', 'certificate_path', 'serial_number', 'password',
        'start_date', 'end_date', 'status', 'issuer', 'certificate_type', 'signature_algorithm', 'uuid', 'description'
    ];
    
    public function company() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM companies WHERE id = ?";
        $data = $db->fetchOne($sql, [$this->company_id]);
        if ($data) {
            $company = new Company();
            return $company->hydrate($data);
        }
        return null;
    }
}


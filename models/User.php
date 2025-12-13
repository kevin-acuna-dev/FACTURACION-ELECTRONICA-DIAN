<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Company.php';
require_once __DIR__ . '/Role.php';

class User extends Model {
    protected $table = 'users';
    protected $fillable = [
        'company_id', 'role_id', 'first_name', 'document_type', 'document_number',
        'address', 'country', 'description', 'password', 'email', 'phone', 'status', 'last_access'
    ];
    protected $hidden = ['password'];
    
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
    
    public function role() {
        $db = Database::getInstance();
        $sql = "SELECT * FROM roles WHERE id = ?";
        $data = $db->fetchOne($sql, [$this->role_id]);
        if ($data) {
            $role = new Role();
            return $role->hydrate($data);
        }
        return null;
    }
}


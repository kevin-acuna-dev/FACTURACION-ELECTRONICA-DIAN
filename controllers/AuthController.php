<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../core/Database.php';

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $errors = $this->validateRegister($data);
        if (!empty($errors)) {
            Response::error('Error de validación', 422, $errors);
        }
        
        $this->db->beginTransaction();
        try {
            $company = new Company();
            $companyData = [
                'business_name' => $data['business_name'],
                'nit' => $data['nit'],
                'email' => $data['company_email'],
                'legal_representative_name' => $data['legal_representative_name'] ?? null,
                'legal_representative_document_type' => $data['legal_representative_document_type'] ?? null,
                'legal_representative_document_number' => $data['legal_representative_document_number'] ?? null,
                'trade_name' => $data['trade_name'] ?? '',
                'address' => $data['address'] ?? 'Sin definir',
                'city' => $data['city'] ?? 'Sin definir',
                'department' => $data['department'] ?? 'Sin definir',
                'country' => $data['country'] ?? 'Sin definir',
                'phone' => $data['phone'] ?? 'Sin definir',
                'tax_regime' => $data['tax_regime'] ?? 'Sin definir',
                'logo_url' => $data['logo_url'] ?? '',
                'ciiu_code' => $data['ciiu_code'] ?? ''
            ];
            $company = $company->create($companyData);
            
            $role = new Role();
            $adminRole = $role->where('role_name', 'administrador')->first();
            if (!$adminRole) {
                $this->db->rollBack();
                Response::error('Rol administrador no encontrado', 500);
            }
            
            $user = new User();
            $userData = [
                'first_name' => $data['first_name'],
                'email' => $data['user_email'],
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'company_id' => $company->id,
                'role_id' => $adminRole->id,
                'status' => 'Active'
            ];
            $user = $user->create($userData);
            
            $this->db->commit();
            
            $token = Auth::createToken($user);
            
            Response::success([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'company_id' => $user->company_id,
                    'role_id' => $user->role_id,
                    'document_number' => $user->document_number
                ],
                'company' => [
                    'id' => $company->id,
                    'business_name' => $company->business_name,
                    'nit' => $company->nit,
                    'email' => $company->email
                ]
            ], 'Empresa y usuario administrador registrados correctamente', 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Error al registrar: ' . $e->getMessage(), 500);
        }
    }
    
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email']) || empty($data['password'])) {
            Response::error('Email y contraseña son requeridos', 422);
        }
        
        $user = new User();
        $userData = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$data['email']]);
        
        if (!$userData || !password_verify($data['password'], $userData['password'])) {
            Response::error('Credenciales incorrectas', 401);
        }
        
        $user->hydrate($userData);
        
        if (strtolower($user->status) === 'inactive') {
            Response::error('Usuario desactivado', 403);
        }
        
        if (!($user instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $user->company();
        $token = Auth::createToken($user);
        
        Response::success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role_id' => $user->role_id,
                'document_number' => $user->document_number
            ],
            'company' => $company ? [
                'id' => $company->id,
                'business_name' => $company->business_name,
                'nit' => $company->nit,
                'email' => $company->email
            ] : null
        ], 'Login exitoso');
    }
    
    public function me() {
        $user = Auth::user();
        if (!$user) {
            Response::error('No autorizado', 401);
        }
        
        if (!($user instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $user->company();
        
        Response::success([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'document_type' => $user->document_type,
                'document_number' => $user->document_number,
                'address' => $user->address,
                'country' => $user->country,
                'description' => $user->description,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'last_access' => $user->last_access
            ],
            'company' => $company ? [
                'id' => $company->id,
                'business_name' => $company->business_name,
                'nit' => $company->nit,
                'trade_name' => $company->trade_name,
                'address' => $company->address,
                'city' => $company->city,
                'department' => $company->department,
                'country' => $company->country,
                'phone' => $company->phone,
                'email' => $company->email,
                'tax_regime' => $company->tax_regime,
                'ciiu_code' => $company->ciiu_code,
                'logo_url' => $company->logo_url,
                'legal_representative_name' => $company->legal_representative_name,
                'legal_representative_document_type' => $company->legal_representative_document_type,
                'legal_representative_document_number' => $company->legal_representative_document_number
            ] : null
        ]);
    }
    
    public function logout() {
        $user = Auth::user();
        if (!$user) {
            Response::error('No autorizado', 401);
        }
        
        $token = Auth::getBearerToken();
        if ($token) {
            Auth::revokeToken($token);
        }
        
        Response::success(null, 'Sesión cerrada correctamente');
    }
    
    public function completeRegistration() {
        $user = Auth::user();
        if (!$user) {
            Response::error('No autorizado', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!($user instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $user->company();
        
        if (!$company) {
            Response::error('No se encontró la empresa asociada', 404);
        }
        
        $this->db->beginTransaction();
        try {
            $companyData = [];
            $fields = ['business_name', 'nit', 'trade_name', 'address', 'city', 'department', 
                      'country', 'phone', 'email', 'tax_regime', 'ciiu_code', 
                      'legal_representative_name', 'legal_representative_document_type', 'legal_representative_document_number'];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $companyData[$field] = $data[$field];
                }
            }
            
            if (!empty($companyData)) {
                $company->update($companyData);
            }
            
            $userData = [];
            $userFields = ['first_name', 'document_type', 'document_number', 'address', 'country', 'phone', 'description'];
            foreach ($userFields as $field) {
                $key = $field === 'address' ? 'user_address' : ($field === 'country' ? 'user_country' : ($field === 'phone' ? 'user_phone' : $field));
                if (isset($data[$key])) {
                    $userData[$field] = $data[$key];
                }
            }
            
            if (!empty($userData)) {
                $user->update($userData);
            }
            
            $this->db->commit();
            
            Response::success([
                'company' => $company->toArray(),
                'user' => $user->toArray()
            ], 'Registro completado exitosamente');
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Error al completar registro: ' . $e->getMessage(), 500);
        }
    }
    
    private function validateRegister($data) {
        $errors = [];
        
        if (empty($data['business_name'])) $errors['business_name'] = 'Razón social requerida';
        if (empty($data['nit'])) $errors['nit'] = 'NIT requerido';
        if (empty($data['company_email'])) $errors['company_email'] = 'Email de empresa requerido';
        if (empty($data['first_name'])) $errors['first_name'] = 'Nombre requerido';
        if (empty($data['user_email'])) $errors['user_email'] = 'Email de usuario requerido';
        if (empty($data['document_type'])) $errors['document_type'] = 'Tipo de documento requerido';
        if (empty($data['document_number'])) $errors['document_number'] = 'Número de documento requerido';
        if (empty($data['password'])) $errors['password'] = 'Contraseña requerida';
        if (isset($data['password']) && strlen($data['password']) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
        }
        
        if (!empty($data['nit'])) {
            $existing = $this->db->fetchOne("SELECT id FROM companies WHERE nit = ?", [$data['nit']]);
            if ($existing) $errors['nit'] = 'El NIT ya está registrado';
        }
        
        if (!empty($data['company_email'])) {
            $existing = $this->db->fetchOne("SELECT id FROM companies WHERE email = ?", [$data['company_email']]);
            if ($existing) $errors['company_email'] = 'El email de empresa ya está registrado';
        }
        
        if (!empty($data['user_email'])) {
            $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$data['user_email']]);
            if ($existing) $errors['user_email'] = 'El email de usuario ya está registrado';
        }
        
        if (!empty($data['document_number'])) {
            $existing = $this->db->fetchOne("SELECT id FROM users WHERE document_number = ?", [$data['document_number']]);
            if ($existing) $errors['document_number'] = 'El número de documento ya está registrado';
        }
        
        return $errors;
    }
}


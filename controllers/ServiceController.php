<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Tax.php';
require_once __DIR__ . '/../models/MeasurementUnit.php';

class ServiceController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function index() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        $status = $_GET['status'] ?? 'Active';
        $sql = "SELECT * FROM services WHERE company_id = ? AND status = ? ORDER BY name";
        $results = $this->db->fetchAll($sql, [$loggedUser->company_id, $status]);
        
        $services = [];
        foreach ($results as $row) {
            $service = new Service();
            $service->hydrate($row);
            if (!($service instanceof Service)) {
                continue;
            }
            $serviceData = $service->toArray();
            $serviceData['taxes'] = array_map(function($t) { return $t->toArray(); }, $service->taxes());
            $unit = $service->measurementUnit();
            $serviceData['measurementUnit'] = $unit ? $unit->toArray() : null;
            $services[] = $serviceData;
        }
        
        Response::success($services);
    }
    
    public function store() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['service_code']) || empty($data['unit_price'])) {
            Response::error('Campos requeridos: name, service_code, unit_price', 422);
        }
        
        $data['company_id'] = $loggedUser->company_id;
        $data['status'] = $data['status'] ?? 'Active';
        
        $service = new Service();
        $service = $service->create($data);
        
        Response::success($service->toArray(), null, 201);
    }
    
    public function show($id) {
        $serviceModel = new Service();
        $service = $serviceModel->find($id);
        
        if (!$service) {
            Response::error('Servicio no encontrado', 404);
        }
        
        if (!($service instanceof Service)) {
            Response::error('Error al obtener datos del servicio', 400);
        }
        
        $serviceData = $service->toArray();
        $serviceData['taxes'] = array_map(function($t) { return $t->toArray(); }, $service->taxes());
        $unit = $service->measurementUnit();
        $serviceData['measurementUnit'] = $unit ? $unit->toArray() : null;
        
        Response::success($serviceData);
    }
    
    public function update($id) {
        $service = new Service();
        $service = $service->find($id);
        
        if (!$service) {
            Response::error('Servicio no encontrado', 404);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $service->update($data);
        
        Response::success($service->toArray(), 'Servicio actualizado');
    }
    
    public function destroy($id) {
        $service = new Service();
        $service = $service->find($id);
        
        if (!$service) {
            Response::error('Servicio no encontrado', 404);
        }
        
        $service->delete();
        Response::success(null, 'Servicio eliminado');
    }
    
    public function active() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        $sql = "SELECT id, service_code, name, description, unit_price, measurement_unit_id, status 
                FROM services WHERE company_id = ? AND status = 'Active' ORDER BY name";
        $results = $this->db->fetchAll($sql, [$loggedUser->company_id]);
        
        foreach ($results as &$service) {
            $taxes = $this->db->fetchAll(
                "SELECT t.* FROM taxes t 
                 INNER JOIN service_tax st ON st.tax_id = t.id 
                 WHERE st.service_id = ?",
                [$service['id']]
            );
            $service['taxes'] = $taxes;
            
            $unit = $this->db->fetchOne("SELECT * FROM measurement_units WHERE id = ?", [$service['measurement_unit_id']]);
            $service['measurementUnit'] = $unit;
        }
        
        Response::success($results);
    }
}


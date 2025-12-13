<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Tax.php';
require_once __DIR__ . '/../models/MeasurementUnit.php';

class ProductController {
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
        $sql = "SELECT * FROM products WHERE company_id = ? AND status = ? ORDER BY name";
        $results = $this->db->fetchAll($sql, [$loggedUser->company_id, $status]);
        
        $products = [];
        foreach ($results as $row) {
            $product = new Product();
            $product->hydrate($row);
            if (!($product instanceof Product)) {
                continue;
            }
            $productData = $product->toArray();
            $productData['taxes'] = array_map(function($t) { return $t->toArray(); }, $product->taxes());
            $unit = $product->measurementUnit();
            $productData['measurementUnit'] = $unit ? $unit->toArray() : null;
            $products[] = $productData;
        }
        
        Response::success($products);
    }
    
    public function store() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['product_code']) || empty($data['unit_price'])) {
            Response::error('Campos requeridos: name, product_code, unit_price', 422);
        }
        
        $data['company_id'] = $loggedUser->company_id;
        $data['status'] = $data['status'] ?? 'Active';
        
        $product = new Product();
        $product = $product->create($data);
        
        Response::success($product->toArray(), null, 201);
    }
    
    public function show($id) {
        $productModel = new Product();
        $product = $productModel->find($id);
        
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }
        
        if (!($product instanceof Product)) {
            Response::error('Error al obtener datos del producto', 400);
        }
        
        $productData = $product->toArray();
        $productData['taxes'] = array_map(function($t) { return $t->toArray(); }, $product->taxes());
        $unit = $product->measurementUnit();
        $productData['measurementUnit'] = $unit ? $unit->toArray() : null;
        
        Response::success($productData);
    }
    
    public function update($id) {
        $product = new Product();
        $product = $product->find($id);
        
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $product->update($data);
        
        Response::success($product->toArray(), 'Producto actualizado');
    }
    
    public function destroy($id) {
        $product = new Product();
        $product = $product->find($id);
        
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }
        
        $product->delete();
        Response::success(null, 'Producto eliminado');
    }
    
    public function active() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        $sql = "SELECT id, product_code, name, description, unit_price, measurement_unit_id, status 
                FROM products WHERE company_id = ? AND status = 'Active' ORDER BY name";
        $results = $this->db->fetchAll($sql, [$loggedUser->company_id]);
        
        foreach ($results as &$product) {
            $taxes = $this->db->fetchAll(
                "SELECT t.* FROM taxes t 
                 INNER JOIN product_tax pt ON pt.tax_id = t.id 
                 WHERE pt.product_id = ?",
                [$product['id']]
            );
            $product['taxes'] = $taxes;
            
            $unit = $this->db->fetchOne("SELECT * FROM measurement_units WHERE id = ?", [$product['measurement_unit_id']]);
            $product['measurementUnit'] = $unit;
        }
        
        Response::success($results);
    }
}


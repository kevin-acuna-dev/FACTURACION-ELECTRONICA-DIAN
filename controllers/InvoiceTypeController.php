<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../services/TaxCalculationService.php';
require_once __DIR__ . '/../services/SectorInvoiceService.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/User.php';

class InvoiceTypeController {
    private $taxCalculationService;
    private $sectorService;
    
    public function __construct() {
        $this->taxCalculationService = new TaxCalculationService();
        $this->sectorService = new SectorInvoiceService();
    }
    
    public function getInvoiceTypes() {
        $types = [
            [
                'code' => '01',
                'name' => 'Factura de Venta',
                'description' => 'Factura electrónica de venta de bienes y/o servicios',
                'applies_iva' => true,
                'requires_export_doc' => false
            ],
            [
                'code' => '02',
                'name' => 'Factura de Exportación',
                'description' => 'Factura para operaciones de exportación de bienes',
                'applies_iva' => false,
                'requires_export_doc' => true
            ],
            [
                'code' => '03',
                'name' => 'Factura de Contingencia',
                'description' => 'Factura emitida en caso de contingencia tecnológica',
                'applies_iva' => true,
                'requires_export_doc' => false
            ],
            [
                'code' => '04',
                'name' => 'Factura de Ajuste',
                'description' => 'Factura para ajustes y correcciones',
                'applies_iva' => true,
                'requires_export_doc' => false
            ]
        ];
        
        Response::success($types);
    }
    
    public function getTaxRegimes() {
        $regimes = [
            [
                'code' => 'Simplificado',
                'name' => 'Régimen Simplificado',
                'applies_iva' => false,
                'iva_rate' => 0,
                'can_export' => false
            ],
            [
                'code' => 'Común',
                'name' => 'Régimen Común',
                'applies_iva' => true,
                'iva_rate' => 19,
                'can_export' => true
            ],
            [
                'code' => 'Gran Contribuyente',
                'name' => 'Gran Contribuyente',
                'applies_iva' => true,
                'iva_rate' => 19,
                'can_export' => true
            ],
            [
                'code' => 'Especial',
                'name' => 'Régimen Especial',
                'applies_iva' => true,
                'iva_rate' => 19,
                'can_export' => true
            ],
            [
                'code' => 'No Responsable',
                'name' => 'No Responsable de IVA',
                'applies_iva' => false,
                'iva_rate' => 0,
                'can_export' => false
            ]
        ];
        
        Response::success($regimes);
    }
    
    public function calculateTaxPreview() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['amount']) || empty($data['invoice_type_code'])) {
            Response::error('amount e invoice_type_code son requeridos', 422);
        }
        
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        if (!($loggedUser instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $loggedUser->company();
        $buyer = null;
        
        if (!empty($data['buyer_id'])) {
            $buyer = new User();
            $buyer = $buyer->find($data['buyer_id']);
        }
        
        $amount = (float)$data['amount'];
        $invoiceTypeCode = $data['invoice_type_code'];
        
        $shouldApplyIVA = $this->taxCalculationService->shouldApplyIVA($company, $buyer, $invoiceTypeCode);
        $ivaRate = $this->taxCalculationService->getIVARate($company, $buyer);
        $ivaAmount = $this->taxCalculationService->calculateIVA($amount, $company, $buyer, $invoiceTypeCode);
        
        $regimeInfo = $this->taxCalculationService->getTaxRegimeInfo($company->tax_regime ?? 'Común');
        
        Response::success([
            'amount' => $amount,
            'invoice_type_code' => $invoiceTypeCode,
            'invoice_type_name' => $this->taxCalculationService->getInvoiceTypeName($invoiceTypeCode),
            'company_regime' => $company->tax_regime ?? 'Común',
            'regime_info' => $regimeInfo,
            'should_apply_iva' => $shouldApplyIVA,
            'iva_rate' => $ivaRate,
            'iva_amount' => $ivaAmount,
            'subtotal' => $amount,
            'total' => $amount + $ivaAmount
        ]);
    }
    
    public function validateInvoiceType() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['invoice_type_code'])) {
            Response::error('invoice_type_code es requerido', 422);
        }
        
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        if (!($loggedUser instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $loggedUser->company();
        $buyer = null;
        
        if (!empty($data['buyer_id'])) {
            $buyer = new User();
            $buyer = $buyer->find($data['buyer_id']);
        }
        
        $validation = $this->taxCalculationService->validateInvoiceConfiguration(
            $company,
            $buyer,
            $data['invoice_type_code']
        );
        
        if ($validation['valid']) {
            Response::success(null, 'La configuración de la factura es válida');
        } else {
            Response::error('Error de validación', 400, $validation['errors']);
        }
    }
    
    public function getSectorInfo() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        if (!($loggedUser instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $loggedUser->company();
        $sectorInfo = $this->sectorService->getSectorInfo($company->ciiu_code ?? '');
        $documentInfo = $this->sectorService->getInvoiceTypeBySector($company);
        
        Response::success([
            'sector_info' => $sectorInfo,
            'document_type' => $documentInfo,
            'required_fields' => $this->sectorService->getRequiredFieldsBySector($company)
        ]);
    }
    
    public function validateSectorConfiguration() {
        $loggedUser = Auth::user();
        if (!$loggedUser || !$loggedUser->company_id) {
            Response::error('Usuario no autenticado', 401);
        }
        
        if (!($loggedUser instanceof User)) {
            Response::error('Error al obtener datos del usuario', 400);
        }
        
        $company = $loggedUser->company();
        $validation = $this->sectorService->validateSectorConfiguration($company);
        
        if ($validation['valid']) {
            Response::success($validation['sector_info'], 'La configuración del sector es válida');
        } else {
            Response::error('Error de validación', 400, $validation['errors']);
        }
    }
}


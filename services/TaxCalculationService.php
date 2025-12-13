<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/SectorInvoiceService.php';

class TaxCalculationService {
    private $db;
    private $sectorService;
    
    const INVOICE_TYPE_VENTA = '01';
    const INVOICE_TYPE_EXPORTACION = '02';
    const INVOICE_TYPE_CONTINGENCIA = '03';
    const INVOICE_TYPE_AJUSTE = '04';
    
    const TAX_REGIME_SIMPLIFICADO = 'Simplificado';
    const TAX_REGIME_COMUN = 'Común';
    const TAX_REGIME_GRAN_CONTRIBUYENTE = 'Gran Contribuyente';
    const TAX_REGIME_ESPECIAL = 'Especial';
    const TAX_REGIME_NO_RESPONSABLE = 'No Responsable';
    
    const PERSON_TYPE_NATURAL = 'Natural';
    const PERSON_TYPE_JURIDICA = 'Jurídica';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->sectorService = new SectorInvoiceService();
    }
    
    public function shouldApplyIVA($company, $buyer, $invoiceTypeCode) {
        if ($invoiceTypeCode === self::INVOICE_TYPE_EXPORTACION) {
            return false;
        }
        
        if ($invoiceTypeCode === self::INVOICE_TYPE_CONTINGENCIA) {
            return $this->shouldApplyIVA($company, $buyer, self::INVOICE_TYPE_VENTA);
        }
        
        $companyRegime = $company->tax_regime ?? '';
        $buyerRegime = $this->getBuyerTaxRegime($buyer);
        
        if ($this->isExemptFromIVA($companyRegime)) {
            return false;
        }
        
        if ($this->isExemptFromIVA($buyerRegime)) {
            return false;
        }
        
        return $this->isResponsibleForIVA($companyRegime);
    }
    
    public function calculateIVA($amount, $company, $buyer, $invoiceTypeCode, $itemType = null) {
        if (!$this->shouldApplyIVA($company, $buyer, $invoiceTypeCode)) {
            return 0;
        }
        
        if ($invoiceTypeCode === self::INVOICE_TYPE_EXPORTACION) {
            return 0;
        }
        
        $ivaRate = $this->getIVARate($company, $buyer, $itemType);
        
        return round(($amount * $ivaRate) / 100, 2);
    }
    
    public function getIVARate($company, $buyer, $itemType = null) {
        $defaultRate = 19;
        
        if ($itemType === 'exento') {
            return 0;
        }
        
        if ($itemType === 'excluido') {
            return 0;
        }
        
        $companyRegime = $company->tax_regime ?? '';
        
        if ($companyRegime === self::TAX_REGIME_SIMPLIFICADO) {
            return 0;
        }
        
        if ($companyRegime === self::TAX_REGIME_NO_RESPONSABLE) {
            return 0;
        }
        
        return $defaultRate;
    }
    
    public function determineInvoiceType($company, $buyer, $isExport = false, $isContingency = false) {
        if ($isExport) {
            return self::INVOICE_TYPE_EXPORTACION;
        }
        
        if ($isContingency) {
            return self::INVOICE_TYPE_CONTINGENCIA;
        }
        
        $sectorInfo = $this->sectorService->getInvoiceTypeBySector($company);
        return $sectorInfo['invoice_type_code'] ?? self::INVOICE_TYPE_VENTA;
    }
    
    public function getDocumentTypeBySector($company) {
        return $this->sectorService->getInvoiceTypeBySector($company);
    }
    
    public function getSectorInfo($company) {
        return $this->sectorService->getSectorInfo($company->ciiu_code ?? '');
    }
    
    public function validateInvoiceTypeForRegime($invoiceTypeCode, $companyRegime) {
        if ($invoiceTypeCode === self::INVOICE_TYPE_EXPORTACION) {
            if ($companyRegime === self::TAX_REGIME_SIMPLIFICADO) {
                return [
                    'valid' => false,
                    'message' => 'El régimen simplificado no puede emitir facturas de exportación'
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    public function getInvoiceTypeName($invoiceTypeCode) {
        $types = [
            self::INVOICE_TYPE_VENTA => 'Factura de Venta',
            self::INVOICE_TYPE_EXPORTACION => 'Factura de Exportación',
            self::INVOICE_TYPE_CONTINGENCIA => 'Factura de Contingencia',
            self::INVOICE_TYPE_AJUSTE => 'Factura de Ajuste'
        ];
        
        return $types[$invoiceTypeCode] ?? 'Factura de Venta';
    }
    
    public function getTaxRegimeInfo($regime) {
        $regimes = [
            self::TAX_REGIME_SIMPLIFICADO => [
                'name' => 'Régimen Simplificado',
                'applies_iva' => false,
                'can_export' => false,
                'can_issue_invoice' => true,
                'iva_rate' => 0
            ],
            self::TAX_REGIME_COMUN => [
                'name' => 'Régimen Común',
                'applies_iva' => true,
                'can_export' => true,
                'can_issue_invoice' => true,
                'iva_rate' => 19
            ],
            self::TAX_REGIME_GRAN_CONTRIBUYENTE => [
                'name' => 'Gran Contribuyente',
                'applies_iva' => true,
                'can_export' => true,
                'can_issue_invoice' => true,
                'iva_rate' => 19
            ],
            self::TAX_REGIME_ESPECIAL => [
                'name' => 'Régimen Especial',
                'applies_iva' => true,
                'can_export' => true,
                'can_issue_invoice' => true,
                'iva_rate' => 19
            ],
            self::TAX_REGIME_NO_RESPONSABLE => [
                'name' => 'No Responsable de IVA',
                'applies_iva' => false,
                'can_export' => false,
                'can_issue_invoice' => true,
                'iva_rate' => 0
            ]
        ];
        
        return $regimes[$regime] ?? $regimes[self::TAX_REGIME_COMUN];
    }
    
    private function isExemptFromIVA($regime) {
        return in_array($regime, [
            self::TAX_REGIME_SIMPLIFICADO,
            self::TAX_REGIME_NO_RESPONSABLE
        ]);
    }
    
    private function isResponsibleForIVA($regime) {
        return in_array($regime, [
            self::TAX_REGIME_COMUN,
            self::TAX_REGIME_GRAN_CONTRIBUYENTE,
            self::TAX_REGIME_ESPECIAL
        ]);
    }
    
    private function getBuyerTaxRegime($buyer) {
        if (!$buyer || !$buyer->company_id) {
            return self::TAX_REGIME_COMUN;
        }
        
        if (!($buyer instanceof User)) {
            return self::TAX_REGIME_COMUN;
        }
        $buyerCompany = $buyer->company();
        if ($buyerCompany) {
            return $buyerCompany->tax_regime ?? self::TAX_REGIME_COMUN;
        }
        
        return self::TAX_REGIME_COMUN;
    }
    
    public function calculateItemTaxes($item, $amount, $company, $buyer, $invoiceTypeCode) {
        if (!($item instanceof Product) && !($item instanceof Service)) {
            throw new Exception('El item debe ser una instancia de Product o Service');
        }
        $taxes = $item->taxes();
        $calculatedTaxes = [];
        $totalTax = 0;
        
        if (empty($taxes)) {
            if ($this->shouldApplyIVA($company, $buyer, $invoiceTypeCode)) {
                $ivaRate = $this->getIVARate($company, $buyer);
                $ivaAmount = $this->calculateIVA($amount, $company, $buyer, $invoiceTypeCode);
                
                if ($ivaAmount > 0) {
                    $calculatedTaxes[] = [
                        'type' => 'IVA',
                        'name' => 'Impuesto sobre las Ventas',
                        'percentage' => $ivaRate,
                        'amount' => $ivaAmount,
                        'base' => $amount
                    ];
                    $totalTax += $ivaAmount;
                }
            }
        } else {
            foreach ($taxes as $tax) {
                if ($tax->status !== 'Activo') {
                    continue;
                }
                
                $taxAmount = 0;
                $taxBase = $amount;
                
                switch ($tax->application_type) {
                    case 'Porcentaje':
                        if ($tax->type === 'IVA' && !$this->shouldApplyIVA($company, $buyer, $invoiceTypeCode)) {
                            continue 2;
                        }
                        $taxAmount = ($taxBase * $tax->percentage) / 100;
                        break;
                    
                    case 'ValorFijo':
                        $taxAmount = $tax->fixed_value ?? 0;
                        break;
                    
                    case 'Retencion':
                        $taxAmount = -($taxBase * $tax->percentage) / 100;
                        break;
                    
                    default:
                        $taxAmount = 0;
                }
                
                if ($taxAmount != 0) {
                    $calculatedTaxes[] = [
                        'type' => $tax->type,
                        'name' => $tax->name,
                        'percentage' => $tax->application_type === 'Porcentaje' ? $tax->percentage : 0,
                        'amount' => $taxAmount,
                        'base' => $taxBase,
                        'application_type' => $tax->application_type
                    ];
                    $totalTax += $taxAmount;
                }
            }
        }
        
        return [
            'taxes' => $calculatedTaxes,
            'total' => round($totalTax, 2)
        ];
    }
    
    public function validateInvoiceConfiguration($company, $buyer, $invoiceTypeCode) {
        $errors = [];
        
        if (!$company) {
            $errors[] = 'La empresa emisora no está configurada';
            return ['valid' => false, 'errors' => $errors];
        }
        
        if (!$buyer) {
            $errors[] = 'El comprador no está configurado';
            return ['valid' => false, 'errors' => $errors];
        }
        
        $companyRegime = $company->tax_regime ?? '';
        $validation = $this->validateInvoiceTypeForRegime($invoiceTypeCode, $companyRegime);
        
        if (!$validation['valid']) {
            $errors[] = $validation['message'];
        }
        
        if (empty($company->nit)) {
            $errors[] = 'La empresa debe tener NIT configurado';
        }
        
        if (empty($buyer->document_number)) {
            $errors[] = 'El comprador debe tener número de documento';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}


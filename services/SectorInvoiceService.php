<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Company.php';

class SectorInvoiceService {
    private $db;
    
    const DOCUMENT_TYPE_FACTURA = 'Factura';
    const DOCUMENT_TYPE_TIQUETE = 'Tiquete';
    const DOCUMENT_TYPE_DOC_EQUIVALENTE = 'Documento Equivalente';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getInvoiceTypeBySector($company) {
        $ciiuCode = $company->ciiu_code ?? '';
        $sector = $this->getSectorByCIIU($ciiuCode);
        
        return $this->determineDocumentType($sector, $ciiuCode);
    }
    
    public function getSectorByCIIU($ciiuCode) {
        if (empty($ciiuCode)) {
            return 'general';
        }
        
        $firstTwo = substr($ciiuCode, 0, 2);
        $firstDigit = substr($ciiuCode, 0, 1);
        
        $sectors = [
            '01' => 'agricultura',
            '02' => 'ganaderia',
            '03' => 'pesca',
            '05' => 'mineria',
            '10' => 'alimentos',
            '11' => 'bebidas',
            '13' => 'textiles',
            '14' => 'confeccion',
            '15' => 'cuero',
            '16' => 'madera',
            '17' => 'papel',
            '18' => 'impresion',
            '20' => 'quimicos',
            '21' => 'farmaceuticos',
            '22' => 'caucho',
            '23' => 'minerales',
            '24' => 'metalurgia',
            '25' => 'metal',
            '26' => 'electronica',
            '27' => 'equipos',
            '28' => 'maquinaria',
            '29' => 'vehiculos',
            '30' => 'transporte',
            '31' => 'muebles',
            '32' => 'otras_manufacturas',
            '33' => 'reparacion',
            '35' => 'energia',
            '36' => 'agua',
            '37' => 'alcantarillado',
            '38' => 'residuos',
            '39' => 'remediacion',
            '41' => 'construccion',
            '42' => 'ingenieria',
            '43' => 'especializada',
            '45' => 'comercio_vehiculos',
            '46' => 'comercio_mayor',
            '47' => 'comercio_menor',
            '49' => 'transporte_terrestre',
            '50' => 'transporte_acuatico',
            '51' => 'transporte_aereo',
            '52' => 'almacenamiento',
            '53' => 'correo',
            '55' => 'alojamiento',
            '56' => 'alimentos_bebidas',
            '58' => 'edicion',
            '59' => 'audiovisual',
            '60' => 'telecomunicaciones',
            '61' => 'tecnologia',
            '62' => 'software',
            '63' => 'servicios_tecnologicos',
            '64' => 'financieros',
            '65' => 'seguros',
            '66' => 'auxiliares',
            '68' => 'inmobiliarios',
            '69' => 'juridicos',
            '70' => 'administracion',
            '71' => 'arquitectura',
            '72' => 'cientificos',
            '73' => 'publicidad',
            '74' => 'profesionales',
            '75' => 'veterinarios',
            '77' => 'alquiler',
            '78' => 'empleo',
            '79' => 'agencias',
            '80' => 'seguridad',
            '81' => 'mantenimiento',
            '82' => 'servicios_administrativos',
            '85' => 'educacion',
            '86' => 'salud',
            '87' => 'asistencia',
            '88' => 'servicios_sociales',
            '90' => 'artisticos',
            '91' => 'bibliotecas',
            '92' => 'juegos',
            '93' => 'deportivos',
            '94' => 'asociaciones',
            '95' => 'reparacion',
            '96' => 'personales',
            '97' => 'hogar',
            '98' => 'no_diferenciados',
            '99' => 'organizaciones'
        ];
        
        if (isset($sectors[$firstTwo])) {
            return $sectors[$firstTwo];
        }
        
        if (isset($sectors[$firstDigit . '0'])) {
            return $sectors[$firstDigit . '0'];
        }
        
        return 'general';
    }
    
    public function determineDocumentType($sector, $ciiuCode) {
        $rules = [
            'comercio_menor' => [
                'document_type' => self::DOCUMENT_TYPE_TIQUETE,
                'invoice_type_code' => '01',
                'requires_pos' => true,
                'description' => 'Tiquete de máquina registradora o POS'
            ],
            'comercio_mayor' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta'
            ],
            'alimentos_bebidas' => [
                'document_type' => self::DOCUMENT_TYPE_TIQUETE,
                'invoice_type_code' => '01',
                'requires_pos' => true,
                'description' => 'Tiquete de restaurante o establecimiento de alimentos'
            ],
            'transporte_terrestre' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de transporte terrestre'
            ],
            'transporte_acuatico' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de transporte acuático'
            ],
            'transporte_aereo' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de transporte aéreo'
            ],
            'energia' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de servicios públicos - Energía'
            ],
            'agua' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de servicios públicos - Agua'
            ],
            'alcantarillado' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de servicios públicos - Alcantarillado'
            ],
            'telecomunicaciones' => [
                'document_type' => self::DOCUMENT_TYPE_DOC_EQUIVALENTE,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Documento de servicios de telecomunicaciones'
            ],
            'construccion' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Construcción'
            ],
            'ingenieria' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Ingeniería'
            ],
            'arquitectura' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Arquitectura'
            ],
            'salud' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Servicios de salud'
            ],
            'educacion' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Educación'
            ],
            'financieros' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Servicios financieros'
            ],
            'inmobiliarios' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Servicios inmobiliarios'
            ],
            'juridicos' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Servicios jurídicos'
            ],
            'publicidad' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Publicidad'
            ],
            'software' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Software y tecnología'
            ],
            'alojamiento' => [
                'document_type' => self::DOCUMENT_TYPE_FACTURA,
                'invoice_type_code' => '01',
                'requires_pos' => false,
                'description' => 'Factura electrónica de venta - Hotelería'
            ]
        ];
        
        if (isset($rules[$sector])) {
            return $rules[$sector];
        }
        
        return [
            'document_type' => self::DOCUMENT_TYPE_FACTURA,
            'invoice_type_code' => '01',
            'requires_pos' => false,
            'description' => 'Factura electrónica de venta'
        ];
    }
    
    public function getSectorInfo($ciiuCode) {
        $sector = $this->getSectorByCIIU($ciiuCode);
        $documentInfo = $this->determineDocumentType($sector, $ciiuCode);
        
        return [
            'ciiu_code' => $ciiuCode,
            'sector' => $sector,
            'sector_name' => $this->getSectorName($sector),
            'document_type' => $documentInfo['document_type'],
            'invoice_type_code' => $documentInfo['invoice_type_code'],
            'requires_pos' => $documentInfo['requires_pos'] ?? false,
            'description' => $documentInfo['description']
        ];
    }
    
    private function getSectorName($sector) {
        $names = [
            'agricultura' => 'Agricultura, Ganadería, Caza y Silvicultura',
            'ganaderia' => 'Ganadería',
            'pesca' => 'Pesca y Acuicultura',
            'mineria' => 'Explotación de Minas y Canteras',
            'alimentos' => 'Elaboración de Productos Alimenticios',
            'bebidas' => 'Elaboración de Bebidas',
            'textiles' => 'Fabricación de Productos Textiles',
            'confeccion' => 'Confección de Prendas de Vestir',
            'construccion' => 'Construcción',
            'comercio_menor' => 'Comercio al por Menor',
            'comercio_mayor' => 'Comercio al por Mayor',
            'transporte_terrestre' => 'Transporte por Carretera',
            'transporte_acuatico' => 'Transporte por Vía Acuática',
            'transporte_aereo' => 'Transporte por Vía Aérea',
            'telecomunicaciones' => 'Telecomunicaciones',
            'energia' => 'Suministro de Electricidad',
            'agua' => 'Captación, Tratamiento y Distribución de Agua',
            'salud' => 'Actividades de Atención de la Salud Humana',
            'educacion' => 'Educación',
            'financieros' => 'Actividades Financieras',
            'inmobiliarios' => 'Actividades Inmobiliarias',
            'juridicos' => 'Actividades Jurídicas',
            'publicidad' => 'Publicidad e Investigación de Mercados',
            'software' => 'Programación, Consultoría y Otras Actividades Relacionadas',
            'alojamiento' => 'Actividades de Alojamiento',
            'alimentos_bebidas' => 'Actividades de Servicios de Comidas y Bebidas',
            'ingenieria' => 'Ingeniería y Otras Actividades de Consultoría Técnica',
            'arquitectura' => 'Arquitectura e Ingeniería'
        ];
        
        return $names[$sector] ?? 'Actividades Generales';
    }
    
    public function validateSectorConfiguration($company) {
        $errors = [];
        
        if (empty($company->ciiu_code)) {
            $errors[] = 'La empresa debe tener un código CIIU configurado';
        }
        
        if (empty($company->tax_regime)) {
            $errors[] = 'La empresa debe tener un régimen tributario configurado';
        }
        
        $sectorInfo = $this->getInvoiceTypeBySector($company);
        
        if ($sectorInfo['requires_pos'] && empty($company->pos_number ?? '')) {
            $errors[] = 'Este sector requiere número de POS o máquina registradora';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sector_info' => $sectorInfo
        ];
    }
    
    public function getRequiredFieldsBySector($company) {
        $sectorInfo = $this->getInvoiceTypeBySector($company);
        $requiredFields = ['buyer_id', 'items'];
        
        if ($sectorInfo['document_type'] === self::DOCUMENT_TYPE_TIQUETE) {
            $requiredFields[] = 'pos_number';
        }
        
        if ($sectorInfo['document_type'] === self::DOCUMENT_TYPE_DOC_EQUIVALENTE) {
            $sector = $this->getSectorByCIIU($company->ciiu_code ?? '');
            
            if (in_array($sector, ['transporte_terrestre', 'transporte_acuatico', 'transporte_aereo'])) {
                $requiredFields[] = 'transport_info';
            }
            
            if (in_array($sector, ['energia', 'agua', 'alcantarillado'])) {
                $requiredFields[] = 'utility_info';
            }
        }
        
        return $requiredFields;
    }
}


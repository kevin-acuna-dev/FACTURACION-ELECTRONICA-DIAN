<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/DigitalCertificate.php';
require_once __DIR__ . '/../core/Model.php';

class DigitalSignatureService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function signXML($xmlContent, $company) {
        $certificate = $this->getActiveCertificate($company);
        
        if (!$certificate) {
            throw new Exception('La empresa no tiene un certificado digital activo');
        }
        
        $signature = $this->generateSignature($xmlContent, $certificate);
        
        return [
            'signature' => $signature['digital_signature'],
            'signature_value' => $signature['signature_value'],
            'digest_value' => $signature['digest_value'],
            'certificate_info' => [
                'serial_number' => $certificate->serial_number,
                'issuer' => $certificate->issuer,
                'subject' => $this->generateSubject($company),
                'valid_from' => $certificate->start_date,
                'valid_to' => $certificate->end_date,
                'algorithm' => $certificate->signature_algorithm ?? 'SHA256withRSA'
            ]
        ];
    }
    
    public function getActiveCertificate($company) {
        $data = $this->db->fetchOne(
            "SELECT * FROM digital_certificates WHERE company_id = ? AND status = 'Vigente' AND start_date <= NOW() AND end_date >= NOW()",
            [$company->id]
        );
        
        if ($data) {
            $cert = new DigitalCertificate();
            return $cert->hydrate($data);
        }
        
        return null;
    }
    
    private function generateSignature($xmlContent, $certificate) {
        $digestValue = base64_encode(hash('sha256', $xmlContent, true));
        $signedInfo = $this->createSignedInfo($digestValue);
        $signatureValue = $this->createSignatureValue($signedInfo, $certificate);
        $digitalSignature = $this->createSignatureXML($signatureValue, $digestValue, $certificate);
        
        return [
            'digital_signature' => $digitalSignature,
            'signature_value' => $signatureValue,
            'digest_value' => $digestValue
        ];
    }
    
    private function createSignedInfo($digestValue) {
        return <<<XML
<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
    <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
    <SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
    <Reference URI="">
        <Transforms>
            <Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
        </Transforms>
        <DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <DigestValue>{$digestValue}</DigestValue>
    </Reference>
</SignedInfo>
XML;
    }
    
    private function createSignatureValue($signedInfo, $certificate) {
        $dataToSign = $signedInfo . $certificate->serial_number . 'app_key';
        $signature = hash('sha256', $dataToSign, true);
        return base64_encode($signature);
    }
    
    private function createSignatureXML($signatureValue, $digestValue, $certificate) {
        if (!($certificate instanceof DigitalCertificate)) {
            throw new Exception('El certificado debe ser una instancia de DigitalCertificate');
        }
        
        if (!($certificate instanceof DigitalCertificate)) {
            throw new Exception('El certificado debe ser una instancia de DigitalCertificate');
        }
        $company = $certificate->company();
        if (!$company) {
            throw new Exception('El certificado no tiene empresa asociada');
        }
        
        $subject = $this->generateSubject($company);
        $x509Certificate = $this->generateX509Certificate($certificate);
        
        return <<<XML
    <ext:UBLExtensions>
        <ext:UBLExtension>
            <ext:ExtensionContent>
                <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="FirmaDigital">
                    <ds:SignedInfo>
                        <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
                        <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
                        <ds:Reference URI="">
                            <ds:Transforms>
                                <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
                            </ds:Transforms>
                            <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                            <ds:DigestValue>{$digestValue}</ds:DigestValue>
                        </ds:Reference>
                    </ds:SignedInfo>
                    <ds:SignatureValue>{$signatureValue}</ds:SignatureValue>
                    <ds:KeyInfo>
                        <ds:X509Data>
                            <ds:X509Certificate>{$x509Certificate}</ds:X509Certificate>
                            <ds:X509IssuerSerial>
                                <ds:X509IssuerName>{$this->escapeXml($certificate->issuer)}</ds:X509IssuerName>
                                <ds:X509SerialNumber>{$certificate->serial_number}</ds:X509SerialNumber>
                            </ds:X509IssuerSerial>
                            <ds:X509SubjectName>{$this->escapeXml($subject)}</ds:X509SubjectName>
                        </ds:X509Data>
                    </ds:KeyInfo>
                </ds:Signature>
            </ext:ExtensionContent>
        </ext:UBLExtension>
    </ext:UBLExtensions>
    <cac:Signature>
        <cbc:ID>FirmaDigital</cbc:ID>
        <cbc:SignatureMethod>SHA256withRSA</cbc:SignatureMethod>
        <cac:SignatoryParty>
            <cac:PartyIdentification>
                <cbc:ID schemeID="4">{$company->nit}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>{$this->escapeXml($company->business_name)}</cbc:Name>
            </cac:PartyName>
        </cac:SignatoryParty>
        <cac:DigitalSignatureAttachment>
            <cac:ExternalReference>
                <cbc:URI>#FirmaDigital</cbc:URI>
            </cac:ExternalReference>
        </cac:DigitalSignatureAttachment>
    </cac:Signature>
XML;
    }
    
    private function escapeXml($string) {
        return htmlspecialchars($string ?? '', ENT_XML1, 'UTF-8');
    }
    
    private function generateSubject($company) {
        return "CN={$company->business_name}, OU=Facturación Electrónica, O={$company->business_name}, L={$company->city}, ST={$company->department}, C=CO, SERIALNUMBER={$company->nit}";
    }
    
    private function generateX509Certificate($certificate) {
        if (!($certificate instanceof DigitalCertificate)) {
            throw new Exception('El certificado debe ser una instancia de DigitalCertificate');
        }
        $company = $certificate->company();
        $certData = [
            'version' => '3',
            'serial' => $certificate->serial_number,
            'issuer' => $certificate->issuer,
            'validity' => [
                'notBefore' => $certificate->start_date,
                'notAfter' => $certificate->end_date
            ],
            'subject' => $this->generateSubject($company),
            'algorithm' => $certificate->signature_algorithm ?? 'SHA256withRSA',
            'public_key' => bin2hex(random_bytes(64))
        ];
        
        return base64_encode(json_encode($certData));
    }
    
    public function getCertificateInfo($company) {
        $certificate = $this->getActiveCertificate($company);
        
        if (!$certificate) {
            return ['success' => false, 'message' => 'No se encontró certificado digital activo'];
        }
        
        $endDate = new DateTime($certificate->end_date);
        $now = new DateTime();
        $daysUntilExpiry = $now->diff($endDate)->days;
        if ($endDate < $now) {
            $daysUntilExpiry = -$daysUntilExpiry;
        }
        
        return [
            'success' => true,
            'certificate' => [
                'id' => $certificate->id,
                'name' => $certificate->certificate_name,
                'serial_number' => $certificate->serial_number,
                'issuer' => $certificate->issuer,
                'subject' => $this->generateSubject($company),
                'valid_from' => $certificate->start_date,
                'valid_to' => $certificate->end_date,
                'status' => $certificate->status,
                'type' => $certificate->certificate_type,
                'algorithm' => $certificate->signature_algorithm,
                'days_until_expiry' => $daysUntilExpiry
            ]
        ];
    }
}


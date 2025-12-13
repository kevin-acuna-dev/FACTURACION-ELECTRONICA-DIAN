<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Service.php';

class InvoiceDetail extends Model {
    protected $table = 'invoice_details';
    protected $fillable = [
        'electronic_invoice_id', 'item_id', 'item_type', 'description', 'quantity',
        'unit_price', 'line_extension_amount', 'discount_amount', 'tax_amount', 'total_line_amount'
    ];
    
    public function item() {
        if (!$this->item_id) {
            return null;
        }
        
        $itemType = $this->item_type;
        if (strpos($itemType, 'Product') !== false) {
            $item = new Product();
        } else {
            $item = new Service();
        }
        return $item->find($this->item_id);
    }
}


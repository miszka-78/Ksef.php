<?php
/**
 * Invoice class to handle invoice operations
 */
class Invoice {
    private $db;
    private $id;
    private $ksefReferenceNumber;
    private $entityId;
    private $invoiceNumber;
    private $issueDate;
    private $sellerName;
    private $sellerTaxId;
    private $buyerName;
    private $buyerTaxId;
    private $totalNet;
    private $totalGross;
    private $currency;
    private $invoiceType;
    private $xmlContent;
    private $isArchived;
    private $isExported;
    private $archivedAt;
    private $exportDate;
    private $items = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Load invoice by ID
     * 
     * @param int $id Invoice ID
     * @return bool Success or failure
     */
    public function loadById($id) {
        $query = "SELECT * FROM invoices WHERE id = ?";
        $invoice = $this->db->fetchRow($query, [$id]);
        
        if ($invoice) {
            $this->setProperties($invoice);
            $this->loadItems();
            return true;
        }
        return false;
    }
    
    /**
     * Load invoice by KSeF reference number
     * 
     * @param string $ksefReferenceNumber KSeF reference number
     * @return bool Success or failure
     */
    public function loadByKsefReference($ksefReferenceNumber) {
        $query = "SELECT * FROM invoices WHERE ksef_reference_number = ?";
        $invoice = $this->db->fetchRow($query, [$ksefReferenceNumber]);
        
        if ($invoice) {
            $this->setProperties($invoice);
            $this->loadItems();
            return true;
        }
        return false;
    }
    
    /**
     * Set invoice properties from database row
     * 
     * @param array $invoice Invoice data from database
     */
    private function setProperties($invoice) {
        $this->id = $invoice['id'];
        $this->ksefReferenceNumber = $invoice['ksef_reference_number'];
        $this->entityId = $invoice['entity_id'];
        $this->invoiceNumber = $invoice['invoice_number'];
        $this->issueDate = $invoice['issue_date'];
        $this->sellerName = $invoice['seller_name'];
        $this->sellerTaxId = $invoice['seller_tax_id'];
        $this->buyerName = $invoice['buyer_name'];
        $this->buyerTaxId = $invoice['buyer_tax_id'];
        $this->totalNet = $invoice['total_net'];
        $this->totalGross = $invoice['total_gross'];
        $this->currency = $invoice['currency'];
        $this->invoiceType = $invoice['invoice_type'];
        $this->xmlContent = $invoice['xml_content'];
        $this->isArchived = $invoice['is_archived'];
        $this->isExported = $invoice['is_exported'];
        $this->archivedAt = $invoice['archived_at'];
        $this->exportDate = $invoice['export_date'];
    }
    
    /**
     * Load invoice items
     */
    private function loadItems() {
        if (!$this->id) {
            return;
        }
        
        $query = "SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id";
        $this->items = $this->db->fetchAll($query, [$this->id]);
    }
    
    /**
     * Create a new invoice from KSeF XML data
     * 
     * @param array $invoiceData Invoice data
     * @param string $xmlContent XML content
     * @return int|bool Invoice ID or false on failure
     */
    public function createFromKsef($invoiceData, $xmlContent) {
        // Convert camelCase keys to snake_case for database
        $dbData = $this->convertKeysToSnakeCase($invoiceData);
        
        // Add XML content
        $dbData['xml_content'] = $xmlContent;
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Insert invoice
            $invoiceId = $this->db->insert('invoices', $dbData);
            
            if (!$invoiceId) {
                $this->db->rollback();
                return false;
            }
            
            // Insert invoice items if provided
            if (isset($invoiceData['items']) && is_array($invoiceData['items'])) {
                foreach ($invoiceData['items'] as $item) {
                    $itemData = $this->convertKeysToSnakeCase($item);
                    $itemData['invoice_id'] = $invoiceId;
                    
                    if (!$this->db->insert('invoice_items', $itemData)) {
                        $this->db->rollback();
                        return false;
                    }
                }
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Load the newly created invoice
            $this->loadById($invoiceId);
            
            return $invoiceId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creating invoice: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert array keys from camelCase to snake_case
     * 
     * @param array $data Data with camelCase keys
     * @return array Data with snake_case keys
     */
    private function convertKeysToSnakeCase($data) {
        $result = [];
        
        foreach ($data as $key => $value) {
            // Convert camelCase to snake_case
            $newKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
            $result[$newKey] = $value;
        }
        
        return $result;
    }
    
    /**
     * Mark invoice as exported
     * 
     * @return bool Success or failure
     */
    public function markAsExported() {
        if (!$this->id) {
            return false;
        }
        
        $data = [
            'is_exported' => true,
            'export_date' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $success = $this->db->update('invoices', $data, 'id = ?', [$this->id]);
        
        if ($success) {
            $this->isExported = true;
            $this->exportDate = $data['export_date'];
        }
        
        return $success;
    }
    
    /**
     * Mark invoice as archived
     * 
     * @return bool Success or failure
     */
    public function markAsArchived() {
        if (!$this->id) {
            return false;
        }
        
        $data = [
            'is_archived' => true,
            'archived_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $success = $this->db->update('invoices', $data, 'id = ?', [$this->id]);
        
        if ($success) {
            $this->isArchived = true;
            $this->archivedAt = $data['archived_at'];
        }
        
        return $success;
    }
    
    /**
     * Get invoices by entity ID with pagination and filtering
     * 
     * @param int $entityId Entity ID
     * @param array $filters Filters (dates, types, etc.)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array List of invoices
     */
    public function getInvoicesByEntity($entityId, $filters = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $params = [$entityId];
        
        $query = "SELECT id, ksef_reference_number, invoice_number, issue_date, 
                 seller_name, seller_tax_id, buyer_name, buyer_tax_id, 
                 total_net, total_gross, currency, invoice_type,
                 is_archived, is_exported
                 FROM invoices 
                 WHERE entity_id = ?";
        
        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['dateFrom']) && $filters['dateFrom']) {
                $query .= " AND issue_date >= ?";
                $params[] = $filters['dateFrom'];
            }
            
            if (isset($filters['dateTo']) && $filters['dateTo']) {
                $query .= " AND issue_date <= ?";
                $params[] = $filters['dateTo'];
            }
            
            if (isset($filters['invoiceNumber']) && $filters['invoiceNumber']) {
                $query .= " AND invoice_number LIKE ?";
                $params[] = '%' . $filters['invoiceNumber'] . '%';
            }
            
            if (isset($filters['sellerTaxId']) && $filters['sellerTaxId']) {
                $query .= " AND seller_tax_id = ?";
                $params[] = $filters['sellerTaxId'];
            }
            
            if (isset($filters['buyerTaxId']) && $filters['buyerTaxId']) {
                $query .= " AND buyer_tax_id = ?";
                $params[] = $filters['buyerTaxId'];
            }
            
            if (isset($filters['invoiceType']) && $filters['invoiceType']) {
                $query .= " AND invoice_type = ?";
                $params[] = $filters['invoiceType'];
            }
            
            if (isset($filters['exported'])) {
                $query .= " AND is_exported = ?";
                $params[] = $filters['exported'] ? true : false;
            }
            
            if (isset($filters['archived'])) {
                $query .= " AND is_archived = ?";
                $params[] = $filters['archived'] ? true : false;
            }
        }
        
        // Add order by and pagination
        $query .= " ORDER BY issue_date DESC, id DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Count total invoices by entity with filters
     * 
     * @param int $entityId Entity ID
     * @param array $filters Filters
     * @return int Total number of invoices
     */
    public function countInvoicesByEntity($entityId, $filters = []) {
        $params = [$entityId];
        
        $query = "SELECT COUNT(*) as count FROM invoices WHERE entity_id = ?";
        
        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['dateFrom']) && $filters['dateFrom']) {
                $query .= " AND issue_date >= ?";
                $params[] = $filters['dateFrom'];
            }
            
            if (isset($filters['dateTo']) && $filters['dateTo']) {
                $query .= " AND issue_date <= ?";
                $params[] = $filters['dateTo'];
            }
            
            if (isset($filters['invoiceNumber']) && $filters['invoiceNumber']) {
                $query .= " AND invoice_number LIKE ?";
                $params[] = '%' . $filters['invoiceNumber'] . '%';
            }
            
            if (isset($filters['sellerTaxId']) && $filters['sellerTaxId']) {
                $query .= " AND seller_tax_id = ?";
                $params[] = $filters['sellerTaxId'];
            }
            
            if (isset($filters['buyerTaxId']) && $filters['buyerTaxId']) {
                $query .= " AND buyer_tax_id = ?";
                $params[] = $filters['buyerTaxId'];
            }
            
            if (isset($filters['invoiceType']) && $filters['invoiceType']) {
                $query .= " AND invoice_type = ?";
                $params[] = $filters['invoiceType'];
            }
            
            if (isset($filters['exported'])) {
                $query .= " AND is_exported = ?";
                $params[] = $filters['exported'] ? true : false;
            }
            
            if (isset($filters['archived'])) {
                $query .= " AND is_archived = ?";
                $params[] = $filters['archived'] ? true : false;
            }
        }
        
        $result = $this->db->fetchRow($query, $params);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Get invoices to export (not exported yet)
     * 
     * @param int $entityId Entity ID
     * @param array $invoiceIds Array of invoice IDs to export
     * @return array List of invoices to export
     */
    public function getInvoicesToExport($entityId, $invoiceIds = []) {
        $query = "SELECT i.*, e.tax_id as entity_tax_id, e.name as entity_name 
                 FROM invoices i 
                 JOIN entities e ON i.entity_id = e.id 
                 WHERE i.entity_id = ?";
        
        $params = [$entityId];
        
        if (!empty($invoiceIds)) {
            $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $query .= " AND i.id IN ($placeholders)";
            $params = array_merge($params, $invoiceIds);
        } else {
            $query .= " AND i.is_exported = false";
        }
        
        $query .= " ORDER BY i.issue_date ASC, i.id ASC";
        
        $invoices = $this->db->fetchAll($query, $params);
        
        // Load items for each invoice
        foreach ($invoices as &$invoice) {
            $invoice['items'] = $this->db->fetchAll(
                "SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id",
                [$invoice['id']]
            );
        }
        
        return $invoices;
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getKsefReferenceNumber() {
        return $this->ksefReferenceNumber;
    }
    
    public function getEntityId() {
        return $this->entityId;
    }
    
    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }
    
    public function getIssueDate() {
        return $this->issueDate;
    }
    
    public function getSellerName() {
        return $this->sellerName;
    }
    
    public function getSellerTaxId() {
        return $this->sellerTaxId;
    }
    
    public function getBuyerName() {
        return $this->buyerName;
    }
    
    public function getBuyerTaxId() {
        return $this->buyerTaxId;
    }
    
    public function getTotalNet() {
        return $this->totalNet;
    }
    
    public function getTotalGross() {
        return $this->totalGross;
    }
    
    public function getCurrency() {
        return $this->currency;
    }
    
    public function getInvoiceType() {
        return $this->invoiceType;
    }
    
    public function getXmlContent() {
        return $this->xmlContent;
    }
    
    public function getItems() {
        return $this->items;
    }
    
    public function isArchived() {
        return $this->isArchived;
    }
    
    public function isExported() {
        return $this->isExported;
    }
    
    public function getArchivedAt() {
        return $this->archivedAt;
    }
    
    public function getExportDate() {
        return $this->exportDate;
    }
}

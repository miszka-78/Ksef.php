<?php
/**
 * Template class to handle invoice visualization templates
 */
class Template {
    private $db;
    private $id;
    private $name;
    private $description;
    private $htmlContent;
    private $cssContent;
    private $isDefault;
    private $entityId;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Load template by ID
     * 
     * @param int $id Template ID
     * @return bool Success or failure
     */
    public function loadById($id) {
        $query = "SELECT * FROM invoice_templates WHERE id = ?";
        $template = $this->db->fetchRow($query, [$id]);
        
        if ($template) {
            $this->setProperties($template);
            return true;
        }
        return false;
    }
    
    /**
     * Load default template for entity
     * 
     * @param int $entityId Entity ID
     * @return bool Success or failure
     */
    public function loadDefaultForEntity($entityId) {
        $query = "SELECT * FROM invoice_templates 
                 WHERE (entity_id = ? OR entity_id IS NULL) 
                 AND is_default = true 
                 ORDER BY entity_id DESC 
                 LIMIT 1";
                 
        $template = $this->db->fetchRow($query, [$entityId]);
        
        if ($template) {
            $this->setProperties($template);
            return true;
        }
        
        // If no default template for entity, try to load global default
        $query = "SELECT * FROM invoice_templates 
                 WHERE entity_id IS NULL 
                 ORDER BY id ASC 
                 LIMIT 1";
                 
        $template = $this->db->fetchRow($query);
        
        if ($template) {
            $this->setProperties($template);
            return true;
        }
        
        return false;
    }
    
    /**
     * Set template properties from database row
     * 
     * @param array $template Template data from database
     */
    private function setProperties($template) {
        $this->id = $template['id'];
        $this->name = $template['name'];
        $this->description = $template['description'];
        $this->htmlContent = $template['html_content'];
        $this->cssContent = $template['css_content'];
        $this->isDefault = $template['is_default'];
        $this->entityId = $template['entity_id'];
    }
    
    /**
     * Create a new template
     * 
     * @param array $templateData Template data
     * @return int|bool Template ID or false on failure
     */
    public function create($templateData) {
        // Convert camelCase keys to snake_case for database
        if (isset($templateData['htmlContent'])) {
            $templateData['html_content'] = $templateData['htmlContent'];
            unset($templateData['htmlContent']);
        }
        
        if (isset($templateData['cssContent'])) {
            $templateData['css_content'] = $templateData['cssContent'];
            unset($templateData['cssContent']);
        }
        
        if (isset($templateData['isDefault'])) {
            $templateData['is_default'] = $templateData['isDefault'];
            unset($templateData['isDefault']);
        }
        
        if (isset($templateData['entityId'])) {
            $templateData['entity_id'] = $templateData['entityId'];
            unset($templateData['entityId']);
        }
        
        // If this is set as default, unset other defaults for the same entity
        if (isset($templateData['is_default']) && $templateData['is_default']) {
            $entityId = $templateData['entity_id'] ?? null;
            $this->unsetDefaultTemplates($entityId);
        }
        
        // Insert into database
        $id = $this->db->insert('invoice_templates', $templateData);
        
        if ($id) {
            // Load the newly created template
            $this->loadById($id);
            return $id;
        }
        
        return false;
    }
    
    /**
     * Update template
     * 
     * @param array $templateData Template data
     * @return bool Success or failure
     */
    public function update($templateData) {
        if (!$this->id) {
            return false;
        }
        
        // Convert camelCase keys to snake_case for database
        if (isset($templateData['htmlContent'])) {
            $templateData['html_content'] = $templateData['htmlContent'];
            unset($templateData['htmlContent']);
        }
        
        if (isset($templateData['cssContent'])) {
            $templateData['css_content'] = $templateData['cssContent'];
            unset($templateData['cssContent']);
        }
        
        if (isset($templateData['isDefault'])) {
            $templateData['is_default'] = $templateData['isDefault'];
            unset($templateData['isDefault']);
        }
        
        if (isset($templateData['entityId'])) {
            $templateData['entity_id'] = $templateData['entityId'];
            unset($templateData['entityId']);
        }
        
        // If this is set as default, unset other defaults for the same entity
        if (isset($templateData['is_default']) && $templateData['is_default']) {
            $entityId = $templateData['entity_id'] ?? $this->entityId;
            $this->unsetDefaultTemplates($entityId, $this->id);
        }
        
        // Update timestamps
        $templateData['updated_at'] = date('Y-m-d H:i:s');
        
        $success = $this->db->update('invoice_templates', $templateData, 'id = ?', [$this->id]);
        
        if ($success) {
            // Reload template data
            $this->loadById($this->id);
        }
        
        return $success;
    }
    
    /**
     * Delete template
     * 
     * @return bool Success or failure
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        return $this->db->delete('invoice_templates', 'id = ?', [$this->id]);
    }
    
    /**
     * Unset default templates for an entity
     * 
     * @param int|null $entityId Entity ID or null for global templates
     * @param int|null $exceptId Template ID to exclude
     */
    private function unsetDefaultTemplates($entityId, $exceptId = null) {
        $query = "UPDATE invoice_templates SET is_default = false, updated_at = ? WHERE ";
        $params = [date('Y-m-d H:i:s')];
        
        if ($entityId === null) {
            $query .= "entity_id IS NULL";
        } else {
            $query .= "entity_id = ?";
            $params[] = $entityId;
        }
        
        if ($exceptId !== null) {
            $query .= " AND id != ?";
            $params[] = $exceptId;
        }
        
        $this->db->query($query, $params);
    }
    
    /**
     * Get all templates with optional filtering by entity
     * 
     * @param int|null $entityId Entity ID or null for all templates
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array List of templates
     */
    public function getAllTemplates($entityId = null, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT t.*, e.name as entity_name 
                 FROM invoice_templates t 
                 LEFT JOIN entities e ON t.entity_id = e.id";
        
        $params = [];
        
        if ($entityId !== null) {
            $query .= " WHERE t.entity_id = ? OR t.entity_id IS NULL";
            $params[] = $entityId;
        }
        
        $query .= " ORDER BY t.entity_id NULLS FIRST, t.name ASC 
                   LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Count total templates with optional filtering by entity
     * 
     * @param int|null $entityId Entity ID or null for all templates
     * @return int Total number of templates
     */
    public function countTemplates($entityId = null) {
        $query = "SELECT COUNT(*) as count FROM invoice_templates";
        $params = [];
        
        if ($entityId !== null) {
            $query .= " WHERE entity_id = ? OR entity_id IS NULL";
            $params[] = $entityId;
        }
        
        $result = $this->db->fetchRow($query, $params);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Render invoice using template
     * 
     * @param array|object $invoice Invoice data
     * @return string Rendered HTML
     */
    public function renderInvoice($invoice) {
        if (!$this->htmlContent) {
            return 'Template content is empty';
        }
        
        // Convert object to array if needed
        if (is_object($invoice)) {
            $invoice = [
                'id' => $invoice->getId(),
                'ksefReferenceNumber' => $invoice->getKsefReferenceNumber(),
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'issueDate' => $invoice->getIssueDate(),
                'sellerName' => $invoice->getSellerName(),
                'sellerTaxId' => $invoice->getSellerTaxId(),
                'buyerName' => $invoice->getBuyerName(),
                'buyerTaxId' => $invoice->getBuyerTaxId(),
                'totalNet' => $invoice->getTotalNet(),
                'totalGross' => $invoice->getTotalGross(),
                'currency' => $invoice->getCurrency(),
                'items' => $invoice->getItems()
            ];
        }
        
        // Start with the HTML template
        $html = $this->htmlContent;
        
        // Replace placeholders with invoice data
        $placeholders = [
            '{{invoiceNumber}}' => $invoice['invoiceNumber'] ?? '',
            '{{issueDate}}' => $invoice['issueDate'] ?? '',
            '{{sellerName}}' => $invoice['sellerName'] ?? '',
            '{{sellerTaxId}}' => $invoice['sellerTaxId'] ?? '',
            '{{buyerName}}' => $invoice['buyerName'] ?? '',
            '{{buyerTaxId}}' => $invoice['buyerTaxId'] ?? '',
            '{{totalNet}}' => number_format(($invoice['totalNet'] ?? 0), 2, ',', ' '),
            '{{totalGross}}' => number_format(($invoice['totalGross'] ?? 0), 2, ',', ' '),
            '{{currency}}' => $invoice['currency'] ?? 'PLN',
            '{{ksefReferenceNumber}}' => $invoice['ksefReferenceNumber'] ?? ''
        ];
        
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);
        
        // Handle invoice items table
        if (isset($invoice['items']) && is_array($invoice['items'])) {
            $itemsHtml = '';
            $rowTemplate = '';
            
            // Extract the item row template from HTML
            if (preg_match('/<tr\s+id="itemRow".*?>(.*?)<\/tr>/s', $html, $matches)) {
                $rowTemplate = $matches[0];
                
                // Generate rows for each item
                foreach ($invoice['items'] as $index => $item) {
                    $itemRow = $rowTemplate;
                    $itemRow = str_replace('id="itemRow"', '', $itemRow);
                    
                    // Replace item placeholders
                    $itemPlaceholders = [
                        '{{item.index}}' => $index + 1,
                        '{{item.name}}' => $item['name'] ?? '',
                        '{{item.quantity}}' => number_format(($item['quantity'] ?? 0), 3, ',', ' '),
                        '{{item.unit}}' => $item['unit'] ?? '',
                        '{{item.unitPriceNet}}' => number_format(($item['unit_price_net'] ?? 0), 2, ',', ' '),
                        '{{item.netValue}}' => number_format(($item['net_value'] ?? 0), 2, ',', ' '),
                        '{{item.vatRate}}' => $item['vat_rate'] ?? '',
                        '{{item.vatValue}}' => number_format(($item['vat_value'] ?? 0), 2, ',', ' '),
                        '{{item.grossValue}}' => number_format(($item['gross_value'] ?? 0), 2, ',', ' ')
                    ];
                    
                    $itemRow = str_replace(array_keys($itemPlaceholders), array_values($itemPlaceholders), $itemRow);
                    $itemsHtml .= $itemRow;
                }
                
                // Replace the template row with generated rows
                $html = str_replace($rowTemplate, $itemsHtml, $html);
            }
        }
        
        // Add CSS if available
        if ($this->cssContent) {
            $html = str_replace('</head>', "<style>{$this->cssContent}</style>\n</head>", $html);
        }
        
        return $html;
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getDescription() {
        return $this->description;
    }
    
    public function getHtmlContent() {
        return $this->htmlContent;
    }
    
    public function getCssContent() {
        return $this->cssContent;
    }
    
    public function isDefault() {
        return $this->isDefault;
    }
    
    public function getEntityId() {
        return $this->entityId;
    }
}

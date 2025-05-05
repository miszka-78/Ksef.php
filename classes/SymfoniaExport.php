<?php
/**
 * SymfoniaExport class to handle exporting invoices to Symfonia FK format
 */
class SymfoniaExport {
    private $db;
    private $entity;
    
    /**
     * Constructor
     * 
     * @param Entity $entity Entity object
     */
    public function __construct(Entity $entity) {
        $this->db = Database::getInstance();
        $this->entity = $entity;
    }
    
    /**
     * Generate Symfonia FK export file for invoices
     * 
     * @param array $invoices List of invoices to export
     * @param string $format Export format (default: 'FK')
     * @return array Export result with file path and count
     */
    public function generateExport($invoices, $format = 'FK') {
        if (empty($invoices)) {
            return [
                'success' => false,
                'error' => 'No invoices to export'
            ];
        }
        
        $exportData = $this->formatInvoicesForExport($invoices, $format);
        
        // Generate unique filename
        $filename = 'symfonia_export_' . $this->entity->getTaxId() . '_' . date('Ymd_His') . '.txt';
        $filePath = EXPORT_DIR . '/' . $filename;
        
        // Write export data to file
        file_put_contents($filePath, $exportData);
        
        // Create export batch record
        $batchData = [
            'entity_id' => $this->entity->getId(),
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'export_date' => date('Y-m-d H:i:s'),
            'filename' => $filename,
            'invoice_count' => count($invoices),
            'status' => 'completed',
            'symfonia_format' => $format
        ];
        
        $batchId = $this->db->insert('export_batches', $batchData);
        
        if ($batchId) {
            // Link invoices to batch
            foreach ($invoices as $invoice) {
                $this->db->query(
                    "INSERT INTO export_batch_invoices (batch_id, invoice_id) VALUES (?, ?)",
                    [$batchId, $invoice['id']]
                );
                
                // Mark invoice as exported
                $this->db->update(
                    'invoices',
                    [
                        'is_exported' => true,
                        'export_date' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ?',
                    [$invoice['id']]
                );
            }
        }
        
        return [
            'success' => true,
            'filePath' => $filePath,
            'filename' => $filename,
            'invoiceCount' => count($invoices),
            'batchId' => $batchId
        ];
    }
    
    /**
     * Format invoices for export to Symfonia FK format
     * 
     * @param array $invoices List of invoices
     * @param string $format Export format
     * @return string Formatted export data
     */
    private function formatInvoicesForExport($invoices, $format) {
        switch ($format) {
            case 'FK':
                return $this->formatForSymfoniaFK($invoices);
            default:
                throw new Exception("Unsupported export format: $format");
        }
    }
    
    /**
     * Format invoices for Symfonia FK format
     * 
     * @param array $invoices List of invoices
     * @return string Formatted export data
     */
    private function formatForSymfoniaFK($invoices) {
        $lines = [];
        
        // Add header
        $lines[] = 'Format;FK;';
        $lines[] = 'Wersja;3.00;';
        
        foreach ($invoices as $invoice) {
            // Format invoice date
            $issueDate = date('Y-m-d', strtotime($invoice['issue_date']));
            
            // Determine document type
            $documentType = 'FV'; // Default - Invoice
            
            // Add document header
            $lines[] = "Nagdok;$documentType;{$invoice['invoice_number']};$issueDate;{$invoice['seller_name']};{$invoice['seller_tax_id']};{$invoice['currency']};";
            
            // Add document description
            $description = "Faktura {$invoice['invoice_number']} z KSeF";
            $lines[] = "Opisdok;$description;";
            
            // Add invoice amounts
            $netAmount = number_format($invoice['total_net'], 2, '.', '');
            $vatAmount = number_format($invoice['total_gross'] - $invoice['total_net'], 2, '.', '');
            $grossAmount = number_format($invoice['total_gross'], 2, '.', '');
            
            $lines[] = "Wart;$netAmount;$vatAmount;$grossAmount;";
            
            // Add buyer information
            $lines[] = "Podmiot;{$invoice['buyer_name']};{$invoice['buyer_tax_id']};";
            
            // Add payment information (due date is assumed to be 14 days after issue date)
            $dueDate = date('Y-m-d', strtotime($invoice['issue_date'] . ' + 14 days'));
            $lines[] = "Platnosc;PRZELEW;$dueDate;$grossAmount;";
            
            // Add VAT summary information
            if (isset($invoice['items']) && !empty($invoice['items'])) {
                // Group items by VAT rate
                $vatRates = [];
                
                foreach ($invoice['items'] as $item) {
                    $vatRate = $item['vat_rate'] ?? '23%';
                    
                    // Remove '%' character if present
                    $vatRateKey = str_replace('%', '', $vatRate);
                    
                    if (!isset($vatRates[$vatRateKey])) {
                        $vatRates[$vatRateKey] = [
                            'netValue' => 0,
                            'vatValue' => 0,
                            'grossValue' => 0
                        ];
                    }
                    
                    $vatRates[$vatRateKey]['netValue'] += $item['net_value'];
                    $vatRates[$vatRateKey]['vatValue'] += $item['vat_value'];
                    $vatRates[$vatRateKey]['grossValue'] += $item['gross_value'];
                }
                
                // Add VAT rates
                foreach ($vatRates as $rate => $values) {
                    $netValue = number_format($values['netValue'], 2, '.', '');
                    $vatValue = number_format($values['vatValue'], 2, '.', '');
                    
                    // Format rate according to Symfonia requirements
                    $rateStr = $rate;
                    
                    if ($rate === 'zw' || $rate === 'np') {
                        $rateStr = strtoupper($rate);
                    } else {
                        $rateStr = $rate;
                    }
                    
                    $lines[] = "Vat;$rateStr;$netValue;$vatValue;";
                }
            } else {
                // If no items, use total values
                $vatRate = '23'; // Default VAT rate
                $lines[] = "Vat;$vatRate;$netAmount;$vatAmount;";
            }
            
            // Add KSeF reference
            $lines[] = "DaneKsef;{$invoice['ksef_reference_number']};";
            
            // Add delimiter between invoices
            $lines[] = "---";
        }
        
        // Join all lines with newline characters
        return implode("\n", $lines);
    }
    
    /**
     * Get previous export batches for entity
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array List of export batches
     */
    public function getExportBatches($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT b.*, u.username as username
                 FROM export_batches b
                 LEFT JOIN users u ON b.user_id = u.id
                 WHERE b.entity_id = ?
                 ORDER BY b.export_date DESC
                 LIMIT ? OFFSET ?";
                 
        return $this->db->fetchAll($query, [
            $this->entity->getId(),
            $perPage,
            $offset
        ]);
    }
    
    /**
     * Count total export batches for entity
     * 
     * @return int Total number of export batches
     */
    public function countExportBatches() {
        $query = "SELECT COUNT(*) as count FROM export_batches WHERE entity_id = ?";
        $result = $this->db->fetchRow($query, [$this->entity->getId()]);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Get export batch details by ID
     * 
     * @param int $batchId Batch ID
     * @return array Batch details with invoices
     */
    public function getExportBatchDetails($batchId) {
        // Get batch info
        $query = "SELECT b.*, u.username as username
                 FROM export_batches b
                 LEFT JOIN users u ON b.user_id = u.id
                 WHERE b.id = ? AND b.entity_id = ?";
                 
        $batch = $this->db->fetchRow($query, [
            $batchId,
            $this->entity->getId()
        ]);
        
        if (!$batch) {
            return null;
        }
        
        // Get invoices in batch
        $query = "SELECT i.*
                 FROM invoices i
                 JOIN export_batch_invoices ebi ON i.id = ebi.invoice_id
                 WHERE ebi.batch_id = ?
                 ORDER BY i.issue_date, i.id";
                 
        $invoices = $this->db->fetchAll($query, [$batchId]);
        
        $batch['invoices'] = $invoices;
        
        return $batch;
    }
}

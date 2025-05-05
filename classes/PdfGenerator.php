<?php
/**
 * PdfGenerator class to generate PDF files for invoices
 */
class PdfGenerator {
    /**
     * Generate PDF from HTML content
     * 
     * @param string $html HTML content
     * @param string $filename Output filename
     * @return string PDF content
     */
    public function generatePdfFromHtml($html, $filename = null) {
        // Initialize Dompdf
        $dompdf = $this->initDompdf();
        
        // Load HTML
        $dompdf->loadHtml($html);
        
        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');
        
        // Render PDF
        $dompdf->render();
        
        // If filename is provided, save PDF to file
        if ($filename) {
            file_put_contents($filename, $dompdf->output());
            return $filename;
        }
        
        // Otherwise return PDF content
        return $dompdf->output();
    }
    
    /**
     * Initialize Dompdf with proper configuration
     * 
     * @return object Dompdf instance
     */
    private function initDompdf() {
        // Check if dompdf is already included
        if (!class_exists('Dompdf\Dompdf')) {
            // If not included, we'll use a simple HTML to PDF conversion
            // This is a fallback method if the dompdf library isn't available
            return new class {
                private $html;
                
                public function loadHtml($html) {
                    $this->html = $html;
                }
                
                public function setPaper($size, $orientation) {
                    // Not implemented in fallback
                }
                
                public function render() {
                    // Not implemented in fallback
                }
                
                public function output() {
                    // In fallback mode, we'll return HTML wrapped with PDF mime type comment
                    return "<!--PDF-FALLBACK-->\n" . $this->html;
                }
            };
        }
        
        // Create and return Dompdf instance
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(true);
        $dompdf->setOptions($options);
        
        return $dompdf;
    }
    
    /**
     * Generate PDF for invoice using template
     * 
     * @param Invoice $invoice Invoice object
     * @param Template $template Template object
     * @param string $filename Output filename
     * @return string PDF content or filename
     */
    public function generateInvoicePdf(Invoice $invoice, Template $template, $filename = null) {
        // Render invoice HTML
        $html = $template->renderInvoice($invoice);
        
        // Generate PDF from HTML
        return $this->generatePdfFromHtml($html, $filename);
    }
    
    /**
     * Check if we can generate real PDFs
     * 
     * @return bool Can generate PDFs or not
     */
    public function canGeneratePdf() {
        return class_exists('Dompdf\Dompdf');
    }
}

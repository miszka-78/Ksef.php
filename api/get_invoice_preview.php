<?php
/**
 * API endpoint to generate invoice preview
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Include classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Entity.php';
require_once __DIR__ . '/../classes/Invoice.php';
require_once __DIR__ . '/../classes/Template.php';

// Include auth functions
require_once __DIR__ . '/../includes/auth.php';

// Don't require authentication for public invoice previews with token
$isPublicPreview = false;
$previewToken = $_GET['token'] ?? null;

if (!$previewToken || !isValidPreviewToken($previewToken)) {
    // Require authentication for non-public previews
    requireAuth();
}

// Initialize classes
$invoice = new Invoice();
$template = new Template();

// Check if this is a direct template preview (with HTML/CSS content)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html_content'])) {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!checkCsrfToken($token)) {
        http_response_code(403);
        echo 'Invalid security token';
        exit;
    }
    
    // Get HTML and CSS from POST
    $htmlContent = $_POST['html_content'] ?? '';
    $cssContent = $_POST['css_content'] ?? '';
    
    // Create sample invoice data
    $sampleInvoice = getSampleInvoiceData();
    
    // Render directly by replacing placeholders
    $html = renderInvoiceWithContent($htmlContent, $cssContent, $sampleInvoice);
    
    // Output HTML
    echo $html;
    exit;
}

// Check if we should use a sample invoice
$useSample = isset($_GET['sample']) && $_GET['sample'] == '1';

if ($useSample) {
    $invoiceData = getSampleInvoiceData();
} else {
    // Load real invoice
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo 'Invoice ID is required';
        exit;
    }
    
    $invoiceId = (int)$_GET['id'];
    
    if (!$invoice->loadById($invoiceId)) {
        http_response_code(404);
        echo 'Invoice not found';
        exit;
    }
    
    // Check access to entity
    $entityId = $invoice->getEntityId();
    if (!isPublicPreview && !userHasEntityAccess($entityId)) {
        http_response_code(403);
        echo 'You do not have access to this invoice';
        exit;
    }
    
    $invoiceData = [
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

// Load template
if (isset($_GET['template_id']) && !empty($_GET['template_id'])) {
    $templateId = (int)$_GET['template_id'];
    if (!$template->loadById($templateId)) {
        http_response_code(404);
        echo 'Template not found';
        exit;
    }
} else {
    // Use default template
    if (!$useSample) {
        $entityId = $invoice->getEntityId();
        if (!$template->loadDefaultForEntity($entityId)) {
            http_response_code(404);
            echo 'No default template found';
            exit;
        }
    } else {
        // For sample, use first available template
        $db = Database::getInstance();
        $templateData = $db->fetchRow("SELECT id FROM invoice_templates ORDER BY id ASC LIMIT 1");
        
        if ($templateData && $template->loadById($templateData['id'])) {
            // Template loaded
        } else {
            http_response_code(404);
            echo 'No templates found';
            exit;
        }
    }
}

// Render invoice
$html = $template->renderInvoice($invoiceData);

// Output rendered HTML
echo $html;
exit;

/**
 * Check if preview token is valid
 * 
 * @param string $token Token to validate
 * @return bool Token is valid or not
 */
function isValidPreviewToken($token) {
    // In a real implementation, this should validate against stored tokens
    // For this example, we'll assume no valid tokens
    return false;
}

/**
 * Get sample invoice data for previews
 * 
 * @return array Sample invoice data
 */
function getSampleInvoiceData() {
    return [
        'id' => 1,
        'ksefReferenceNumber' => '1234567890',
        'invoiceNumber' => 'FV/2023/12345',
        'issueDate' => date('Y-m-d'),
        'sellerName' => 'Example Seller Company Sp. z o.o.',
        'sellerTaxId' => '1234567890',
        'buyerName' => 'Example Buyer Ltd.',
        'buyerTaxId' => '0987654321',
        'totalNet' => 1000.00,
        'totalGross' => 1230.00,
        'currency' => 'PLN',
        'items' => [
            [
                'name' => 'Professional Services',
                'quantity' => 1,
                'unit' => 'szt',
                'unit_price_net' => 800.00,
                'net_value' => 800.00,
                'vat_rate' => '23%',
                'vat_value' => 184.00,
                'gross_value' => 984.00
            ],
            [
                'name' => 'Additional Materials',
                'quantity' => 2,
                'unit' => 'szt',
                'unit_price_net' => 100.00,
                'net_value' => 200.00,
                'vat_rate' => '23%',
                'vat_value' => 46.00,
                'gross_value' => 246.00
            ]
        ]
    ];
}

/**
 * Render invoice using provided HTML and CSS content
 * 
 * @param string $htmlContent HTML template
 * @param string $cssContent CSS content
 * @param array $invoice Invoice data
 * @return string Rendered HTML
 */
function renderInvoiceWithContent($htmlContent, $cssContent, $invoice) {
    // Start with the HTML template
    $html = $htmlContent;
    
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
        '{{totalVat}}' => number_format(($invoice['totalGross'] ?? 0) - ($invoice['totalNet'] ?? 0), 2, ',', ' '),
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
    if ($cssContent) {
        $html = str_replace('</head>', "<style>{$cssContent}</style>\n</head>", $html);
    }
    
    return $html;
}

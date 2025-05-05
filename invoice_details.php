<?php
/**
 * Invoice details page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';
require_once __DIR__ . '/classes/Invoice.php';
require_once __DIR__ . '/classes/Template.php';
require_once __DIR__ . '/classes/PdfGenerator.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();

// Set page title
$pageTitle = 'Invoice Details';

// Initialize classes
$user = new User();
$user->loadById($_SESSION['user_id']);
$invoice = new Invoice();
$entity = new Entity();
$template = new Template();
$pdfGenerator = new PdfGenerator();

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('error', 'Invoice ID is required');
    redirect('invoices.php');
}

// Load invoice
$invoiceId = (int)$_GET['id'];
if (!$invoice->loadById($invoiceId)) {
    setFlashMessage('error', 'Invoice not found');
    redirect('invoices.php');
}

// Load entity and check user access
$entityId = $invoice->getEntityId();
if (!$entity->loadById($entityId) || !userHasEntityAccess($entityId)) {
    setFlashMessage('error', 'You do not have access to this invoice');
    redirect('invoices.php');
}

// Load default template for entity
$template->loadDefaultForEntity($entityId);

// Handle invoice actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $token = $_GET['csrf_token'] ?? '';
    
    if (!checkCsrfToken($token)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        switch ($action) {
            case 'export':
                // Check export permission
                if (!userHasEntityAccess($entityId, 'export')) {
                    setFlashMessage('error', 'You do not have permission to export this invoice');
                    break;
                }
                
                // Redirect to export page
                redirect('invoice_export.php?entity_id=' . $entityId . '&ids=' . $invoiceId);
                break;
                
            case 'archive':
                // Check download permission
                if (!userHasEntityAccess($entityId, 'download')) {
                    setFlashMessage('error', 'You do not have permission to archive this invoice');
                    break;
                }
                
                // Archive the invoice
                if ($invoice->markAsArchived()) {
                    setFlashMessage('success', 'Invoice archived successfully');
                    logUserActivity('archive_invoice', $entityId, 'Archived invoice: ' . $invoice->getInvoiceNumber());
                } else {
                    setFlashMessage('error', 'Failed to archive invoice');
                }
                
                // Reload the invoice
                $invoice->loadById($invoiceId);
                break;
                
            case 'download_pdf':
                // Check download permission
                if (!userHasEntityAccess($entityId, 'download')) {
                    setFlashMessage('error', 'You do not have permission to download this invoice');
                    break;
                }
                
                // Get template ID if provided
                $templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;
                
                if ($templateId) {
                    $template->loadById($templateId);
                }
                
                // Generate PDF
                $filename = 'invoice_' . preg_replace('/[^a-zA-Z0-9]/', '_', $invoice->getInvoiceNumber()) . '.pdf';
                
                // Render invoice using template
                $html = $template->renderInvoice($invoice);
                
                // Generate PDF and send to browser
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                
                echo $pdfGenerator->generatePdfFromHtml($html);
                exit;
                
            case 'download_xml':
                // Check download permission
                if (!userHasEntityAccess($entityId, 'download')) {
                    setFlashMessage('error', 'You do not have permission to download this invoice');
                    break;
                }
                
                // Get XML content
                $xmlContent = $invoice->getXmlContent();
                
                if ($xmlContent) {
                    $filename = 'invoice_' . preg_replace('/[^a-zA-Z0-9]/', '_', $invoice->getInvoiceNumber()) . '.xml';
                    
                    header('Content-Type: application/xml');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Cache-Control: max-age=0');
                    
                    echo $xmlContent;
                    exit;
                } else {
                    setFlashMessage('error', 'XML content not available');
                }
                break;
        }
    }
}

// Get all templates for this entity
$allTemplates = $template->getAllTemplates($entityId);

// Get invoice items
$invoiceItems = $invoice->getItems();

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoice Details</h1>
    
    <div>
        <a href="invoices.php?entity_id=<?= $entityId ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Invoices
        </a>
    </div>
</div>

<!-- Invoice Information -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Invoice Information</h5>
                
                <div class="btn-group">
                    <?php if (!$invoice->isExported() && userHasEntityAccess($entityId, 'export')): ?>
                    <a href="?id=<?= $invoiceId ?>&action=export&csrf_token=<?= getCsrfToken() ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-file-export me-1"></i> Export
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!$invoice->isArchived() && userHasEntityAccess($entityId, 'download')): ?>
                    <a href="?id=<?= $invoiceId ?>&action=archive&csrf_token=<?= getCsrfToken() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to archive this invoice?')">
                        <i class="fas fa-box-archive me-1"></i> Archive
                    </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Download
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (userHasEntityAccess($entityId, 'download')): ?>
                        <li><h6 class="dropdown-header">Download as PDF</h6></li>
                        <?php foreach ($allTemplates as $templateItem): ?>
                        <li>
                            <a class="dropdown-item" href="?id=<?= $invoiceId ?>&action=download_pdf&template_id=<?= $templateItem['id'] ?>&csrf_token=<?= getCsrfToken() ?>">
                                With <?= sanitize($templateItem['name']) ?> template
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="?id=<?= $invoiceId ?>&action=download_xml&csrf_token=<?= getCsrfToken() ?>">
                                Download as XML
                            </a>
                        </li>
                        <?php else: ?>
                        <li><span class="dropdown-item text-muted">You don't have download permission</span></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Invoice Details</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th width="40%">Invoice Number</th>
                                <td><?= sanitize($invoice->getInvoiceNumber()) ?></td>
                            </tr>
                            <tr>
                                <th>Issue Date</th>
                                <td><?= formatDate($invoice->getIssueDate()) ?></td>
                            </tr>
                            <tr>
                                <th>Invoice Type</th>
                                <td><?= sanitize($invoice->getInvoiceType()) ?></td>
                            </tr>
                            <tr>
                                <th>KSeF Reference</th>
                                <td>
                                    <small class="text-muted"><?= sanitize($invoice->getKsefReferenceNumber()) ?></small>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold">Status Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th width="40%">Export Status</th>
                                <td>
                                    <?php if ($invoice->isExported()): ?>
                                    <span class="badge bg-success">Exported</span>
                                    <small class="text-muted ms-2">
                                        on <?= formatDate($invoice->getExportDate(), 'Y-m-d H:i') ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Not Exported</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Archive Status</th>
                                <td>
                                    <?php if ($invoice->isArchived()): ?>
                                    <span class="badge bg-secondary">Archived</span>
                                    <small class="text-muted ms-2">
                                        on <?= formatDate($invoice->getArchivedAt(), 'Y-m-d H:i') ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark">Not Archived</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Total Amount</th>
                                <td class="fw-bold"><?= formatMoney($invoice->getTotalGross(), $invoice->getCurrency()) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h6 class="fw-bold mb-2">Seller</h6>
                                <p class="mb-1"><?= sanitize($invoice->getSellerName()) ?></p>
                                <p class="mb-0"><small class="text-muted">NIP: <?= sanitize(formatNip($invoice->getSellerTaxId())) ?></small></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h6 class="fw-bold mb-2">Buyer</h6>
                                <p class="mb-1"><?= sanitize($invoice->getBuyerName()) ?></p>
                                <p class="mb-0"><small class="text-muted">NIP: <?= sanitize(formatNip($invoice->getBuyerTaxId())) ?></small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Invoice Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Invoice Items</h5>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40px">#</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Net Value</th>
                                <th>VAT Rate</th>
                                <th>VAT Amount</th>
                                <th>Gross Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoiceItems)): ?>
                                <?php foreach ($invoiceItems as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= sanitize($item['name']) ?></td>
                                    <td><?= formatNumber($item['quantity'], 3) ?> <?= sanitize($item['unit'] ?? '') ?></td>
                                    <td><?= formatMoney($item['unit_price_net'], $invoice->getCurrency()) ?></td>
                                    <td><?= formatMoney($item['net_value'], $invoice->getCurrency()) ?></td>
                                    <td><?= sanitize($item['vat_rate']) ?></td>
                                    <td><?= formatMoney($item['vat_value'], $invoice->getCurrency()) ?></td>
                                    <td><?= formatMoney($item['gross_value'], $invoice->getCurrency()) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-3">
                                        <span class="text-muted">No items available</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Total:</th>
                                <th><?= formatMoney($invoice->getTotalNet(), $invoice->getCurrency()) ?></th>
                                <th></th>
                                <th><?= formatMoney($invoice->getTotalGross() - $invoice->getTotalNet(), $invoice->getCurrency()) ?></th>
                                <th><?= formatMoney($invoice->getTotalGross(), $invoice->getCurrency()) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Invoice Preview -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Invoice Preview</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Change Template
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($allTemplates as $templateItem): ?>
                        <li>
                            <a class="dropdown-item" href="#" data-template-id="<?= $templateItem['id'] ?>">
                                <?= sanitize($templateItem['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="invoice-preview-container" style="height: 600px; overflow: auto; border-top: 1px solid #eee;">
                    <div class="invoice-preview">
                        <iframe id="previewFrame" style="width: 100%; height: 800px; border: none;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load initial preview
    loadInvoicePreview(<?= $template->getId() ?>);
    
    // Template change handlers
    document.querySelectorAll('[data-template-id]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const templateId = this.getAttribute('data-template-id');
            loadInvoicePreview(templateId);
        });
    });
    
    function loadInvoicePreview(templateId) {
        const previewFrame = document.getElementById('previewFrame');
        const previewUrl = `api/get_invoice_preview.php?id=<?= $invoiceId ?>&template_id=${templateId}`;
        
        previewFrame.src = previewUrl;
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

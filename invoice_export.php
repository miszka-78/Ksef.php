<?php
/**
 * Invoice export page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';
require_once __DIR__ . '/classes/Invoice.php';
require_once __DIR__ . '/classes/SymfoniaExport.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();

// Set page title
$pageTitle = 'Export Invoices';

// Initialize classes
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();
$invoice = new Invoice();

// Check entity ID
if (!isset($_GET['entity_id']) || empty($_GET['entity_id'])) {
    setFlashMessage('error', 'Entity ID is required');
    redirect('entities.php');
}

// Load entity and check user access
$entityId = (int)$_GET['entity_id'];
if (!$entity->loadById($entityId)) {
    setFlashMessage('error', 'Entity not found');
    redirect('entities.php');
}

// Check export permission
if (!userHasEntityAccess($entityId, 'export')) {
    setFlashMessage('error', 'You do not have permission to export invoices for this entity');
    redirect('invoices.php?entity_id=' . $entityId);
}

// Initialize Symfonia export
$symfoniaExport = new SymfoniaExport($entity);

// Check if specific invoices are selected
$selectedInvoiceIds = [];
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    foreach ($ids as $id) {
        $selectedInvoiceIds[] = (int)$id;
    }
}

// Get invoices to export
$invoicesToExport = $invoice->getInvoicesToExport($entityId, $selectedInvoiceIds);

// Handle export form submission
$exportResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!checkCsrfToken($token)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        // Get selected invoice IDs
        $exportInvoiceIds = $_POST['invoice_ids'] ?? [];
        
        if (empty($exportInvoiceIds)) {
            setFlashMessage('error', 'No invoices selected for export');
        } else {
            // Filter invoices to export
            $filteredInvoices = array_filter($invoicesToExport, function($inv) use ($exportInvoiceIds) {
                return in_array($inv['id'], $exportInvoiceIds);
            });
            
            // Generate export
            $exportResult = $symfoniaExport->generateExport($filteredInvoices, $_POST['export_format'] ?? 'FK');
            
            if ($exportResult['success']) {
                logUserActivity('export_invoices', $entityId, 'Exported ' . $exportResult['invoiceCount'] . ' invoices');
            } else {
                logError('Export failed: ' . ($exportResult['error'] ?? 'Unknown error'));
            }
        }
    }
}

// Previous exports
$previousExports = $symfoniaExport->getExportBatches(1, 5);

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Export Invoices to Symfonia FK</h1>
    
    <div>
        <a href="invoices.php?entity_id=<?= $entityId ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Invoices
        </a>
    </div>
</div>

<?php if ($exportResult): ?>
    <?php if ($exportResult['success']): ?>
    <div class="alert alert-success">
        <h4 class="alert-heading">Export Successful!</h4>
        <p><?= $exportResult['invoiceCount'] ?> invoice(s) have been successfully exported.</p>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="mb-0">
                    <strong>Export File:</strong> <?= sanitize($exportResult['filename']) ?>
                </p>
            </div>
            <div>
                <a href="exports/<?= urlencode($exportResult['filename']) ?>" class="btn btn-primary" download>
                    <i class="fas fa-download me-1"></i> Download Export File
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading">Export Failed</h4>
        <p><?= sanitize($exportResult['error']) ?></p>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Export Form -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Invoices Available for Export</h5>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($invoicesToExport)): ?>
                <div class="p-4 text-center">
                    <p class="text-muted mb-0">No invoices available for export. All invoices have been exported or none match the selection criteria.</p>
                    <p class="mt-2">
                        <a href="invoices.php?entity_id=<?= $entityId ?>" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Browse Invoices
                        </a>
                    </p>
                </div>
                <?php else: ?>
                <form method="post" id="exportForm">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="40px">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                        </div>
                                    </th>
                                    <th>Invoice Number</th>
                                    <th>Date</th>
                                    <th>Seller</th>
                                    <th>Buyer</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoicesToExport as $inv): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input invoice-checkbox" type="checkbox" 
                                                name="invoice_ids[]" value="<?= $inv['id'] ?>" 
                                                id="invoice<?= $inv['id'] ?>" 
                                                <?= !empty($selectedInvoiceIds) && in_array($inv['id'], $selectedInvoiceIds) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="invoice_details.php?id=<?= $inv['id'] ?>">
                                            <?= sanitize($inv['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= formatDate($inv['issue_date']) ?></td>
                                    <td title="<?= sanitize($inv['seller_name']) ?>">
                                        <?= sanitize(truncateText($inv['seller_name'], 25)) ?><br>
                                        <small class="text-muted"><?= sanitize(formatNip($inv['seller_tax_id'])) ?></small>
                                    </td>
                                    <td title="<?= sanitize($inv['buyer_name']) ?>">
                                        <?= sanitize(truncateText($inv['buyer_name'], 25)) ?><br>
                                        <small class="text-muted"><?= sanitize(formatNip($inv['buyer_tax_id'])) ?></small>
                                    </td>
                                    <td><?= formatMoney($inv['total_gross'], $inv['currency']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-3 bg-light border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="export_format" class="form-label">Export Format</label>
                                    <select class="form-select" id="export_format" name="export_format">
                                        <option value="FK" selected>Symfonia FK Standard Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end justify-content-end">
                                <button type="submit" class="btn btn-primary" id="exportButton" disabled>
                                    <i class="fas fa-file-export me-1"></i> Export Selected Invoices
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Entity Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Entity Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <th width="35%">Name</th>
                        <td><?= sanitize($entity->getName()) ?></td>
                    </tr>
                    <tr>
                        <th>Tax ID (NIP)</th>
                        <td><?= sanitize(formatNip($entity->getTaxId())) ?></td>
                    </tr>
                    <tr>
                        <th>KSeF Environment</th>
                        <td>
                            <span class="badge bg-<?= $entity->getKsefEnvironment() === 'prod' ? 'danger' : ($entity->getKsefEnvironment() === 'test' ? 'warning' : 'info') ?>">
                                <?= strtoupper(sanitize($entity->getKsefEnvironment())) ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Previous Exports -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Exports</h5>
                <?php if (count($previousExports) > 0): ?>
                <a href="export_history.php?entity_id=<?= $entityId ?>" class="btn btn-sm btn-outline-primary">View All</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($previousExports)): ?>
                <div class="p-4 text-center">
                    <p class="text-muted mb-0">No previous exports found.</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($previousExports as $export): ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h6 class="mb-1">
                                <?= formatDate($export['export_date'], 'Y-m-d H:i') ?>
                            </h6>
                            <span class="badge bg-<?= $export['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= ucfirst(sanitize($export['status'])) ?>
                            </span>
                        </div>
                        <p class="mb-1">
                            <small>
                                <strong>Invoices:</strong> <?= $export['invoice_count'] ?><br>
                                <strong>Format:</strong> <?= sanitize($export['symfonia_format']) ?><br>
                                <strong>File:</strong> <?= sanitize($export['filename']) ?>
                            </small>
                        </p>
                        <div class="mt-1">
                            <a href="exports/<?= urlencode($export['filename']) ?>" class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const exportButton = document.getElementById('exportButton');
    const exportForm = document.getElementById('exportForm');
    
    if (selectAllCheckbox && invoiceCheckboxes.length > 0) {
        // Select All functionality
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateExportButton();
        });
        
        // Individual checkbox change handler
        invoiceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateExportButton();
                
                // Update "Select All" checkbox state
                const allChecked = [...invoiceCheckboxes].every(cb => cb.checked);
                const someChecked = [...invoiceCheckboxes].some(cb => cb.checked);
                
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
                selectAllCheckbox.checked = allChecked;
            });
        });
        
        // Initial state
        updateExportButton();
        
        // Check if any pre-selected checkboxes (from URL parameter)
        const someChecked = [...invoiceCheckboxes].some(cb => cb.checked);
        const allChecked = [...invoiceCheckboxes].every(cb => cb.checked);
        
        if (someChecked) {
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
            selectAllCheckbox.checked = allChecked;
            updateExportButton();
        }
        
        // Form submission confirmation
        if (exportForm) {
            exportForm.addEventListener('submit', function(e) {
                const checkedCount = [...invoiceCheckboxes].filter(cb => cb.checked).length;
                
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one invoice to export');
                    return false;
                }
                
                return confirm(`Are you sure you want to export ${checkedCount} selected invoice(s)?`);
            });
        }
    }
    
    function updateExportButton() {
        if (exportButton) {
            const checkedCount = [...invoiceCheckboxes].filter(cb => cb.checked).length;
            
            exportButton.disabled = checkedCount === 0;
            
            if (checkedCount > 0) {
                exportButton.textContent = `Export Selected Invoices (${checkedCount})`;
            } else {
                exportButton.textContent = 'Export Selected Invoices';
            }
        }
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

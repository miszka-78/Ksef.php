<?php
/**
 * Invoices listing and management page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';
require_once __DIR__ . '/classes/Invoice.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();

// Set page title
$pageTitle = 'Invoices';

// Initialize classes
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();
$invoice = new Invoice();

// Handle entity selection
$entityId = null;
if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
    $entityId = (int)$_GET['entity_id'];
    
    // Check if user has access to this entity
    if (!userHasEntityAccess($entityId)) {
        setFlashMessage('error', 'You do not have access to this entity');
        redirect('entities.php');
    }
    
    // Set as selected entity
    setSelectedEntity($entityId);
} else {
    // Try to get selected entity from session
    $entityId = getSelectedEntity();
    
    if (!$entityId) {
        // Redirect to entities page to select an entity
        setFlashMessage('warning', 'Please select an entity first');
        redirect('entities.php');
    }
}

// Load the entity
if (!$entity->loadById($entityId)) {
    setFlashMessage('error', 'Entity not found');
    redirect('entities.php');
}

// Process bulk actions if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!checkCsrfToken($token)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        $action = $_POST['bulk_action'];
        $selectedIds = $_POST['invoice_ids'] ?? [];
        
        if (!empty($selectedIds)) {
            switch ($action) {
                case 'export':
                    // Redirect to export page with selected IDs
                    redirect('invoice_export.php?entity_id=' . $entityId . '&ids=' . implode(',', $selectedIds));
                    break;
                    
                case 'archive':
                    // Check export permission
                    if (!userHasEntityAccess($entityId, 'download')) {
                        setFlashMessage('error', 'You do not have permission to archive invoices');
                        break;
                    }
                    
                    // Archive each selected invoice
                    $successCount = 0;
                    foreach ($selectedIds as $id) {
                        if ($invoice->loadById($id) && $invoice->getEntityId() == $entityId) {
                            if ($invoice->markAsArchived()) {
                                $successCount++;
                            }
                        }
                    }
                    
                    if ($successCount > 0) {
                        setFlashMessage('success', $successCount . ' invoice(s) archived successfully');
                        logUserActivity('archive_invoices', $entityId, 'Archived ' . $successCount . ' invoices');
                    } else {
                        setFlashMessage('error', 'Failed to archive invoices');
                    }
                    break;
            }
        } else {
            setFlashMessage('warning', 'No invoices selected');
        }
    }
}

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = ITEMS_PER_PAGE;

// Build filters from GET parameters
$filters = [];

if (isset($_GET['filter']) && $_GET['filter'] === 'unexported') {
    $filters['exported'] = false;
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['dateFrom'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['dateTo'] = $_GET['date_to'];
}

if (isset($_GET['invoice_number']) && !empty($_GET['invoice_number'])) {
    $filters['invoiceNumber'] = $_GET['invoice_number'];
}

if (isset($_GET['seller_tax_id']) && !empty($_GET['seller_tax_id'])) {
    $filters['sellerTaxId'] = $_GET['seller_tax_id'];
}

if (isset($_GET['buyer_tax_id']) && !empty($_GET['buyer_tax_id'])) {
    $filters['buyerTaxId'] = $_GET['buyer_tax_id'];
}

if (isset($_GET['invoice_type']) && !empty($_GET['invoice_type'])) {
    $filters['invoiceType'] = $_GET['invoice_type'];
}

if (isset($_GET['exported']) && $_GET['exported'] !== '') {
    $filters['exported'] = ($_GET['exported'] == '1');
}

if (isset($_GET['archived']) && $_GET['archived'] !== '') {
    $filters['archived'] = ($_GET['archived'] == '1');
}

// Get invoices with filters
$invoices = $invoice->getInvoicesByEntity($entityId, $filters, $page, $perPage);
$totalInvoices = $invoice->countInvoicesByEntity($entityId, $filters);

// Calculate pagination
$totalPages = ceil($totalInvoices / $perPage);

// Build pagination URL with filters
$paginationUrl = 'invoices.php?entity_id=' . $entityId;
foreach ($filters as $key => $value) {
    if ($value !== '') {
        $paginationUrl .= '&' . $key . '=' . urlencode($value);
    }
}
$paginationUrl .= '&page={page}';

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoices - <?= sanitize($entity->getName()) ?></h1>
    
    <div>
        <a href="api/fetch_invoices.php?entity_id=<?= $entityId ?>" class="btn btn-primary me-2">
            <i class="fas fa-sync-alt me-1"></i> Sync Invoices from KSeF
        </a>
        
        <?php if (userHasEntityAccess($entityId, 'export')): ?>
        <a href="invoice_export.php?entity_id=<?= $entityId ?>" class="btn btn-success">
            <i class="fas fa-file-export me-1"></i> Export to Symfonia
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <a href="#filtersCollapse" data-bs-toggle="collapse" class="text-decoration-none text-dark">
                <i class="fas fa-filter me-1"></i> Filters <small class="text-muted">(click to expand)</small>
            </a>
        </h5>
    </div>
    
    <div class="collapse <?= !empty($filters) ? 'show' : '' ?>" id="filtersCollapse">
        <div class="card-body">
            <form method="get" action="invoices.php">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= sanitize($filters['dateFrom'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= sanitize($filters['dateTo'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="invoice_number" class="form-label">Invoice Number</label>
                        <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?= sanitize($filters['invoiceNumber'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="invoice_type" class="form-label">Invoice Type</label>
                        <select class="form-select" id="invoice_type" name="invoice_type">
                            <option value="">All Types</option>
                            <option value="VAT" <?= (isset($filters['invoiceType']) && $filters['invoiceType'] === 'VAT') ? 'selected' : '' ?>>VAT</option>
                            <option value="CORRECTION" <?= (isset($filters['invoiceType']) && $filters['invoiceType'] === 'CORRECTION') ? 'selected' : '' ?>>Correction</option>
                            <option value="ADVANCE" <?= (isset($filters['invoiceType']) && $filters['invoiceType'] === 'ADVANCE') ? 'selected' : '' ?>>Advance</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="seller_tax_id" class="form-label">Seller Tax ID</label>
                        <input type="text" class="form-control" id="seller_tax_id" name="seller_tax_id" value="<?= sanitize($filters['sellerTaxId'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="buyer_tax_id" class="form-label">Buyer Tax ID</label>
                        <input type="text" class="form-control" id="buyer_tax_id" name="buyer_tax_id" value="<?= sanitize($filters['buyerTaxId'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="exported" class="form-label">Export Status</label>
                        <select class="form-select" id="exported" name="exported">
                            <option value="">All</option>
                            <option value="1" <?= (isset($filters['exported']) && $filters['exported'] === true) ? 'selected' : '' ?>>Exported</option>
                            <option value="0" <?= (isset($filters['exported']) && $filters['exported'] === false) ? 'selected' : '' ?>>Not Exported</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="archived" class="form-label">Archive Status</label>
                        <select class="form-select" id="archived" name="archived">
                            <option value="">All</option>
                            <option value="1" <?= (isset($filters['archived']) && $filters['archived'] === true) ? 'selected' : '' ?>>Archived</option>
                            <option value="0" <?= (isset($filters['archived']) && $filters['archived'] === false) ? 'selected' : '' ?>>Not Archived</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="invoices.php?entity_id=<?= $entityId ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-eraser me-1"></i> Clear Filters
                    </a>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoices List -->
<?php if (empty($invoices)): ?>
<div class="alert alert-info">
    <p class="mb-0">No invoices found matching your criteria. Try changing filters or sync invoices from KSeF.</p>
</div>
<?php else: ?>

<form method="post" id="invoicesForm">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <?php if (userHasEntityAccess($entityId, 'export') || userHasEntityAccess($entityId, 'download')): ?>
                <div class="me-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">Select All</label>
                    </div>
                </div>
                
                <div class="bulk-actions">
                    <div class="input-group">
                        <select name="bulk_action" class="form-select" id="bulkAction">
                            <option value="">Bulk Actions</option>
                            <?php if (userHasEntityAccess($entityId, 'export')): ?>
                            <option value="export">Export Selected</option>
                            <?php endif; ?>
                            <?php if (userHasEntityAccess($entityId, 'download')): ?>
                            <option value="archive">Archive Selected</option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary" id="applyBulkAction" disabled>Apply</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div>
                <span class="badge bg-secondary">Total: <?= $totalInvoices ?> invoices</span>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php if (userHasEntityAccess($entityId, 'export') || userHasEntityAccess($entityId, 'download')): ?>
                            <th width="40px"></th>
                            <?php endif; ?>
                            <th>Invoice Number</th>
                            <th>Date</th>
                            <th>Seller</th>
                            <th>Buyer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <?php if (userHasEntityAccess($entityId, 'export') || userHasEntityAccess($entityId, 'download')): ?>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input invoice-checkbox" type="checkbox" name="invoice_ids[]" value="<?= $inv['id'] ?>" id="invoice<?= $inv['id'] ?>">
                                </div>
                            </td>
                            <?php endif; ?>
                            
                            <td>
                                <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="fw-medium">
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
                            <td>
                                <?php if ($inv['is_exported']): ?>
                                <span class="badge bg-success me-1">Exported</span>
                                <?php else: ?>
                                <span class="badge bg-warning me-1">Not Exported</span>
                                <?php endif; ?>
                                
                                <?php if ($inv['is_archived']): ?>
                                <span class="badge bg-secondary">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (userHasEntityAccess($entityId, 'export') && !$inv['is_exported']): ?>
                                    <a href="invoice_export.php?entity_id=<?= $entityId ?>&ids=<?= $inv['id'] ?>" class="btn btn-outline-success" title="Export to Symfonia">
                                        <i class="fas fa-file-export"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (userHasEntityAccess($entityId, 'download') && !$inv['is_archived']): ?>
                                    <a href="?entity_id=<?= $entityId ?>&archive=<?= $inv['id'] ?>&csrf_token=<?= getCsrfToken() ?>" class="btn btn-outline-secondary" title="Archive Invoice" onclick="return confirm('Are you sure you want to archive this invoice?')">
                                        <i class="fas fa-box-archive"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <?= generatePagination($page, $totalPages, $paginationUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const bulkActionSelect = document.getElementById('bulkAction');
    const applyBulkButton = document.getElementById('applyBulkAction');
    
    if (selectAllCheckbox) {
        // Select All functionality
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActionButton();
        });
        
        // Individual checkbox change handler
        invoiceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkActionButton();
                
                // Update "Select All" checkbox state
                const allChecked = [...invoiceCheckboxes].every(cb => cb.checked);
                const someChecked = [...invoiceCheckboxes].some(cb => cb.checked);
                
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
                selectAllCheckbox.checked = allChecked;
            });
        });
        
        // Bulk action select change handler
        if (bulkActionSelect) {
            bulkActionSelect.addEventListener('change', updateBulkActionButton);
        }
        
        // Form submission confirmation
        document.getElementById('invoicesForm').addEventListener('submit', function(e) {
            const action = bulkActionSelect.value;
            const checkedCount = [...invoiceCheckboxes].filter(cb => cb.checked).length;
            
            if (action && checkedCount > 0) {
                if (!confirm(`Are you sure you want to ${action} ${checkedCount} selected invoice(s)?`)) {
                    e.preventDefault();
                }
            } else {
                e.preventDefault();
                alert('Please select invoices and an action');
            }
        });
    }
    
    function updateBulkActionButton() {
        if (applyBulkButton) {
            const checkedCount = [...invoiceCheckboxes].filter(cb => cb.checked).length;
            const hasAction = bulkActionSelect.value !== '';
            
            applyBulkButton.disabled = checkedCount === 0 || !hasAction;
            
            if (checkedCount > 0) {
                applyBulkButton.textContent = `Apply (${checkedCount})`;
            } else {
                applyBulkButton.textContent = 'Apply';
            }
        }
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

<?php
/**
 * Export history page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';
require_once __DIR__ . '/classes/SymfoniaExport.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();

// Set page title
$pageTitle = 'Export History';

// Initialize classes
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();

// Handle entity selection
$entityId = null;
if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
    $entityId = (int)$_GET['entity_id'];
    
    // Check if user has access to this entity
    if (!userHasEntityAccess($entityId, 'export')) {
        setFlashMessage('error', 'You do not have permission to view export history for this entity');
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

// Initialize Symfonia export
$symfoniaExport = new SymfoniaExport($entity);

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = ITEMS_PER_PAGE;

// Get export history with pagination
$exports = $symfoniaExport->getExportBatches($page, $perPage);
$totalExports = $symfoniaExport->countExportBatches();

// Calculate pagination
$totalPages = ceil($totalExports / $perPage);
$paginationUrl = 'export_history.php?entity_id=' . $entityId . '&page={page}';

// Get specific export batch details if requested
$batchDetails = null;
if (isset($_GET['batch_id']) && !empty($_GET['batch_id'])) {
    $batchId = (int)$_GET['batch_id'];
    $batchDetails = $symfoniaExport->getExportBatchDetails($batchId);
    
    if (!$batchDetails) {
        setFlashMessage('error', 'Export batch not found');
    }
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Export History - <?= sanitize($entity->getName()) ?></h1>
    
    <div>
        <a href="invoice_export.php?entity_id=<?= $entityId ?>" class="btn btn-primary">
            <i class="fas fa-file-export me-1"></i> New Export
        </a>
    </div>
</div>

<?php if ($batchDetails): ?>
<!-- Batch Details View -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Export Batch Details</h5>
        
        <a href="export_history.php?entity_id=<?= $entityId ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to List
        </a>
    </div>
    
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Export Date:</th>
                        <td><?= formatDate($batchDetails['export_date'], 'Y-m-d H:i:s') ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?= $batchDetails['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= ucfirst(sanitize($batchDetails['status'])) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Format:</th>
                        <td><?= sanitize($batchDetails['symfonia_format']) ?></td>
                    </tr>
                    <tr>
                        <th>User:</th>
                        <td><?= sanitize($batchDetails['username'] ?? 'Unknown') ?></td>
                    </tr>
                    <tr>
                        <th>Invoice Count:</th>
                        <td><?= $batchDetails['invoice_count'] ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-4 text-md-end">
                <a href="exports/<?= urlencode($batchDetails['filename']) ?>" class="btn btn-primary" download>
                    <i class="fas fa-download me-1"></i> Download Export File
                </a>
            </div>
        </div>
        
        <h5 class="mb-3">Exported Invoices</h5>
        
        <?php if (empty($batchDetails['invoices'])): ?>
        <div class="alert alert-info">
            <p class="mb-0">No invoice details available for this export.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Issue Date</th>
                        <th>Seller</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batchDetails['invoices'] as $invoice): ?>
                    <tr>
                        <td>
                            <a href="invoice_details.php?id=<?= $invoice['id'] ?>">
                                <?= sanitize($invoice['invoice_number']) ?>
                            </a>
                        </td>
                        <td><?= formatDate($invoice['issue_date']) ?></td>
                        <td title="<?= sanitize($invoice['seller_name']) ?>">
                            <?= sanitize(truncateText($invoice['seller_name'], 25)) ?>
                            <div><small class="text-muted"><?= sanitize(formatNip($invoice['seller_tax_id'])) ?></small></div>
                        </td>
                        <td title="<?= sanitize($invoice['buyer_name']) ?>">
                            <?= sanitize(truncateText($invoice['buyer_name'], 25)) ?>
                            <div><small class="text-muted"><?= sanitize(formatNip($invoice['buyer_tax_id'])) ?></small></div>
                        </td>
                        <td><?= formatMoney($invoice['total_gross'], $invoice['currency']) ?></td>
                        <td>
                            <a href="invoice_details.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Export History List -->
<div class="card">
    <?php if (empty($exports)): ?>
    <div class="card-body p-4 text-center">
        <p class="text-muted mb-3">No export history found. Create your first export to see it here.</p>
        <a href="invoice_export.php?entity_id=<?= $entityId ?>" class="btn btn-primary">
            <i class="fas fa-file-export me-1"></i> Create Export
        </a>
    </div>
    <?php else: ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Date</th>
                        <th>Format</th>
                        <th>Invoices</th>
                        <th>Status</th>
                        <th>User</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exports as $export): ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= formatDate($export['export_date'], 'Y-m-d') ?></div>
                            <div><small class="text-muted"><?= formatDate($export['export_date'], 'H:i:s') ?></small></div>
                        </td>
                        <td><?= sanitize($export['symfonia_format']) ?></td>
                        <td><?= $export['invoice_count'] ?></td>
                        <td>
                            <span class="badge bg-<?= $export['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= ucfirst(sanitize($export['status'])) ?>
                            </span>
                        </td>
                        <td><?= sanitize($export['username'] ?? 'Unknown') ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="export_history.php?entity_id=<?= $entityId ?>&batch_id=<?= $export['id'] ?>" 
                                   class="btn btn-outline-primary" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="exports/<?= urlencode($export['filename']) ?>" 
                                   class="btn btn-outline-success" title="Download File" download>
                                    <i class="fas fa-download"></i>
                                </a>
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
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

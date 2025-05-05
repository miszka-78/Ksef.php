<?php
/**
 * Dashboard page
 * Shows summary information and system status
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
$pageTitle = 'Dashboard';

// Initialize classes
$db = Database::getInstance();
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();
$invoice = new Invoice();

// Get entities accessible to the user
$userEntities = $user->getUserEntities();

// Selected entity handling
$selectedEntityId = null;
if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
    $selectedEntityId = (int)$_GET['entity_id'];
    setSelectedEntity($selectedEntityId);
} else {
    $selectedEntityId = getSelectedEntity();
    
    // If no entity is selected and user has access to entities, select the first one
    if ($selectedEntityId === null && !empty($userEntities)) {
        $selectedEntityId = $userEntities[0]['id'];
        setSelectedEntity($selectedEntityId);
    }
}

// Summary data
$summaryData = [
    'total_invoices' => 0,
    'new_invoices' => 0,
    'exported_invoices' => 0,
    'archived_invoices' => 0,
    'total_entities' => count($userEntities),
    'last_sync' => 'Never'
];

// If an entity is selected, get invoice statistics
if ($selectedEntityId) {
    $entity->loadById($selectedEntityId);
    
    // Count invoices
    $summaryData['total_invoices'] = $invoice->countInvoicesByEntity($selectedEntityId);
    
    // Count new (not exported) invoices
    $summaryData['new_invoices'] = $invoice->countInvoicesByEntity($selectedEntityId, ['exported' => false]);
    
    // Count exported invoices
    $summaryData['exported_invoices'] = $invoice->countInvoicesByEntity($selectedEntityId, ['exported' => true]);
    
    // Count archived invoices
    $summaryData['archived_invoices'] = $invoice->countInvoicesByEntity($selectedEntityId, ['archived' => true]);
    
    // Get last sync time from activity log
    $query = "SELECT created_at FROM activity_logs 
             WHERE action = 'sync_invoices' AND entity_id = ? 
             ORDER BY created_at DESC LIMIT 1";
    $lastSync = $db->fetchRow($query, [$selectedEntityId]);
    
    if ($lastSync) {
        $summaryData['last_sync'] = formatDate($lastSync['created_at'], 'Y-m-d H:i');
    }
    
    // Get recent invoices
    $recentInvoices = $invoice->getInvoicesByEntity($selectedEntityId, [], 1, 5);
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Dashboard</h1>
    
    <?php if (!empty($userEntities)): ?>
    <div class="entity-selector">
        <form action="" method="get" class="d-flex align-items-center">
            <label for="entity_id" class="me-2">Select Entity:</label>
            <select name="entity_id" id="entity_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($userEntities as $entityItem): ?>
                <option value="<?= $entityItem['id'] ?>" <?= $selectedEntityId == $entityItem['id'] ? 'selected' : '' ?>>
                    <?= sanitize($entityItem['name']) ?> (<?= sanitize($entityItem['tax_id']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($userEntities)): ?>
<div class="alert alert-info">
    <h4 class="alert-heading">Welcome to KSeF Invoice Manager!</h4>
    <p>You don't have access to any entities yet. Please contact your administrator to get access to entities.</p>
</div>
<?php elseif ($selectedEntityId): ?>
<div class="row">
    <!-- Summary Cards -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Invoices</h6>
                        <h3 class="mb-0"><?= $summaryData['total_invoices'] ?></h3>
                    </div>
                    <div class="bg-light-primary p-3 rounded">
                        <i class="fas fa-file-invoice fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card border-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">New Invoices</h6>
                        <h3 class="mb-0"><?= $summaryData['new_invoices'] ?></h3>
                    </div>
                    <div class="bg-light-success p-3 rounded">
                        <i class="fas fa-file-circle-plus fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card border-info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Exported</h6>
                        <h3 class="mb-0"><?= $summaryData['exported_invoices'] ?></h3>
                    </div>
                    <div class="bg-light-info p-3 rounded">
                        <i class="fas fa-file-export fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card border-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Archived</h6>
                        <h3 class="mb-0"><?= $summaryData['archived_invoices'] ?></h3>
                    </div>
                    <div class="bg-light-warning p-3 rounded">
                        <i class="fas fa-box-archive fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Entity Information -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Entity Information</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr>
                            <th width="35%">Name</th>
                            <td><?= sanitize($entity->getName()) ?></td>
                        </tr>
                        <tr>
                            <th>Tax ID (NIP)</th>
                            <td><?= sanitize(formatNip($entity->getTaxId())) ?></td>
                        </tr>
                        <tr>
                            <th>KSeF Identifier</th>
                            <td><?= sanitize($entity->getKsefIdentifier() ?: 'Not set') ?></td>
                        </tr>
                        <tr>
                            <th>KSeF Environment</th>
                            <td>
                                <span class="badge bg-<?= $entity->getKsefEnvironment() === 'prod' ? 'danger' : ($entity->getKsefEnvironment() === 'test' ? 'warning' : 'info') ?>">
                                    <?= strtoupper(sanitize($entity->getKsefEnvironment())) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>KSeF Token Status</th>
                            <td>
                                <?php if ($entity->hasValidKsefToken()): ?>
                                <span class="badge bg-success">Valid</span>
                                <small class="text-muted ms-2">Expires: <?= formatDate($entity->getKsefTokenExpiry(), 'Y-m-d H:i') ?></small>
                                <?php else: ?>
                                <span class="badge bg-danger">Invalid or Expired</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Last Sync</th>
                            <td><?= sanitize($summaryData['last_sync']) ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="d-flex mt-3">
                    <a href="invoices.php?entity_id=<?= $entity->getId() ?>" class="btn btn-primary me-2">
                        <i class="fas fa-file-invoice me-1"></i> View Invoices
                    </a>
                    <a href="ksef_fetch_form.php?entity_id=<?= $entity->getId() ?>" class="btn btn-success me-2">
                        <i class="fas fa-cloud-download-alt me-1"></i> Fetch from KSeF
                    </a>
                    <a href="api/fetch_invoices.php?entity_id=<?= $entity->getId() ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt me-1"></i> Quick Sync
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Invoices -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Invoices</h5>
                <a href="invoices.php?entity_id=<?= $entity->getId() ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (isset($recentInvoices) && !empty($recentInvoices)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Number</th>
                                <th>Date</th>
                                <th>Seller/Buyer</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td>
                                    <a href="invoice_details.php?id=<?= $inv['id'] ?>">
                                        <?= sanitize($inv['invoice_number']) ?>
                                    </a>
                                </td>
                                <td><?= formatDate($inv['issue_date']) ?></td>
                                <td>
                                    <small title="<?= sanitize($inv['seller_name']) ?>">
                                        <?= sanitize(truncateText($inv['seller_name'], 20)) ?>
                                    </small><br>
                                    <small class="text-muted" title="<?= sanitize($inv['buyer_name']) ?>">
                                        <?= sanitize(truncateText($inv['buyer_name'], 20)) ?>
                                    </small>
                                </td>
                                <td><?= formatMoney($inv['total_gross'], $inv['currency']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-4 text-center">
                    <p class="text-muted mb-0">No invoices available.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="invoice_export.php?entity_id=<?= $entity->getId() ?>" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-file-export fa-2x mb-2"></i><br>
                            Export to Symfonia FK
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="invoices.php?entity_id=<?= $entity->getId() ?>&filter=unexported" class="btn btn-outline-success w-100 p-3">
                            <i class="fas fa-file-circle-plus fa-2x mb-2"></i><br>
                            New Invoices
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="templates.php?entity_id=<?= $entity->getId() ?>" class="btn btn-outline-info w-100 p-3">
                            <i class="fas fa-paint-brush fa-2x mb-2"></i><br>
                            Invoice Templates
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="entity_form.php?id=<?= $entity->getId() ?>" class="btn btn-outline-secondary w-100 p-3">
                            <i class="fas fa-building fa-2x mb-2"></i><br>
                            Entity Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

<?php
/**
 * KSeF Fetch Form
 * Form for fetching invoices from KSeF with parameters
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();

// Set page title
$pageTitle = 'Fetch Invoices from KSeF';

// Initialize classes
$db = Database::getInstance();
$user = new User();
$user->loadById($_SESSION['user_id']);

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

// Load selected entity
$entity = null;
if ($selectedEntityId) {
    $entity = new Entity();
    $entity->loadById($selectedEntityId);
}

// Set default date values
$defaultDateFrom = date('Y-m-d', strtotime('-30 days'));
$defaultDateTo = date('Y-m-d');

// Check if the form was submitted
$formSubmitted = isset($_POST['fetch_invoices']);

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Fetch Invoices from KSeF</h1>
    
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
    <h4 class="alert-heading">No Entities Available</h4>
    <p>You don't have access to any entities yet. Please contact your administrator to get access to entities.</p>
</div>
<?php elseif ($selectedEntityId && $entity): ?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">KSeF Fetch Parameters</h5>
            </div>
            <div class="card-body">
                <?php if (!$entity->hasValidKsefToken()): ?>
                <div class="alert alert-warning">
                    <h5 class="alert-heading">KSeF Authentication Required</h5>
                    <p>Your KSeF token is invalid or expired. Please provide authentication details to fetch invoices.</p>
                </div>
                <?php endif; ?>
                
                <form action="api/fetch_invoices.php" method="get" id="fetch-form">
                    <input type="hidden" name="entity_id" value="<?= $selectedEntityId ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_from" class="form-label">Date From:</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" value="<?= $defaultDateFrom ?>" required>
                            <div class="form-text">Start date for invoice search</div>
                        </div>
                        <div class="col-md-6">
                            <label for="date_to" class="form-label">Date To:</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" value="<?= $defaultDateTo ?>" required>
                            <div class="form-text">End date for invoice search</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="page_size" class="form-label">Results Per Page:</label>
                            <select id="page_size" name="page_size" class="form-select">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100" selected>100 (Maximum)</option>
                            </select>
                            <div class="form-text">Number of invoices to fetch per request</div>
                        </div>
                        <div class="col-md-6">
                            <label for="page" class="form-label">Page Number:</label>
                            <input type="number" id="page" name="page" class="form-control" value="1" min="1" required>
                            <div class="form-text">Page of results to retrieve</div>
                        </div>
                    </div>
                    
                    <?php if (!$entity->hasValidKsefToken()): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="ksef_password" class="form-label">KSeF Password:</label>
                            <input type="password" id="ksef_password" name="ksef_password" class="form-control" required>
                            <div class="form-text">Password for KSeF authentication</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="json" id="format" name="format">
                                <label class="form-check-label" for="format">
                                    Return result as JSON
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex">
                        <button type="submit" name="fetch_invoices" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-1"></i> Fetch Invoices
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Entity Information -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Entity Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-striped">
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
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <th width="35%">KSeF Environment</th>
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
                                    <th>KSeF API URL</th>
                                    <td><small><?= sanitize($entity->getKsefApiUrl()) ?></small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <h5>Fetch Instructions</h5>
    <ol class="mb-4">
        <li>Select the date range for which you want to fetch invoices from KSeF.</li>
        <li>Choose the number of results per page (maximum 100).</li>
        <li>If your token is expired, provide your KSeF password for authentication.</li>
        <li>Click the "Fetch Invoices" button to start the fetch process.</li>
        <li>If you need to fetch multiple pages, increment the page number and fetch again.</li>
    </ol>
    <p class="text-muted">Note: The process may take some time depending on the number of invoices to fetch and process.</p>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fetchForm = document.getElementById('fetch-form');
    if (fetchForm) {
        fetchForm.addEventListener('submit', function(e) {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            if (new Date(dateFrom) > new Date(dateTo)) {
                e.preventDefault();
                alert('Date From cannot be later than Date To');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>
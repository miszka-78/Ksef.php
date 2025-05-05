<?php
/**
 * Entities listing and management page
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
$pageTitle = 'Entities';

// Initialize classes
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = ITEMS_PER_PAGE;

// Get entities based on user role
$entities = [];
$totalEntities = 0;

if ($user->isAdmin()) {
    $entities = $entity->getAllEntities($page, $perPage);
    $totalEntities = $entity->countEntities();
} else {
    $entities = $user->getUserEntities();
    $totalEntities = count($entities);
    
    // Apply pagination manually for non-admin users
    $entities = array_slice($entities, ($page - 1) * $perPage, $perPage);
}

// Calculate pagination
$totalPages = ceil($totalEntities / $perPage);
$paginationUrl = 'entities.php?page={page}';

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Entities</h1>
    
    <?php if ($user->isAdmin()): ?>
    <a href="entity_form.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add New Entity
    </a>
    <?php endif; ?>
</div>

<?php if (empty($entities)): ?>
<div class="alert alert-info">
    <p class="mb-0">No entities found. <?= $user->isAdmin() ? 'Click "Add New Entity" to create one.' : 'Please contact your administrator to get access to entities.' ?></p>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Name</th>
                        <th>Tax ID</th>
                        <th>KSeF Identifier</th>
                        <th>Environment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entities as $entityItem): ?>
                    <tr>
                        <td class="fw-medium"><?= sanitize($entityItem['name']) ?></td>
                        <td><?= sanitize(formatNip($entityItem['tax_id'])) ?></td>
                        <td>
                            <?php if (!empty($entityItem['ksef_identifier'])): ?>
                            <?= sanitize($entityItem['ksef_identifier']) ?>
                            <?php else: ?>
                            <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $entityItem['ksef_environment'] === 'prod' ? 'danger' : ($entityItem['ksef_environment'] === 'test' ? 'warning' : 'info') ?>">
                                <?= strtoupper(sanitize($entityItem['ksef_environment'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($entityItem['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex">
                                <a href="invoices.php?entity_id=<?= $entityItem['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View Invoices">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                                
                                <?php if ($user->isAdmin() || $user->isManager()): ?>
                                <a href="entity_form.php?id=<?= $entityItem['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit Entity">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="dashboard.php?entity_id=<?= $entityItem['id'] ?>" class="btn btn-sm btn-outline-info" title="Set as Default">
                                    <i class="fas fa-check"></i>
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
</div>
<?php endif; ?>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

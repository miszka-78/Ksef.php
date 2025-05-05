<?php
/**
 * Entity creation and editing form
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';
require_once __DIR__ . '/classes/KsefApi.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication and admin/manager role
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

// Set page title
$pageTitle = 'Entity Form';

// Initialize classes
$entity = new Entity();
$user = new User();
$user->loadById($_SESSION['user_id']);

// Check if editing existing entity or creating new one
$isEditing = false;
$entityId = null;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $entityId = (int)$_GET['id'];
    $isEditing = $entity->loadById($entityId);
    
    // Check if editing allowed
    if ($isEditing && !$user->isAdmin() && !$user->hasEntityAccess($entityId, 'edit')) {
        setFlashMessage('error', 'You do not have permission to edit this entity');
        redirect('entities.php');
    }
    
    $pageTitle = 'Edit Entity';
} else {
    // Only admin can create new entities
    requireRole(ROLE_ADMIN);
    $pageTitle = 'Add New Entity';
}

// Process form submission
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!checkCsrfToken($token)) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Get form data
        $entityData = [
            'name' => $_POST['name'] ?? '',
            'tax_id' => $_POST['tax_id'] ?? '',
            'ksef_identifier' => $_POST['ksef_identifier'] ?? null,
            'ksef_environment' => $_POST['ksef_environment'] ?? 'test',
            'is_active' => isset($_POST['is_active']) ? true : false
        ];
        
        // Validate form data
        if (empty($entityData['name'])) {
            $errors[] = 'Entity name is required';
        }
        
        if (empty($entityData['tax_id'])) {
            $errors[] = 'Tax ID is required';
        } elseif (!preg_match('/^\d{10}$/', preg_replace('/[^0-9]/', '', $entityData['tax_id']))) {
            $errors[] = 'Tax ID must be a valid 10-digit NIP number';
        }
        
        // Handle KSeF authentication if provided
        $ksefPassword = $_POST['ksef_password'] ?? '';
        
        if (!empty($ksefPassword)) {
            if ($isEditing) {
                // Use existing entity for API
                $ksefApi = new KsefApi($entity);
                
                // Try to authenticate with password
                $authResult = $ksefApi->authenticate('password', [
                    'nip' => $entityData['tax_id'],
                    'password' => $ksefPassword
                ]);
                
                if (!$authResult['success']) {
                    $errors[] = 'KSeF authentication failed: ' . ($authResult['error'] ?? 'Unknown error');
                }
            } else {
                // For new entity, we'll authenticate after creation
                $doKsefAuth = true;
            }
        }
        
        // If no errors, save the entity
        if (empty($errors)) {
            if ($isEditing) {
                // Update existing entity
                if ($entity->update($entityData)) {
                    setFlashMessage('success', 'Entity updated successfully');
                    logUserActivity('update_entity', $entity->getId(), 'Updated entity: ' . $entity->getName());
                    redirect('entities.php');
                } else {
                    $errors[] = 'Failed to update entity';
                }
            } else {
                // Create new entity
                $newEntityId = $entity->create($entityData);
                
                if ($newEntityId) {
                    // Load the newly created entity
                    $entity->loadById($newEntityId);
                    
                    // Try KSeF authentication if password was provided
                    if (isset($doKsefAuth) && $doKsefAuth && !empty($ksefPassword)) {
                        $ksefApi = new KsefApi($entity);
                        $authResult = $ksefApi->authenticate('password', [
                            'nip' => $entityData['tax_id'],
                            'password' => $ksefPassword
                        ]);
                        
                        if (!$authResult['success']) {
                            $successMessage = 'Entity created successfully, but KSeF authentication failed: ' . ($authResult['error'] ?? 'Unknown error');
                        } else {
                            $successMessage = 'Entity created successfully and KSeF authentication completed';
                        }
                    } else {
                        $successMessage = 'Entity created successfully';
                    }
                    
                    logUserActivity('create_entity', $newEntityId, 'Created new entity: ' . $entity->getName());
                    
                    if (empty($successMessage)) {
                        setFlashMessage('success', 'Entity created successfully');
                        redirect('entities.php');
                    }
                } else {
                    $errors[] = 'Failed to create entity';
                }
            }
        }
    }
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $isEditing ? 'Edit Entity' : 'Add New Entity' ?></h1>
    
    <a href="entities.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Entities
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= sanitize($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success">
    <?= sanitize($successMessage) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Entity Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= sanitize($isEditing ? $entity->getName() : ($_POST['name'] ?? '')) ?>" required>
                    <div class="form-text">Full legal name of the company or organization</div>
                </div>
                
                <div class="col-md-6">
                    <label for="tax_id" class="form-label">Tax ID (NIP) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= sanitize($isEditing ? $entity->getTaxId() : ($_POST['tax_id'] ?? '')) ?>" required <?= $isEditing ? 'readonly' : '' ?>>
                    <div class="form-text">10-digit NIP number without dashes</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="ksef_identifier" class="form-label">KSeF Identifier</label>
                    <input type="text" class="form-control" id="ksef_identifier" name="ksef_identifier" value="<?= sanitize($isEditing ? $entity->getKsefIdentifier() : ($_POST['ksef_identifier'] ?? '')) ?>">
                    <div class="form-text">Optional identifier for KSeF API authentication</div>
                </div>
                
                <div class="col-md-6">
                    <label for="ksef_environment" class="form-label">KSeF Environment <span class="text-danger">*</span></label>
                    <select class="form-select" id="ksef_environment" name="ksef_environment" required>
                        <option value="test" <?= ($isEditing && $entity->getKsefEnvironment() === 'test') || (!$isEditing && ($_POST['ksef_environment'] ?? '') === 'test') ? 'selected' : '' ?>>Test</option>
                        <option value="demo" <?= ($isEditing && $entity->getKsefEnvironment() === 'demo') || (!$isEditing && ($_POST['ksef_environment'] ?? '') === 'demo') ? 'selected' : '' ?>>Demo</option>
                        <option value="prod" <?= ($isEditing && $entity->getKsefEnvironment() === 'prod') || (!$isEditing && ($_POST['ksef_environment'] ?? '') === 'prod') ? 'selected' : '' ?>>Production</option>
                    </select>
                    <div class="form-text">Select the KSeF environment to use</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="ksef_password" class="form-label">KSeF Password</label>
                    <input type="password" class="form-control" id="ksef_password" name="ksef_password">
                    <div class="form-text">
                        <?php if ($isEditing && $entity->hasValidKsefToken()): ?>
                        Current token valid until <?= formatDate($entity->getKsefTokenExpiry(), 'Y-m-d H:i') ?>. Enter password only to refresh.
                        <?php else: ?>
                        Enter KSeF password to authenticate with KSeF API
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $isEditing ? ($entity->isActive() ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label" for="is_active">
                            Entity is active
                        </label>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-between">
                <a href="entities.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <?= $isEditing ? 'Update Entity' : 'Create Entity' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($isEditing): ?>
<div class="mt-4">
    <h4>Entity Access</h4>
    <div class="card">
        <div class="card-body">
            <h5>Users with Access to This Entity</h5>
            
            <?php
            $entityUsers = $entity->getEntityUsers();
            if (empty($entityUsers)):
            ?>
            <p class="text-muted">No users have access to this entity yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="bg-light">
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Access Rights</th>
                            <?php if ($user->isAdmin()): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entityUsers as $entityUser): ?>
                        <tr>
                            <td><?= sanitize($entityUser['username']) ?></td>
                            <td><?= sanitize($entityUser['full_name']) ?></td>
                            <td><?= sanitize(ucfirst($entityUser['role'])) ?></td>
                            <td>
                                <?php if ($entityUser['can_view']): ?>
                                <span class="badge bg-success me-1">View</span>
                                <?php endif; ?>
                                
                                <?php if ($entityUser['can_download']): ?>
                                <span class="badge bg-info me-1">Download</span>
                                <?php endif; ?>
                                
                                <?php if ($entityUser['can_export']): ?>
                                <span class="badge bg-primary">Export</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($user->isAdmin()): ?>
                            <td>
                                <a href="user_form.php?id=<?= $entityUser['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if ($user->isAdmin()): ?>
            <div class="mt-3">
                <a href="user_form.php?entity_id=<?= $entity->getId() ?>" class="btn btn-outline-primary">
                    <i class="fas fa-user-plus me-1"></i> Add User Access
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

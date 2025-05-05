<?php
/**
 * User creation and editing form
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication and admin role
requireRole(ROLE_ADMIN);

// Set page title
$pageTitle = 'User Form';

// Initialize classes
$user = new User();
$entity = new Entity();

// Check if editing existing user or creating new one
$isEditing = false;
$userId = null;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $isEditing = $user->loadById($userId);
    $pageTitle = 'Edit User';
} else {
    $pageTitle = 'Add New User';
}

// Check if specific entity access is being added
$specificEntityId = null;
if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
    $specificEntityId = (int)$_GET['entity_id'];
    // Verify entity exists
    if (!$entity->loadById($specificEntityId)) {
        setFlashMessage('error', 'Entity not found');
        redirect('entities.php');
    }
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
        $userData = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'fullName' => $_POST['full_name'] ?? '',
            'role' => $_POST['role'] ?? ROLE_USER,
            'is_active' => isset($_POST['is_active']) ? true : false
        ];
        
        // Handle password
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // For new users, password is required
        if (!$isEditing && empty($password)) {
            $errors[] = 'Password is required for new users';
        }
        
        // If password is provided, check confirmation
        if (!empty($password)) {
            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters long';
            } else {
                $userData['password'] = $password;
            }
        }
        
        // Validate other fields
        if (empty($userData['username'])) {
            $errors[] = 'Username is required';
        } elseif (!$isEditing) {
            // Check username uniqueness for new users
            $checkUser = new User();
            if ($checkUser->loadByUsername($userData['username'])) {
                $errors[] = 'Username already exists';
            }
        }
        
        if (empty($userData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($userData['fullName'])) {
            $errors[] = 'Full name is required';
        }
        
        // If no errors, save the user
        if (empty($errors)) {
            if ($isEditing) {
                // Update existing user
                if ($user->update($userData)) {
                    $successMessage = 'User updated successfully';
                    logUserActivity('update_user', null, 'Updated user: ' . $user->getUsername());
                } else {
                    $errors[] = 'Failed to update user';
                }
            } else {
                // Create new user
                $newUserId = $user->create($userData);
                
                if ($newUserId) {
                    $user->loadById($newUserId);
                    $successMessage = 'User created successfully';
                    logUserActivity('create_user', null, 'Created new user: ' . $user->getUsername());
                } else {
                    $errors[] = 'Failed to create user';
                }
            }
            
            // If there's a specific entity to grant access to
            if (!empty($successMessage) && $specificEntityId) {
                $accessRights = [
                    'can_view' => isset($_POST['entity_can_view']),
                    'can_download' => isset($_POST['entity_can_download']),
                    'can_export' => isset($_POST['entity_can_export'])
                ];
                
                if ($user->grantEntityAccess($specificEntityId, $accessRights)) {
                    $successMessage .= ' and entity access has been granted';
                    logUserActivity('grant_entity_access', $specificEntityId, 'Granted entity access to user: ' . $user->getUsername());
                } else {
                    $successMessage .= ' but failed to grant entity access';
                }
            }
            
            // Handle entity access updates for existing users
            if ($isEditing && isset($_POST['entity_access']) && is_array($_POST['entity_access'])) {
                foreach ($_POST['entity_access'] as $entityId => $access) {
                    $accessRights = [
                        'can_view' => isset($access['view']),
                        'can_download' => isset($access['download']),
                        'can_export' => isset($access['export'])
                    ];
                    
                    if (isset($access['remove'])) {
                        $user->revokeEntityAccess($entityId);
                        logUserActivity('revoke_entity_access', $entityId, 'Revoked entity access from user: ' . $user->getUsername());
                    } else {
                        $user->grantEntityAccess($entityId, $accessRights);
                        logUserActivity('update_entity_access', $entityId, 'Updated entity access for user: ' . $user->getUsername());
                    }
                }
                
                $successMessage .= ' and entity access has been updated';
            }
            
            // If no specific message to display, redirect to users list
            if (empty($successMessage)) {
                setFlashMessage('success', 'User saved successfully');
                redirect('users.php');
            }
        }
    }
}

// Get all entities for access management
$allEntities = $entity->getAllEntities(1, 100);

// Get user's entity access if editing
$userEntityAccess = [];
if ($isEditing) {
    $userEntityAccess = $user->getUserEntities();
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $isEditing ? 'Edit User' : 'Add New User' ?></h1>
    
    <a href="<?= $specificEntityId ? 'entity_form.php?id=' . $specificEntityId : 'users.php' ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
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
                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" 
                        value="<?= sanitize($isEditing ? $user->getUsername() : ($_POST['username'] ?? '')) ?>" 
                        <?= $isEditing ? 'readonly' : '' ?> required>
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" 
                        value="<?= sanitize($isEditing ? $user->getEmail() : ($_POST['email'] ?? '')) ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                        value="<?= sanitize($isEditing ? $user->getFullName() : ($_POST['full_name'] ?? '')) ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="<?= ROLE_USER ?>" <?= ($isEditing && $user->getRole() === ROLE_USER) || (!$isEditing && ($_POST['role'] ?? '') === ROLE_USER) ? 'selected' : '' ?>>User</option>
                        <option value="<?= ROLE_MANAGER ?>" <?= ($isEditing && $user->getRole() === ROLE_MANAGER) || (!$isEditing && ($_POST['role'] ?? '') === ROLE_MANAGER) ? 'selected' : '' ?>>Manager</option>
                        <option value="<?= ROLE_ADMIN ?>" <?= ($isEditing && $user->getRole() === ROLE_ADMIN) || (!$isEditing && ($_POST['role'] ?? '') === ROLE_ADMIN) ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label"><?= $isEditing ? 'New Password (leave blank to keep current)' : 'Password <span class="text-danger">*</span>' ?></label>
                    <input type="password" class="form-control" id="password" name="password" 
                        <?= $isEditing ? '' : 'required' ?>>
                    <?php if ($isEditing): ?>
                    <div class="form-text">Leave blank to keep the current password</div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label"><?= $isEditing ? 'Confirm New Password' : 'Confirm Password <span class="text-danger">*</span>' ?></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                        <?= $isEditing ? '' : 'required' ?>>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                        <?= $isEditing ? ($user->isActive() ? 'checked' : '') : 'checked' ?>>
                    <label class="form-check-label" for="is_active">
                        User is active
                    </label>
                </div>
            </div>
            
            <!-- Entity Access Section -->
            <?php if ($specificEntityId): ?>
            <hr>
            <h5>Entity Access for <?= sanitize($entity->getName()) ?></h5>
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="entity_can_view" name="entity_can_view" checked>
                        <label class="form-check-label" for="entity_can_view">
                            <strong>Can View</strong> - User can view invoices for this entity
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="entity_can_download" name="entity_can_download">
                        <label class="form-check-label" for="entity_can_download">
                            <strong>Can Download</strong> - User can download and archive invoices
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="entity_can_export" name="entity_can_export">
                        <label class="form-check-label" for="entity_can_export">
                            <strong>Can Export</strong> - User can export invoices to Symfonia FK
                        </label>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Entity Access Management for Existing Users -->
            <?php if ($isEditing && !empty($allEntities)): ?>
            <hr>
            <h5>Entity Access Management</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Entity Name</th>
                            <th>Tax ID</th>
                            <th>Access Rights</th>
                            <th width="80px">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allEntities as $entityItem): 
                            // Check if user has access to this entity
                            $hasAccess = false;
                            $entityAccess = null;
                            
                            foreach ($userEntityAccess as $access) {
                                if ($access['id'] == $entityItem['id']) {
                                    $hasAccess = true;
                                    $entityAccess = $access;
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td><?= sanitize($entityItem['name']) ?></td>
                            <td><?= sanitize(formatNip($entityItem['tax_id'])) ?></td>
                            <td>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" 
                                        id="entity_<?= $entityItem['id'] ?>_view" 
                                        name="entity_access[<?= $entityItem['id'] ?>][view]" 
                                        <?= ($hasAccess && $entityAccess['can_view']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="entity_<?= $entityItem['id'] ?>_view">View</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" 
                                        id="entity_<?= $entityItem['id'] ?>_download" 
                                        name="entity_access[<?= $entityItem['id'] ?>][download]" 
                                        <?= ($hasAccess && $entityAccess['can_download']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="entity_<?= $entityItem['id'] ?>_download">Download</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" 
                                        id="entity_<?= $entityItem['id'] ?>_export" 
                                        name="entity_access[<?= $entityItem['id'] ?>][export]" 
                                        <?= ($hasAccess && $entityAccess['can_export']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="entity_<?= $entityItem['id'] ?>_export">Export</label>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($hasAccess): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                        id="entity_<?= $entityItem['id'] ?>_remove" 
                                        name="entity_access[<?= $entityItem['id'] ?>][remove]">
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <div class="d-flex justify-content-between">
                <a href="<?= $specificEntityId ? 'entity_form.php?id=' . $specificEntityId : 'users.php' ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <?= $isEditing ? 'Update User' : 'Create User' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

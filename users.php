<?php
/**
 * Users management page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication and admin role
requireRole(ROLE_ADMIN);

// Set page title
$pageTitle = 'Users Management';

// Initialize classes
$user = new User();

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = ITEMS_PER_PAGE;

// Get users with pagination
$users = $user->getAllUsers($page, $perPage);
$totalUsers = $user->countUsers();

// Calculate pagination
$totalPages = ceil($totalUsers / $perPage);
$paginationUrl = 'users.php?page={page}';

// Handle user activation/deactivation
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $userId = (int)$_GET['id'];
    $token = $_GET['csrf_token'] ?? '';
    
    if (!checkCsrfToken($token)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        $targetUser = new User();
        
        if ($targetUser->loadById($userId)) {
            // Don't allow deactivating your own account
            if ($userId == $_SESSION['user_id'] && $action === 'deactivate') {
                setFlashMessage('error', 'You cannot deactivate your own account');
            } else {
                $userData = [
                    'is_active' => ($action === 'activate')
                ];
                
                if ($targetUser->update($userData)) {
                    $actionText = $action === 'activate' ? 'activated' : 'deactivated';
                    setFlashMessage('success', 'User ' . $targetUser->getUsername() . ' has been ' . $actionText);
                    logUserActivity($action . '_user', null, 'User ' . $targetUser->getUsername() . ' ' . $actionText);
                } else {
                    setFlashMessage('error', 'Failed to update user status');
                }
            }
        } else {
            setFlashMessage('error', 'User not found');
        }
    }
    
    // Redirect to remove action from URL
    redirect('users.php?page=' . $page);
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Users Management</h1>
    
    <a href="user_form.php" class="btn btn-primary">
        <i class="fas fa-user-plus me-1"></i> Add New User
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">
                            <span class="text-muted">No users found</span>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $userItem): ?>
                        <tr>
                            <td class="fw-medium"><?= sanitize($userItem['username']) ?></td>
                            <td><?= sanitize($userItem['full_name']) ?></td>
                            <td><?= sanitize($userItem['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $userItem['role'] === ROLE_ADMIN ? 'danger' : ($userItem['role'] === ROLE_MANAGER ? 'primary' : 'secondary') ?>">
                                    <?= ucfirst(sanitize($userItem['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($userItem['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($userItem['created_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="user_form.php?id=<?= $userItem['id'] ?>" class="btn btn-outline-primary" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($userItem['id'] != $_SESSION['user_id']): // Can't deactivate your own account ?>
                                        <?php if ($userItem['is_active']): ?>
                                        <a href="?action=deactivate&id=<?= $userItem['id'] ?>&csrf_token=<?= getCsrfToken() ?>" 
                                           class="btn btn-outline-danger" 
                                           title="Deactivate User"
                                           onclick="return confirm('Are you sure you want to deactivate this user?')">
                                            <i class="fas fa-user-slash"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="?action=activate&id=<?= $userItem['id'] ?>&csrf_token=<?= getCsrfToken() ?>" 
                                           class="btn btn-outline-success" 
                                           title="Activate User"
                                           onclick="return confirm('Are you sure you want to activate this user?')">
                                            <i class="fas fa-user-check"></i>
                                        </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

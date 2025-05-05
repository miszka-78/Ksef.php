<?php
/**
 * Invoice templates listing and management page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Entity.php';
require_once __DIR__ . '/classes/Template.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Require authentication
requireAuth();

// Set page title
$pageTitle = 'Invoice Templates';

// Initialize classes
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();
$template = new Template();

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

// Handle template actions (set as default, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $templateId = (int)$_GET['id'];
    $token = $_GET['csrf_token'] ?? '';
    
    if (!checkCsrfToken($token)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        if ($template->loadById($templateId)) {
            // Check if template belongs to current entity or is global
            if ($template->getEntityId() == $entityId || $template->getEntityId() === null || $user->isAdmin()) {
                switch ($action) {
                    case 'default':
                        // Set as default
                        if ($template->update(['isDefault' => true])) {
                            setFlashMessage('success', 'Template set as default successfully');
                            logUserActivity('set_default_template', $entityId, 'Set template as default: ' . $template->getName());
                        } else {
                            setFlashMessage('error', 'Failed to set template as default');
                        }
                        break;
                        
                    case 'delete':
                        // Only admins can delete templates
                        if (!$user->isAdmin() && !$user->isManager()) {
                            setFlashMessage('error', 'You do not have permission to delete templates');
                            break;
                        }
                        
                        // Can't delete the last template
                        $totalTemplates = $template->countTemplates($entityId);
                        if ($totalTemplates <= 1) {
                            setFlashMessage('error', 'Cannot delete the last template');
                            break;
                        }
                        
                        // Delete template
                        if ($template->delete()) {
                            setFlashMessage('success', 'Template deleted successfully');
                            logUserActivity('delete_template', $entityId, 'Deleted template: ' . $template->getName());
                        } else {
                            setFlashMessage('error', 'Failed to delete template');
                        }
                        break;
                }
            } else {
                setFlashMessage('error', 'You do not have permission to modify this template');
            }
        } else {
            setFlashMessage('error', 'Template not found');
        }
    }
    
    // Redirect to remove action from URL
    redirect('templates.php?entity_id=' . $entityId);
}

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = ITEMS_PER_PAGE;

// Get templates for entity (including global templates)
$templates = $template->getAllTemplates($entityId, $page, $perPage);
$totalTemplates = $template->countTemplates($entityId);

// Calculate pagination
$totalPages = ceil($totalTemplates / $perPage);
$paginationUrl = 'templates.php?entity_id=' . $entityId . '&page={page}';

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoice Templates - <?= sanitize($entity->getName()) ?></h1>
    
    <div>
        <?php if ($user->isAdmin() || $user->isManager()): ?>
        <a href="template_form.php?entity_id=<?= $entityId ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add New Template
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($templates)): ?>
        <div class="p-4 text-center">
            <p class="text-muted mb-0">No templates found. Add a new template to get started.</p>
            <?php if ($user->isAdmin() || $user->isManager()): ?>
            <div class="mt-3">
                <a href="template_form.php?entity_id=<?= $entityId ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add New Template
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Scope</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $templateItem): ?>
                    <tr>
                        <td class="fw-medium"><?= sanitize($templateItem['name']) ?></td>
                        <td>
                            <?php if (!empty($templateItem['description'])): ?>
                            <?= sanitize(truncateText($templateItem['description'], 100)) ?>
                            <?php else: ?>
                            <span class="text-muted">No description</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($templateItem['entity_id'] === null): ?>
                            <span class="badge bg-info">Global</span>
                            <?php else: ?>
                            <span class="badge bg-primary">Entity Specific</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($templateItem['is_default']): ?>
                            <span class="badge bg-success">Default</span>
                            <?php else: ?>
                            <span class="badge bg-light text-dark">Alternative</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($user->isAdmin() || $user->isManager() || $templateItem['entity_id'] == $entityId): ?>
                                <a href="template_form.php?id=<?= $templateItem['id'] ?>&entity_id=<?= $entityId ?>" class="btn btn-outline-primary" title="Edit Template">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!$templateItem['is_default']): ?>
                                <a href="?action=default&id=<?= $templateItem['id'] ?>&entity_id=<?= $entityId ?>&csrf_token=<?= getCsrfToken() ?>" 
                                   class="btn btn-outline-success" 
                                   title="Set as Default"
                                   onclick="return confirm('Set this template as default?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (($user->isAdmin() || $user->isManager()) && !$templateItem['is_default']): ?>
                                <a href="?action=delete&id=<?= $templateItem['id'] ?>&entity_id=<?= $entityId ?>&csrf_token=<?= getCsrfToken() ?>" 
                                   class="btn btn-outline-danger" 
                                   title="Delete Template"
                                   onclick="return confirm('Are you sure you want to delete this template? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                
                                <!-- View Sample button -->
                                <a href="javascript:void(0);" 
                                   class="btn btn-outline-info" 
                                   title="Preview Template"
                                   data-bs-toggle="modal" 
                                   data-bs-target="#templatePreviewModal" 
                                   data-template-id="<?= $templateItem['id'] ?>">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <?= generatePagination($page, $totalPages, $paginationUrl) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Template Preview Modal -->
<div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-labelledby="templatePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templatePreviewModalLabel">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="templatePreviewFrame" style="width: 100%; height: 600px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Template preview modal handling
    const templatePreviewModal = document.getElementById('templatePreviewModal');
    if (templatePreviewModal) {
        templatePreviewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const templateId = button.getAttribute('data-template-id');
            const previewFrame = document.getElementById('templatePreviewFrame');
            
            // Load sample invoice with the selected template
            previewFrame.src = `api/get_invoice_preview.php?sample=1&template_id=${templateId}`;
        });
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

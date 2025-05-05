<?php
/**
 * Invoice template creation and editing form
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

// Require authentication and manager/admin role
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

// Set page title
$pageTitle = 'Template Form';

// Initialize classes
$template = new Template();
$user = new User();
$user->loadById($_SESSION['user_id']);
$entity = new Entity();

// Handle entity selection
$entityId = null;
if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
    $entityId = (int)$_GET['entity_id'];
    
    // Check if user has access to this entity
    if (!userHasEntityAccess($entityId)) {
        setFlashMessage('error', 'You do not have access to this entity');
        redirect('entities.php');
    }
    
    // Load entity
    if (!$entity->loadById($entityId)) {
        setFlashMessage('error', 'Entity not found');
        redirect('entities.php');
    }
}

// Check if editing existing template or creating new one
$isEditing = false;
$templateId = null;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $templateId = (int)$_GET['id'];
    $isEditing = $template->loadById($templateId);
    
    if (!$isEditing) {
        setFlashMessage('error', 'Template not found');
        redirect('templates.php' . ($entityId ? '?entity_id=' . $entityId : ''));
    }
    
    // Check if user has permission to edit this template
    if (!$user->isAdmin() && !$user->isManager() && $template->getEntityId() != $entityId) {
        setFlashMessage('error', 'You do not have permission to edit this template');
        redirect('templates.php?entity_id=' . $entityId);
    }
    
    $pageTitle = 'Edit Template';
} else {
    $pageTitle = 'Add New Template';
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
        $templateData = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'htmlContent' => $_POST['html_content'] ?? '',
            'cssContent' => $_POST['css_content'] ?? '',
            'isDefault' => isset($_POST['is_default']) ? true : false,
            'entityId' => isset($_POST['entity_specific']) && $_POST['entity_specific'] ? $entityId : null
        ];
        
        // Validate form data
        if (empty($templateData['name'])) {
            $errors[] = 'Template name is required';
        }
        
        if (empty($templateData['htmlContent'])) {
            $errors[] = 'HTML content is required';
        }
        
        // If no errors, save the template
        if (empty($errors)) {
            if ($isEditing) {
                // Update existing template
                if ($template->update($templateData)) {
                    setFlashMessage('success', 'Template updated successfully');
                    logUserActivity('update_template', $entityId, 'Updated template: ' . $template->getName());
                    redirect('templates.php?entity_id=' . $entityId);
                } else {
                    $errors[] = 'Failed to update template';
                }
            } else {
                // Create new template
                $newTemplateId = $template->create($templateData);
                
                if ($newTemplateId) {
                    setFlashMessage('success', 'Template created successfully');
                    logUserActivity('create_template', $entityId, 'Created new template: ' . $templateData['name']);
                    redirect('templates.php?entity_id=' . $entityId);
                } else {
                    $errors[] = 'Failed to create template';
                }
            }
        }
    }
}

// Default template content if creating new
$defaultHtmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice {{invoiceNumber}}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .invoice-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .invoice-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .company-details, .invoice-details { margin-bottom: 20px; }
        .seller-buyer { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .seller, .buyer { width: 48%; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .label { font-weight: bold; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .totals { margin-left: auto; width: 300px; }
        .totals table { margin-bottom: 0; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="invoice-header">
        <div>
            <div class="invoice-title">INVOICE</div>
            <div>Invoice Number: {{invoiceNumber}}</div>
            <div>Issue Date: {{issueDate}}</div>
        </div>
        <div>
            <div class="label">KSeF Reference:</div>
            <div>{{ksefReferenceNumber}}</div>
        </div>
    </div>
    
    <div class="seller-buyer">
        <div class="seller">
            <div class="label">Seller:</div>
            <div>{{sellerName}}</div>
            <div>NIP: {{sellerTaxId}}</div>
        </div>
        <div class="buyer">
            <div class="label">Buyer:</div>
            <div>{{buyerName}}</div>
            <div>NIP: {{buyerTaxId}}</div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Unit Price</th>
                <th>Net Value</th>
                <th>VAT Rate</th>
                <th>VAT Amount</th>
                <th>Gross Value</th>
            </tr>
        </thead>
        <tbody>
            <tr id="itemRow">
                <td>{{item.index}}</td>
                <td>{{item.name}}</td>
                <td>{{item.quantity}}</td>
                <td>{{item.unit}}</td>
                <td>{{item.unitPriceNet}}</td>
                <td>{{item.netValue}}</td>
                <td>{{item.vatRate}}</td>
                <td>{{item.vatValue}}</td>
                <td>{{item.grossValue}}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right;">Total:</th>
                <th>{{totalNet}}</th>
                <th></th>
                <th>{{totalVat}}</th>
                <th>{{totalGross}}</th>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        <p>This is an electronically generated invoice from KSeF system.</p>
    </div>
</body>
</html>';

$defaultCssContent = '/* Additional CSS can be added here */';

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $isEditing ? 'Edit Template' : 'Add New Template' ?></h1>
    
    <a href="templates.php<?= $entityId ? '?entity_id=' . $entityId : '' ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Templates
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

<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="" id="templateForm">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Template Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" 
                        value="<?= sanitize($isEditing ? $template->getName() : ($_POST['name'] ?? '')) ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" 
                        value="<?= sanitize($isEditing ? $template->getDescription() : ($_POST['description'] ?? '')) ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
                            <?= $isEditing ? ($template->isDefault() ? 'checked' : '') : '' ?>>
                        <label class="form-check-label" for="is_default">
                            Set as default template
                        </label>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <?php if ($entityId): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="entity_specific" name="entity_specific" 
                            <?= $isEditing ? ($template->getEntityId() !== null ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label" for="entity_specific">
                            Make template specific to <?= sanitize($entity->getName()) ?>
                        </label>
                        <div class="form-text">If unchecked, the template will be available to all entities</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="html_content" class="form-label">HTML Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="html_content" name="html_content" rows="20" required><?= sanitize($isEditing ? $template->getHtmlContent() : ($_POST['html_content'] ?? $defaultHtmlContent)) ?></textarea>
                        <div class="form-text">
                            Use placeholders like {{invoiceNumber}}, {{sellerName}}, etc. for invoice data.
                            For invoice items, use an HTML element with id="itemRow" containing {{item.xxx}} placeholders.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="css_content" class="form-label">CSS Content</label>
                        <textarea class="form-control" id="css_content" name="css_content" rows="10"><?= sanitize($isEditing ? $template->getCssContent() : ($_POST['css_content'] ?? $defaultCssContent)) ?></textarea>
                        <div class="form-text">
                            Additional CSS to style the invoice. You can also include styles directly in the HTML content.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Available Placeholders</h5>
                        </div>
                        <div class="card-body">
                            <h6>Basic Information</h6>
                            <ul>
                                <li><code>{{invoiceNumber}}</code> - Invoice number</li>
                                <li><code>{{issueDate}}</code> - Issue date</li>
                                <li><code>{{ksefReferenceNumber}}</code> - KSeF reference</li>
                                <li><code>{{currency}}</code> - Currency (PLN, EUR, etc.)</li>
                            </ul>
                            
                            <h6>Seller Information</h6>
                            <ul>
                                <li><code>{{sellerName}}</code> - Seller name</li>
                                <li><code>{{sellerTaxId}}</code> - Seller NIP</li>
                            </ul>
                            
                            <h6>Buyer Information</h6>
                            <ul>
                                <li><code>{{buyerName}}</code> - Buyer name</li>
                                <li><code>{{buyerTaxId}}</code> - Buyer NIP</li>
                            </ul>
                            
                            <h6>Totals</h6>
                            <ul>
                                <li><code>{{totalNet}}</code> - Total net amount</li>
                                <li><code>{{totalGross}}</code> - Total gross amount</li>
                                <li><code>{{totalVat}}</code> - Total VAT amount</li>
                            </ul>
                            
                            <h6>Item Information (inside id="itemRow")</h6>
                            <ul>
                                <li><code>{{item.index}}</code> - Item index</li>
                                <li><code>{{item.name}}</code> - Item name</li>
                                <li><code>{{item.quantity}}</code> - Quantity</li>
                                <li><code>{{item.unit}}</code> - Unit</li>
                                <li><code>{{item.unitPriceNet}}</code> - Unit price (net)</li>
                                <li><code>{{item.netValue}}</code> - Net value</li>
                                <li><code>{{item.vatRate}}</code> - VAT rate</li>
                                <li><code>{{item.vatValue}}</code> - VAT amount</li>
                                <li><code>{{item.grossValue}}</code> - Gross value</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-primary" id="previewButton">
                    <i class="fas fa-eye me-1"></i> Preview Template
                </button>
                
                <div>
                    <a href="templates.php<?= $entityId ? '?entity_id=' . $entityId : '' ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $isEditing ? 'Update Template' : 'Create Template' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
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
    const previewButton = document.getElementById('previewButton');
    const templateForm = document.getElementById('templateForm');
    
    previewButton.addEventListener('click', function() {
        // Get form data
        const htmlContent = document.getElementById('html_content').value;
        const cssContent = document.getElementById('css_content').value;
        
        // Create form to post data
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/get_invoice_preview.php?sample=1';
        form.target = 'templatePreviewFrame';
        form.style.display = 'none';
        
        // Add HTML content
        const htmlInput = document.createElement('input');
        htmlInput.name = 'html_content';
        htmlInput.value = htmlContent;
        form.appendChild(htmlInput);
        
        // Add CSS content
        const cssInput = document.createElement('input');
        cssInput.name = 'css_content';
        cssInput.value = cssContent;
        form.appendChild(cssInput);
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?= getCsrfToken() ?>';
        form.appendChild(csrfInput);
        
        // Add to document, submit and show modal
        document.body.appendChild(form);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('templatePreviewModal'));
        modal.show();
        
        // Submit the form after modal is shown
        form.submit();
        
        // Remove form
        document.body.removeChild(form);
    });
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>

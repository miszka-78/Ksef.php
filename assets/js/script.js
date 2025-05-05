/**
 * Main JavaScript for KSeF Invoice Manager
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Create bootstrap alert object
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Confirm deletion before form submission
    const deleteForms = document.querySelectorAll('.delete-form');
    deleteForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Entity selector change handler
    const entitySelector = document.getElementById('entity_selector');
    if (entitySelector) {
        entitySelector.addEventListener('change', function() {
            // Submit the parent form
            this.closest('form').submit();
        });
    }
    
    // Table row highlighting on checkbox selection
    const tableCheckboxes = document.querySelectorAll('table input[type="checkbox"]');
    tableCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('table-active');
            } else {
                row.classList.remove('table-active');
            }
        });
    });
    
    // Bulk actions form submission
    const bulkActionForm = document.getElementById('bulk-action-form');
    if (bulkActionForm) {
        const bulkActionSelect = document.getElementById('bulk_action');
        const applyButton = document.getElementById('apply_bulk_action');
        
        // Enable/disable apply button based on selection
        bulkActionSelect.addEventListener('change', function() {
            applyButton.disabled = (this.value === '');
        });
        
        // Confirm before applying bulk action
        bulkActionForm.addEventListener('submit', function(e) {
            const selectedItemsCount = document.querySelectorAll('input[name="selected_items[]"]:checked').length;
            const action = bulkActionSelect.value;
            
            if (selectedItemsCount === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${selectedItemsCount} selected item(s)? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            } else if (action === 'archive') {
                if (!confirm(`Are you sure you want to archive ${selectedItemsCount} selected item(s)?`)) {
                    e.preventDefault();
                }
            } else if (action === 'export') {
                if (!confirm(`Are you sure you want to export ${selectedItemsCount} selected item(s)?`)) {
                    e.preventDefault();
                }
            }
        });
    }
    
    // Date range filter synchronization
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    
    if (dateFromInput && dateToInput) {
        dateFromInput.addEventListener('change', function() {
            // If date_to is before date_from, update it
            if (dateToInput.value && dateToInput.value < dateFromInput.value) {
                dateToInput.value = dateFromInput.value;
            }
        });
        
        dateToInput.addEventListener('change', function() {
            // If date_from is after date_to, update it
            if (dateFromInput.value && dateFromInput.value > dateToInput.value) {
                dateFromInput.value = dateToInput.value;
            }
        });
    }
    
    // Handle Select All functionality for checkboxes
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        const checkboxes = document.querySelectorAll('input[type="checkbox"].row-checkbox');
        
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
                
                // Also trigger change event to update row highlighting
                const event = new Event('change');
                checkbox.dispatchEvent(event);
            });
        });
        
        // Update "Select All" state based on individual checkboxes
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(function(cb) {
                    return cb.checked;
                });
                
                const someChecked = Array.from(checkboxes).some(function(cb) {
                    return cb.checked;
                });
                
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            });
        });
    }
});

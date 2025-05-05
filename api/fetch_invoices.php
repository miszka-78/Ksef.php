<?php
/**
 * API endpoint to fetch invoices from KSeF
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Include classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Entity.php';
require_once __DIR__ . '/../classes/Invoice.php';
require_once __DIR__ . '/../classes/KsefApi.php';

// Include auth functions
require_once __DIR__ . '/../includes/auth.php';

// Require authentication
requireAuth();

// Check if we should return JSON
$returnJson = isset($_GET['format']) && $_GET['format'] === 'json';

// Set appropriate content type if returning JSON
if ($returnJson) {
    header('Content-Type: application/json');
}

// Function to return error as JSON or redirect with flash message
function handleError($message, $redirectUrl = null) {
    global $returnJson;
    
    if ($returnJson) {
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    } else {
        setFlashMessage('error', $message);
        if ($redirectUrl) {
            redirect($redirectUrl);
        } else {
            redirect('/dashboard.php');
        }
    }
}

// Check if entity ID is provided
if (!isset($_GET['entity_id']) || empty($_GET['entity_id'])) {
    handleError('Entity ID is required');
}

// Initialize classes
$entityId = (int)$_GET['entity_id'];
$entity = new Entity();
$invoice = new Invoice();

// Load entity and check user access
if (!$entity->loadById($entityId) || !userHasEntityAccess($entityId)) {
    handleError('You do not have access to this entity', '/entities.php');
}

// Initialize KSeF API
$ksefApi = new KsefApi($entity);

// Check if entity has valid KSeF token
if (!$entity->hasValidKsefToken()) {
    // Attempt to authenticate with token if provided in query params
    if (isset($_GET['ksef_password']) && !empty($_GET['ksef_password'])) {
        $authResult = $ksefApi->authenticate('password', [
            'nip' => $entity->getTaxId(),
            'password' => $_GET['ksef_password']
        ]);
        
        if (!$authResult['success']) {
            handleError('Failed to authenticate with KSeF: ' . ($authResult['error'] ?? 'Unknown error'), '/entity_form.php?id=' . $entityId);
        }
    } else {
        // Redirect to entity form to add authentication
        handleError('KSeF token is expired or invalid. Please authenticate with KSeF.', '/entity_form.php?id=' . $entityId);
    }
}

// Get query parameters for fetching invoices
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$pageSize = isset($_GET['page_size']) ? min(100, (int)$_GET['page_size']) : 100;
$pageNumber = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Get invoices from KSeF
$fetchParams = [
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
    'pageSize' => $pageSize,
    'pageNumber' => $pageNumber
];

$fetchResult = $ksefApi->getInvoices($fetchParams);

if (!$fetchResult['success']) {
    handleError('Failed to fetch invoices from KSeF: ' . ($fetchResult['error'] ?? 'Unknown error'));
}

// Process fetched invoices
$invoices = $fetchResult['invoices'] ?? [];
$processedCount = 0;
$newCount = 0;
$errorCount = 0;
$errors = [];

foreach ($invoices as $ksefInvoice) {
    // Check if invoice already exists in database
    $existingInvoice = new Invoice();
    if ($existingInvoice->loadByKsefReference($ksefInvoice['ksefReferenceNumber'])) {
        $processedCount++;
        continue; // Skip if already exists
    }
    
    // Get invoice XML
    $xmlResult = $ksefApi->getInvoiceXml($ksefInvoice['ksefReferenceNumber']);
    
    if (!$xmlResult['success']) {
        $errorCount++;
        $errors[] = 'Failed to get XML for invoice ' . $ksefInvoice['ksefReferenceNumber'] . ': ' . ($xmlResult['error'] ?? 'Unknown error');
        continue;
    }
    
    // Parse invoice XML
    $xmlContent = $xmlResult['xml'];
    $parseResult = $ksefApi->parseInvoiceXml($xmlContent);
    
    if (!$parseResult['success']) {
        $errorCount++;
        $errors[] = 'Failed to parse XML for invoice ' . $ksefInvoice['ksefReferenceNumber'] . ': ' . ($parseResult['error'] ?? 'Unknown error');
        continue;
    }
    
    // Create invoice in database
    $invoiceData = $parseResult['invoice'];
    
    // Ensure entity ID is set correctly
    $invoiceData['entityId'] = $entityId;
    
    // Create invoice
    $newInvoiceId = $invoice->createFromKsef($invoiceData, $xmlContent);
    
    if ($newInvoiceId) {
        $newCount++;
    } else {
        $errorCount++;
        $errors[] = 'Failed to create invoice ' . $ksefInvoice['ksefReferenceNumber'] . ' in database';
    }
    
    $processedCount++;
}

// Log activity
logUserActivity('sync_invoices', $entityId, "Synced $processedCount invoices, $newCount new, $errorCount errors");

// Prepare result
$result = [
    'success' => true,
    'total_processed' => $processedCount,
    'new_invoices' => $newCount,
    'error_count' => $errorCount,
    'errors' => $errors,
    'has_more' => $fetchResult['hasMorePages'] ?? false,
    'total_available' => $fetchResult['totalCount'] ?? count($invoices)
];

// Return result as JSON or redirect with flash message
if ($returnJson) {
    echo json_encode($result);
} else {
    if ($errorCount > 0) {
        setFlashMessage('warning', "Invoices synchronized with some errors. Added $newCount new invoices, encountered $errorCount errors.");
    } else {
        setFlashMessage('success', "Invoices synchronized successfully. Added $newCount new invoices.");
    }
    
    // Redirect back to invoices page
    redirect('/invoices.php?entity_id=' . $entityId);
}

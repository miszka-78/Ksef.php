<?php
/**
 * Login page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Set page title
$pageTitle = 'Login';

// If already logged in, redirect to dashboard
if (isAuthenticated()) {
    redirect('dashboard.php');
}

// Process login form
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no errors, try to authenticate
    if (empty($errors)) {
        $authResult = authenticateUser($username, $password);
        
        if ($authResult) {
            // Redirect to requested page or dashboard
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            redirect($redirect);
        } else {
            $errors[] = 'Invalid username or password';
            logError("Failed login attempt for username: $username", 'WARNING');
        }
    }
}

// Include header (without navigation)
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h2 class="mb-0"><?= APP_NAME ?></h2>
                    </div>
                    <div class="card-body p-4">
                        <h3 class="mb-3 text-center">Login</h3>
                        
                        <?php
                        // Display flash messages
                        $flashMessage = getFlashMessage();
                        if ($flashMessage): 
                        ?>
                        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
                            <?= sanitize($flashMessage['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                <li><?= sanitize($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?= $_SERVER['PHP_SELF'] . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '') ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= sanitize($_POST['username'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3 bg-light">
                        <p class="text-muted mb-0">KSeF Invoice Manager v<?= APP_VERSION ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

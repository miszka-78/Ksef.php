<ul class="navbar-nav me-auto">
    <li class="nav-item">
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'dashboard.php') ? 'active' : '' ?>" href="/dashboard.php">
            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'entities.php') ? 'active' : '' ?>" href="/entities.php">
            <i class="fas fa-building me-1"></i> Entities
        </a>
    </li>
    <?php if (getSelectedEntity()): ?>
    <li class="nav-item">
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'invoices.php') ? 'active' : '' ?>" href="/invoices.php?entity_id=<?= getSelectedEntity() ?>">
            <i class="fas fa-file-invoice me-1"></i> Invoices
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'templates.php') ? 'active' : '' ?>" href="/templates.php?entity_id=<?= getSelectedEntity() ?>">
            <i class="fas fa-paint-brush me-1"></i> Templates
        </a>
    </li>
    <?php if (userHasEntityAccess(getSelectedEntity(), 'export')): ?>
    <li class="nav-item">
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'invoice_export.php') ? 'active' : '' ?>" href="/invoice_export.php?entity_id=<?= getSelectedEntity() ?>">
            <i class="fas fa-file-export me-1"></i> Export
        </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (userHasRole(ROLE_ADMIN)): ?>
    <li class="nav-item">
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'users.php') ? 'active' : '' ?>" href="/users.php">
            <i class="fas fa-users me-1"></i> Users
        </a>
    </li>
    <?php endif; ?>
</ul>

<ul class="navbar-nav ms-auto">
    <?php if (getSelectedEntity()): ?>
    <li class="nav-item dropdown me-3">
        <a class="nav-link dropdown-toggle" href="#" id="entityDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-building me-1"></i> <?= getSelectedEntityName() ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="entityDropdown">
            <?php
            $userEntities = getUserEntities();
            foreach ($userEntities as $userEntity):
            ?>
            <li>
                <a class="dropdown-item <?= getSelectedEntity() == $userEntity['id'] ? 'active' : '' ?>" 
                   href="/dashboard.php?entity_id=<?= $userEntity['id'] ?>">
                    <?= sanitize($userEntity['name']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </li>
    <?php endif; ?>
    
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle me-1"></i> <?= sanitize($_SESSION['user_name']) ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li>
                <a class="dropdown-item" href="#">
                    <i class="fas fa-user me-1"></i> Profile
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </li>
        </ul>
    </li>
</ul>

<?php
/**
 * Helper function to get selected entity name
 */
function getSelectedEntityName() {
    $entityId = getSelectedEntity();
    if (!$entityId) {
        return 'No Entity Selected';
    }
    
    $entity = new Entity();
    if ($entity->loadById($entityId)) {
        return $entity->getName();
    }
    
    return 'Unknown Entity';
}
?>

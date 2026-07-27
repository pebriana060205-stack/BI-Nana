<?php
// ============================================================
//  Entry Point — Router Aplikasi & Auth Protection
// ============================================================

session_start();

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));

require_once BASE_PATH . '/config/database.php';

// Route handler
$page = $_GET['page'] ?? 'landing';

// Handle Logout Action
if ($page === 'logout') {
    unset($_SESSION['user']);
    session_destroy();
    header('Location: ?page=landing');
    exit;
}

// Allowed Pages Definition
$publicPages = ['landing', 'login', 'register', 'api'];
$protectedPages = [
    'dashboard', 'customers', 'customer-detail',
    'products', 'product-detail',
    'reports', 'mining', 'clustering',
    'datamining', 'import'
];
$allowedPages = array_merge($publicPages, $protectedPages);

if (!in_array($page, $allowedPages)) {
    $page = 'landing';
}

// Redirect unauthenticated users trying to access protected pages to Login
if (in_array($page, $protectedPages) && !isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

// Handle API requests (AJAX)
if ($page === 'api') {
    require_once BASE_PATH . '/app/controllers/ApiController.php';
    $controller = new ApiController();
    $controller->handle($_GET['action'] ?? '');
    exit;
}

// Render Header & Layout
require_once BASE_PATH . '/app/views/layout/header.php';

// Determine View File Location
if ($page === 'landing') {
    $viewFile = BASE_PATH . '/app/views/landing/index.php';
} elseif ($page === 'login') {
    $viewFile = BASE_PATH . '/app/views/auth/login.php';
} elseif ($page === 'register') {
    $viewFile = BASE_PATH . '/app/views/auth/register.php';
} elseif ($page === 'customer-detail' && isset($_GET['id'])) {
    $viewFile = BASE_PATH . '/app/views/customers/detail.php';
} elseif ($page === 'product-detail' && isset($_GET['id'])) {
    $viewFile = BASE_PATH . '/app/views/products/detail.php';
} else {
    $viewFile = BASE_PATH . '/app/views/' . $page . '/index.php';
}

if (file_exists($viewFile)) {
    require_once $viewFile;
} else {
    echo '<div class="empty-state"><h2>Halaman tidak ditemukan</h2></div>';
}

require_once BASE_PATH . '/app/views/layout/footer.php';

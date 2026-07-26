<?php
// ============================================================
//  Entry Point — Router Aplikasi
//  URL: index.php?page=dashboard
//       index.php?page=customers
//       index.php?page=import
//       dll.
// ============================================================

session_start();

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));

require_once BASE_PATH . '/config/database.php';

// Routing sederhana
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard', 'customers', 'customer-detail',
    'products', 'product-detail',
    'reports', 'mining', 'clustering',
    'import', 'api'
];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Handle API requests (AJAX)
if ($page === 'api') {
    require_once BASE_PATH . '/app/controllers/ApiController.php';
    $controller = new ApiController();
    $controller->handle($_GET['action'] ?? '');
    exit;
}

// Render halaman
require_once BASE_PATH . '/app/views/layout/header.php';

$viewFile = BASE_PATH . '/app/views/' . $page . '/index.php';

// Handle halaman detail
if ($page === 'customer-detail' && isset($_GET['id'])) {
    $viewFile = BASE_PATH . '/app/views/customers/detail.php';
}
if ($page === 'product-detail' && isset($_GET['id'])) {
    $viewFile = BASE_PATH . '/app/views/products/detail.php';
}

if (file_exists($viewFile)) {
    require_once $viewFile;
} else {
    echo '<div class="empty-state"><h2>Halaman tidak ditemukan</h2></div>';
}

require_once BASE_PATH . '/app/views/layout/footer.php';

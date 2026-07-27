<?php
// ============================================================
//  Layout: Header (Sidebar + Top Bar + Auth Status)
// ============================================================

$currentPage = $_GET['page'] ?? 'landing';
$isLoggedIn  = isset($_SESSION['user']);
$currentUser = $_SESSION['user'] ?? null;

$navItems = [
    ['id' => 'landing',     'icon' => '🏠',  'label' => 'Landing Page',       'section' => 'public'],
    ['id' => 'dashboard',   'icon' => '📊',  'label' => 'Dashboard Utama',   'section' => 'main'],
    ['id' => 'import',      'icon' => '📥',  'label' => 'Import Data CSV',   'section' => 'main'],
    ['id' => 'customers',   'icon' => '👥',  'label' => 'Pelanggan & RFM',   'section' => 'analitik'],
    ['id' => 'products',    'icon' => '📦',  'label' => 'Produk & ABC',      'section' => 'analitik'],
    ['id' => 'clustering',  'icon' => '🔮',  'label' => 'Clustering',        'section' => 'analitik'],
    ['id' => 'datamining',  'icon' => '🧠',  'label' => 'Data Mining',       'section' => 'analitik'],
    ['id' => 'mining',      'icon' => '⛏️',  'label' => 'Market Basket',     'section' => 'analitik'],
    ['id' => 'reports',     'icon' => '📋',  'label' => 'Laporan & Export',  'section' => 'laporan'],
];

$pageTitles = [
    'landing'    => ['title' => 'Landing Page',            'breadcrumb' => 'Business Intelligence Overview'],
    'login'      => ['title' => 'Login System',            'breadcrumb' => 'Authentication'],
    'register'   => ['title' => 'Registrasi Akun',         'breadcrumb' => 'Authentication'],
    'dashboard'  => ['title' => 'Dashboard Utama',         'breadcrumb' => 'Overview & KPI'],
    'import'     => ['title' => 'Import Data CSV',         'breadcrumb' => 'Integration Services → Upload CSV'],
    'customers'  => ['title' => 'Analisis Pelanggan',      'breadcrumb' => 'Analysis Services → RFM Segmentation'],
    'products'   => ['title' => 'Analisis & CRUD Produk',  'breadcrumb' => 'Data Mining & Master Data Produk'],
    'clustering' => ['title' => 'Customer Clustering',     'breadcrumb' => 'Clustering Support → K-Means (k=4)'],
    'datamining' => ['title' => 'Data Mining Center',      'breadcrumb' => 'RFM · Pareto ABC · K-Means · Association Rules'],
    'mining'     => ['title' => 'Market Basket Analysis',  'breadcrumb' => 'Data Mining → Association Rules'],
    'reports'    => ['title' => 'Laporan & Export',        'breadcrumb' => 'Reporting Services'],
];

$currentTitle = $pageTitles[$currentPage] ?? ['title' => 'BI Dashboard', 'breadcrumb' => ''];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Online Retail Business Intelligence Dashboard — Analisis penjualan, pelanggan, dan produk berbasis PHP & MySQL">
    <title><?= htmlspecialchars($currentTitle['title']) ?> — Online Retail BI</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>

<!-- ========= SIDEBAR ========= -->
<aside class="sidebar">
    <a href="?page=landing" class="sidebar-logo">
        <div class="logo-icon">📊</div>
        <div class="logo-text">
            <span class="logo-name">Online Retail BI</span>
            <span class="logo-sub">Retail Analytics Platform</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <?php
        $lastSection = '';
        foreach ($navItems as $item):
            // Hide protected nav items if not logged in
            if (!$isLoggedIn && $item['id'] !== 'landing') continue;

            if ($item['section'] !== $lastSection):
                $sectionLabels = ['public' => 'Overview', 'main' => 'Menu Utama', 'analitik' => 'BI Features', 'laporan' => 'Reporting'];
                echo '<div class="nav-section-label">' . ($sectionLabels[$item['section']] ?? $item['section']) . '</div>';
                $lastSection = $item['section'];
            endif;
            $isActive = ($currentPage === $item['id']) ? 'active' : '';
        ?>
        <a href="?page=<?= $item['id'] ?>" 
           class="nav-item <?= $isActive ?>"
           id="nav-<?= $item['id'] ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <?= htmlspecialchars($item['label']) ?>
        </a>
        <?php endforeach; ?>

        <?php if (!$isLoggedIn): ?>
        <div class="nav-section-label">Autentikasi</div>
        <a href="?page=login" class="nav-item <?= $currentPage==='login'?'active':'' ?>">
            <span class="nav-icon">🔑</span> Login System
        </a>
        <a href="?page=register" class="nav-item <?= $currentPage==='register'?'active':'' ?>">
            <span class="nav-icon">✍️</span> Registrasi Akun
        </a>
        <?php endif; ?>
    </nav>

    <!-- DB Status di bawah sidebar -->
    <div style="padding: 16px; border-top: 1px solid var(--border-color);">
        <?php
        try {
            getDB();
            echo '<div class="badge-status" style="width:100%;justify-content:center;">MySQL Connected</div>';
        } catch (Exception $e) {
            echo '<div class="badge-status" style="background:rgba(244,63,94,.15);color:#f43f5e;border-color:rgba(244,63,94,.3);width:100%;justify-content:center;">DB Error</div>';
        }
        ?>
    </div>
</aside>

<!-- ========= MAIN WRAPPER ========= -->
<div class="main-wrapper">

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <div class="page-title"><?= htmlspecialchars($currentTitle['title']) ?></div>
            <div class="page-breadcrumb"><?= htmlspecialchars($currentTitle['breadcrumb']) ?></div>
        </div>
        <div class="top-bar-right" style="display:flex; align-items:center; gap:14px;">
            <?php if ($isLoggedIn): ?>
                <div style="display:flex; align-items:center; gap:10px; background: rgba(255,255,255,0.03); padding: 4px 12px; border-radius: 20px; border: 1px solid var(--border-color);">
                    <span style="font-size: 1.1rem;">👤</span>
                    <div style="text-align: left;">
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($currentUser['name']) ?></div>
                        <div style="font-size: 0.7rem; color: var(--accent-teal);"><?= htmlspecialchars($currentUser['email']) ?></div>
                    </div>
                </div>
                <a href="?page=logout" class="btn btn-sm btn-secondary" style="color: var(--accent-rose); border-color: rgba(244,63,94,0.3);">
                    🚪 Logout
                </a>
            <?php else: ?>
                <a href="?page=login" class="btn btn-sm btn-primary">
                    🔑 Login
                </a>
                <a href="?page=register" class="btn btn-sm btn-secondary">
                    ✍️ Register
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="content">

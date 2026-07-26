<?php
// ============================================================
//  Layout: Header (Sidebar + Top Bar)
// ============================================================

$currentPage = $_GET['page'] ?? 'dashboard';

$navItems = [
    ['id' => 'dashboard',  'icon' => '📊', 'label' => 'Dashboard',        'section' => 'main'],
    ['id' => 'import',     'icon' => '📥', 'label' => 'Import Data CSV',   'section' => 'main'],
    ['id' => 'customers',  'icon' => '👥', 'label' => 'Pelanggan & RFM',   'section' => 'analitik'],
    ['id' => 'products',   'icon' => '📦', 'label' => 'Produk & ABC',      'section' => 'analitik'],
    ['id' => 'clustering', 'icon' => '🔮', 'label' => 'Clustering',        'section' => 'analitik'],
    ['id' => 'mining',     'icon' => '⛏️',  'label' => 'Market Basket',    'section' => 'analitik'],
    ['id' => 'reports',    'icon' => '📋', 'label' => 'Laporan & Export',  'section' => 'laporan'],
];

$pageTitles = [
    'dashboard'  => ['title' => 'Dashboard',              'breadcrumb' => 'Overview & KPI'],
    'import'     => ['title' => 'Import Data',            'breadcrumb' => 'Integration Services → Upload CSV'],
    'customers'  => ['title' => 'Analisis Pelanggan',     'breadcrumb' => 'Analysis Services → RFM Segmentation'],
    'products'   => ['title' => 'Analisis Produk',        'breadcrumb' => 'Data Mining → ABC Analysis'],
    'clustering' => ['title' => 'Customer Clustering',    'breadcrumb' => 'Clustering Support → K-Means'],
    'mining'     => ['title' => 'Market Basket Analysis', 'breadcrumb' => 'Data Mining → Association Rules'],
    'reports'    => ['title' => 'Laporan & Export',       'breadcrumb' => 'Reporting Services'],
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
    <link  rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>

<!-- ========= SIDEBAR ========= -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">📊</div>
        <div class="logo-text">
            <span class="logo-name">Retail BI</span>
            <span class="logo-sub">Online Retail Analytics</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php
        $lastSection = '';
        foreach ($navItems as $item):
            if ($item['section'] !== $lastSection):
                $sectionLabels = ['main' => 'Menu Utama', 'analitik' => 'BI Features', 'laporan' => 'Reporting'];
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
        <div class="top-bar-right">
            <span class="badge-status">System Online</span>
            <a href="?page=import" class="btn btn-primary btn-sm">
                📥 Import Data
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content-area">

<?php
// ============================================================
//  Dashboard View — KPI Cards + 5 Charts + Dashboard CRUD Panel
// ============================================================

$db = getDB();
$dashAlert = null;

// ---- POST Handler: CRUD Operations directly from Dashboard ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dash_crud_action'])) {
    $action = $_POST['dash_crud_action'];
    if ($action === 'create') {
        $code = trim($_POST['stock_code'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['unit_price'] ?? 0);
        $tier = $_POST['price_tier'] ?? 'Mid';

        if (empty($code) || empty($desc)) {
            $dashAlert = ['type' => 'error', 'msg' => 'Kode produk & deskripsi wajib diisi.'];
        } else {
            try {
                $db->prepare("INSERT INTO products (stock_code, description, unit_price, price_tier) VALUES (?, ?, ?, ?)")
                   ->execute([$code, $desc, $price, $tier]);
                $dashAlert = ['type' => 'success', 'msg' => "Produk '$code' berhasil ditambahkan dari Dashboard!"];
            } catch (PDOException $e) {
                $dashAlert = ['type' => 'error', 'msg' => 'Gagal menambah produk: ' . $e->getMessage()];
            }
        }
    } elseif ($action === 'delete') {
        $code = trim($_POST['stock_code'] ?? '');
        try {
            $db->prepare("DELETE FROM mining_product_abc WHERE stock_code = ?")->execute([$code]);
            $db->prepare("DELETE FROM products WHERE stock_code = ?")->execute([$code]);
            $dashAlert = ['type' => 'success', 'msg' => "Produk '$code' berhasil dihapus dari Dashboard!"];
        } catch (PDOException $e) {
            $dashAlert = ['type' => 'error', 'msg' => 'Gagal menghapus produk: ' . $e->getMessage()];
        }
    }
}

// ---- KPI Queries ----
$totalRevenue = $db->query("
    SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE is_cancelled = 0
")->fetchColumn();

$totalOrders = $db->query("
    SELECT COUNT(DISTINCT invoice_no) FROM transactions WHERE is_cancelled = 0
")->fetchColumn();

$uniqueCustomers = $db->query("
    SELECT COUNT(DISTINCT customer_id) FROM transactions 
    WHERE is_cancelled = 0 AND customer_id IS NOT NULL AND customer_id > 0
")->fetchColumn();

$uniqueProducts = $db->query("
    SELECT COUNT(DISTINCT stock_code) FROM products WHERE is_active = 1
")->fetchColumn();

$cancelledCount = $db->query("
    SELECT COUNT(DISTINCT invoice_no) FROM transactions WHERE is_cancelled = 1
")->fetchColumn();

// ---- Monthly Revenue (untuk Line Chart) ----
$monthlyData = $db->query("
    SELECT tahun, bulan, nama_bulan, total_revenue, total_order
    FROM vw_monthly_sales
    ORDER BY tahun, bulan
    LIMIT 24
")->fetchAll();

// ---- Top 10 Products ----
$topProducts = $db->query("
    SELECT stock_code, description, total_revenue, total_qty_terjual
    FROM vw_top_products LIMIT 10
")->fetchAll();

// ---- Sales by Country (Top 8) ----
$countryData = $db->query("
    SELECT country_name, total_revenue FROM vw_sales_by_country LIMIT 8
")->fetchAll();

// ---- RFM Segment Distribution ----
$rfmSegments = $db->query("
    SELECT rfm_segment, COUNT(*) AS count FROM analytics_rfm
    GROUP BY rfm_segment ORDER BY count DESC
")->fetchAll();

// ---- ABC Class Distribution ----
$abcSummary = $db->query("
    SELECT abc_class, COUNT(*) as count, SUM(total_revenue) as rev
    FROM mining_product_abc
    GROUP BY abc_class ORDER BY abc_class
")->fetchAll();

// ---- Clustering Summary ----
$clusterSummary = $db->query("
    SELECT cluster_label, COUNT(*) as count, AVG(monetary) as avg_spend
    FROM clustering_customer_groups c
    JOIN analytics_rfm r ON c.customer_id = r.customer_id
    GROUP BY cluster_label ORDER BY avg_spend DESC
")->fetchAll();

// ---- ETL Log (last import) ----
$lastImport = $db->query("
    SELECT * FROM etl_log ORDER BY log_id DESC LIMIT 1
")->fetch();

// ---- Format angka ----
function formatCurrency($val): string {
    if ($val >= 1_000_000) return '£' . number_format($val/1_000_000, 2) . 'M';
    if ($val >= 1_000)     return '£' . number_format($val/1_000, 1) . 'K';
    return '£' . number_format($val, 2);
}
?>

<!-- Alert Dashboard CRUD -->
<?php if ($dashAlert): ?>
<div class="alert alert-<?= $dashAlert['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 24px;">
    <?= $dashAlert['type'] === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($dashAlert['msg']) ?>
</div>
<?php endif; ?>

<!-- Quick Dashboard CRUD Actions Panel -->
<div class="panel" style="margin-bottom: 24px; background: rgba(30, 37, 53, 0.6); border-color: rgba(20,184,166,0.3);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <div class="panel-title" style="font-size: 1.1rem;">⚡ Control Panel CRUD & Operasi Cepat</div>
            <div class="panel-subtitle">Kelola master data produk dan akses fitur BI langsung dari Dashboard</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="button" class="btn btn-primary" onclick="openDashModal()">
                ➕ Tambah Produk (CRUD)
            </button>
            <a href="?page=products" class="btn btn-secondary">
                📦 Master Produk Full →
            </a>
            <a href="?page=customers" class="btn btn-secondary">
                👥 Kelola Pelanggan →
            </a>
            <a href="?page=import" class="btn btn-secondary">
                📥 Import CSV →
            </a>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card teal">
        <div class="kpi-icon">💰</div>
        <div class="kpi-label">Total Revenue</div>
        <div class="kpi-value"><?= formatCurrency($totalRevenue) ?></div>
        <div class="kpi-change up">↑ Dari <?= number_format($totalOrders) ?> transaksi</div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-icon">🛒</div>
        <div class="kpi-label">Total Orders</div>
        <div class="kpi-value"><?= number_format($totalOrders) ?></div>
        <div class="kpi-change up">↑ Invoice sukses</div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-icon">👥</div>
        <div class="kpi-label">Pelanggan Unik</div>
        <div class="kpi-value"><?= number_format($uniqueCustomers) ?></div>
        <div class="kpi-change">🌍 Multi-negara</div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-icon">📦</div>
        <div class="kpi-label">Produk Aktif</div>
        <div class="kpi-value"><?= number_format($uniqueProducts) ?></div>
        <div class="kpi-change">📋 SKU Berbeda</div>
    </div>
    <div class="kpi-card rose">
        <div class="kpi-icon">❌</div>
        <div class="kpi-label">Order Dibatalkan</div>
        <div class="kpi-value"><?= number_format($cancelledCount) ?></div>
        <div class="kpi-change down">↓ Perlu perhatian</div>
    </div>
</div>

<?php if (empty($monthlyData)): ?>
<!-- Empty State: belum ada data -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="empty-state">
        <span class="empty-state-icon">📭</span>
        <h3>Belum ada data untuk ditampilkan</h3>
        <p>Silakan import file CSV terlebih dahulu untuk melihat dashboard.</p>
        <a href="?page=import" class="btn btn-primary" style="margin-top: 20px;">
            📥 Import Data Sekarang
        </a>
    </div>
</div>
<?php else: ?>

<!-- Row 1: Revenue Trend + Country -->
<div class="chart-grid wide" style="margin-bottom: 24px;">
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">📈 Tren Revenue Bulanan</div>
                <div class="panel-subtitle">Pendapatan total per bulan (Reporting Services)</div>
            </div>
            <span class="panel-badge">Reporting Services</span>
        </div>
        <div class="chart-container" style="height: 260px;">
            <canvas id="chartRevenueTrend"></canvas>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🌍 Revenue per Negara</div>
                <div class="panel-subtitle">Top 8 negara berdasarkan pendapatan</div>
            </div>
            <span class="panel-badge">Geographic</span>
        </div>
        <div class="chart-container" style="height: 260px;">
            <canvas id="chartCountry"></canvas>
        </div>
    </div>
</div>

<!-- Row 2: RFM Segments + Pareto ABC + K-Means Cluster -->
<div class="chart-grid" style="margin-bottom: 24px; grid-template-columns: repeat(3, 1fr);">
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🔍 Segmentasi RFM</div>
                <div class="panel-subtitle">Analysis Services</div>
            </div>
        </div>
        <?php if (empty($rfmSegments)): ?>
        <div class="empty-state" style="padding: 24px;">
            <p>Jalankan kalkulasi RFM di menu Pelanggan</p>
        </div>
        <?php else: ?>
        <div class="chart-container" style="height: 230px;">
            <canvas id="chartRFM"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🅰️ Pareto ABC Class</div>
                <div class="panel-subtitle">Data Mining Revenue</div>
            </div>
        </div>
        <?php if (empty($abcSummary)): ?>
        <div class="empty-state" style="padding: 24px;">
            <p>Jalankan ABC Analysis di menu Produk</p>
        </div>
        <?php else: ?>
        <div class="chart-container" style="height: 230px;">
            <canvas id="chartABC"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🔮 K-Means Clusters</div>
                <div class="panel-subtitle">Clustering Support</div>
            </div>
        </div>
        <?php if (empty($clusterSummary)): ?>
        <div class="empty-state" style="padding: 24px;">
            <p>Jalankan K-Means di menu Clustering</p>
        </div>
        <?php else: ?>
        <div class="chart-container" style="height: 230px;">
            <canvas id="chartCluster"></canvas>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Row 3: Top 10 Products with Quick CRUD Actions -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header">
        <div>
            <div class="panel-title">🏆 Top 10 Produk Terbaik & Aksi CRUD</div>
            <div class="panel-subtitle">Daftar produk dengan pendapatan tertinggi & opsi hapus cepat</div>
        </div>
        <span class="panel-badge">Data Mining + CRUD</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kode Produk</th>
                    <th>Deskripsi Produk</th>
                    <th>Total Revenue</th>
                    <th>Jumlah Terjual</th>
                    <th>Aksi CRUD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProducts as $i => $p): ?>
                <tr>
                    <td style="color: var(--text-muted);"><?= $i + 1 ?></td>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:3px 8px;border-radius:4px;"><?= htmlspecialchars($p['stock_code']) ?></code></td>
                    <td style="font-size:.85rem;"><?= htmlspecialchars($p['description']) ?></td>
                    <td style="color: var(--accent-teal); font-weight:600;"><?= formatCurrency($p['total_revenue']) ?></td>
                    <td style="color: var(--text-muted);"><?= number_format($p['total_qty_terjual']) ?> item</td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Hapus produk ini dari database?');" style="display:inline;">
                            <input type="hidden" name="dash_crud_action" value="delete">
                            <input type="hidden" name="stock_code" value="<?= htmlspecialchars($p['stock_code'], ENT_QUOTES) ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="background:rgba(244,63,94,.15);color:var(--accent-rose);border:1px solid rgba(244,63,94,.3);">
                                🗑️ Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- Last Import Status -->
<?php if ($lastImport): ?>
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📥 Status Integration Services (ETL Import)</div>
        <span class="badge <?= $lastImport['status'] === 'success' ? 'badge-regular' : 'badge-risk' ?>">
            <?= strtoupper($lastImport['status']) ?>
        </span>
    </div>
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">FILE SUMBER</div>
            <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($lastImport['source_file']) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">TOTAL BARIS CSV</div>
            <div style="font-weight:600;"><?= number_format($lastImport['total_rows']) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">BARIS BERHASIL</div>
            <div style="font-weight:600;color:var(--accent-emerald);"><?= number_format($lastImport['success_rows']) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">BARIS SKIPPED/GAGAL</div>
            <div style="font-weight:600;color:var(--accent-rose);"><?= number_format($lastImport['failed_rows']) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Dashboard CRUD Modal (Create Product) -->
<div id="dashModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
    <div class="panel" style="width:100%; max-width:460px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:1.1rem; color:var(--text-primary);">➕ Tambah Produk dari Dashboard</h3>
            <button type="button" onclick="closeDashModal()" style="background:none; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer;">✖</button>
        </div>
        <form method="POST">
            <input type="hidden" name="dash_crud_action" value="create">
            
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Kode Produk (StockCode)</label>
                <input type="text" name="stock_code" required style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
            </div>
            
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Deskripsi Produk</label>
                <input type="text" name="description" required style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Harga Satuan (£)</label>
                <input type="number" step="0.01" name="unit_price" required style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Tier Harga</label>
                <select name="price_tier" style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
                    <option value="Low">Low (< £2)</option>
                    <option value="Mid" selected>Mid (£2 - £10)</option>
                    <option value="Premium">Premium (> £10)</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeDashModal()" class="btn btn-secondary">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan ke Database</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDashModal() {
    $('#dashModal').css('display', 'flex');
}
function closeDashModal() {
    $('#dashModal').css('display', 'none');
}

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } } },
};

<?php if (!empty($monthlyData)): ?>
// --- 1. Line Chart: Revenue Trend ---
new Chart(document.getElementById('chartRevenueTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nama_bulan'].' '.$r['tahun'], $monthlyData)) ?>,
        datasets: [{
            label: 'Revenue (£)',
            data: <?= json_encode(array_column($monthlyData, 'total_revenue')) ?>,
            borderColor: '#14b8a6',
            backgroundColor: 'rgba(20,184,166,.12)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#14b8a6',
            pointRadius: 3,
        }]
    },
    options: {
        ...chartDefaults,
        scales: {
            x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.04)' } },
            y: { ticks: { color: '#64748b', callback: v => '£' + (v>=1000 ? (v/1000).toFixed(0)+'K' : v) }, grid: { color: 'rgba(255,255,255,.04)' } }
        }
    }
});

// --- 2. Horizontal Bar Chart: Country Revenue ---
new Chart(document.getElementById('chartCountry'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($countryData, 'country_name')) ?>,
        datasets: [{
            label: 'Revenue (£)',
            data: <?= json_encode(array_column($countryData, 'total_revenue')) ?>,
            backgroundColor: ['#14b8a6','#6366f1','#8b5cf6','#f43f5e','#f59e0b','#10b981','#3b82f6','#ec4899'],
            borderRadius: 6,
        }]
    },
    options: {
        ...chartDefaults,
        indexAxis: 'y',
        scales: {
            x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.04)' } },
            y: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { display: false } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($rfmSegments)): ?>
// --- 3. Doughnut Chart: RFM Segments ---
new Chart(document.getElementById('chartRFM'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($rfmSegments, 'rfm_segment')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($rfmSegments, 'count')) ?>,
            backgroundColor: ['#14b8a6','#6366f1','#f59e0b','#f43f5e','#10b981','#8b5cf6','#3b82f6','#ec4899','#64748b'],
            borderColor: '#1e2535',
            borderWidth: 2,
        }]
    },
    options: {
        ...chartDefaults,
        cutout: '60%',
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10 }, boxWidth: 10 } } }
    }
});
<?php endif; ?>

<?php if (!empty($abcSummary)): ?>
// --- 4. Pie Chart: Pareto ABC ---
new Chart(document.getElementById('chartABC'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_map(fn($r) => 'Kelas '.$r['abc_class'], $abcSummary)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($abcSummary, 'count')) ?>,
            backgroundColor: ['#14b8a6','#f59e0b','#64748b'],
            borderColor: '#1e2535',
            borderWidth: 2,
        }]
    },
    options: {
        ...chartDefaults,
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10 } } } }
    }
});
<?php endif; ?>

<?php if (!empty($clusterSummary)): ?>
// --- 5. Bar Chart: K-Means Clusters ---
new Chart(document.getElementById('chartCluster'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($clusterSummary, 'cluster_label')) ?>,
        datasets: [{
            label: 'Jumlah Pelanggan',
            data: <?= json_encode(array_column($clusterSummary, 'count')) ?>,
            backgroundColor: ['#8b5cf6','#14b8a6','#f43f5e','#f59e0b'],
            borderRadius: 6,
        }]
    },
    options: {
        ...chartDefaults,
        scales: {
            x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
            y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.04)' } }
        }
    }
});
<?php endif; ?>
</script>

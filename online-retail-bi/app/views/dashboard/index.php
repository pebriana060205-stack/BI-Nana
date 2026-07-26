<?php
// ============================================================
//  Dashboard View — KPI Cards + Charts
// ============================================================

$db = getDB();

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

<!-- Charts Row 1: Revenue Trend + Country -->
<div class="chart-grid wide" style="margin-bottom: 24px;">
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">📈 Tren Revenue Bulanan</div>
                <div class="panel-subtitle">Pendapatan total per bulan (bukan cancelled)</div>
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
            <span class="panel-badge">Analysis</span>
        </div>
        <div class="chart-container" style="height: 260px;">
            <canvas id="chartCountry"></canvas>
        </div>
    </div>
</div>

<!-- Charts Row 2: RFM Segments + Top Products -->
<div class="chart-grid" style="margin-bottom: 24px;">
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🔍 Segmentasi RFM Pelanggan</div>
                <div class="panel-subtitle">Distribusi pelanggan berdasarkan analisis RFM</div>
            </div>
            <span class="panel-badge">Analysis Services</span>
        </div>
        <?php if (empty($rfmSegments)): ?>
        <div class="empty-state" style="padding: 32px;">
            <span class="empty-state-icon" style="font-size:2rem;">🔮</span>
            <p>Jalankan kalkulasi RFM di halaman Pelanggan terlebih dahulu</p>
            <a href="?page=customers" class="btn btn-secondary btn-sm" style="margin-top:12px;">Ke Halaman Pelanggan →</a>
        </div>
        <?php else: ?>
        <div class="chart-container" style="height: 240px;">
            <canvas id="chartRFM"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🏆 Top 10 Produk</div>
                <div class="panel-subtitle">Berdasarkan total revenue</div>
            </div>
            <span class="panel-badge">Data Mining</span>
        </div>
        <div class="table-wrapper" style="max-height: 280px; overflow-y: auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode</th>
                        <th>Produk</th>
                        <th>Revenue</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $i => $p): ?>
                    <tr>
                        <td style="color: var(--text-muted);"><?= $i + 1 ?></td>
                        <td><code style="font-size:.75rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($p['stock_code']) ?></code></td>
                        <td style="font-size:.8rem; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['description']) ?></td>
                        <td style="color: var(--accent-teal); font-weight:600;"><?= formatCurrency($p['total_revenue']) ?></td>
                        <td style="color: var(--text-muted);"><?= number_format($p['total_qty_terjual']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Last Import Status -->
<?php if ($lastImport): ?>
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📥 Status Import Terakhir</div>
        <span class="badge <?= $lastImport['status'] === 'success' ? 'badge-regular' : 'badge-risk' ?>">
            <?= strtoupper($lastImport['status']) ?>
        </span>
    </div>
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">FILE</div>
            <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($lastImport['source_file']) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">TOTAL BARIS</div>
            <div style="font-weight:600;"><?= number_format($lastImport['total_rows']) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">BERHASIL</div>
            <div style="font-weight:600;color:var(--accent-emerald);"><?= number_format($lastImport['success_rows']) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">GAGAL</div>
            <div style="font-weight:600;color:var(--accent-rose);"><?= number_format($lastImport['failed_rows']) ?></div>
        </div>
    </div>
    <?php if ($lastImport['total_rows'] > 0): ?>
    <div style="margin-top: 16px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:.75rem;color:var(--text-muted);">
            <span>Progress Import</span>
            <span><?= round($lastImport['success_rows'] / $lastImport['total_rows'] * 100, 1) ?>%</span>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width: <?= round($lastImport['success_rows'] / $lastImport['total_rows'] * 100, 1) ?>%"></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Chart.js Scripts -->
<script>
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } } },
};

<?php if (!empty($monthlyData)): ?>
// --- Revenue Trend Chart ---
new Chart(document.getElementById('chartRevenueTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nama_bulan'].' '.$r['tahun'], $monthlyData)) ?>,
        datasets: [{
            label: 'Revenue (£)',
            data: <?= json_encode(array_column($monthlyData, 'total_revenue')) ?>,
            borderColor: '#14b8a6',
            backgroundColor: 'rgba(20,184,166,.1)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
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

// --- Country Bar Chart ---
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
// --- RFM Donut Chart ---
new Chart(document.getElementById('chartRFM'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($rfmSegments, 'rfm_segment')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($rfmSegments, 'count')) ?>,
            backgroundColor: ['#14b8a6','#6366f1','#f59e0b','#f43f5e','#10b981','#8b5cf6','#3b82f6','#ec4899','#64748b'],
            borderColor: '#1e2535',
            borderWidth: 3,
        }]
    },
    options: {
        ...chartDefaults,
        cutout: '65%',
        plugins: {
            legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 10 }, padding: 12, boxWidth: 12 } }
        }
    }
});
<?php endif; ?>
</script>

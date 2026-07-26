<?php
// ============================================================
//  Products View — Data Mining (ABC Analysis)
// ============================================================

require_once BASE_PATH . '/helpers/ABC.php';

$db  = getDB();
$abc = new ABC();

if (isset($_POST['calc_abc'])) {
    $abcResult = $abc->calculate();
}

$abcSummary  = $abc->getSummary();
$productsA   = $abc->getProducts('A', 100);
$totalProds  = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$avgPrice    = $db->query("SELECT AVG(unit_price) FROM products")->fetchColumn();
$premiumCount= $db->query("SELECT COUNT(*) FROM products WHERE price_tier='Premium'")->fetchColumn();
?>

<div class="kpi-grid" style="grid-template-columns: repeat(4,1fr);">
    <div class="kpi-card teal">
        <div class="kpi-icon">📦</div>
        <div class="kpi-label">Total Produk</div>
        <div class="kpi-value"><?= number_format($totalProds) ?></div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-icon">🅰️</div>
        <div class="kpi-label">Kelas A (Top 80%)</div>
        <div class="kpi-value"><?= number_format($abcSummary[0]['jumlah_produk'] ?? 0) ?></div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-icon">💎</div>
        <div class="kpi-label">Produk Premium</div>
        <div class="kpi-value"><?= number_format($premiumCount) ?></div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-icon">💷</div>
        <div class="kpi-label">Rata-rata Harga</div>
        <div class="kpi-value">£<?= number_format($avgPrice, 2) ?></div>
    </div>
</div>

<!-- Hitung ABC -->
<div class="panel" style="margin-bottom: 24px; border-color: rgba(245,158,11,.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <div class="panel-title">⛏️ Data Mining — ABC Analysis (Pareto 80/20)</div>
            <div class="panel-subtitle">Klasifikasi produk: A = menghasilkan 80% revenue | B = 80–95% | C = sisanya</div>
        </div>
        <form method="POST">
            <button type="submit" name="calc_abc" class="btn btn-primary btn-lg">
                ⚙️ Hitung ABC Analysis
            </button>
        </form>
    </div>
    <?php if (isset($abcResult)): ?>
    <div class="alert alert-<?= $abcResult['success'] ? 'success' : 'error' ?>" style="margin-top:16px;margin-bottom:0;">
        <?php if ($abcResult['success']): ?>
        ✅ Selesai! <?= $abcResult['total_products'] ?> produk dianalisis.
        Kelas A: <?= $abcResult['class_A_count'] ?> | B: <?= $abcResult['class_B_count'] ?> | C: <?= $abcResult['class_C_count'] ?>
        <?php else: ?>
        ❌ <?= htmlspecialchars($abcResult['message']) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ABC Charts -->
<?php if (!empty($abcSummary)): ?>
<div class="chart-grid" style="margin-bottom: 24px;">
    <div class="panel">
        <div class="panel-title" style="margin-bottom:16px;">📊 Distribusi Kelas ABC</div>
        <div class="chart-container" style="height:240px;">
            <canvas id="chartABC"></canvas>
        </div>
    </div>
    <div class="panel">
        <div class="panel-title" style="margin-bottom:16px;">📋 Ringkasan ABC</div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Kelas</th><th>Produk</th><th>Revenue</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <?php 
                    $abcDesc = ['A'=>'Top 80% Revenue','B'=>'80–95% Revenue','C'=>'Sisanya'];
                    foreach ($abcSummary as $s): ?>
                    <tr>
                        <td><span class="badge badge-<?= strtolower($s['abc_class']) ?>"><?= $s['abc_class'] ?></span></td>
                        <td><strong><?= number_format($s['jumlah_produk']) ?></strong></td>
                        <td style="color:var(--accent-teal);">£<?= number_format($s['total_revenue']) ?></td>
                        <td style="font-size:.78rem;color:var(--text-muted);"><?= $abcDesc[$s['abc_class']] ?? '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Table Produk Kelas A -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🅰️ Produk Kelas A — Top Revenue Generators</div>
        <span class="badge badge-a">Prioritas Utama</span>
    </div>
    <div class="table-wrapper">
        <table id="productTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kode Produk</th>
                    <th>Deskripsi</th>
                    <th>Revenue</th>
                    <th>% Revenue</th>
                    <th>% Kumulatif</th>
                    <th>Kelas</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $allProds = $abc->getProducts('', 200);
                foreach ($allProds as $i => $p): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td><code style="font-size:.75rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($p['stock_code']) ?></code></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars(mb_substr($p['description'],0,50)) ?></td>
                    <td style="font-weight:600;color:var(--accent-teal);">£<?= number_format($p['total_revenue']) ?></td>
                    <td><?= $p['revenue_pct'] ?>%</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-bar-wrap" style="width:60px;height:6px;">
                                <div class="progress-bar-fill" style="width:<?= min($p['cumulative_pct'],100) ?>%"></div>
                            </div>
                            <span style="font-size:.78rem;"><?= $p['cumulative_pct'] ?>%</span>
                        </div>
                    </td>
                    <td><span class="badge badge-<?= strtolower($p['abc_class']) ?>"><?= $p['abc_class'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="panel">
    <div class="empty-state">
        <span class="empty-state-icon">📦</span>
        <h3>Belum ada hasil ABC Analysis</h3>
        <p>Klik tombol "Hitung ABC Analysis" di atas setelah data diimport.</p>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#productTable').DataTable({
        pageLength: 25,
        order: [[3,'desc']],
        language: { search: 'Cari:', info: 'Menampilkan _START_–_END_ dari _TOTAL_', paginate: { previous: '←', next: '→' } }
    });
});

<?php if (!empty($abcSummary)): ?>
new Chart(document.getElementById('chartABC'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($abcSummary, 'abc_class')) ?>,
        datasets: [
            {
                label: 'Jumlah Produk',
                data: <?= json_encode(array_column($abcSummary, 'jumlah_produk')) ?>,
                backgroundColor: ['#14b8a6','#f59e0b','#64748b'],
                borderRadius: 8, yAxisID: 'y',
            },
            {
                label: 'Revenue (£)',
                data: <?= json_encode(array_column($abcSummary, 'total_revenue')) ?>,
                backgroundColor: ['rgba(20,184,166,.2)','rgba(245,158,11,.2)','rgba(100,116,139,.2)'],
                borderColor: ['#14b8a6','#f59e0b','#64748b'],
                borderWidth: 2, type: 'line', yAxisID: 'y2', tension: 0.3,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.04)' } },
            y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.04)' } },
            y2: { position: 'right', ticks: { color: '#64748b', callback: v => '£'+(v/1000).toFixed(0)+'K' }, grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>

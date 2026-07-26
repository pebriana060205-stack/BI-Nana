<?php
// ============================================================
//  Customers View — Analysis Services (RFM Segmentation)
// ============================================================

require_once BASE_PATH . '/helpers/RFM.php';

$db  = getDB();
$rfm = new RFM();

// Handle kalkulasi RFM
if (isset($_POST['calc_rfm'])) {
    $calcResult = $rfm->calculate();
}

// KPI
$totalCust = $db->query("SELECT COUNT(*) FROM customers WHERE customer_id > 0")->fetchColumn();
$rfmCount  = $db->query("SELECT COUNT(*) FROM analytics_rfm")->fetchColumn();
$champions = $db->query("SELECT COUNT(*) FROM analytics_rfm WHERE rfm_segment = 'Champions'")->fetchColumn();
$atRisk    = $db->query("SELECT COUNT(*) FROM analytics_rfm WHERE rfm_segment = 'At Risk'")->fetchColumn();

// Segment distribution
$segments = $rfm->getSegmentSummary();
$topCusts = $rfm->getTopCustomers(20);

// Tabel semua customers dengan RFM
$customerList = $db->query("
    SELECT 
        c.customer_id,
        co.country_name,
        c.segment,
        c.total_orders,
        c.total_spent,
        c.last_purchase_date,
        r.recency_days,
        r.frequency,
        r.monetary,
        r.r_score,
        r.f_score,
        r.m_score,
        r.rfm_segment
    FROM customers c
    LEFT JOIN countries co ON c.country_id = co.country_id
    LEFT JOIN analytics_rfm r ON c.customer_id = r.customer_id
    WHERE c.customer_id > 0
    ORDER BY c.total_spent DESC
    LIMIT 500
")->fetchAll();

function segBadgeClass(string $seg): string {
    return match(true) {
        str_contains($seg, 'Champions')   => 'badge-champions',
        str_contains($seg, 'Loyal')       => 'badge-loyal',
        str_contains($seg, 'At Risk')     => 'badge-risk',
        str_contains($seg, 'Cant Lose')   => 'badge-risk',
        str_contains($seg, 'Lost')        => 'badge-lost',
        str_contains($seg, 'Hibernating') => 'badge-lost',
        default                           => 'badge-new',
    };
}
?>

<!-- KPI -->
<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="kpi-card teal">
        <div class="kpi-icon">👥</div>
        <div class="kpi-label">Total Pelanggan</div>
        <div class="kpi-value"><?= number_format($totalCust) ?></div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-icon">🔍</div>
        <div class="kpi-label">Sudah di-RFM</div>
        <div class="kpi-value"><?= number_format($rfmCount) ?></div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-icon">🏆</div>
        <div class="kpi-label">Champions</div>
        <div class="kpi-value"><?= number_format($champions) ?></div>
    </div>
    <div class="kpi-card rose">
        <div class="kpi-icon">⚠️</div>
        <div class="kpi-label">At Risk</div>
        <div class="kpi-value"><?= number_format($atRisk) ?></div>
    </div>
</div>

<!-- Tombol Hitung RFM -->
<div class="panel" style="margin-bottom: 24px; border-color: rgba(99,102,241,.3);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div class="panel-title">🔬 Analysis Services — Kalkulasi RFM</div>
            <div class="panel-subtitle">Menghitung skor Recency, Frequency, Monetary setiap pelanggan lalu mengklasifikasikannya ke segmen.</div>
        </div>
        <form method="POST">
            <button type="submit" name="calc_rfm" class="btn btn-primary btn-lg">
                🔬 Hitung / Update RFM
            </button>
        </form>
    </div>
    <?php if (isset($calcResult)): ?>
    <div class="alert alert-<?= $calcResult['success'] ? 'success' : 'error' ?>" style="margin-top: 16px; margin-bottom: 0;">
        <?= $calcResult['success']
            ? "✅ Selesai! {$calcResult['customers_processed']} pelanggan berhasil disegmentasi."
            : "❌ Error: " . $calcResult['message'] ?>
    </div>
    <?php endif; ?>
</div>

<!-- Charts: Segment Distribution + Scatter -->
<?php if (!empty($segments)): ?>
<div class="chart-grid" style="margin-bottom: 24px;">
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">📊 Distribusi Segmen Pelanggan</div>
                <div class="panel-subtitle">Berdasarkan analisis RFM (<?= number_format($rfmCount) ?> pelanggan)</div>
            </div>
        </div>
        <div class="chart-container" style="height: 280px;">
            <canvas id="chartSegment"></canvas>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Ringkasan per Segmen</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Segmen</th><th>Jumlah</th><th>Avg Monetary</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($segments as $s): ?>
                    <tr>
                        <td><span class="badge <?= segBadgeClass($s['rfm_segment']) ?>"><?= htmlspecialchars($s['rfm_segment']) ?></span></td>
                        <td><strong><?= number_format($s['jumlah_pelanggan']) ?></strong></td>
                        <td style="color:var(--accent-teal);">£<?= number_format($s['rata_monetary'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Customer Table -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">👥 Daftar Pelanggan dengan Skor RFM</div>
        <div class="panel-subtitle">Menampilkan 500 pelanggan teratas</div>
    </div>
    <div class="table-wrapper">
        <table id="customerTable">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Negara</th>
                    <th>Segmen</th>
                    <th>Total Orders</th>
                    <th>Total Spent</th>
                    <th>R Score</th>
                    <th>F Score</th>
                    <th>M Score</th>
                    <th>Last Purchase</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customerList as $c): ?>
                <tr>
                    <td><strong>#<?= htmlspecialchars($c['customer_id']) ?></strong></td>
                    <td><?= htmlspecialchars($c['country_name'] ?? '-') ?></td>
                    <td>
                        <?php if ($c['rfm_segment']): ?>
                        <span class="badge <?= segBadgeClass($c['rfm_segment']) ?>"><?= htmlspecialchars($c['rfm_segment']) ?></span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($c['total_orders']) ?></td>
                    <td style="color:var(--accent-teal);font-weight:600;">£<?= number_format($c['total_spent'], 2) ?></td>
                    <td><?= $c['r_score'] ? '<span style="color:var(--accent-emerald);font-weight:700;">' . $c['r_score'] . '/5</span>' : '—' ?></td>
                    <td><?= $c['f_score'] ? $c['f_score'] . '/5' : '—' ?></td>
                    <td><?= $c['m_score'] ? $c['m_score'] . '/5' : '—' ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= $c['last_purchase_date'] ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// DataTable
$(document).ready(function() {
    $('#customerTable').DataTable({
        pageLength: 25,
        order: [[4, 'desc']],
        language: { 
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_',
            paginate: { previous: '←', next: '→' }
        },
    });
});

<?php if (!empty($segments)): ?>
// Segment Donut Chart
new Chart(document.getElementById('chartSegment'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($segments, 'rfm_segment')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($segments, 'jumlah_pelanggan')) ?>,
            backgroundColor: ['#14b8a6','#6366f1','#f59e0b','#f43f5e','#10b981','#8b5cf6','#3b82f6','#ec4899','#64748b','#f97316'],
            borderColor: '#1e2535', borderWidth: 3,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 10 }, padding: 10, boxWidth: 12 } }
        }
    }
});
<?php endif; ?>
</script>

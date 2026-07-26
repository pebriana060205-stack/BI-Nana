<?php
// ============================================================
//  Clustering View — K-Means Customer Segmentation
// ============================================================

require_once BASE_PATH . '/helpers/Clustering.php';
require_once BASE_PATH . '/helpers/RFM.php';

$db       = getDB();
$cluster  = new Clustering(k: 4);
$rfmObj   = new RFM();

if (isset($_POST['run_clustering'])) {
    $rfmCount = (int)$db->query("SELECT COUNT(*) FROM analytics_rfm")->fetchColumn();
    if ($rfmCount < 4) {
        $clusterError = 'Hitung RFM terlebih dahulu di halaman Pelanggan sebelum menjalankan Clustering.';
    } else {
        $clusterResult = $cluster->run();
    }
}

$clusterSummary = $cluster->getClusterSummary();
$rfmCount = (int)$db->query("SELECT COUNT(*) FROM analytics_rfm")->fetchColumn();
?>

<!-- Header Action -->
<div class="panel" style="margin-bottom: 24px; border-color: rgba(139,92,246,.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <div class="panel-title">🔮 Clustering Support — K-Means (k=4)</div>
            <div class="panel-subtitle">
                Mengelompokkan <?= number_format($rfmCount) ?> pelanggan berdasarkan fitur RFM menjadi 4 klaster: <strong>VIP, Regular, Dormant, One-Time</strong>
            </div>
        </div>
        <form method="POST">
            <button type="submit" name="run_clustering" class="btn btn-primary btn-lg" <?= $rfmCount < 4 ? 'disabled' : '' ?>>
                🔮 Jalankan K-Means Clustering
            </button>
        </form>
    </div>
    <?php if (isset($clusterError)): ?>
    <div class="alert alert-warning" style="margin-top:16px;margin-bottom:0;">⚠️ <?= htmlspecialchars($clusterError) ?></div>
    <?php endif; ?>
    <?php if (isset($clusterResult)): ?>
    <div class="alert alert-<?= $clusterResult['success'] ? 'success' : 'error' ?>" style="margin-top:16px;margin-bottom:0;">
        <?= $clusterResult['success']
            ? "✅ Clustering selesai! k={$clusterResult['k']}, konvergen dalam {$clusterResult['iterations']} iterasi."
            : "❌ " . htmlspecialchars($clusterResult['message']) ?>
    </div>
    <?php endif; ?>
    <?php if ($rfmCount < 4): ?>
    <div class="alert alert-warning" style="margin-top:16px;margin-bottom:0;">
        ⚠️ Belum ada data RFM. 
        <a href="?page=customers" style="color:var(--accent-amber);font-weight:600;">→ Hitung RFM dulu di halaman Pelanggan</a>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($clusterSummary)): ?>

<!-- Cluster KPI Cards -->
<div class="kpi-grid" style="grid-template-columns: repeat(4,1fr); margin-bottom: 24px;">
    <?php 
    $clusterColors = ['VIP'=>'violet','Regular'=>'teal','Dormant'=>'rose','One-Time'=>'amber'];
    foreach ($clusterSummary as $cs):
        $colorClass = $clusterColors[$cs['cluster_label']] ?? 'indigo';
    ?>
    <div class="kpi-card <?= $colorClass ?>">
        <div class="kpi-icon"><?= match($cs['cluster_label']) {
            'VIP'      => '👑',
            'Regular'  => '⭐',
            'Dormant'  => '💤',
            'One-Time' => '👋',
            default    => '🔵',
        } ?></div>
        <div class="kpi-label"><?= htmlspecialchars($cs['cluster_label']) ?></div>
        <div class="kpi-value"><?= number_format($cs['jumlah_pelanggan']) ?></div>
        <div class="kpi-change">Avg Spend: £<?= number_format($cs['avg_monetary'], 0) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts: Cluster Distribution + Radar -->
<div class="chart-grid" style="margin-bottom: 24px;">
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">🎯 Distribusi Klaster Pelanggan</div>
        </div>
        <div class="chart-container" style="height: 280px;">
            <canvas id="chartCluster"></canvas>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📊 Profil RFM per Klaster</div>
        </div>
        <div class="chart-container" style="height: 280px;">
            <canvas id="chartRadar"></canvas>
        </div>
    </div>
</div>

<!-- Cluster Detail Table -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📋 Statistik Detail per Klaster</div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Klaster</th>
                    <th>Label</th>
                    <th>Jumlah Pelanggan</th>
                    <th>Avg Recency (hari)</th>
                    <th>Avg Frequency</th>
                    <th>Avg Monetary (£)</th>
                    <th>Strategi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $strategies = [
                    'VIP'      => '🎁 Reward & Retain — Berikan loyalty program eksklusif',
                    'Regular'  => '📈 Upsell & Cross-sell — Tingkatkan nilai transaksi',
                    'Dormant'  => '📧 Win-back Campaign — Email re-engagement',
                    'One-Time' => '🔁 Convert to Loyal — Promosi pembelian kedua',
                ];
                $badgeMap = ['VIP'=>'badge-vip','Regular'=>'badge-regular','Dormant'=>'badge-dormant','One-Time'=>'badge-new'];
                foreach ($clusterSummary as $cs): ?>
                <tr>
                    <td style="color:var(--text-muted);">C<?= $cs['cluster_id'] ?></td>
                    <td><span class="badge <?= $badgeMap[$cs['cluster_label']] ?? 'badge-new' ?>"><?= htmlspecialchars($cs['cluster_label']) ?></span></td>
                    <td><strong><?= number_format($cs['jumlah_pelanggan']) ?></strong></td>
                    <td><?= $cs['avg_recency'] ?> hari</td>
                    <td><?= $cs['avg_frequency'] ?>x</td>
                    <td style="color:var(--accent-teal);font-weight:600;">£<?= number_format($cs['avg_monetary']) ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= $strategies[$cs['cluster_label']] ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const clusterLabels = <?= json_encode(array_column($clusterSummary, 'cluster_label')) ?>;
const clusterCounts = <?= json_encode(array_column($clusterSummary, 'jumlah_pelanggan')) ?>;
const avgRecency    = <?= json_encode(array_column($clusterSummary, 'avg_recency')) ?>;
const avgFrequency  = <?= json_encode(array_column($clusterSummary, 'avg_frequency')) ?>;
const avgMonetary   = <?= json_encode(array_column($clusterSummary, 'avg_monetary')) ?>;

const colors = ['#8b5cf6','#14b8a6','#f43f5e','#f59e0b'];

// Donut Chart
new Chart(document.getElementById('chartCluster'), {
    type: 'doughnut',
    data: {
        labels: clusterLabels,
        datasets: [{ data: clusterCounts, backgroundColor: colors, borderColor: '#1e2535', borderWidth: 3 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '60%',
        plugins: { legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 11 }, padding: 14, boxWidth: 14 } } }
    }
});

// Radar Chart
new Chart(document.getElementById('chartRadar'), {
    type: 'radar',
    data: {
        labels: ['Recency\n(terbalik)', 'Frequency', 'Monetary'],
        datasets: clusterLabels.map((label, i) => ({
            label: label,
            data: [
                Math.max(...avgRecency) - avgRecency[i] + 1,
                avgFrequency[i],
                avgMonetary[i] / Math.max(...avgMonetary) * 10,
            ],
            borderColor: colors[i],
            backgroundColor: colors[i] + '22',
            borderWidth: 2,
            pointBackgroundColor: colors[i],
        }))
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
            r: {
                ticks: { color: '#64748b', backdropColor: 'transparent' },
                grid: { color: 'rgba(255,255,255,.06)' },
                pointLabels: { color: '#94a3b8', font: { size: 11 } }
            }
        },
        plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } }
    }
});
</script>

<?php else: ?>
<div class="panel">
    <div class="empty-state">
        <span class="empty-state-icon">🔮</span>
        <h3>Belum ada hasil Clustering</h3>
        <p>Pastikan sudah menghitung RFM terlebih dahulu, lalu klik "Jalankan K-Means Clustering" di atas.</p>
    </div>
</div>
<?php endif; ?>

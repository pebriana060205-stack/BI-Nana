<?php
// ============================================================
//  Reports View — Reporting Services
// ============================================================

$db = getDB();

// Monthly Sales Report
$monthlySales = $db->query("SELECT * FROM vw_monthly_sales ORDER BY tahun, bulan")->fetchAll();

// Country Report
$countrySales = $db->query("SELECT * FROM vw_sales_by_country LIMIT 30")->fetchAll();

// Summary KPI
$totalRevAll = array_sum(array_column($monthlySales, 'total_revenue'));
$bestMonth   = !empty($monthlySales) ? array_reduce($monthlySales, fn($carry, $item) => $item['total_revenue'] > ($carry['total_revenue'] ?? 0) ? $item : $carry) : null;
?>

<div class="kpi-grid" style="grid-template-columns: repeat(3,1fr);">
    <div class="kpi-card teal">
        <div class="kpi-icon">💰</div>
        <div class="kpi-label">Total Revenue Keseluruhan</div>
        <div class="kpi-value">£<?= number_format($totalRevAll/1000000, 2) ?>M</div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-icon">📅</div>
        <div class="kpi-label">Bulan Terbaik</div>
        <div class="kpi-value" style="font-size:1.2rem;"><?= $bestMonth ? $bestMonth['nama_bulan'].' '.$bestMonth['tahun'] : '—' ?></div>
        <div class="kpi-change up"><?= $bestMonth ? '£'.number_format($bestMonth['total_revenue']) : '' ?></div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-icon">🌍</div>
        <div class="kpi-label">Negara Terdata</div>
        <div class="kpi-value"><?= count($countrySales) ?></div>
    </div>
</div>

<!-- Export Buttons -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header">
        <div class="panel-title">📤 Export Laporan</div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="?page=api&action=export_monthly_csv" class="btn btn-secondary">
            📊 Export Laporan Bulanan (CSV)
        </a>
        <a href="?page=api&action=export_country_csv" class="btn btn-secondary">
            🌍 Export Laporan Negara (CSV)
        </a>
        <a href="?page=api&action=export_products_csv" class="btn btn-secondary">
            📦 Export Laporan Produk (CSV)
        </a>
        <a href="?page=api&action=export_rfm_csv" class="btn btn-secondary">
            👥 Export Data RFM (CSV)
        </a>
    </div>
</div>

<!-- Monthly Sales Table -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header">
        <div class="panel-title">📅 Laporan Penjualan Bulanan</div>
        <span class="panel-badge">Reporting Services</span>
    </div>
    <div class="chart-container" style="height:250px;margin-bottom:20px;">
        <canvas id="chartMonthly"></canvas>
    </div>
    <div class="table-wrapper">
        <table id="monthlyTable">
            <thead>
                <tr>
                    <th>Tahun</th>
                    <th>Bulan</th>
                    <th>Total Order</th>
                    <th>Pelanggan Unik</th>
                    <th>Total Revenue</th>
                    <th>Avg Order Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlySales as $row): ?>
                <tr>
                    <td><?= $row['tahun'] ?></td>
                    <td><?= $row['nama_bulan'] ?></td>
                    <td><?= number_format($row['total_order']) ?></td>
                    <td><?= number_format($row['pelanggan_unik']) ?></td>
                    <td style="font-weight:600;color:var(--accent-teal);">£<?= number_format($row['total_revenue']) ?></td>
                    <td>£<?= number_format($row['rata_rata_order'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Country Report -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🌍 Laporan Penjualan per Negara</div>
    </div>
    <div class="table-wrapper">
        <table id="countryTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Negara</th>
                    <th>Region</th>
                    <th>Total Order</th>
                    <th>Pelanggan Unik</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($countrySales as $i => $row): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['country_name']) ?></td>
                    <td style="color:var(--text-muted);font-size:.8rem;"><?= htmlspecialchars($row['region']) ?></td>
                    <td><?= number_format($row['total_order']) ?></td>
                    <td><?= number_format($row['pelanggan_unik']) ?></td>
                    <td style="font-weight:600;color:var(--accent-teal);">£<?= number_format($row['total_revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#monthlyTable').DataTable({ pageLength: 12, order: [[4,'desc']], language: { search:'Cari:', paginate:{previous:'←',next:'→'} } });
    $('#countryTable').DataTable({ pageLength: 15, order: [[5,'desc']], language: { search:'Cari:', paginate:{previous:'←',next:'→'} } });
});

<?php if (!empty($monthlySales)): ?>
new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => substr($r['nama_bulan'],0,3)."'".$r['tahun'], $monthlySales)) ?>,
        datasets: [
            {
                label: 'Revenue (£)', type: 'bar',
                data: <?= json_encode(array_column($monthlySales, 'total_revenue')) ?>,
                backgroundColor: 'rgba(20,184,166,.3)', borderColor: '#14b8a6', borderWidth: 1, borderRadius: 4, yAxisID: 'y',
            },
            {
                label: 'Jumlah Order', type: 'line',
                data: <?= json_encode(array_column($monthlySales, 'total_order')) ?>,
                borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.1)',
                borderWidth: 2.5, fill: true, tension: 0.4, yAxisID: 'y2', pointRadius: 2,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
            x: { ticks: { color: '#64748b', font:{size:10} }, grid: { color: 'rgba(255,255,255,.03)' } },
            y: { ticks: { color: '#64748b', callback: v => '£'+(v/1000).toFixed(0)+'K' }, grid: { color: 'rgba(255,255,255,.04)' } },
            y2: { position: 'right', ticks: { color: '#6366f1' }, grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>

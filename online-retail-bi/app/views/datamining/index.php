<?php
// ============================================================
//  Data Mining Center — RFM + Pareto ABC + K-Means + Assoc Rules
// ============================================================

$db = getDB();

// ── 1. RFM Summary ──────────────────────────────────────────
$rfmTotal     = (int) $db->query("SELECT COUNT(*) FROM analytics_rfm")->fetchColumn();
$rfmSegments  = $db->query("
    SELECT rfm_segment, COUNT(*) AS cnt
    FROM analytics_rfm GROUP BY rfm_segment ORDER BY cnt DESC
")->fetchAll();

$rfmSample = $db->query("
    SELECT r.customer_id, r.recency_days, r.frequency, r.monetary,
           r.rfm_score, r.rfm_segment
    FROM analytics_rfm r
    ORDER BY r.monetary DESC
    LIMIT 15
")->fetchAll();

// ── 2. Pareto ABC Summary ────────────────────────────────────
$abcTotal   = (int) $db->query("SELECT COUNT(*) FROM mining_product_abc")->fetchColumn();
$abcSummary = $db->query("
    SELECT abc_class, COUNT(*) AS cnt,
           SUM(total_revenue) AS total_rev,
           ROUND(SUM(cumulative_pct),2) AS avg_cum_pct
    FROM mining_product_abc GROUP BY abc_class ORDER BY abc_class
")->fetchAll();

$abcSample = $db->query("
    SELECT stock_code, description, total_revenue, total_qty, abc_class
    FROM mining_product_abc ORDER BY total_revenue DESC LIMIT 15
")->fetchAll();

// ── 3. K-Means Cluster Summary ───────────────────────────────
$clusterTotal   = (int) $db->query("SELECT COUNT(*) FROM clustering_customer_groups")->fetchColumn();
$clusterSummary = $db->query("
    SELECT cluster_id, cluster_label, COUNT(*) AS cnt,
           ROUND(AVG(avg_monetary),2) AS avg_monetary,
           ROUND(AVG(avg_frequency),2) AS avg_frequency
    FROM clustering_customer_groups
    GROUP BY cluster_id, cluster_label ORDER BY cluster_id
")->fetchAll();

// ── 4. Association Rules Summary ────────────────────────────
$ruleCount = (int) $db->query("SELECT COUNT(*) FROM mining_association_rules")->fetchColumn();
$topRules  = $db->query("
    SELECT antecedent, consequent, support, confidence, lift
    FROM mining_association_rules ORDER BY lift DESC LIMIT 10
")->fetchAll();

// ── Helpers ──────────────────────────────────────────────────
function fmtM($val) {
    if ($val >= 1_000_000) return '£' . number_format($val/1_000_000,2) . 'M';
    if ($val >= 1_000)     return '£' . number_format($val/1_000,1) . 'K';
    return '£' . number_format($val,0);
}
?>

<!-- ═══════════════════════════════════════════════════════════
     HERO HEADER
════════════════════════════════════════════════════════════ -->
<div style="background: linear-gradient(135deg, rgba(99,102,241,.18) 0%, rgba(139,92,246,.12) 50%, rgba(20,184,166,.1) 100%);
            border: 1px solid rgba(99,102,241,.25); border-radius:16px; padding:28px 32px; margin-bottom:28px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="font-size:2rem; margin-bottom:8px;">🧠</div>
            <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin:0 0 6px;">
                Data Mining Center
            </h1>
            <p style="font-size:.875rem; color:var(--text-muted); margin:0; max-width:560px; line-height:1.6;">
                Pusat eksplorasi semua algoritma data mining yang diterapkan pada dataset Online Retail.
                Mencakup <strong style="color:#818cf8;">RFM Segmentation</strong>, <strong style="color:#14b8a6;">Pareto ABC</strong>,
                <strong style="color:#8b5cf6;">K-Means Clustering</strong>, dan <strong style="color:#f59e0b;">Market Basket (Apriori)</strong>.
            </p>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:14px 18px; min-width:90px;">
                <div style="font-size:1.4rem; font-weight:800; color:#818cf8;"><?= number_format($rfmTotal) ?></div>
                <div style="font-size:.68rem; color:var(--text-muted); margin-top:2px;">Pelanggan RFM</div>
            </div>
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:14px 18px; min-width:90px;">
                <div style="font-size:1.4rem; font-weight:800; color:#14b8a6;"><?= number_format($abcTotal) ?></div>
                <div style="font-size:.68rem; color:var(--text-muted); margin-top:2px;">Produk ABC</div>
            </div>
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:14px 18px; min-width:90px;">
                <div style="font-size:1.4rem; font-weight:800; color:#8b5cf6;"><?= number_format($clusterTotal) ?></div>
                <div style="font-size:.68rem; color:var(--text-muted); margin-top:2px;">K-Means Members</div>
            </div>
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:14px 18px; min-width:90px;">
                <div style="font-size:1.4rem; font-weight:800; color:#f59e0b;"><?= number_format($ruleCount) ?></div>
                <div style="font-size:.68rem; color:var(--text-muted); margin-top:2px;">Assoc. Rules</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB NAVIGATION
════════════════════════════════════════════════════════════ -->
<div style="display:flex; gap:8px; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:0; overflow-x:auto;">
    <?php
    $tabs = [
        ['id'=>'tab-rfm',     'label'=>'📈 RFM Segmentation',      'color'=>'#818cf8'],
        ['id'=>'tab-abc',     'label'=>'📊 Pareto ABC Analysis',    'color'=>'#14b8a6'],
        ['id'=>'tab-kmeans',  'label'=>'🔮 K-Means Clustering',     'color'=>'#8b5cf6'],
        ['id'=>'tab-assoc',   'label'=>'⛏️ Market Basket (Apriori)', 'color'=>'#f59e0b'],
        ['id'=>'tab-summary', 'label'=>'📋 Ringkasan Algoritma',    'color'=>'#f43f5e'],
    ];
    foreach ($tabs as $i => $tab): ?>
    <button onclick="switchTab('<?= $tab['id'] ?>')"
            id="btn-<?= $tab['id'] ?>"
            style="padding:10px 18px; font-size:.8rem; font-weight:600; border:none; cursor:pointer;
                   background:transparent; border-bottom: 3px solid transparent; color:var(--text-muted);
                   white-space:nowrap; transition:all .2s; border-radius:0;"
            class="tab-btn <?= $i===0 ? 'tab-active' : '' ?>">
        <?= $tab['label'] ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 1 — RFM SEGMENTATION
════════════════════════════════════════════════════════════ -->
<div id="tab-rfm" class="tab-content">
    <!-- Formula Card -->
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
        <div style="background:rgba(129,140,248,.08); border:1px solid rgba(129,140,248,.2); border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:8px;">⏳</div>
            <div style="font-size:.75rem; color:#818cf8; font-weight:700; letter-spacing:.05em; margin-bottom:4px;">RECENCY (R)</div>
            <div style="font-size:.85rem; color:var(--text-secondary); line-height:1.6;">Jumlah hari sejak transaksi terakhir pelanggan</div>
            <div style="margin-top:10px; font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.2); padding:8px; border-radius:6px; color:#a5b4fc;">
                R = NOW() − MAX(invoice_date)
            </div>
        </div>
        <div style="background:rgba(129,140,248,.08); border:1px solid rgba(129,140,248,.2); border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:8px;">🔁</div>
            <div style="font-size:.75rem; color:#818cf8; font-weight:700; letter-spacing:.05em; margin-bottom:4px;">FREQUENCY (F)</div>
            <div style="font-size:.85rem; color:var(--text-secondary); line-height:1.6;">Total transaksi unik (invoice) yang dilakukan</div>
            <div style="margin-top:10px; font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.2); padding:8px; border-radius:6px; color:#a5b4fc;">
                F = COUNT(DISTINCT invoice_no)
            </div>
        </div>
        <div style="background:rgba(129,140,248,.08); border:1px solid rgba(129,140,248,.2); border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:8px;">💰</div>
            <div style="font-size:.75rem; color:#818cf8; font-weight:700; letter-spacing:.05em; margin-bottom:4px;">MONETARY (M)</div>
            <div style="font-size:.85rem; color:var(--text-secondary); line-height:1.6;">Total nilai pembelian (qty × unit_price)</div>
            <div style="margin-top:10px; font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.2); padding:8px; border-radius:6px; color:#a5b4fc;">
                M = SUM(qty × unit_price)
            </div>
        </div>
    </div>

    <!-- Charts row -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
        <!-- Doughnut Segment -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">🍩 Distribusi Segmen RFM</div>
                <span class="panel-badge" style="background:rgba(129,140,248,.15);color:#818cf8;border-color:rgba(129,140,248,.25);"><?= count($rfmSegments) ?> Segmen</span>
            </div>
            <div class="chart-container" style="height:240px;"><canvas id="chartRfmDonut"></canvas></div>
        </div>
        <!-- Bar count per segment -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">📊 Jumlah Pelanggan per Segmen</div>
                <span class="panel-badge"><?= number_format($rfmTotal) ?> total</span>
            </div>
            <div class="chart-container" style="height:240px;"><canvas id="chartRfmBar"></canvas></div>
        </div>
    </div>

    <!-- RFM Table -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">📋 Top 15 Pelanggan Berdasarkan Monetary</div>
                <div class="panel-subtitle">Skor RFM dihitung dari skala 1–5 per dimensi</div>
            </div>
            <a href="?page=customers" class="btn btn-sm btn-secondary">Lihat Semua →</a>
        </div>
        <div class="table-wrapper">
            <table id="rfmTable">
                <thead><tr>
                    <th>#</th><th>Customer ID</th><th>Recency (hari)</th>
                    <th>Frequency</th><th>Monetary</th><th>Skor RFM</th><th>Segmen</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rfmSample as $i => $r):
                    $seg = $r['rfm_segment'];
                    $segColor = match(true) {
                        str_contains($seg,'Champion')        => '#10b981',
                        str_contains($seg,'Loyal')           => '#818cf8',
                        str_contains($seg,'Potential')       => '#14b8a6',
                        str_contains($seg,'At Risk')         => '#f43f5e',
                        str_contains($seg,'Lost')            => '#64748b',
                        default                              => '#94a3b8',
                    };
                ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:2px 7px;border-radius:4px;"><?= htmlspecialchars($r['customer_id']) ?></code></td>
                    <td style="color:#f59e0b;"><?= number_format($r['recency_days']) ?> hari</td>
                    <td><?= number_format($r['frequency']) ?>×</td>
                    <td style="color:#14b8a6;font-weight:600;"><?= fmtM($r['monetary']) ?></td>
                    <td style="font-weight:700;color:#818cf8;"><?= htmlspecialchars($r['rfm_score']) ?></td>
                    <td><span style="background:<?= $segColor ?>22;color:<?= $segColor ?>;border:1px solid <?= $segColor ?>44;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:600;"><?= htmlspecialchars($seg) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 2 — PARETO ABC
════════════════════════════════════════════════════════════ -->
<div id="tab-abc" class="tab-content" style="display:none;">
    <!-- ABC Explanation -->
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
        <?php
        $abcDefs = [
            'A' => ['color'=>'#14b8a6','pct'=>'Top 80%','desc'=>'Produk premium — kontribusi terbesar terhadap total revenue','icon'=>'🏆'],
            'B' => ['color'=>'#f59e0b','pct'=>'80–95%', 'desc'=>'Produk menengah — kontribusi signifikan namun tidak dominan','icon'=>'⚡'],
            'C' => ['color'=>'#64748b','pct'=>'95–100%','desc'=>'Produk ekor — volume banyak namun revenue kecil','icon'=>'📦'],
        ];
        foreach ($abcDefs as $cls => $d): ?>
        <div style="background:<?= $d['color'] ?>11; border:1px solid <?= $d['color'] ?>33; border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:6px;"><?= $d['icon'] ?></div>
            <div style="font-size:1.6rem; font-weight:900; color:<?= $d['color'] ?>; margin-bottom:4px;">Kelas <?= $cls ?></div>
            <div style="font-size:.72rem; color:<?= $d['color'] ?>; font-weight:700; margin-bottom:6px;">Kumulatif Revenue: <?= $d['pct'] ?></div>
            <div style="font-size:.8rem; color:var(--text-secondary); line-height:1.5;"><?= $d['desc'] ?></div>
            <?php foreach ($abcSummary as $ab): if ($ab['abc_class'] === $cls): ?>
            <div style="margin-top:12px; padding-top:12px; border-top:1px solid <?= $d['color'] ?>22;">
                <div style="font-size:1.2rem; font-weight:800; color:<?= $d['color'] ?>;"><?= number_format($ab['cnt']) ?> produk</div>
                <div style="font-size:.72rem; color:var(--text-muted);">Revenue: <?= fmtM($ab['total_rev']) ?></div>
            </div>
            <?php endif; endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">🥧 Distribusi ABC (Jumlah Produk)</div>
            </div>
            <div class="chart-container" style="height:230px;"><canvas id="chartAbcPie"></canvas></div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">📊 Revenue per Kelas ABC</div>
            </div>
            <div class="chart-container" style="height:230px;"><canvas id="chartAbcRev"></canvas></div>
        </div>
    </div>

    <!-- ABC Table -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">📋 Top 15 Produk Berdasarkan Revenue (ABC)</div>
                <div class="panel-subtitle">Urutan kumulatif menentukan kelas Pareto A/B/C</div>
            </div>
            <a href="?page=products" class="btn btn-sm btn-secondary">Lihat Semua →</a>
        </div>
        <div class="table-wrapper">
            <table id="abcTable">
                <thead><tr>
                    <th>#</th><th>Kode Produk</th><th>Deskripsi</th>
                    <th>Total Revenue</th><th>Total Qty</th><th>Kelas ABC</th>
                </tr></thead>
                <tbody>
                <?php foreach ($abcSample as $i => $p):
                    $cls   = $p['abc_class'];
                    $color = ['A'=>'#14b8a6','B'=>'#f59e0b','C'=>'#64748b'][$cls] ?? '#94a3b8';
                ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:2px 7px;border-radius:4px;"><?= htmlspecialchars($p['stock_code']) ?></code></td>
                    <td style="font-size:.83rem;"><?= htmlspecialchars($p['description']) ?></td>
                    <td style="color:#14b8a6;font-weight:600;"><?= fmtM($p['total_revenue']) ?></td>
                    <td style="color:var(--text-muted);"><?= number_format($p['total_qty']) ?></td>
                    <td><span style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44;border-radius:20px;padding:2px 14px;font-size:.8rem;font-weight:800;">Kelas <?= $cls ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 3 — K-MEANS
════════════════════════════════════════════════════════════ -->
<div id="tab-kmeans" class="tab-content" style="display:none;">
    <!-- Cluster cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; margin-bottom:24px;">
        <?php
        $clusterColors = ['#8b5cf6','#14b8a6','#f43f5e','#f59e0b','#3b82f6','#10b981'];
        foreach ($clusterSummary as $i => $cl):
            $col = $clusterColors[$i % count($clusterColors)];
        ?>
        <div style="background:<?= $col ?>11; border:1px solid <?= $col ?>33; border-radius:12px; padding:20px;">
            <div style="font-size:1.6rem; font-weight:900; color:<?= $col ?>; margin-bottom:6px;">C<?= $cl['cluster_id'] ?></div>
            <div style="font-size:.8rem; font-weight:700; color:var(--text-primary); margin-bottom:8px;"><?= htmlspecialchars($cl['cluster_label']) ?></div>
            <div style="display:grid; gap:6px;">
                <div style="display:flex; justify-content:space-between; font-size:.75rem;">
                    <span style="color:var(--text-muted);">Anggota</span>
                    <span style="color:<?= $col ?>; font-weight:700;"><?= number_format($cl['cnt']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:.75rem;">
                    <span style="color:var(--text-muted);">Rata² Monetary</span>
                    <span style="font-weight:600;"><?= fmtM($cl['avg_monetary']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:.75rem;">
                    <span style="color:var(--text-muted);">Rata² Frequency</span>
                    <span style="font-weight:600;"><?= number_format($cl['avg_frequency'],1) ?>×</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Cluster Chart -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">🔮 Distribusi Anggota per Cluster</div>
                <span class="panel-badge" style="background:rgba(139,92,246,.15);color:#8b5cf6;border-color:rgba(139,92,246,.25);">k = <?= count($clusterSummary) ?></span>
            </div>
            <div class="chart-container" style="height:230px;"><canvas id="chartKBar"></canvas></div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">💰 Rata-rata Monetary per Cluster</div>
            </div>
            <div class="chart-container" style="height:230px;"><canvas id="chartKMon"></canvas></div>
        </div>
    </div>

    <!-- Algorithm note -->
    <div class="panel">
        <div class="panel-header"><div class="panel-title">⚙️ Detail Algoritma K-Means (k=<?= count($clusterSummary) ?>)</div></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; padding:4px 0 8px;">
            <div>
                <p style="font-size:.83rem; color:var(--text-secondary); line-height:1.7; margin:0 0 12px;">
                    K-Means Clustering mengelompokkan <strong><?= number_format($clusterTotal) ?> pelanggan</strong> ke dalam
                    <strong>k=<?= count($clusterSummary) ?> cluster</strong> berdasarkan vektor fitur RFM (Recency, Frequency, Monetary).
                </p>
                <div style="font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.25); padding:14px; border-radius:8px; color:#c4b5fd; line-height:1.8;">
                    Jarak Euclidean:<br>
                    d(x,μ) = √[ (R-R̄)² + (F-F̄)² + (M-M̄)² ]<br><br>
                    Centroid Update:<br>
                    μₖ = (1/|Cₖ|) × Σ xᵢ  ∀ xᵢ ∈ Cₖ
                </div>
            </div>
            <div>
                <h4 style="font-size:.8rem; font-weight:700; color:var(--text-muted); margin:0 0 10px; letter-spacing:.05em;">LANGKAH ALGORITMA</h4>
                <?php
                $steps = [
                    'Normalisasi fitur R, F, M ke skala [0,1] (Min-Max Scaling)',
                    'Inisialisasi k='.count($clusterSummary).' centroid secara acak (atau K-Means++)',
                    'Hitung jarak Euclidean setiap pelanggan ke tiap centroid',
                    'Tugaskan pelanggan ke cluster dengan centroid terdekat',
                    'Hitung ulang posisi centroid dari rata-rata anggota cluster',
                    'Ulangi langkah 3–5 hingga konvergen (δ centroid < ε)',
                ];
                foreach ($steps as $i => $s): ?>
                <div style="display:flex; gap:10px; margin-bottom:8px; align-items:flex-start;">
                    <span style="min-width:22px; height:22px; border-radius:50%; background:rgba(139,92,246,.2); color:#8b5cf6; font-size:.72rem; font-weight:700; display:flex; align-items:center; justify-content:center;"><?= $i+1 ?></span>
                    <span style="font-size:.78rem; color:var(--text-secondary); line-height:1.5;"><?= $s ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 4 — ASSOCIATION RULES
════════════════════════════════════════════════════════════ -->
<div id="tab-assoc" class="tab-content" style="display:none;">
    <!-- Metric Cards -->
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
        <div style="background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.2); border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:6px;">📏</div>
            <div style="font-size:.75rem; color:#f59e0b; font-weight:700; letter-spacing:.05em; margin-bottom:4px;">SUPPORT</div>
            <div style="font-size:.82rem; color:var(--text-secondary); line-height:1.6;">Seberapa sering itemset {A,B} muncul dalam semua transaksi</div>
            <div style="margin-top:10px; font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.2); padding:8px; border-radius:6px; color:#fcd34d;">
                supp(A→B) = freq(A∪B) / N
            </div>
        </div>
        <div style="background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.2); border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:6px;">🎯</div>
            <div style="font-size:.75rem; color:#f59e0b; font-weight:700; letter-spacing:.05em; margin-bottom:4px;">CONFIDENCE</div>
            <div style="font-size:.82rem; color:var(--text-secondary); line-height:1.6;">Jika A dibeli, seberapa besar kemungkinan B juga dibeli P(B|A)</div>
            <div style="margin-top:10px; font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.2); padding:8px; border-radius:6px; color:#fcd34d;">
                conf(A→B) = freq(A∪B) / freq(A)
            </div>
        </div>
        <div style="background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.2); border-radius:12px; padding:20px;">
            <div style="font-size:1.3rem; margin-bottom:6px;">🚀</div>
            <div style="font-size:.75rem; color:#f59e0b; font-weight:700; letter-spacing:.05em; margin-bottom:4px;">LIFT</div>
            <div style="font-size:.82rem; color:var(--text-secondary); line-height:1.6;">Lift > 1 = asosiasi signifikan, semakin tinggi semakin kuat</div>
            <div style="margin-top:10px; font-family:monospace; font-size:.78rem; background:rgba(0,0,0,.2); padding:8px; border-radius:6px; color:#fcd34d;">
                lift(A→B) = conf(A→B) / supp(B)
            </div>
        </div>
    </div>

    <?php if (!empty($topRules)): ?>
    <!-- Rules Table -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">📋 Top 10 Association Rules (Lift Tertinggi)</div>
                <div class="panel-subtitle">Total <?= number_format($ruleCount) ?> aturan tersimpan di database</div>
            </div>
            <a href="?page=mining" class="btn btn-sm btn-secondary">Lihat Semua →</a>
        </div>
        <div class="table-wrapper">
            <table id="assocTable">
                <thead><tr>
                    <th>#</th><th>Antecedent (A)</th><th>Consequent (B)</th>
                    <th>Support</th><th>Confidence</th><th>Lift</th><th>Kekuatan</th>
                </tr></thead>
                <tbody>
                <?php foreach ($topRules as $i => $r):
                    $lift = (float)$r['lift'];
                    $str  = $lift >= 2 ? ['🔥 Sangat Kuat','#10b981'] : ($lift >= 1.5 ? ['⚡ Kuat','#14b8a6'] : ['✓ Moderate','#64748b']);
                ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td style="font-size:.8rem;"><?= htmlspecialchars($r['antecedent']) ?></td>
                    <td style="font-size:.8rem;color:#f59e0b;"><?= htmlspecialchars($r['consequent']) ?></td>
                    <td><?= number_format((float)$r['support']*100,2) ?>%</td>
                    <td style="color:#818cf8;font-weight:600;"><?= number_format((float)$r['confidence']*100,1) ?>%</td>
                    <td style="color:#f59e0b;font-weight:800;"><?= number_format($lift,3) ?></td>
                    <td><span style="color:<?= $str[1] ?>;font-size:.78rem;font-weight:600;"><?= $str[0] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="panel">
        <div class="empty-state">
            <p>⛏️ Belum ada association rules tersimpan.</p>
            <p style="font-size:.82rem;color:var(--text-muted);">Jalankan script Apriori di menu <a href="?page=mining" style="color:var(--accent-teal);">Market Basket</a> untuk mengisi tabel.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 5 — RINGKASAN ALGORITMA
════════════════════════════════════════════════════════════ -->
<div id="tab-summary" class="tab-content" style="display:none;">
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Ringkasan Semua Algoritma Data Mining</div>
            <span class="panel-badge" style="background:rgba(244,63,94,.12);color:#f43f5e;border-color:rgba(244,63,94,.2);">4 Algoritma</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr>
                    <th>Algoritma</th><th>Tujuan</th><th>Input Fitur</th>
                    <th>Output</th><th>Tabel MySQL</th><th>Status</th>
                </tr></thead>
                <tbody>
                    <tr>
                        <td><strong style="color:#818cf8;">📈 RFM Segmentation</strong></td>
                        <td style="font-size:.82rem;">Segmentasi pelanggan berdasarkan perilaku belanja</td>
                        <td style="font-family:monospace;font-size:.75rem;">recency, frequency, monetary</td>
                        <td style="font-size:.82rem;">Skor 1–5 + label segmen</td>
                        <td><code style="font-size:.75rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;">analytics_rfm</code></td>
                        <td><span style="color:#10b981;font-weight:700;">✓ <?= number_format($rfmTotal) ?> baris</span></td>
                    </tr>
                    <tr>
                        <td><strong style="color:#14b8a6;">📊 Pareto ABC</strong></td>
                        <td style="font-size:.82rem;">Klasifikasi produk berdasarkan kontribusi revenue</td>
                        <td style="font-family:monospace;font-size:.75rem;">total_revenue, cumulative_%</td>
                        <td style="font-size:.82rem;">Kelas A (80%), B (95%), C (100%)</td>
                        <td><code style="font-size:.75rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;">mining_product_abc</code></td>
                        <td><span style="color:#10b981;font-weight:700;">✓ <?= number_format($abcTotal) ?> baris</span></td>
                    </tr>
                    <tr>
                        <td><strong style="color:#8b5cf6;">🔮 K-Means (k=<?= count($clusterSummary) ?>)</strong></td>
                        <td style="font-size:.82rem;">Pengelompokan pelanggan ke cluster homogen</td>
                        <td style="font-family:monospace;font-size:.75rem;">R_norm, F_norm, M_norm</td>
                        <td style="font-size:.82rem;"><?= count($clusterSummary) ?> cluster dengan centroid</td>
                        <td><code style="font-size:.75rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;">clustering_customer_groups</code></td>
                        <td><span style="color:#10b981;font-weight:700;">✓ <?= number_format($clusterTotal) ?> baris</span></td>
                    </tr>
                    <tr>
                        <td><strong style="color:#f59e0b;">⛏️ Apriori / MBA</strong></td>
                        <td style="font-size:.82rem;">Menemukan pola produk sering dibeli bersamaan</td>
                        <td style="font-family:monospace;font-size:.75rem;">transaction_items (basket)</td>
                        <td style="font-size:.82rem;">Rules: Support, Confidence, Lift</td>
                        <td><code style="font-size:.75rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;">mining_association_rules</code></td>
                        <td><span style="color:<?= $ruleCount>0?'#10b981':'#f43f5e' ?>;font-weight:700;"><?= $ruleCount>0 ? '✓ '.$ruleCount.' rules' : '⚠ Belum dijalankan' ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SCRIPTS: Tab Switcher + Charts
════════════════════════════════════════════════════════════ -->
<style>
.tab-btn { transition: color .2s, border-color .2s; }
.tab-btn:hover { color: var(--text-primary) !important; }
.tab-active { color: #818cf8 !important; border-bottom: 3px solid #818cf8 !important; }
</style>
<script>
function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
    document.getElementById(id).style.display = '';
    document.getElementById('btn-' + id).classList.add('tab-active');
}

const chartOpts = (title) => ({
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 10 }, boxWidth: 12 } },
        title: { display: false }
    }
});

// ── RFM Charts ──
<?php if (!empty($rfmSegments)): ?>
const rfmLabels = <?= json_encode(array_column($rfmSegments,'rfm_segment')) ?>;
const rfmCounts = <?= json_encode(array_column($rfmSegments,'cnt')) ?>;
const rfmColors = ['#14b8a6','#818cf8','#f59e0b','#f43f5e','#10b981','#8b5cf6','#3b82f6','#ec4899','#64748b'];

new Chart(document.getElementById('chartRfmDonut'), {
    type: 'doughnut',
    data: { labels: rfmLabels, datasets: [{ data: rfmCounts, backgroundColor: rfmColors, borderColor:'#1e2535', borderWidth:2 }] },
    options: { ...chartOpts(), cutout:'60%', plugins: { legend:{ position:'bottom', labels:{ color:'#94a3b8', font:{size:10}, boxWidth:10 } } } }
});
new Chart(document.getElementById('chartRfmBar'), {
    type: 'bar',
    data: { labels: rfmLabels, datasets: [{ label:'Pelanggan', data: rfmCounts, backgroundColor: rfmColors, borderRadius:5 }] },
    options: { ...chartOpts(), indexAxis:'y', scales: {
        x: { ticks:{color:'#64748b'}, grid:{color:'rgba(255,255,255,.04)'} },
        y: { ticks:{color:'#94a3b8',font:{size:10}}, grid:{display:false} }
    }, plugins:{ legend:{ display:false } } }
});
<?php endif; ?>

// ── ABC Charts ──
<?php if (!empty($abcSummary)): ?>
const abcLabels = <?= json_encode(array_map(fn($r)=>'Kelas '.$r['abc_class'],$abcSummary)) ?>;
const abcCounts = <?= json_encode(array_column($abcSummary,'cnt')) ?>;
const abcRevs   = <?= json_encode(array_map(fn($r)=>round($r['total_rev']),$abcSummary)) ?>;
const abcColors = ['#14b8a6','#f59e0b','#64748b'];

new Chart(document.getElementById('chartAbcPie'), {
    type: 'pie',
    data: { labels: abcLabels, datasets: [{ data: abcCounts, backgroundColor: abcColors, borderColor:'#1e2535', borderWidth:2 }] },
    options: { ...chartOpts(), plugins:{ legend:{ position:'bottom', labels:{ color:'#94a3b8',font:{size:10} } } } }
});
new Chart(document.getElementById('chartAbcRev'), {
    type: 'bar',
    data: { labels: abcLabels, datasets: [{ label:'Revenue (£)', data: abcRevs, backgroundColor: abcColors, borderRadius:6 }] },
    options: { ...chartOpts(), plugins:{ legend:{display:false} }, scales: {
        x: { ticks:{color:'#94a3b8'}, grid:{display:false} },
        y: { ticks:{color:'#64748b', callback: v => '£'+(v>=1e6?(v/1e6).toFixed(1)+'M':(v>=1000?(v/1000).toFixed(0)+'K':v)) }, grid:{color:'rgba(255,255,255,.04)'} }
    } }
});
<?php endif; ?>

// ── K-Means Charts ──
<?php if (!empty($clusterSummary)): ?>
const kLabels  = <?= json_encode(array_column($clusterSummary,'cluster_label')) ?>;
const kCounts  = <?= json_encode(array_column($clusterSummary,'cnt')) ?>;
const kMons    = <?= json_encode(array_column($clusterSummary,'avg_monetary')) ?>;
const kColors  = ['#8b5cf6','#14b8a6','#f43f5e','#f59e0b'];

new Chart(document.getElementById('chartKBar'), {
    type: 'bar',
    data: { labels: kLabels, datasets: [{ label:'Anggota', data: kCounts, backgroundColor: kColors, borderRadius:6 }] },
    options: { ...chartOpts(), plugins:{legend:{display:false}}, scales: {
        x: { ticks:{color:'#94a3b8',font:{size:10}}, grid:{display:false} },
        y: { ticks:{color:'#64748b'}, grid:{color:'rgba(255,255,255,.04)'} }
    } }
});
new Chart(document.getElementById('chartKMon'), {
    type: 'bar',
    data: { labels: kLabels, datasets: [{ label:'Avg Monetary (£)', data: kMons, backgroundColor: kColors, borderRadius:6 }] },
    options: { ...chartOpts(), plugins:{legend:{display:false}}, scales: {
        x: { ticks:{color:'#94a3b8',font:{size:10}}, grid:{display:false} },
        y: { ticks:{color:'#64748b', callback: v => '£'+(v>=1000?(v/1000).toFixed(1)+'K':v)}, grid:{color:'rgba(255,255,255,.04)'} }
    } }
});
<?php endif; ?>
</script>

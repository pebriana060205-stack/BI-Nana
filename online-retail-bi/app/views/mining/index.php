<?php
// ============================================================
//  Mining View — Market Basket Analysis (Association Rules)
// ============================================================

$db = getDB();

// Handle Run Apriori action (POST)
$aprioriMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_apriori'])) {
    $minSupport    = max(0.01, min(0.5, (float)($_POST['min_support'] ?? 0.02)));
    $minConfidence = max(0.1,  min(1.0, (float)($_POST['min_confidence'] ?? 0.30)));
    $minLift       = max(1.0,  (float)($_POST['min_lift'] ?? 1.0));

    // Load baskets
    $rows = $db->query("
        SELECT ti.transaction_id, ti.stock_code, p.description
        FROM transaction_items ti
        LEFT JOIN products p ON p.stock_code = ti.stock_code
        WHERE ti.quantity > 0
    ")->fetchAll();

    $baskets = []; $itemCount = []; $itemNames = [];
    foreach ($rows as $r) {
        $baskets[$r['transaction_id']][] = $r['stock_code'];
        $itemNames[$r['stock_code']] = $r['description'] ?? $r['stock_code'];
    }
    $totalTx = count($baskets);

    // 1-itemset frequency
    foreach ($baskets as $items) {
        foreach (array_unique($items) as $item) {
            $itemCount[$item] = ($itemCount[$item] ?? 0) + 1;
        }
    }
    $freqItems = array_filter(
        array_map(fn($c) => $c / $totalTx, $itemCount),
        fn($s) => $s >= $minSupport
    );

    // 2-itemset pairs
    $pairCount = [];
    foreach ($baskets as $items) {
        $fi = array_values(array_filter(array_unique($items), fn($i) => isset($freqItems[$i])));
        $n  = count($fi);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $key = $fi[$i] < $fi[$j] ? "{$fi[$i]}|||{$fi[$j]}" : "{$fi[$j]}|||{$fi[$i]}";
                $pairCount[$key] = ($pairCount[$key] ?? 0) + 1;
            }
        }
    }

    // Generate rules
    $rules = [];
    foreach ($pairCount as $key => $cnt) {
        $supp = $cnt / $totalTx;
        if ($supp < $minSupport) continue;
        [$a, $b] = explode('|||', $key);

        $cAB = $cnt / $itemCount[$a]; $lAB = $cAB / $freqItems[$b];
        if ($cAB >= $minConfidence && $lAB >= $minLift)
            $rules[] = [mb_substr($itemNames[$a]??$a,0,95), mb_substr($itemNames[$b]??$b,0,95), round($supp,4), round($cAB,4), round($lAB,4)];

        $cBA = $cnt / $itemCount[$b]; $lBA = $cBA / $freqItems[$a];
        if ($cBA >= $minConfidence && $lBA >= $minLift)
            $rules[] = [mb_substr($itemNames[$b]??$b,0,95), mb_substr($itemNames[$a]??$a,0,95), round($supp,4), round($cBA,4), round($lBA,4)];
    }

    $db->exec("DELETE FROM mining_association_rules");
    if (!empty($rules)) {
        $stmt = $db->prepare("INSERT INTO mining_association_rules (antecedent, consequent, support, confidence, lift) VALUES (?,?,?,?,?)");
        foreach ($rules as $r) $stmt->execute($r);
    }
    $aprioriMsg = ['type' => 'success', 'count' => count($rules), 'tx' => $totalTx];
    header("Location: ?page=mining&apriori_done=1&rules=" . count($rules));
    exit;
}

// Ambil hasil association rules
$rules = $db->query("
    SELECT * FROM mining_association_rules 
    ORDER BY lift DESC, confidence DESC
    LIMIT 200
")->fetchAll();

$ruleCount = $db->query("SELECT COUNT(*) FROM mining_association_rules")->fetchColumn();
$topLift   = $db->query("SELECT MAX(lift) FROM mining_association_rules")->fetchColumn();
$avgConf   = $db->query("SELECT AVG(confidence)*100 FROM mining_association_rules")->fetchColumn();
?>

<!-- Stats Bar -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px;">
    <div class="stat-card" style="border-color:rgba(245,158,11,.25);">
        <div class="stat-value" style="color:#f59e0b;"><?= number_format($ruleCount) ?></div>
        <div class="stat-label">Total Rules</div>
    </div>
    <div class="stat-card" style="border-color:rgba(16,185,129,.25);">
        <div class="stat-value" style="color:#10b981;"><?= $ruleCount > 0 ? number_format($topLift,2) : '—' ?></div>
        <div class="stat-label">Lift Tertinggi</div>
    </div>
    <div class="stat-card" style="border-color:rgba(129,140,248,.25);">
        <div class="stat-value" style="color:#818cf8;"><?= $ruleCount > 0 ? number_format($avgConf,1).'%' : '—' ?></div>
        <div class="stat-label">Avg Confidence</div>
    </div>
    <div class="stat-card" style="border-color:rgba(20,184,166,.25);">
        <div class="stat-value" style="color:#14b8a6;"><?= $db->query("SELECT COUNT(DISTINCT transaction_id) FROM transaction_items")->fetchColumn() ?></div>
        <div class="stat-label">Total Transaksi</div>
    </div>
</div>

<?php if (isset($_GET['apriori_done'])): ?>
<div class="alert alert-success" style="margin-bottom:20px;">
    ✅ Apriori selesai! <strong><?= number_format($_GET['rules']) ?> aturan asosiasi</strong> berhasil digenerate dan disimpan ke database.
</div>
<?php endif; ?>

<!-- Control Panel: Run Apriori -->
<div class="panel" style="margin-bottom:24px; border-color:rgba(245,158,11,.25);">
    <div class="panel-header">
        <div>
            <div class="panel-title">⚙️ Jalankan Algoritma Apriori</div>
            <div class="panel-subtitle">Generate ulang association rules dari data transaksi dengan parameter kustom</div>
        </div>
        <span class="panel-badge" style="background:rgba(245,158,11,.12);color:#f59e0b;border-color:rgba(245,158,11,.25);">PHP Apriori</span>
    </div>
    <form method="POST" style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="run_apriori" value="1">
        <div>
            <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:5px;">Min Support (%)</label>
            <input type="number" name="min_support" step="0.01" min="0.01" max="0.5" value="0.02"
                   style="width:120px;padding:8px 12px;background:var(--bg-primary);border:1px solid var(--border-color);color:var(--text-primary);border-radius:7px;font-size:.85rem;">
            <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;">Nilai: 0.02 = 2%</div>
        </div>
        <div>
            <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:5px;">Min Confidence</label>
            <input type="number" name="min_confidence" step="0.05" min="0.10" max="1.0" value="0.30"
                   style="width:120px;padding:8px 12px;background:var(--bg-primary);border:1px solid var(--border-color);color:var(--text-primary);border-radius:7px;font-size:.85rem;">
            <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;">Nilai: 0.30 = 30%</div>
        </div>
        <div>
            <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:5px;">Min Lift</label>
            <input type="number" name="min_lift" step="0.5" min="1.0" max="50" value="1.0"
                   style="width:120px;padding:8px 12px;background:var(--bg-primary);border:1px solid var(--border-color);color:var(--text-primary);border-radius:7px;font-size:.85rem;">
            <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;">Minimal 1.0</div>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" id="btnApriori" onclick="this.textContent='⏳ Memproses...';this.disabled=true;">
                ⛏️ Run Apriori
            </button>
        </div>
    </form>
    <div style="margin-top:14px;padding:10px 14px;background:rgba(0,0,0,.2);border-radius:8px;font-size:.78rem;color:var(--text-muted);line-height:1.7;">
        <strong style="color:var(--text-secondary);">ℹ️ Rumus:</strong>
        &nbsp; <code>Support(A→B) = freq(A∪B)/N</code>
        &nbsp;|&nbsp; <code>Confidence(A→B) = freq(A∪B)/freq(A)</code>
        &nbsp;|&nbsp; <code>Lift(A→B) = Confidence/Support(B)</code>
        &nbsp;·&nbsp; <span style="color:#10b981;">Lift &gt; 1 = asosiasi positif signifikan</span>
    </div>
</div>

<!-- Rules Table -->
<?php if (!empty($rules)): ?>
<div class="panel">
    <div class="panel-header">
        <div>
            <div class="panel-title">📋 Association Rules (Top 200 by Lift)</div>
            <div class="panel-subtitle">Produk-produk yang sering dibeli bersama oleh pelanggan Online Retail</div>
        </div>
        <span class="panel-badge"><?= number_format($ruleCount) ?> total rules</span>
    </div>
    <div class="table-wrapper">
        <table id="rulesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Antecedent (A) — Jika beli ini</th>
                    <th>Consequent (B) — Kemungkinan beli ini</th>
                    <th>Support</th>
                    <th>Confidence</th>
                    <th>Lift ↓</th>
                    <th>Kekuatan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $i => $r):
                    $lift = (float)$r['lift'];
                    if ($lift >= 10)      { $str = '🔥 Sangat Kuat'; $sc = '#10b981'; }
                    elseif ($lift >= 5)   { $str = '⚡ Kuat';        $sc = '#14b8a6'; }
                    elseif ($lift >= 2)   { $str = '✅ Baik';         $sc = '#818cf8'; }
                    else                  { $str = '○ Lemah';         $sc = '#64748b'; }
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.8rem;"><?= $i+1 ?></td>
                    <td style="font-size:.8rem;max-width:220px;"><?= htmlspecialchars($r['antecedent']) ?></td>
                    <td style="font-size:.8rem;max-width:220px;color:#f59e0b;"><?= htmlspecialchars($r['consequent']) ?></td>
                    <td style="font-size:.8rem;"><?= number_format((float)$r['support']*100,2) ?>%</td>
                    <td style="color:#818cf8;font-weight:600;"><?= number_format((float)$r['confidence']*100,1) ?>%</td>
                    <td style="font-weight:800;color:<?= $sc ?>;font-size:.9rem;"><?= number_format($lift,3) ?></td>
                    <td>
                        <span style="color:<?= $sc ?>;font-size:.75rem;font-weight:600;"><?= $str ?></span>
                        <div style="width:70px;height:5px;background:rgba(255,255,255,.06);border-radius:3px;margin-top:3px;">
                            <div style="width:<?= min(($lift/25)*100,100) ?>%;height:100%;background:<?= $sc ?>;border-radius:3px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="panel">
    <div class="empty-state">
        <span class="empty-state-icon">⛏️</span>
        <h3>Belum ada Association Rules</h3>
        <p>Klik tombol <strong>"⛏️ Run Apriori"</strong> di panel atas untuk mengenerate rules dari data transaksi.</p>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    if ($('#rulesTable tbody tr').length > 0) {
        $('#rulesTable').DataTable({
            pageLength: 25,
            order: [[5, 'desc']],
            language: {
                search: 'Cari:', paginate: { previous: '←', next: '→' },
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ rules'
            }
        });
    }
});
</script>

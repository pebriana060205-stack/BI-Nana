<?php
// ============================================================
//  Mining View — Market Basket Analysis (Association Rules)
// ============================================================

$db = getDB();

// Ambil hasil association rules
$rules = $db->query("
    SELECT * FROM mining_association_rules 
    ORDER BY lift DESC, confidence DESC
    LIMIT 100
")->fetchAll();

$ruleCount = $db->query("SELECT COUNT(*) FROM mining_association_rules")->fetchColumn();
?>

<div class="panel" style="margin-bottom:24px;border-color:rgba(99,102,241,.3);">
    <div class="panel-header">
        <div>
            <div class="panel-title">⛏️ Data Mining — Market Basket Analysis</div>
            <div class="panel-subtitle">
                Association Rules: Menemukan produk yang sering dibeli bersama.
                Saat ini tersimpan <strong style="color:var(--accent-indigo);"><?= number_format($ruleCount) ?> aturan asosiasi</strong>.
            </div>
        </div>
    </div>
    <div class="alert alert-info" style="margin-bottom:0;">
        <div>
            <strong>ℹ️ Tentang Market Basket Analysis:</strong><br>
            <ul style="margin-top:8px;font-size:.8rem;list-style:disc;padding-left:18px;line-height:1.8;">
                <li><strong>Support</strong>: Seberapa sering A dan B muncul bersama dalam semua transaksi</li>
                <li><strong>Confidence</strong>: Jika A dibeli, seberapa besar kemungkinan B juga dibeli (P(B|A))</li>
                <li><strong>Lift > 1</strong>: Asosiasi signifikan; semakin tinggi = semakin kuat keterkaitannya</li>
            </ul>
            <br>
            <strong>Catatan:</strong> Untuk menghasilkan Association Rules, dibutuhkan library Python (mlxtend/apriori) atau 
            implementasi Apriori berbasis SQL. Tambahkan script <code>helpers/AssociationRules.php</code> untuk menghitung dan mengisi tabel <code>mining_association_rules</code>.
            Anda bisa <a href="https://github.com/asaini/Apriori-Algorithm-PHP" target="_blank" style="color:var(--accent-indigo);">referensi implementasi PHP Apriori di sini</a>.
        </div>
    </div>
</div>

<?php if (!empty($rules)): ?>
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📋 Tabel Association Rules</div>
        <span class="panel-badge">Top <?= count($rules) ?> Rules</span>
    </div>
    <div class="table-wrapper">
        <table id="rulesTable">
            <thead>
                <tr>
                    <th>Antecedent (A)</th>
                    <th>Consequent (B)</th>
                    <th>Support</th>
                    <th>Confidence</th>
                    <th>Lift</th>
                    <th>Kekuatan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $r): ?>
                <tr>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:2px 8px;border-radius:4px;"><?= htmlspecialchars($r['antecedent']) ?></code></td>
                    <td><code style="font-size:.78rem;background:rgba(99,102,241,.1);padding:2px 8px;border-radius:4px;color:var(--accent-indigo);"><?= htmlspecialchars($r['consequent']) ?></code></td>
                    <td><?= number_format($r['support']*100, 2) ?>%</td>
                    <td><?= number_format($r['confidence']*100, 1) ?>%</td>
                    <td style="font-weight:700;color:<?= $r['lift'] >= 2 ? 'var(--accent-emerald)' : ($r['lift'] >= 1.5 ? 'var(--accent-amber)' : 'var(--text-secondary)') ?>;">
                        <?= number_format($r['lift'], 3) ?>
                    </td>
                    <td>
                        <div class="progress-bar-wrap" style="width:80px;">
                            <div class="progress-bar-fill" style="width:<?= min(($r['lift']-1)*20, 100) ?>%"></div>
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
        <p>Jalankan script Apriori untuk mengisi tabel <code>mining_association_rules</code>.<br>
        Lihat panduan di panel di atas.</p>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#rulesTable').DataTable({
        pageLength: 25, order: [[4,'desc']],
        language: { search:'Cari:', paginate:{previous:'←',next:'→'} }
    });
});
</script>

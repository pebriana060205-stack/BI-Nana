<?php
// ============================================================
//  Products View — Master Data Management (CRUD) & ABC Analysis
// ============================================================

require_once BASE_PATH . '/helpers/ABC.php';

$db  = getDB();
$abc = new ABC();
$crudAlert = null;

// ------------------------------------------------------------
//  POST Handler: Handle CRUD (Create, Update, Delete) & ABC
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crud_action'])) {
        $action = $_POST['crud_action'];
        
        if ($action === 'create') {
            $stockCode   = trim($_POST['stock_code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $unitPrice   = (float)($_POST['unit_price'] ?? 0);
            $priceTier   = $_POST['price_tier'] ?? 'Mid';

            if (empty($stockCode) || empty($description)) {
                $crudAlert = ['type' => 'error', 'msg' => 'Kode produk dan deskripsi wajib diisi.'];
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO products (stock_code, description, unit_price, price_tier) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$stockCode, $description, $unitPrice, $priceTier]);
                    $crudAlert = ['type' => 'success', 'msg' => "Produk '$stockCode' berhasil ditambahkan!"];
                } catch (PDOException $e) {
                    $crudAlert = ['type' => 'error', 'msg' => 'Gagal menambah produk: ' . $e->getMessage()];
                }
            }
        } elseif ($action === 'update') {
            $stockCode   = trim($_POST['stock_code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $unitPrice   = (float)($_POST['unit_price'] ?? 0);
            $priceTier   = $_POST['price_tier'] ?? 'Mid';

            try {
                $stmt = $db->prepare("UPDATE products SET description = ?, unit_price = ?, price_tier = ? WHERE stock_code = ?");
                $stmt->execute([$description, $unitPrice, $priceTier, $stockCode]);
                $crudAlert = ['type' => 'success', 'msg' => "Produk '$stockCode' berhasil diperbarui!"];
            } catch (PDOException $e) {
                $crudAlert = ['type' => 'error', 'msg' => 'Gagal memperbarui produk: ' . $e->getMessage()];
            }
        } elseif ($action === 'delete') {
            $stockCode = trim($_POST['stock_code'] ?? '');
            try {
                // Delete from mining_product_abc first if exists
                $db->prepare("DELETE FROM mining_product_abc WHERE stock_code = ?")->execute([$stockCode]);
                $stmt = $db->prepare("DELETE FROM products WHERE stock_code = ?");
                $stmt->execute([$stockCode]);
                $crudAlert = ['type' => 'success', 'msg' => "Produk '$stockCode' berhasil dihapus!"];
            } catch (PDOException $e) {
                $crudAlert = ['type' => 'error', 'msg' => 'Gagal menghapus produk: ' . $e->getMessage()];
            }
        }
    } elseif (isset($_POST['calc_abc'])) {
        $abcResult = $abc->calculate();
    }
}

$abcSummary   = $abc->getSummary();
$totalProds   = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$avgPrice     = $db->query("SELECT AVG(unit_price) FROM products")->fetchColumn();
$premiumCount = $db->query("SELECT COUNT(*) FROM products WHERE price_tier='Premium'")->fetchColumn();
$allProducts  = $db->query("
    SELECT p.stock_code, p.description, p.unit_price, p.price_tier, p.is_active,
           COALESCE(m.abc_class, 'N/A') as abc_class, COALESCE(m.total_revenue, 0) as total_revenue
    FROM products p
    LEFT JOIN mining_product_abc m ON p.stock_code = m.stock_code
    ORDER BY total_revenue DESC LIMIT 300
")->fetchAll();
?>

<!-- Alert Feedback CRUD -->
<?php if ($crudAlert): ?>
<div class="alert alert-<?= $crudAlert['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 24px;">
    <?= $crudAlert['type'] === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($crudAlert['msg']) ?>
</div>
<?php endif; ?>

<!-- KPI Cards -->
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

<!-- Header Action: CRUD & ABC Analysis -->
<div class="panel" style="margin-bottom: 24px; border-color: rgba(245,158,11,.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <div class="panel-title">📦 Kelola Data Produk (CRUD) & Data Mining ABC</div>
            <div class="panel-subtitle">Tambah, edit, hapus produk, atau jalankan klasifikasi Pareto 80/20</div>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-secondary" onclick="openCreateModal()">
                ➕ Tambah Produk
            </button>
            <form method="POST" style="display:inline;">
                <button type="submit" name="calc_abc" class="btn btn-primary">
                    ⚙️ Hitung ABC Analysis
                </button>
            </form>
        </div>
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
<?php endif; ?>

<!-- Tabel Master Data Produk dengan CRUD -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📦 Master Data Produk & Hasil ABC</div>
        <span class="badge badge-a">Fitur CRUD Aktif</span>
    </div>
    <div class="table-wrapper">
        <table id="productCrudTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kode Produk</th>
                    <th>Deskripsi</th>
                    <th>Harga Satuan</th>
                    <th>Tier Harga</th>
                    <th>Total Revenue</th>
                    <th>Kelas ABC</th>
                    <th>Aksi CRUD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allProducts as $i => $p): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:3px 8px;border-radius:4px;"><?= htmlspecialchars($p['stock_code']) ?></code></td>
                    <td style="font-size:.85rem;"><?= htmlspecialchars(mb_substr($p['description'], 0, 45)) ?></td>
                    <td style="font-weight:600;">£<?= number_format($p['unit_price'], 2) ?></td>
                    <td>
                        <span class="badge <?= $p['price_tier'] === 'Premium' ? 'badge-vip' : ($p['price_tier'] === 'Mid' ? 'badge-regular' : 'badge-new') ?>">
                            <?= htmlspecialchars($p['price_tier']) ?>
                        </span>
                    </td>
                    <td style="color:var(--accent-teal);font-weight:600;">£<?= number_format($p['total_revenue']) ?></td>
                    <td>
                        <?php if ($p['abc_class'] !== 'N/A'): ?>
                            <span class="badge badge-<?= strtolower($p['abc_class']) ?>"><?= $p['abc_class'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="btn btn-sm btn-secondary" 
                                    onclick="openEditModal('<?= htmlspecialchars($p['stock_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['description'], ENT_QUOTES) ?>', <?= (float)$p['unit_price'] ?>, '<?= htmlspecialchars($p['price_tier'], ENT_QUOTES) ?>')">
                                ✏️ Edit
                            </button>
                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');" style="display:inline;">
                                <input type="hidden" name="crud_action" value="delete">
                                <input type="hidden" name="stock_code" value="<?= htmlspecialchars($p['stock_code'], ENT_QUOTES) ?>">
                                <button type="submit" class="btn btn-sm btn-danger" style="background:rgba(244,63,94,.15);color:var(--accent-rose);border:1px solid rgba(244,63,94,.3);">
                                    🗑️ Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal CRUD Form (Create / Update) -->
<div id="crudModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
    <div class="panel" style="width:100%; max-width:480px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; padding:24px; position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 id="modalTitle" style="margin:0; font-size:1.1rem; color:var(--text-primary);">➕ Tambah Produk Baru</h3>
            <button type="button" onclick="closeCrudModal()" style="background:none; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer;">✖</button>
        </div>
        <form method="POST" id="crudForm">
            <input type="hidden" name="crud_action" id="modalAction" value="create">
            
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Kode Produk (StockCode)</label>
                <input type="text" name="stock_code" id="modalStockCode" required style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
            </div>
            
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Deskripsi Produk</label>
                <input type="text" name="description" id="modalDescription" required style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Harga Satuan (£)</label>
                <input type="number" step="0.01" name="unit_price" id="modalUnitPrice" required style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:.8rem; color:var(--text-muted); margin-bottom:6px;">Tier Harga</label>
                <select name="price_tier" id="modalPriceTier" style="width:100%; padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-primary); border-radius:6px; font-size:.875rem;">
                    <option value="Low">Low (< £2)</option>
                    <option value="Mid" selected>Mid (£2 - £10)</option>
                    <option value="Premium">Premium (> £10)</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeCrudModal()" class="btn btn-secondary">Batal</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#productCrudTable').DataTable({
        pageLength: 25,
        order: [[5,'desc']],
        language: { search: 'Cari Produk:', info: 'Menampilkan _START_–_END_ dari _TOTAL_ produk', paginate: { previous: '←', next: '→' } }
    });
});

function openCreateModal() {
    $('#modalTitle').text('➕ Tambah Produk Baru');
    $('#modalAction').val('create');
    $('#modalStockCode').val('').prop('readonly', false);
    $('#modalDescription').val('');
    $('#modalUnitPrice').val('0.00');
    $('#modalPriceTier').val('Mid');
    $('#crudModal').css('display', 'flex');
}

function openEditModal(code, desc, price, tier) {
    $('#modalTitle').text('✏️ Edit Produk (' + code + ')');
    $('#modalAction').val('update');
    $('#modalStockCode').val(code).prop('readonly', true);
    $('#modalDescription').val(desc);
    $('#modalUnitPrice').val(price);
    $('#modalPriceTier').val(tier);
    $('#crudModal').css('display', 'flex');
}

function closeCrudModal() {
    $('#crudModal').css('display', 'none');
}

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

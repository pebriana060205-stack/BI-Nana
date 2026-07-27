<?php
// ============================================================
//  Input Data Center — Transaksi, Pelanggan, Produk
// ============================================================

$db = getDB();
$alert = null;

// ─────────────────────────────────────────────────────────────
//  POST Handler
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    // ── 1. Simpan Transaksi Baru ──────────────────────────────
    if ($action === 'add_transaction') {
        try {
            $invoiceNo  = trim($_POST['invoice_no'] ?? '');
            $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $countryId  = (int)($_POST['country_id'] ?? 1);
            $date       = $_POST['invoice_date'] ?? date('Y-m-d H:i:s');
            $codes      = $_POST['stock_code']   ?? [];
            $qtys       = $_POST['quantity']      ?? [];
            $prices     = $_POST['unit_price']    ?? [];

            if (empty($invoiceNo)) throw new Exception('Nomor invoice wajib diisi.');
            if (empty(array_filter($codes))) throw new Exception('Minimal 1 item produk wajib diisi.');

            // Check duplicate invoice
            $chk = $db->prepare("SELECT COUNT(*) FROM transactions WHERE invoice_no = ?");
            $chk->execute([$invoiceNo]);
            if ($chk->fetchColumn() > 0) throw new Exception("Invoice '$invoiceNo' sudah ada di database.");

            $db->beginTransaction();

            // Insert transaction header
            $totalAmt = 0; $totalItems = 0;
            $validItems = [];
            foreach ($codes as $i => $code) {
                $code  = trim($code);
                $qty   = (int)($qtys[$i] ?? 0);
                $price = (float)($prices[$i] ?? 0);
                if (empty($code) || $qty <= 0 || $price <= 0) continue;
                $subtotal = $qty * $price;
                $totalAmt  += $subtotal;
                $totalItems += $qty;
                $validItems[] = compact('code','qty','price','subtotal');
            }
            if (empty($validItems)) throw new Exception('Tidak ada item valid yang diisi.');

            $stmtTx = $db->prepare("
                INSERT INTO transactions (invoice_no, customer_id, country_id, invoice_date, total_amount, total_items, is_cancelled)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $stmtTx->execute([$invoiceNo, $customerId, $countryId, $date, $totalAmt, $totalItems]);
            $txId = $db->lastInsertId();

            // Insert items & auto-upsert products
            $stmtItem = $db->prepare("
                INSERT INTO transaction_items (transaction_id, stock_code, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtProd = $db->prepare("
                INSERT INTO products (stock_code, description, unit_price, price_tier)
                VALUES (?, ?, ?, 'standard')
                ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price)
            ");
            foreach ($validItems as $item) {
                $stmtProd->execute([$item['code'], $item['code'], $item['price']]);
                $stmtItem->execute([$txId, $item['code'], $item['qty'], $item['price'], $item['subtotal']]);
            }

            $db->commit();
            $alert = ['type'=>'success','msg'=>"✅ Invoice <strong>$invoiceNo</strong> berhasil disimpan! Total: <strong>£" . number_format($totalAmt, 2) . "</strong> | " . count($validItems) . " item."];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $alert = ['type'=>'error','msg'=>'❌ ' . $e->getMessage()];
        }
    }

    // ── 2. Simpan Pelanggan Baru ──────────────────────────────
    elseif ($action === 'add_customer') {
        try {
            $custId    = (int)trim($_POST['customer_id'] ?? 0);
            $countryId = (int)($_POST['country_id'] ?? 1);
            $segment   = trim($_POST['segment'] ?? 'Regular');

            if ($custId <= 0) throw new Exception('Customer ID wajib diisi (angka positif).');

            $chk = $db->prepare("SELECT COUNT(*) FROM customers WHERE customer_id = ?");
            $chk->execute([$custId]);
            if ($chk->fetchColumn() > 0) throw new Exception("Customer ID $custId sudah terdaftar.");

            $db->prepare("
                INSERT INTO customers (customer_id, country_id, segment, first_purchase_date, last_purchase_date, total_orders, total_spent)
                VALUES (?, ?, ?, CURDATE(), CURDATE(), 0, 0.00)
            ")->execute([$custId, $countryId, $segment]);

            $alert = ['type'=>'success','msg'=>"✅ Pelanggan ID <strong>$custId</strong> berhasil didaftarkan."];
        } catch (Exception $e) {
            $alert = ['type'=>'error','msg'=>'❌ ' . $e->getMessage()];
        }
    }

    // ── 3. Simpan Produk Baru ─────────────────────────────────
    elseif ($action === 'add_product') {
        try {
            $code  = strtoupper(trim($_POST['stock_code'] ?? ''));
            $desc  = trim($_POST['description'] ?? '');
            $price = (float)($_POST['unit_price'] ?? 0);
            $cat   = trim($_POST['category'] ?? 'General');

            if (empty($code) || empty($desc)) throw new Exception('Stock code dan deskripsi wajib diisi.');
            if ($price <= 0) throw new Exception('Harga harus lebih dari 0.');

            $chk = $db->prepare("SELECT COUNT(*) FROM products WHERE stock_code = ?");
            $chk->execute([$code]);
            if ($chk->fetchColumn() > 0) throw new Exception("Stock code '$code' sudah ada.");

            $tier = $price >= 10 ? 'premium' : ($price >= 3 ? 'standard' : 'budget');
            $db->prepare("
                INSERT INTO products (stock_code, description, category, unit_price, price_tier)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$code, $desc, $cat, $price, $tier]);

            $alert = ['type'=>'success','msg'=>"✅ Produk <strong>$code — $desc</strong> berhasil ditambahkan. Tier: <em>$tier</em>."];
        } catch (Exception $e) {
            $alert = ['type'=>'error','msg'=>'❌ ' . $e->getMessage()];
        }
    }
}

// ─────────────────────────────────────────────────────────────
//  Data untuk Dropdown
// ─────────────────────────────────────────────────────────────
$countries = $db->query("SELECT country_id, country_name FROM countries ORDER BY country_name")->fetchAll();
$customers = $db->query("SELECT customer_id FROM customers ORDER BY customer_id LIMIT 300")->fetchAll();
$products  = $db->query("SELECT stock_code, description, unit_price FROM products ORDER BY stock_code LIMIT 500")->fetchAll();

// Stats
$txCount   = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$custCount = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$prodCount = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lastInv   = $db->query("SELECT invoice_no, invoice_date, total_amount FROM transactions ORDER BY transaction_id DESC LIMIT 1")->fetch();
?>

<!-- ═══════════════ HEADER ═══════════════════════════════════ -->
<div style="background: linear-gradient(135deg,rgba(20,184,166,.15),rgba(99,102,241,.1));
            border:1px solid rgba(20,184,166,.25); border-radius:16px; padding:24px 28px; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <div style="font-size:1.8rem; margin-bottom:6px;">📝</div>
            <h1 style="font-size:1.5rem; font-weight:800; color:var(--text-primary); margin:0 0 4px;">Input Data Manual</h1>
            <p style="font-size:.82rem; color:var(--text-muted); margin:0;">
                Tambah transaksi, pelanggan, atau produk baru langsung ke database tanpa import CSV.
            </p>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:12px 16px;">
                <div style="font-size:1.3rem; font-weight:800; color:#14b8a6;"><?= number_format($txCount) ?></div>
                <div style="font-size:.65rem; color:var(--text-muted);">Total Transaksi</div>
            </div>
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:12px 16px;">
                <div style="font-size:1.3rem; font-weight:800; color:#818cf8;"><?= number_format($custCount) ?></div>
                <div style="font-size:.65rem; color:var(--text-muted);">Total Pelanggan</div>
            </div>
            <div style="text-align:center; background:rgba(255,255,255,.04); border:1px solid var(--border-color); border-radius:10px; padding:12px 16px;">
                <div style="font-size:1.3rem; font-weight:800; color:#f59e0b;"><?= number_format($prodCount) ?></div>
                <div style="font-size:.65rem; color:var(--text-muted);">Total Produk</div>
            </div>
        </div>
    </div>
</div>

<!-- Alert -->
<?php if ($alert): ?>
<div class="alert alert-<?= $alert['type']==='success' ? 'success' : 'error' ?>" style="margin-bottom:20px;">
    <?= $alert['msg'] ?>
</div>
<?php endif; ?>

<!-- ═══════════════ TAB NAV ══════════════════════════════════ -->
<div style="display:flex; gap:4px; margin-bottom:20px; background:rgba(0,0,0,.2); padding:5px; border-radius:12px; width:fit-content;">
    <?php
    $tabs = [
        ['id'=>'tx',   'icon'=>'🧾', 'label'=>'Input Transaksi'],
        ['id'=>'cust', 'icon'=>'👤', 'label'=>'Input Pelanggan'],
        ['id'=>'prod', 'icon'=>'📦', 'label'=>'Input Produk'],
        ['id'=>'hist', 'icon'=>'📋', 'label'=>'Riwayat Input'],
    ];
    foreach ($tabs as $i => $t): ?>
    <button onclick="switchTab('<?= $t['id'] ?>')"
            id="tbtn-<?= $t['id'] ?>"
            class="tab-btn2 <?= $i===0?'tab2-active':'' ?>"
            style="padding:9px 18px; font-size:.82rem; font-weight:600; border:none; cursor:pointer;
                   border-radius:8px; transition:all .2s; background:transparent; color:var(--text-muted);">
        <?= $t['icon'] ?> <?= $t['label'] ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ═══════════════ TAB 1: INPUT TRANSAKSI ═══════════════════ -->
<div id="tab-tx" class="tab2-content">
<form method="POST" id="formTx">
    <input type="hidden" name="_action" value="add_transaction">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

        <!-- Kiri: Header Transaksi -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">🧾 Header Invoice</div>
                <span class="panel-badge" style="background:rgba(20,184,166,.12);color:#14b8a6;border-color:rgba(20,184,166,.25);">Wajib Isi</span>
            </div>
            <div style="display:grid; gap:16px;">
                <div>
                    <label class="form-label">Nomor Invoice <span style="color:#f43f5e;">*</span></label>
                    <input type="text" name="invoice_no" id="invoice_no" required
                           placeholder="Contoh: INV-2024-0001"
                           class="form-input">
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">Harus unik, belum pernah ada di database</div>
                </div>
                <div>
                    <label class="form-label">Customer ID (Opsional)</label>
                    <input type="number" name="customer_id" id="customer_id"
                           placeholder="Kosongkan jika pelanggan baru / guest"
                           class="form-input" min="1">
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">
                        <?= number_format($custCount) ?> pelanggan terdaftar
                    </div>
                </div>
                <div>
                    <label class="form-label">Negara <span style="color:#f43f5e;">*</span></label>
                    <select name="country_id" class="form-input">
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= $c['country_id'] ?>" <?= $c['country_name']==='United Kingdom'?'selected':'' ?>>
                            <?= htmlspecialchars($c['country_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tanggal & Waktu Invoice <span style="color:#f43f5e;">*</span></label>
                    <input type="datetime-local" name="invoice_date"
                           value="<?= date('Y-m-d\TH:i') ?>"
                           class="form-input">
                </div>
            </div>
        </div>

        <!-- Kanan: Item Produk -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">📦 Item Produk</div>
                <button type="button" onclick="addItemRow()" class="btn btn-sm btn-secondary">+ Tambah Item</button>
            </div>

            <div id="itemRows" style="display:grid; gap:10px;">
                <!-- Row Template 1 -->
                <?php for ($r = 0; $r < 3; $r++): ?>
                <div class="item-row" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:end;">
                    <div>
                        <?php if ($r === 0): ?><label class="form-label" style="font-size:.7rem;">Stock Code / Produk</label><?php endif; ?>
                        <input type="text" name="stock_code[]"
                               placeholder="Kode produk" class="form-input" style="font-size:.82rem;"
                               oninput="autoFillPrice(this)">
                    </div>
                    <div>
                        <?php if ($r === 0): ?><label class="form-label" style="font-size:.7rem;">Qty</label><?php endif; ?>
                        <input type="number" name="quantity[]" min="1" value="1"
                               class="form-input" style="font-size:.82rem;" oninput="calcTotal()">
                    </div>
                    <div>
                        <?php if ($r === 0): ?><label class="form-label" style="font-size:.7rem;">Harga (£)</label><?php endif; ?>
                        <input type="number" name="unit_price[]" step="0.01" min="0.01"
                               placeholder="0.00" class="form-input" style="font-size:.82rem;" oninput="calcTotal()">
                    </div>
                    <div style="<?= $r===0?'padding-top:22px':'' ?>">
                        <button type="button" onclick="removeRow(this)"
                                style="width:30px;height:30px;border-radius:6px;border:1px solid rgba(244,63,94,.3);
                                       background:rgba(244,63,94,.1);color:#f43f5e;cursor:pointer;font-size:.9rem;">×</button>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Total Preview -->
            <div style="margin-top:16px;padding:14px;background:rgba(20,184,166,.08);border:1px solid rgba(20,184,166,.2);border-radius:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-size:.8rem;color:var(--text-muted);">Estimasi Total Invoice:</div>
                    <div style="font-size:1.3rem;font-weight:800;color:#14b8a6;" id="totalPreview">£0.00</div>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
        <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
        <button type="submit" class="btn btn-primary btn-lg" id="btnSaveTx">
            💾 Simpan Transaksi
        </button>
    </div>
</form>
</div>

<!-- ═══════════════ TAB 2: INPUT PELANGGAN ═══════════════════ -->
<div id="tab-cust" class="tab2-content" style="display:none;">
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">👤 Tambah Pelanggan Baru</div>
            <span class="panel-badge" style="background:rgba(129,140,248,.12);color:#818cf8;border-color:rgba(129,140,248,.25);">customers</span>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="add_customer">
            <div style="display:grid; gap:16px;">
                <div>
                    <label class="form-label">Customer ID <span style="color:#f43f5e;">*</span></label>
                    <input type="number" name="customer_id" required min="10000" max="99999"
                           placeholder="Contoh: 15000" class="form-input">
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">Angka unik 5 digit (sesuai dataset Online Retail)</div>
                </div>
                <div>
                    <label class="form-label">Negara <span style="color:#f43f5e;">*</span></label>
                    <select name="country_id" class="form-input">
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= $c['country_id'] ?>" <?= $c['country_name']==='United Kingdom'?'selected':'' ?>>
                            <?= htmlspecialchars($c['country_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Segmen Awal</label>
                    <select name="segment" class="form-input">
                        <?php foreach (['New Customer','Regular','Loyal Customers','Champions','At Risk','Lost'] as $seg): ?>
                        <option value="<?= $seg ?>"><?= $seg ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">Akan diperbarui otomatis saat RFM dijalankan ulang</div>
                </div>
                <button type="submit" class="btn btn-primary">👤 Daftarkan Pelanggan</button>
            </div>
        </form>
    </div>

    <!-- Pelanggan Terbaru -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Pelanggan Terbaru</div>
            <a href="?page=customers" class="btn btn-sm btn-secondary">Lihat Semua →</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>ID</th><th>Negara</th><th>Segmen</th><th>Terdaftar</th></tr></thead>
                <tbody>
                <?php
                $recentCust = $db->query("
                    SELECT c.customer_id, co.country_name, c.segment, c.created_at
                    FROM customers c
                    LEFT JOIN countries co ON co.country_id = c.country_id
                    ORDER BY c.created_at DESC LIMIT 10
                ")->fetchAll();
                foreach ($recentCust as $rc): ?>
                <tr>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:2px 7px;border-radius:4px;"><?= $rc['customer_id'] ?></code></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($rc['country_name'] ?? '-') ?></td>
                    <td><span style="font-size:.72rem;color:#818cf8;"><?= htmlspecialchars($rc['segment'] ?? '-') ?></span></td>
                    <td style="font-size:.75rem;color:var(--text-muted);"><?= date('d/m/Y', strtotime($rc['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════ TAB 3: INPUT PRODUK ══════════════════════ -->
<div id="tab-prod" class="tab2-content" style="display:none;">
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📦 Tambah Produk Baru</div>
            <span class="panel-badge" style="background:rgba(245,158,11,.12);color:#f59e0b;border-color:rgba(245,158,11,.25);">products</span>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="add_product">
            <div style="display:grid; gap:16px;">
                <div>
                    <label class="form-label">Stock Code <span style="color:#f43f5e;">*</span></label>
                    <input type="text" name="stock_code" required
                           placeholder="Contoh: 85099B" class="form-input"
                           style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">Kode unik produk, akan diubah ke UPPERCASE otomatis</div>
                </div>
                <div>
                    <label class="form-label">Deskripsi Produk <span style="color:#f43f5e;">*</span></label>
                    <input type="text" name="description" required
                           placeholder="Nama lengkap produk" class="form-input">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="form-label">Harga Satuan (£) <span style="color:#f43f5e;">*</span></label>
                        <input type="number" name="unit_price" step="0.01" min="0.01" required
                               placeholder="0.00" class="form-input" id="prodPrice"
                               oninput="updatePriceTier(this.value)">
                    </div>
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-input">
                            <?php foreach (['General','Home & Garden','Gift & Novelty','Stationery','Kitchenware','Seasonal','Accessories'] as $cat): ?>
                            <option><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Price Tier Preview -->
                <div style="padding:10px 14px; background:rgba(0,0,0,.15); border-radius:8px; font-size:.8rem;">
                    Price Tier:&nbsp;
                    <span id="tierPreview" style="font-weight:700;color:#94a3b8;">— (masukkan harga)</span>
                </div>
                <button type="submit" class="btn btn-primary">📦 Tambah Produk</button>
            </div>
        </form>
    </div>

    <!-- Produk Terbaru -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Produk Terbaru Ditambahkan</div>
            <a href="?page=products" class="btn btn-sm btn-secondary">CRUD Produk →</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Kode</th><th>Deskripsi</th><th>Harga</th><th>Tier</th></tr></thead>
                <tbody>
                <?php
                $recentProd = $db->query("
                    SELECT stock_code, description, unit_price, price_tier
                    FROM products ORDER BY created_at DESC LIMIT 10
                ")->fetchAll();
                foreach ($recentProd as $p):
                    $tc = ['premium'=>'#818cf8','standard'=>'#14b8a6','budget'=>'#64748b'][$p['price_tier']] ?? '#94a3b8';
                ?>
                <tr>
                    <td><code style="font-size:.72rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($p['stock_code']) ?></code></td>
                    <td style="font-size:.78rem; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['description']) ?></td>
                    <td style="color:#14b8a6;font-weight:600;font-size:.82rem;">£<?= number_format($p['unit_price'],2) ?></td>
                    <td><span style="color:<?= $tc ?>;font-size:.72rem;font-weight:700;"><?= ucfirst($p['price_tier']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════ TAB 4: RIWAYAT INPUT ═════════════════════ -->
<div id="tab-hist" class="tab2-content" style="display:none;">
    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">
            <div class="panel-title">📋 20 Transaksi Terbaru</div>
            <a href="?page=reports" class="btn btn-sm btn-secondary">Laporan Lengkap →</a>
        </div>
        <div class="table-wrapper">
            <table id="histTable">
                <thead><tr>
                    <th>#</th><th>Invoice No</th><th>Customer ID</th><th>Negara</th>
                    <th>Tanggal</th><th>Total Amt</th><th>Items</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php
                $histTx = $db->query("
                    SELECT t.transaction_id, t.invoice_no, t.customer_id,
                           co.country_name, t.invoice_date, t.total_amount,
                           t.total_items, t.is_cancelled
                    FROM transactions t
                    LEFT JOIN countries co ON co.country_id = t.country_id
                    ORDER BY t.transaction_id DESC LIMIT 20
                ")->fetchAll();
                foreach ($histTx as $i => $t): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.8rem;"><?= $i+1 ?></td>
                    <td><code style="font-size:.78rem;background:rgba(255,255,255,.05);padding:2px 7px;border-radius:4px;"><?= htmlspecialchars($t['invoice_no']) ?></code></td>
                    <td style="color:#818cf8;font-size:.82rem;"><?= $t['customer_id'] ?? '<em style="color:var(--text-muted);">Guest</em>' ?></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($t['country_name'] ?? '-') ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= date('d/m/Y H:i', strtotime($t['invoice_date'])) ?></td>
                    <td style="color:#14b8a6;font-weight:600;">£<?= number_format($t['total_amount'],2) ?></td>
                    <td style="font-size:.82rem;"><?= number_format($t['total_items']) ?></td>
                    <td>
                        <?php if ($t['is_cancelled']): ?>
                        <span style="background:rgba(244,63,94,.15);color:#f43f5e;border:1px solid rgba(244,63,94,.3);border-radius:20px;padding:2px 10px;font-size:.7rem;">Dibatalkan</span>
                        <?php else: ?>
                        <span style="background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);border-radius:20px;padding:2px 10px;font-size:.7rem;">Aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════ CSS & JS ══════════════════════════════════ -->
<style>
.form-label { display:block; font-size:.78rem; color:var(--text-muted); margin-bottom:5px; font-weight:500; }
.form-input { width:100%; padding:9px 13px; background:var(--bg-primary); border:1px solid var(--border-color);
              color:var(--text-primary); border-radius:8px; font-size:.875rem; box-sizing:border-box;
              transition:border-color .2s; }
.form-input:focus { outline:none; border-color:var(--accent-teal); }
.tab2-active { background:rgba(255,255,255,.08) !important; color:var(--text-primary) !important; }
</style>

<!-- Product lookup data -->
<script>
const productData = <?= json_encode(array_column($products, null, 'stock_code')) ?>;

function switchTab(id) {
    document.querySelectorAll('.tab2-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn2').forEach(b => b.classList.remove('tab2-active'));
    document.getElementById('tab-' + id).style.display = '';
    document.getElementById('tbtn-' + id).classList.add('tab2-active');
}

function addItemRow() {
    const container = document.getElementById('itemRows');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.style.cssText = 'display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:center;';
    div.innerHTML = `
        <input type="text" name="stock_code[]" placeholder="Kode produk"
               class="form-input" style="font-size:.82rem;" oninput="autoFillPrice(this)">
        <input type="number" name="quantity[]" min="1" value="1"
               class="form-input" style="font-size:.82rem;" oninput="calcTotal()">
        <input type="number" name="unit_price[]" step="0.01" min="0.01" placeholder="0.00"
               class="form-input" style="font-size:.82rem;" oninput="calcTotal()">
        <button type="button" onclick="removeRow(this)"
                style="width:30px;height:30px;border-radius:6px;border:1px solid rgba(244,63,94,.3);
                       background:rgba(244,63,94,.1);color:#f43f5e;cursor:pointer;font-size:.9rem;">×</button>
    `;
    container.appendChild(div);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length <= 1) return;
    btn.closest('.item-row').remove();
    calcTotal();
}

function autoFillPrice(input) {
    const code  = input.value.trim().toUpperCase();
    const row   = input.closest('.item-row');
    const price = row.querySelector('input[name="unit_price[]"]');
    if (productData[code] && price.value == '' || price.value == '0') {
        price.value = productData[code].unit_price;
        calcTotal();
    }
}

function calcTotal() {
    let total = 0;
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(row => {
        const qty   = parseFloat(row.querySelector('input[name="quantity[]"]')?.value || 0);
        const price = parseFloat(row.querySelector('input[name="unit_price[]"]')?.value || 0);
        total += qty * price;
    });
    document.getElementById('totalPreview').textContent = '£' + total.toFixed(2);
}

function updatePriceTier(val) {
    const v = parseFloat(val);
    const el = document.getElementById('tierPreview');
    if (isNaN(v) || v <= 0) { el.textContent = '— (masukkan harga)'; el.style.color = '#94a3b8'; return; }
    if (v >= 10) { el.textContent = '💎 Premium (≥ £10)'; el.style.color = '#818cf8'; }
    else if (v >= 3) { el.textContent = '⭐ Standard (£3–£10)'; el.style.color = '#14b8a6'; }
    else { el.textContent = '💰 Budget (< £3)'; el.style.color = '#f59e0b'; }
}

// Auto-generate invoice number
document.addEventListener('DOMContentLoaded', () => {
    const invField = document.getElementById('invoice_no');
    if (invField && !invField.value) {
        const now = new Date();
        invField.value = 'INV-' + now.getFullYear() +
            String(now.getMonth()+1).padStart(2,'0') +
            String(now.getDate()).padStart(2,'0') + '-' +
            String(Math.floor(Math.random()*9000)+1000);
    }
    calcTotal();
});
</script>

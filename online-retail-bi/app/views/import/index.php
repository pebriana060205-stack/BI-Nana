<?php
// ============================================================
//  Import View — Integration Services (Upload & ETL CSV)
// ============================================================

require_once BASE_PATH . '/helpers/ETL.php';

$message = null;
$result  = null;

// Handle form POST import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $allowedExt = ['csv', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = ['type' => 'error', 'text' => 'Error upload: ' . $file['error']];
    } elseif (!in_array($ext, $allowedExt)) {
        $message = ['type' => 'error', 'text' => 'Format file harus .csv atau .txt'];
    } elseif ($file['size'] > 50 * 1024 * 1024) { // Max 50MB
        $message = ['type' => 'error', 'text' => 'Ukuran file maksimal 50MB'];
    } else {
        $uploadDir  = BASE_PATH . '/storage/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $uploadPath = $uploadDir . time() . '_' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            set_time_limit(300); // 5 menit untuk import besar
            $etl    = new ETL();
            $result = $etl->importCSV($uploadPath);

            if ($result['success']) {
                $message = ['type' => 'success', 'text' => "Import berhasil! {$result['success_rows']} baris diimport dari {$result['total_rows']} total baris."];
            } else {
                $message = ['type' => 'error', 'text' => 'Import gagal: ' . ($result['message'] ?? 'Error tidak diketahui')];
            }
        } else {
            $message = ['type' => 'error', 'text' => 'Gagal menyimpan file upload.'];
        }
    }
}

// Handle import dari file CSV yang sudah ada di server
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_existing'])) {
    $csvPath = BASE_PATH . '/../online_retail_II_20000.csv';
    if (file_exists($csvPath)) {
        set_time_limit(600);
        $etl    = new ETL();
        $result = $etl->importCSV($csvPath);
        if ($result['success']) {
            $message = ['type' => 'success', 'text' => "Import dataset berhasil! {$result['success_rows']} baris diproses."];
        } else {
            $message = ['type' => 'error', 'text' => 'Gagal: ' . ($result['message'] ?? 'Error')];
        }
    } else {
        $message = ['type' => 'error', 'text' => 'File online_retail_II_20000.csv tidak ditemukan di folder project.'];
    }
}

// Ambil ETL log history
$db     = getDB();
$logs   = $db->query("SELECT * FROM etl_log ORDER BY log_id DESC LIMIT 10")->fetchAll();
$hasData = (int)$db->query("SELECT COUNT(*) FROM transactions")->fetchColumn() > 0;
?>

<div style="max-width: 900px;">

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?= $message['type'] ?>" style="margin-bottom: 24px;">
        <?= $message['type'] === 'success' ? '✅' : '❌' ?>
        <?= htmlspecialchars($message['text']) ?>
    </div>
    <?php endif; ?>

    <!-- Result Detail -->
    <?php if ($result && $result['success']): ?>
    <div class="panel" style="margin-bottom: 24px;">
        <div class="panel-title" style="margin-bottom: 16px;">📊 Hasil Import Detail</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
            <div style="text-align:center;padding:16px;background:rgba(16,185,129,.08);border-radius:12px;border:1px solid rgba(16,185,129,.2);">
                <div style="font-size:2rem;font-weight:800;color:var(--accent-emerald);"><?= number_format($result['success_rows']) ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px;">Baris Berhasil</div>
            </div>
            <div style="text-align:center;padding:16px;background:rgba(244,63,94,.08);border-radius:12px;border:1px solid rgba(244,63,94,.2);">
                <div style="font-size:2rem;font-weight:800;color:var(--accent-rose);"><?= number_format($result['failed_rows']) ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px;">Baris Gagal/Skip</div>
            </div>
            <div style="text-align:center;padding:16px;background:rgba(99,102,241,.08);border-radius:12px;border:1px solid rgba(99,102,241,.2);">
                <div style="font-size:2rem;font-weight:800;color:var(--accent-indigo);"><?= number_format($result['total_rows']) ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px;">Total Baris</div>
            </div>
        </div>

        <?php if (!empty($result['errors'])): ?>
        <div class="alert alert-warning">
            <div>
                <strong>⚠️ Beberapa baris dilewati (sample error pertama):</strong>
                <ul style="margin-top:8px;font-size:.78rem;list-style:disc;padding-left:20px;">
                    <?php foreach (array_slice($result['errors'], 0, 5) as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <a href="?page=dashboard" class="btn btn-primary">
            📊 Lihat Dashboard →
        </a>
    </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="panel" style="margin-bottom: 24px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">📥 Upload File CSV Baru</div>
                <div class="panel-subtitle">Integration Services — Drag & Drop atau pilih file</div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
                <span class="upload-icon">☁️</span>
                <div class="upload-title">Klik atau Drag & Drop File CSV</div>
                <div class="upload-sub">Format: .csv | Maksimal: 50MB | Encoding: UTF-8</div>
                <div class="upload-sub" style="margin-top: 8px;">Kolom: Invoice, StockCode, Description, Quantity, InvoiceDate, Price, CustomerID, Country</div>
            </div>
            <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display:none;" onchange="handleFileSelect(this)">

            <div id="fileInfo" style="display:none;margin-top:16px;" class="alert alert-info">
                📄 File dipilih: <strong id="fileName"></strong> (<span id="fileSize"></span>)
            </div>

            <div style="margin-top: 20px; display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                    📥 Mulai Import
                </button>
                <span style="color: var(--text-muted); font-size: .8rem;">
                    ⚠️ Proses import 20.000 baris dapat memakan waktu 1-3 menit
                </span>
            </div>
        </form>
    </div>

    <!-- Import Dataset Existing -->
    <?php
    $csvPath = BASE_PATH . '/../online_retail_II_20000.csv';
    if (file_exists($csvPath)):
    ?>
    <div class="panel" style="margin-bottom: 24px; border-color: rgba(20,184,166,.3);">
        <div class="panel-header">
            <div>
                <div class="panel-title">⚡ Import Dataset Existing</div>
                <div class="panel-subtitle">
                    File <code style="background:rgba(255,255,255,.06);padding:2px 6px;border-radius:4px;">online_retail_II_20000.csv</code> 
                    terdeteksi di folder project
                </div>
            </div>
            <span class="panel-badge">Ready</span>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <form method="POST">
                <button type="submit" name="import_existing" value="1" class="btn btn-primary">
                    ⚡ Import Sekarang (20.000 Baris)
                </button>
            </form>
            <?php if ($hasData): ?>
            <div class="alert alert-warning" style="margin:0;padding:8px 14px;">
                ⚠️ Database sudah memiliki data. Import ulang akan menambah duplikasi jika tidak di-reset terlebih dahulu.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ETL Log History -->
    <?php if (!empty($logs)): ?>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">📋 Riwayat Import (ETL Log)</div>
        </div>
        <div class="table-wrapper">
            <table id="etlLogTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>File</th>
                        <th>Total</th>
                        <th>Berhasil</th>
                        <th>Gagal</th>
                        <th>Status</th>
                        <th>Waktu</th>
                        <th>Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $dur = '-';
                        if ($log['started_at'] && $log['completed_at']) {
                            $secs = strtotime($log['completed_at']) - strtotime($log['started_at']);
                            $dur  = $secs >= 60 ? round($secs/60,1).'m' : $secs.'s';
                        }
                        $statusClass = match($log['status']) {
                            'success' => 'badge-regular',
                            'failed'  => 'badge-risk',
                            'partial' => 'badge-new',
                            default   => 'badge-lost',
                        };
                    ?>
                    <tr>
                        <td>#<?= $log['log_id'] ?></td>
                        <td><code style="font-size:.75rem;"><?= htmlspecialchars($log['source_file']) ?></code></td>
                        <td><?= number_format($log['total_rows']) ?></td>
                        <td style="color:var(--accent-emerald);"><?= number_format($log['success_rows']) ?></td>
                        <td style="color:var(--accent-rose);"><?= number_format($log['failed_rows']) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= strtoupper($log['status']) ?></span></td>
                        <td style="font-size:.78rem;color:var(--text-muted);"><?= $log['started_at'] ?></td>
                        <td><?= $dur ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Drag & Drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('csvFile').files = e.dataTransfer.files;
        showFileInfo(file);
    }
});

function handleFileSelect(input) {
    if (input.files[0]) showFileInfo(input.files[0]);
}

function showFileInfo(file) {
    const info = document.getElementById('fileInfo');
    const sizeMB = (file.size / 1024 / 1024).toFixed(2);
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = sizeMB + ' MB';
    info.style.display = 'flex';
    document.getElementById('submitBtn').disabled = false;
}

// Show loading on submit
document.getElementById('uploadForm')?.addEventListener('submit', function() {
    document.getElementById('submitBtn').textContent = '⏳ Sedang Memproses...';
    document.getElementById('submitBtn').disabled = true;
});
</script>

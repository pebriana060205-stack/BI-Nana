<?php
// ============================================================
//  Setup Database & Initial ETL Import
// ============================================================

define('BASE_PATH', __DIR__);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/ETL.php';
require_once __DIR__ . '/helpers/RFM.php';
require_once __DIR__ . '/helpers/ABC.php';
require_once __DIR__ . '/helpers/Clustering.php';

echo "=== 1. Menghubungkan ke MySQL Server ===\n";
try {
    $pdo = new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Terhubung ke MySQL!\n\n";

    echo "=== 2. Membuat Database & Tabel (setup_database.sql) ===\n";
    $sql = file_get_contents(__DIR__ . '/config/setup_database.sql');
    $pdo->exec($sql);
    echo "✅ Database & Tabel berhasil dibuat!\n\n";

    echo "=== 3. Menjalankan ETL Import (online_retail_II_20000.csv) ===\n";
    $csvPath = dirname(__DIR__) . '/online_retail_II_20000.csv';
    if (!file_exists($csvPath)) {
        echo "❌ File CSV tidak ditemukan di: $csvPath\n";
        exit(1);
    }

    $etl = new ETL();
    $result = $etl->importCSV($csvPath);
    echo "Status ETL: " . ($result['success'] ? '✅ SUKSES' : '❌ GAGAL') . "\n";
    echo "Message: " . $result['message'] . "\n\n";

    echo "=== 4. Menjalankan Analysis Services (RFM) ===\n";
    $rfm = new RFM();
    $rfmRes = $rfm->calculate();
    echo "Status RFM: " . ($rfmRes['success'] ? '✅ SUKSES (' . $rfmRes['processed_customers'] . ' pelanggan)' : '❌ GAGAL') . "\n\n";

    echo "=== 5. Menjalankan Data Mining (ABC Analysis) ===\n";
    $abc = new ABC();
    $abcRes = $abc->calculate();
    echo "Status ABC: " . ($abcRes['success'] ? '✅ SUKSES (' . $abcRes['total_products'] . ' produk)' : '❌ GAGAL') . "\n\n";

    echo "=== 6. Menjalankan Clustering Support (K-Means k=4) ===\n";
    $cluster = new Clustering(k: 4);
    $cRes = $cluster->run();
    echo "Status Clustering: " . ($cRes['success'] ? '✅ SUKSES (k=' . $cRes['k'] . ')' : '❌ GAGAL') . "\n\n";

    echo "🎉 PROSES SETUP & IMPORT SELESAI DENGAN SUKSES!\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

echo "=========================================================\n";
echo "       VERIFIKASI KESESUAIAN DATASET & WEBSITE           \n";
echo "=========================================================\n";
echo "1. Total Header Transaksi (transactions)    : " . number_format($db->query("SELECT COUNT(*) FROM transactions")->fetchColumn()) . "\n";
echo "2. Total Baris Item (transaction_items)    : " . number_format($db->query("SELECT COUNT(*) FROM transaction_items")->fetchColumn()) . "\n";
echo "3. Total Produk Aktif (products)            : " . number_format($db->query("SELECT COUNT(*) FROM products")->fetchColumn()) . "\n";
echo "4. Total Pelanggan Unik (customers)         : " . number_format($db->query("SELECT COUNT(*) FROM customers")->fetchColumn()) . "\n";
echo "5. Data Terkalkulasi RFM (analytics_rfm)   : " . number_format($db->query("SELECT COUNT(*) FROM analytics_rfm")->fetchColumn()) . "\n";
echo "6. Data Klasifikasi ABC (mining_product_abc): " . number_format($db->query("SELECT COUNT(*) FROM mining_product_abc")->fetchColumn()) . "\n";
echo "7. Data Klaster K-Means (clustering_groups) : " . number_format($db->query("SELECT COUNT(*) FROM clustering_customer_groups")->fetchColumn()) . "\n";
echo "8. Log ETL Import (etl_log)                 : " . number_format($db->query("SELECT COUNT(*) FROM etl_log")->fetchColumn()) . "\n";
echo "=========================================================\n";

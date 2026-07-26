<?php
// ============================================================
//  Integration Services — Helper ETL
//  Membaca CSV, memvalidasi, dan memasukkan ke database MySQL
// ============================================================

require_once __DIR__ . '/../config/database.php';

class ETL {

    private PDO $db;
    private int $logId;
    private array $errors = [];

    public function __construct() {
        $this->db = getDB();
    }

    // --------------------------------------------------------
    //  ENTRY POINT: Import CSV ke database
    // --------------------------------------------------------
    public function importCSV(string $filePath): array {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'File tidak ditemukan: ' . $filePath];
        }

        $this->logId = $this->startLog(basename($filePath));
        $this->clearStaging();

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle); // Baca baris header
        $totalRows = 0;
        $successRows = 0;
        $failedRows = 0;

        // --- Tahap 1: Load ke staging ---
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO etl_staging 
                (raw_invoice, raw_stockcode, raw_description, raw_quantity, raw_date, raw_price, raw_customerid, raw_country)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) continue;
                $totalRows++;
                $stmt->execute([
                    trim($row[0]), trim($row[1]), trim($row[2]),
                    trim($row[3]), trim($row[4]), trim($row[5]),
                    trim($row[6]), trim($row[7])
                ]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Gagal load staging: ' . $e->getMessage()];
        }
        fclose($handle);

        // --- Tahap 2: Validasi & Transform staging ---
        $this->validateStaging();

        // --- Tahap 3: Load ke tabel utama ---
        $result = $this->loadFromStaging();
        $successRows = $result['success'];
        $failedRows  = $result['failed'];

        // Update log
        $this->finishLog($this->logId, $totalRows, $successRows, $failedRows, $this->errors);

        return [
            'success'      => true,
            'total_rows'   => $totalRows,
            'success_rows' => $successRows,
            'failed_rows'  => $failedRows,
            'errors'       => array_slice($this->errors, 0, 20), // tampilkan max 20 error
        ];
    }

    // --------------------------------------------------------
    //  Validasi data di tabel staging
    // --------------------------------------------------------
    private function validateStaging(): void {
        // Tandai baris valid
        $this->db->exec("
            UPDATE etl_staging SET is_valid = 1
            WHERE raw_price > 0
              AND raw_stockcode NOT IN ('POST','DOT','BANK','AMAZONFEE','M','DCGSSBOY','DCGSSGIRL','PADS')
              AND raw_stockcode REGEXP '^[A-Za-z0-9]+$'
        ");

        // Tandai baris tidak valid + alasan
        $this->db->exec("
            UPDATE etl_staging SET is_valid = 0, error_reason = 'Harga <= 0 atau kode non-produk'
            WHERE is_valid = 0 AND error_reason IS NULL
        ");
    }

    // --------------------------------------------------------
    //  Load dari staging ke tabel OLTP utama
    // --------------------------------------------------------
    private function loadFromStaging(): array {
        $success = 0;
        $failed  = 0;

        $rows = $this->db->query("SELECT * FROM etl_staging WHERE is_valid = 1")->fetchAll();

        // Cache countries & products untuk performa
        $countryCache  = [];
        $productCache  = [];
        $customerCache = [];
        $invoiceCache  = [];

        foreach ($rows as $row) {
            try {
                // 1. Country
                $countryName = trim($row['raw_country']);
                if (!isset($countryCache[$countryName])) {
                    $countryCache[$countryName] = $this->upsertCountry($countryName);
                }
                $countryId = $countryCache[$countryName];

                // 2. Customer
                $rawCustId  = $row['raw_customerid'];
                $customerId = (is_numeric($rawCustId) && $rawCustId > 0) ? (int)$rawCustId : 0;
                if (!isset($customerCache[$customerId])) {
                    $this->upsertCustomer($customerId, $countryId);
                    $customerCache[$customerId] = true;
                }

                // 3. Product
                $stockCode = strtoupper(trim($row['raw_stockcode']));
                if (!isset($productCache[$stockCode])) {
                    $price = (float)$row['raw_price'];
                    $this->upsertProduct($stockCode, trim($row['raw_description']), $price);
                    $productCache[$stockCode] = true;
                }

                // 4. Transaction (header per invoice)
                $invoiceNo    = trim($row['raw_invoice']);
                $isCancelled  = (str_starts_with($invoiceNo, 'C')) ? 1 : 0;
                $invoiceDate  = date('Y-m-d H:i:s', strtotime($row['raw_date']));

                if (!isset($invoiceCache[$invoiceNo])) {
                    $transactionId = $this->insertTransaction($invoiceNo, $customerId, $countryId, $invoiceDate, $isCancelled);
                    $invoiceCache[$invoiceNo] = $transactionId;
                }
                $transactionId = $invoiceCache[$invoiceNo];

                // 5. Transaction Item
                $quantity  = (int)$row['raw_quantity'];
                $unitPrice = (float)$row['raw_price'];
                $subtotal  = round($quantity * $unitPrice, 2);
                $this->insertTransactionItem($transactionId, $stockCode, $quantity, $unitPrice, $subtotal);

                $success++;
            } catch (Exception $e) {
                $failed++;
                $this->errors[] = "Row error: " . $e->getMessage();
            }
        }

        // Update total_amount & total_items di transactions
        $this->db->exec("
            UPDATE transactions t
            SET 
                total_amount = (SELECT COALESCE(SUM(subtotal),0) FROM transaction_items WHERE transaction_id = t.transaction_id),
                total_items  = (SELECT COALESCE(COUNT(*), 0)    FROM transaction_items WHERE transaction_id = t.transaction_id)
        ");

        // Update statistik pelanggan
        $this->updateCustomerStats();

        return ['success' => $success, 'failed' => $failed];
    }

    // --------------------------------------------------------
    //  Helper: Upsert Country
    // --------------------------------------------------------
    private function upsertCountry(string $name): int {
        $regionMap = [
            'United Kingdom' => 'Europe', 'Germany' => 'Europe', 'France' => 'Europe',
            'Netherlands' => 'Europe', 'Belgium' => 'Europe', 'Switzerland' => 'Europe',
            'Spain' => 'Europe', 'Portugal' => 'Europe', 'Italy' => 'Europe',
            'Norway' => 'Europe', 'Denmark' => 'Europe', 'Sweden' => 'Europe',
            'Finland' => 'Europe', 'Poland' => 'Europe', 'Austria' => 'Europe',
            'Australia' => 'Oceania', 'Japan' => 'Asia', 'Singapore' => 'Asia',
            'Bahrain' => 'Asia', 'Lebanon' => 'Asia', 'United Arab Emirates' => 'Asia',
            'Israel' => 'Asia', 'Saudi Arabia' => 'Asia', 'India' => 'Asia',
            'Canada' => 'Americas', 'USA' => 'Americas', 'Brazil' => 'Americas',
            'Cyprus' => 'Europe', 'Malta' => 'Europe', 'Iceland' => 'Europe',
            'Czech Republic' => 'Europe', 'Greece' => 'Europe', 'Hungary' => 'Europe',
            'Lithuania' => 'Europe', 'RSA' => 'Africa',
        ];
        $region = $regionMap[$name] ?? 'Unknown';

        $stmt = $this->db->prepare("SELECT country_id FROM countries WHERE country_name = ?");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['country_id'];

        $stmt = $this->db->prepare("INSERT INTO countries (country_name, region) VALUES (?, ?)");
        $stmt->execute([$name, $region]);
        return (int)$this->db->lastInsertId();
    }

    // --------------------------------------------------------
    //  Helper: Upsert Customer
    // --------------------------------------------------------
    private function upsertCustomer(int $customerId, int $countryId): void {
        $stmt = $this->db->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        if ($stmt->fetch()) return;

        $stmt = $this->db->prepare("INSERT INTO customers (customer_id, country_id) VALUES (?, ?)");
        $stmt->execute([$customerId, $countryId]);
    }

    // --------------------------------------------------------
    //  Helper: Upsert Product
    // --------------------------------------------------------
    private function upsertProduct(string $code, string $desc, float $price): void {
        $stmt = $this->db->prepare("SELECT stock_code FROM products WHERE stock_code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) return;

        $tier = $price < 2 ? 'Low' : ($price <= 10 ? 'Mid' : 'Premium');
        $stmt = $this->db->prepare("
            INSERT INTO products (stock_code, description, unit_price, price_tier) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$code, $desc ?: 'Unknown Product', $price, $tier]);
    }

    // --------------------------------------------------------
    //  Helper: Insert Transaction Header
    // --------------------------------------------------------
    private function insertTransaction(string $invoiceNo, int $customerId, int $countryId, string $date, int $isCancelled): int {
        $stmt = $this->db->prepare("SELECT transaction_id FROM transactions WHERE invoice_no = ?");
        $stmt->execute([$invoiceNo]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['transaction_id'];

        $stmt = $this->db->prepare("
            INSERT INTO transactions (invoice_no, customer_id, country_id, invoice_date, is_cancelled)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoiceNo, $customerId ?: null, $countryId, $date, $isCancelled]);
        return (int)$this->db->lastInsertId();
    }

    // --------------------------------------------------------
    //  Helper: Insert Transaction Item
    // --------------------------------------------------------
    private function insertTransactionItem(int $transId, string $stockCode, int $qty, float $price, float $subtotal): void {
        $stmt = $this->db->prepare("
            INSERT INTO transaction_items (transaction_id, stock_code, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$transId, $stockCode, $qty, $price, $subtotal]);
    }

    // --------------------------------------------------------
    //  Update statistik summary di tabel customers
    // --------------------------------------------------------
    private function updateCustomerStats(): void {
        $this->db->exec("
            UPDATE customers c
            INNER JOIN (
                SELECT 
                    customer_id,
                    COUNT(DISTINCT invoice_no)  AS total_orders,
                    SUM(total_amount)           AS total_spent,
                    MIN(DATE(invoice_date))     AS first_purchase,
                    MAX(DATE(invoice_date))     AS last_purchase
                FROM transactions
                WHERE is_cancelled = 0 AND customer_id IS NOT NULL AND customer_id > 0
                GROUP BY customer_id
            ) stats ON c.customer_id = stats.customer_id
            SET 
                c.total_orders        = stats.total_orders,
                c.total_spent         = stats.total_spent,
                c.first_purchase_date = stats.first_purchase,
                c.last_purchase_date  = stats.last_purchase
        ");
    }

    // --------------------------------------------------------
    //  ETL Log helpers
    // --------------------------------------------------------
    private function startLog(string $fileName): int {
        $stmt = $this->db->prepare("
            INSERT INTO etl_log (source_file, started_at, status) VALUES (?, NOW(), 'running')
        ");
        $stmt->execute([$fileName]);
        return (int)$this->db->lastInsertId();
    }

    private function finishLog(int $logId, int $total, int $success, int $failed, array $errors): void {
        $status = $failed === 0 ? 'success' : ($success > 0 ? 'partial' : 'failed');
        $stmt = $this->db->prepare("
            UPDATE etl_log 
            SET total_rows=?, success_rows=?, failed_rows=?, error_details=?, completed_at=NOW(), status=?
            WHERE log_id=?
        ");
        $stmt->execute([$total, $success, $failed, json_encode(array_slice($errors,0,50)), $status, $logId]);
    }

    private function clearStaging(): void {
        $this->db->exec("TRUNCATE TABLE etl_staging");
    }
}

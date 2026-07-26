<?php
// ============================================================
//  Analysis Services — ABC Analysis Produk (Pareto 80/20)
//  Mengklasifikasi produk berdasarkan kontribusi revenue
// ============================================================

require_once __DIR__ . '/../config/database.php';

class ABC {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // --------------------------------------------------------
    //  ENTRY POINT: Hitung dan simpan ABC Analysis
    // --------------------------------------------------------
    public function calculate(): array {
        $rows = $this->db->query("
            SELECT
                p.stock_code,
                p.description,
                ROUND(SUM(ti.subtotal), 2) AS total_revenue
            FROM transaction_items ti
            JOIN products     p ON ti.stock_code     = p.stock_code
            JOIN transactions t ON ti.transaction_id = t.transaction_id
            WHERE t.is_cancelled = 0 AND ti.quantity > 0
            GROUP BY p.stock_code, p.description
            ORDER BY total_revenue DESC
        ")->fetchAll();

        if (empty($rows)) return ['success' => false, 'message' => 'Tidak ada data produk'];

        $totalRevenue = array_sum(array_column($rows, 'total_revenue'));
        if ($totalRevenue <= 0) return ['success' => false, 'message' => 'Total revenue 0'];

        $this->db->exec("TRUNCATE TABLE mining_product_abc");

        $stmt = $this->db->prepare("
            INSERT INTO mining_product_abc 
            (stock_code, description, total_revenue, revenue_pct, cumulative_pct, abc_class)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Update abc_class di dim_product
        $updateProduct = $this->db->prepare("
            UPDATE products SET category = CONCAT(category, '') WHERE stock_code = ?
        ");

        $cumulative = 0;
        $counts = ['A' => 0, 'B' => 0, 'C' => 0];

        foreach ($rows as $row) {
            $pct        = round($row['total_revenue'] / $totalRevenue * 100, 4);
            $cumulative = round($cumulative + $pct, 4);

            $class = $cumulative <= 80 ? 'A' : ($cumulative <= 95 ? 'B' : 'C');
            $counts[$class]++;

            $stmt->execute([
                $row['stock_code'], $row['description'],
                $row['total_revenue'], $pct, $cumulative, $class
            ]);
        }

        return [
            'success'       => true,
            'total_products'=> count($rows),
            'total_revenue' => $totalRevenue,
            'class_A_count' => $counts['A'],
            'class_B_count' => $counts['B'],
            'class_C_count' => $counts['C'],
        ];
    }

    // --------------------------------------------------------
    //  Ambil data ABC untuk tampilan dashboard
    // --------------------------------------------------------
    public function getSummary(): array {
        return $this->db->query("
            SELECT 
                abc_class,
                COUNT(*) AS jumlah_produk,
                ROUND(SUM(total_revenue), 2) AS total_revenue,
                ROUND(AVG(revenue_pct), 4) AS avg_revenue_pct
            FROM mining_product_abc
            GROUP BY abc_class
            ORDER BY abc_class
        ")->fetchAll();
    }

    public function getProducts(string $class = '', int $limit = 50): array {
        $where = $class ? "WHERE abc_class = " . $this->db->quote($class) : '';
        return $this->db->query("
            SELECT * FROM mining_product_abc $where
            ORDER BY total_revenue DESC LIMIT $limit
        ")->fetchAll();
    }
}

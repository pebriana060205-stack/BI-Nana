<?php
// ============================================================
//  API Controller — Handle AJAX & Export Requests
// ============================================================

require_once dirname(__DIR__, 3) . '/config/database.php';

class ApiController {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function handle(string $action): void {
        header('Content-Type: application/json');

        switch ($action) {
            case 'export_monthly_csv':
                $this->exportCSV(
                    'laporan_bulanan.csv',
                    "SELECT tahun,bulan,nama_bulan,total_order,pelanggan_unik,total_revenue,rata_rata_order FROM vw_monthly_sales"
                );
                break;

            case 'export_country_csv':
                $this->exportCSV(
                    'laporan_negara.csv',
                    "SELECT country_name,region,total_order,pelanggan_unik,total_revenue FROM vw_sales_by_country"
                );
                break;

            case 'export_products_csv':
                $this->exportCSV(
                    'laporan_produk.csv',
                    "SELECT stock_code,description,total_revenue,revenue_pct,cumulative_pct,abc_class FROM mining_product_abc ORDER BY total_revenue DESC"
                );
                break;

            case 'export_rfm_csv':
                $this->exportCSV(
                    'laporan_rfm.csv',
                    "SELECT customer_id,recency_days,frequency,monetary,r_score,f_score,m_score,rfm_segment,calculated_at FROM analytics_rfm ORDER BY (r_score+f_score+m_score) DESC"
                );
                break;

            case 'kpi_summary':
                echo json_encode($this->getKPISummary());
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Action tidak ditemukan: ' . $action]);
        }
    }

    private function exportCSV(string $filename, string $sql): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "Tidak ada data untuk diekspor.";
            return;
        }

        $out = fopen('php://output', 'w');
        // BOM untuk Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        // Header
        fputcsv($out, array_keys($rows[0]));
        // Data
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function getKPISummary(): array {
        return [
            'total_revenue'   => $this->db->query("SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE is_cancelled=0")->fetchColumn(),
            'total_orders'    => $this->db->query("SELECT COUNT(DISTINCT invoice_no) FROM transactions WHERE is_cancelled=0")->fetchColumn(),
            'unique_customers'=> $this->db->query("SELECT COUNT(DISTINCT customer_id) FROM transactions WHERE is_cancelled=0 AND customer_id>0")->fetchColumn(),
            'unique_products' => $this->db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn(),
        ];
    }
}

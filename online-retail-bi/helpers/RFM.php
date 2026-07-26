<?php
// ============================================================
//  Analysis Services — Kalkulasi RFM (Recency, Frequency, Monetary)
//  Segmentasi pelanggan berdasarkan perilaku belanja
// ============================================================

require_once __DIR__ . '/../config/database.php';

class RFM {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // --------------------------------------------------------
    //  ENTRY POINT: Hitung dan simpan RFM semua pelanggan
    // --------------------------------------------------------
    public function calculate(): array {
        // Referensi tanggal = hari terakhir dalam dataset
        $refDate = $this->db->query("SELECT MAX(DATE(invoice_date)) FROM transactions WHERE is_cancelled = 0")->fetchColumn();

        // Ambil data transaksi per pelanggan
        $rows = $this->db->query("
            SELECT
                customer_id,
                DATEDIFF('$refDate', MAX(DATE(invoice_date))) AS recency_days,
                COUNT(DISTINCT invoice_no)                     AS frequency,
                ROUND(SUM(total_amount), 2)                    AS monetary
            FROM transactions
            WHERE is_cancelled = 0 AND customer_id IS NOT NULL AND customer_id > 0
            GROUP BY customer_id
        ")->fetchAll();

        if (empty($rows)) return ['success' => false, 'message' => 'Tidak ada data transaksi'];

        // Hitung kuantil untuk scoring
        $recencies  = array_column($rows, 'recency_days');
        $frequencies = array_column($rows, 'frequency');
        $monetaries  = array_column($rows, 'monetary');

        sort($recencies);
        sort($frequencies);
        sort($monetaries);

        $rQuintiles = $this->quintiles($recencies);
        $fQuintiles = $this->quintiles($frequencies);
        $mQuintiles = $this->quintiles($monetaries);

        // Truncate tabel RFM sebelum isi ulang
        $this->db->exec("TRUNCATE TABLE analytics_rfm");

        $stmt = $this->db->prepare("
            INSERT INTO analytics_rfm 
            (customer_id, recency_days, frequency, monetary, r_score, f_score, m_score, rfm_segment, calculated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $updateCust = $this->db->prepare("
            UPDATE customers SET rfm_score=?, segment=? WHERE customer_id=?
        ");

        $count = 0;
        foreach ($rows as $row) {
            // Recency: semakin kecil hari = skor lebih tinggi
            $rScore = $this->scoreRecency($row['recency_days'], $rQuintiles);
            $fScore = $this->scoreAscending($row['frequency'],    $fQuintiles);
            $mScore = $this->scoreAscending($row['monetary'],     $mQuintiles);

            $rfmScore   = round(($rScore + $fScore + $mScore) / 15 * 100, 2);
            $rfmSegment = $this->classifySegment($rScore, $fScore, $mScore);

            $stmt->execute([
                $row['customer_id'], $row['recency_days'], $row['frequency'],
                $row['monetary'], $rScore, $fScore, $mScore, $rfmSegment
            ]);

            $updateCust->execute([$rfmScore, $rfmSegment, $row['customer_id']]);
            $count++;
        }

        return ['success' => true, 'customers_processed' => $count];
    }

    // --------------------------------------------------------
    //  Scoring Methods
    // --------------------------------------------------------
    private function scoreRecency(float $value, array $quintiles): int {
        // Recency: nilai kecil = bagus → skor terbalik
        if ($value <= $quintiles[0]) return 5;
        if ($value <= $quintiles[1]) return 4;
        if ($value <= $quintiles[2]) return 3;
        if ($value <= $quintiles[3]) return 2;
        return 1;
    }

    private function scoreAscending(float $value, array $quintiles): int {
        // Frequency & Monetary: nilai besar = bagus
        if ($value <= $quintiles[0]) return 1;
        if ($value <= $quintiles[1]) return 2;
        if ($value <= $quintiles[2]) return 3;
        if ($value <= $quintiles[3]) return 4;
        return 5;
    }

    private function quintiles(array $sorted): array {
        $n = count($sorted);
        return [
            $sorted[(int)($n * 0.20)] ?? 0,
            $sorted[(int)($n * 0.40)] ?? 0,
            $sorted[(int)($n * 0.60)] ?? 0,
            $sorted[(int)($n * 0.80)] ?? 0,
        ];
    }

    // --------------------------------------------------------
    //  Klasifikasi Segmen berdasarkan kombinasi R, F, M score
    // --------------------------------------------------------
    private function classifySegment(int $r, int $f, int $m): string {
        $rfm = "$r$f$m";

        if ($r >= 4 && $f >= 4 && $m >= 4) return 'Champions';
        if ($r >= 3 && $f >= 3 && $m >= 4) return 'Loyal Customers';
        if ($r >= 4 && $f <= 2)             return 'Recent Customers';
        if ($r >= 3 && $f >= 3)             return 'Potential Loyalists';
        if ($r >= 3 && $f == 1)             return 'Promising';
        if ($r == 2 && $f >= 3 && $m >= 3) return 'At Risk';
        if ($r == 2 && $f >= 2)             return 'Need Attention';
        if ($r == 1 && $f >= 4)             return 'Cant Lose Them';
        if ($r == 1 && $f >= 2)             return 'Hibernating';
        if ($r == 1 && $f == 1)             return 'Lost';
        return 'New';
    }

    // --------------------------------------------------------
    //  Ambil ringkasan distribusi segmen untuk dashboard
    // --------------------------------------------------------
    public function getSegmentSummary(): array {
        return $this->db->query("
            SELECT rfm_segment, COUNT(*) AS jumlah_pelanggan,
                   ROUND(AVG(monetary), 2) AS rata_monetary
            FROM analytics_rfm
            GROUP BY rfm_segment
            ORDER BY jumlah_pelanggan DESC
        ")->fetchAll();
    }

    // --------------------------------------------------------
    //  Ambil top N pelanggan berdasarkan RFM score
    // --------------------------------------------------------
    public function getTopCustomers(int $limit = 10): array {
        return $this->db->query("
            SELECT c.customer_id, c.segment, c.total_spent, c.total_orders,
                   r.recency_days, r.frequency, r.monetary, r.rfm_segment,
                   r.r_score, r.f_score, r.m_score
            FROM customers c
            JOIN analytics_rfm r ON c.customer_id = r.customer_id
            ORDER BY (r.r_score + r.f_score + r.m_score) DESC
            LIMIT $limit
        ")->fetchAll();
    }
}

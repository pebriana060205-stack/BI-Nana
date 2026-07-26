<?php
// ============================================================
//  Clustering Support — K-Means Sederhana (PHP Native)
//  Mengelompokkan pelanggan berdasarkan fitur RFM
// ============================================================

require_once __DIR__ . '/../config/database.php';

class Clustering {

    private PDO $db;
    private int $k;
    private int $maxIter;

    // Label klaster berurutan dari yang paling bagus
    private array $clusterLabels = ['VIP', 'Regular', 'Dormant', 'One-Time'];

    public function __construct(int $k = 4, int $maxIter = 100) {
        $this->db      = getDB();
        $this->k       = $k;
        $this->maxIter = $maxIter;
    }

    // --------------------------------------------------------
    //  ENTRY POINT: Jalankan clustering & simpan ke DB
    // --------------------------------------------------------
    public function run(): array {
        // Ambil data RFM yang sudah dikalkulasi
        $rows = $this->db->query("
            SELECT customer_id, recency_days, frequency, monetary
            FROM analytics_rfm
        ")->fetchAll();

        if (count($rows) < $this->k) {
            return ['success' => false, 'message' => 'Data terlalu sedikit untuk clustering'];
        }

        // Normalisasi fitur ke [0,1]
        $data       = $this->normalize($rows);
        $centroids  = $this->initCentroids($data);
        $assignments = [];

        for ($iter = 0; $iter < $this->maxIter; $iter++) {
            $newAssignments = $this->assign($data, $centroids);
            if ($newAssignments === $assignments) break; // Konvergen
            $assignments = $newAssignments;
            $centroids   = $this->updateCentroids($data, $assignments);
        }

        // Beri label: klaster dengan monetary tertinggi = VIP
        $clusterStats = $this->computeClusterStats($rows, $assignments);
        $labelMap     = $this->assignLabels($clusterStats);

        // Simpan ke database
        $this->saveResults($rows, $assignments, $centroids, $labelMap);

        return [
            'success'       => true,
            'k'             => $this->k,
            'iterations'    => $iter,
            'cluster_stats' => $clusterStats,
        ];
    }

    // --------------------------------------------------------
    //  Normalisasi Min-Max ke [0,1]
    // --------------------------------------------------------
    private function normalize(array $rows): array {
        $rAll = array_column($rows, 'recency_days');
        $fAll = array_column($rows, 'frequency');
        $mAll = array_column($rows, 'monetary');

        $rMin = min($rAll); $rMax = max($rAll);
        $fMin = min($fAll); $fMax = max($fAll);
        $mMin = min($mAll); $mMax = max($mAll);

        $normalized = [];
        foreach ($rows as $row) {
            // Recency dibalik: semakin kecil nilainya semakin bagus
            $normalized[] = [
                'customer_id' => $row['customer_id'],
                'r' => $rMax > $rMin ? 1 - ($row['recency_days'] - $rMin) / ($rMax - $rMin) : 0,
                'f' => $fMax > $fMin ? ($row['frequency']    - $fMin) / ($fMax - $fMin) : 0,
                'm' => $mMax > $mMin ? ($row['monetary']      - $mMin) / ($mMax - $mMin) : 0,
            ];
        }
        return $normalized;
    }

    // --------------------------------------------------------
    //  Init K centroid awal (metode K-Means++)
    // --------------------------------------------------------
    private function initCentroids(array $data): array {
        $centroids = [];
        $idx       = array_rand($data);
        $centroids[] = ['r' => $data[$idx]['r'], 'f' => $data[$idx]['f'], 'm' => $data[$idx]['m']];

        for ($i = 1; $i < $this->k; $i++) {
            $distances = [];
            foreach ($data as $point) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($centroids as $c) {
                    $d = $this->euclidean($point, $c);
                    if ($d < $minDist) $minDist = $d;
                }
                $distances[] = $minDist * $minDist;
            }
            $total = array_sum($distances);
            $rand  = mt_rand() / mt_getrandmax() * $total;
            $cum   = 0;
            foreach ($distances as $j => $d) {
                $cum += $d;
                if ($cum >= $rand) {
                    $centroids[] = ['r' => $data[$j]['r'], 'f' => $data[$j]['f'], 'm' => $data[$j]['m']];
                    break;
                }
            }
        }
        return $centroids;
    }

    // --------------------------------------------------------
    //  Assign setiap titik ke centroid terdekat
    // --------------------------------------------------------
    private function assign(array $data, array $centroids): array {
        $assignments = [];
        foreach ($data as $point) {
            $minDist  = PHP_FLOAT_MAX;
            $minClust = 0;
            foreach ($centroids as $idx => $c) {
                $d = $this->euclidean($point, $c);
                if ($d < $minDist) {
                    $minDist  = $d;
                    $minClust = $idx;
                }
            }
            $assignments[] = $minClust;
        }
        return $assignments;
    }

    // --------------------------------------------------------
    //  Update centroid = rata-rata titik dalam klaster
    // --------------------------------------------------------
    private function updateCentroids(array $data, array $assignments): array {
        $sums   = array_fill(0, $this->k, ['r'=>0,'f'=>0,'m'=>0,'count'=>0]);
        foreach ($data as $i => $point) {
            $k = $assignments[$i];
            $sums[$k]['r'] += $point['r'];
            $sums[$k]['f'] += $point['f'];
            $sums[$k]['m'] += $point['m'];
            $sums[$k]['count']++;
        }

        $centroids = [];
        foreach ($sums as $s) {
            $n = max($s['count'], 1);
            $centroids[] = ['r' => $s['r']/$n, 'f' => $s['f']/$n, 'm' => $s['m']/$n];
        }
        return $centroids;
    }

    // --------------------------------------------------------
    //  Hitung statistik per klaster dari data original
    // --------------------------------------------------------
    private function computeClusterStats(array $rows, array $assignments): array {
        $stats = array_fill(0, $this->k, ['count'=>0,'avg_r'=>0,'avg_f'=>0,'avg_m'=>0]);
        foreach ($rows as $i => $row) {
            $k = $assignments[$i];
            $stats[$k]['count']++;
            $stats[$k]['avg_r'] += $row['recency_days'];
            $stats[$k]['avg_f'] += $row['frequency'];
            $stats[$k]['avg_m'] += $row['monetary'];
        }
        foreach ($stats as &$s) {
            $n = max($s['count'], 1);
            $s['avg_r'] = round($s['avg_r']/$n, 2);
            $s['avg_f'] = round($s['avg_f']/$n, 2);
            $s['avg_m'] = round($s['avg_m']/$n, 2);
        }
        return $stats;
    }

    // --------------------------------------------------------
    //  Assign label berdasarkan avg_m tertinggi = VIP
    // --------------------------------------------------------
    private function assignLabels(array $clusterStats): array {
        $sorted = $clusterStats;
        arsort($sorted); // sort by avg_m desc
        $labelMap = [];
        $i = 0;
        foreach ($sorted as $clusterId => $_) {
            $labelMap[$clusterId] = $this->clusterLabels[$i] ?? "Cluster $i";
            $i++;
        }
        return $labelMap;
    }

    // --------------------------------------------------------
    //  Simpan hasil ke DB
    // --------------------------------------------------------
    private function saveResults(array $rows, array $assignments, array $centroids, array $labelMap): void {
        $this->db->exec("TRUNCATE TABLE clustering_customer_groups");

        $stmt = $this->db->prepare("
            INSERT INTO clustering_customer_groups 
            (cluster_id, customer_id, cluster_label, centroid_r, centroid_f, centroid_m)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $updateCust = $this->db->prepare("UPDATE customers SET cluster_id=? WHERE customer_id=?");

        foreach ($rows as $i => $row) {
            $clustId = $assignments[$i];
            $label   = $labelMap[$clustId] ?? 'Unknown';
            $c       = $centroids[$clustId];
            $stmt->execute([$clustId, $row['customer_id'], $label, $c['r'], $c['f'], $c['m']]);
            $updateCust->execute([$clustId, $row['customer_id']]);
        }
    }

    // --------------------------------------------------------
    //  Euclidean distance antara dua titik 3D
    // --------------------------------------------------------
    private function euclidean(array $a, array $b): float {
        return sqrt(pow($a['r']-$b['r'],2) + pow($a['f']-$b['f'],2) + pow($a['m']-$b['m'],2));
    }

    // --------------------------------------------------------
    //  Ambil hasil clustering untuk tampilan dashboard
    // --------------------------------------------------------
    public function getClusterSummary(): array {
        return $this->db->query("
            SELECT 
                ccg.cluster_id,
                ccg.cluster_label,
                COUNT(*) AS jumlah_pelanggan,
                ROUND(AVG(r.recency_days),1) AS avg_recency,
                ROUND(AVG(r.frequency),1)    AS avg_frequency,
                ROUND(AVG(r.monetary),2)     AS avg_monetary
            FROM clustering_customer_groups ccg
            JOIN analytics_rfm r ON ccg.customer_id = r.customer_id
            GROUP BY ccg.cluster_id, ccg.cluster_label
            ORDER BY avg_monetary DESC
        ")->fetchAll();
    }
}

<?php
// Quick test: simulate datamining query logic
$db = new PDO('mysql:host=localhost;dbname=online_retail_bi', 'root', '');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

try {
    $rfmSample = $db->query("
        SELECT customer_id, recency_days, frequency, monetary,
               CONCAT(r_score, f_score, m_score) AS rfm_score, rfm_segment
        FROM analytics_rfm ORDER BY monetary DESC LIMIT 3
    ")->fetchAll();
    echo "[RFM OK] " . count($rfmSample) . " rows\n";
    print_r($rfmSample[0] ?? []);

    $abcSample = $db->query("
        SELECT stock_code, description, total_revenue, abc_class
        FROM mining_product_abc ORDER BY total_revenue DESC LIMIT 3
    ")->fetchAll();
    echo "\n[ABC OK] " . count($abcSample) . " rows\n";

    $clusterSummary = $db->query("
        SELECT cluster_id, cluster_label, COUNT(*) AS cnt,
               ROUND(AVG(centroid_m), 2) AS avg_monetary,
               ROUND(AVG(centroid_f), 2) AS avg_frequency
        FROM clustering_customer_groups
        GROUP BY cluster_id, cluster_label ORDER BY cluster_id
    ")->fetchAll();
    echo "\n[CLUSTER OK] " . count($clusterSummary) . " clusters\n";
    print_r($clusterSummary[0] ?? []);

    echo "\n\nAll queries PASSED!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

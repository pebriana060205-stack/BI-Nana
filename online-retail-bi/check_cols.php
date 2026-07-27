<?php
$db = new PDO('mysql:host=localhost;dbname=online_retail_bi', 'root', '');

echo "=== analytics_rfm ===\n";
foreach($db->query('DESCRIBE analytics_rfm')->fetchAll(PDO::FETCH_ASSOC) as $c)
    echo $c['Field'].' | '.$c['Type']."\n";

echo "\n=== mining_product_abc ===\n";
foreach($db->query('DESCRIBE mining_product_abc')->fetchAll(PDO::FETCH_ASSOC) as $c)
    echo $c['Field'].' | '.$c['Type']."\n";

echo "\n=== clustering_customer_groups ===\n";
foreach($db->query('DESCRIBE clustering_customer_groups')->fetchAll(PDO::FETCH_ASSOC) as $c)
    echo $c['Field'].' | '.$c['Type']."\n";

<?php
$db = new PDO('mysql:host=localhost;dbname=online_retail_bi', 'root', '');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "=== mining_association_rules columns ===\n";
foreach ($db->query('DESCRIBE mining_association_rules')->fetchAll() as $c)
    echo $c['Field'] . ' | ' . $c['Type'] . "\n";

echo "\n=== transaction_items ===\n";
echo "Total rows: " . $db->query('SELECT COUNT(*) FROM transaction_items')->fetchColumn() . "\n";
echo "Distinct tx: " . $db->query('SELECT COUNT(DISTINCT transaction_id) FROM transaction_items')->fetchColumn() . "\n";

echo "\n=== Sample transaction_items ===\n";
foreach ($db->query('SELECT * FROM transaction_items LIMIT 5')->fetchAll() as $r)
    print_r($r);

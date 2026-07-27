<?php
$db = new PDO('mysql:host=localhost;dbname=online_retail_bi', 'root', '');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach (['customers','transactions','transaction_items','products','countries'] as $tbl) {
    echo "\n=== $tbl ===\n";
    foreach ($db->query("DESCRIBE $tbl")->fetchAll() as $c)
        echo "  {$c['Field']} | {$c['Type']} | {$c['Null']} | {$c['Key']}\n";
}

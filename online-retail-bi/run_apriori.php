<?php
// ============================================================
//  Apriori Association Rules Generator
//  - Reads transaction_items from MySQL
//  - Generates frequent itemsets (1-item & 2-item)
//  - Calculates Support, Confidence, Lift
//  - Inserts rules into mining_association_rules
// ============================================================

set_time_limit(300);
ini_set('memory_limit', '256M');

$db = new PDO('mysql:host=localhost;dbname=online_retail_bi', 'root', '');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ── Config ───────────────────────────────────────────────────
$MIN_SUPPORT    = 0.02;  // item pair must appear in ≥2% of transactions
$MIN_CONFIDENCE = 0.30;  // P(B|A) ≥ 30%
$MIN_LIFT       = 1.0;   // only positive associations

echo "=== Apriori Association Rules Generator ===\n";
echo "Min Support: " . ($MIN_SUPPORT * 100) . "%\n";
echo "Min Confidence: " . ($MIN_CONFIDENCE * 100) . "%\n";
echo "Min Lift: $MIN_LIFT\n\n";

// ── Step 1: Load transactions ────────────────────────────────
echo "[1] Loading transactions from database...\n";

$rows = $db->query("
    SELECT ti.transaction_id, ti.stock_code, p.description
    FROM transaction_items ti
    LEFT JOIN products p ON p.stock_code = ti.stock_code
    WHERE ti.quantity > 0
    ORDER BY ti.transaction_id
")->fetchAll();

// Build basket: transaction_id => [stock_codes]
$baskets = [];
$itemNames = [];
foreach ($rows as $r) {
    $txId = $r['transaction_id'];
    $code = $r['stock_code'];
    $baskets[$txId][] = $code;
    $itemNames[$code] = $r['description'] ?? $code;
}

$totalTx = count($baskets);
echo "  → $totalTx transactions loaded\n";

// ── Step 2: Frequent 1-itemsets ──────────────────────────────
echo "[2] Calculating frequent 1-itemsets...\n";

$itemCount = [];
foreach ($baskets as $items) {
    $uniqueItems = array_unique($items);
    foreach ($uniqueItems as $item) {
        $itemCount[$item] = ($itemCount[$item] ?? 0) + 1;
    }
}

$freqItems = [];
foreach ($itemCount as $item => $count) {
    $support = $count / $totalTx;
    if ($support >= $MIN_SUPPORT) {
        $freqItems[$item] = $support;
    }
}
$freqItemKeys = array_keys($freqItems);
echo "  → " . count($freqItems) . " frequent items (support ≥ {$MIN_SUPPORT})\n";

// ── Step 3: Frequent 2-itemsets (pairs) ──────────────────────
echo "[3] Calculating frequent 2-itemsets (pairs)...\n";

$pairCount = [];
foreach ($baskets as $items) {
    $uniqueItems = array_values(array_unique($items));
    // Only consider items that are already frequent
    $freqInBasket = array_filter($uniqueItems, fn($i) => isset($freqItems[$i]));
    $freqInBasket = array_values($freqInBasket);

    $n = count($freqInBasket);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $a = $freqInBasket[$i];
            $b = $freqInBasket[$j];
            $key = $a < $b ? "$a|||$b" : "$b|||$a";
            $pairCount[$key] = ($pairCount[$key] ?? 0) + 1;
        }
    }
}

$freqPairs = [];
foreach ($pairCount as $key => $count) {
    $support = $count / $totalTx;
    if ($support >= $MIN_SUPPORT) {
        $freqPairs[$key] = ['count' => $count, 'support' => $support];
    }
}
echo "  → " . count($freqPairs) . " frequent pairs\n";

// ── Step 4: Generate Association Rules ───────────────────────
echo "[4] Generating association rules...\n";

$rules = [];
foreach ($freqPairs as $key => $pairData) {
    [$a, $b] = explode('|||', $key);

    // Rule A → B
    $confAB   = $pairData['count'] / $itemCount[$a];
    $liftAB   = $confAB / $freqItems[$b];
    if ($confAB >= $MIN_CONFIDENCE && $liftAB >= $MIN_LIFT) {
        $rules[] = [
            'antecedent' => $itemNames[$a] ?? $a,
            'consequent' => $itemNames[$b] ?? $b,
            'support'    => round($pairData['support'], 4),
            'confidence' => round($confAB, 4),
            'lift'       => round($liftAB, 4),
        ];
    }

    // Rule B → A
    $confBA   = $pairData['count'] / $itemCount[$b];
    $liftBA   = $confBA / $freqItems[$a];
    if ($confBA >= $MIN_CONFIDENCE && $liftBA >= $MIN_LIFT) {
        $rules[] = [
            'antecedent' => $itemNames[$b] ?? $b,
            'consequent' => $itemNames[$a] ?? $a,
            'support'    => round($pairData['support'], 4),
            'confidence' => round($confBA, 4),
            'lift'       => round($liftBA, 4),
        ];
    }
}

// Sort by lift descending
usort($rules, fn($a, $b) => $b['lift'] <=> $a['lift']);
echo "  → " . count($rules) . " rules generated\n";

// ── Step 5: Save to database ─────────────────────────────────
echo "[5] Saving rules to database...\n";

$db->exec("DELETE FROM mining_association_rules");
$inserted = 0;

if (!empty($rules)) {
    $stmt = $db->prepare("
        INSERT INTO mining_association_rules 
            (antecedent, consequent, support, confidence, lift)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($rules as $r) {
        // Truncate to fit varchar(100)
        $ant = mb_substr($r['antecedent'], 0, 95);
        $con = mb_substr($r['consequent'], 0, 95);
        $stmt->execute([$ant, $con, $r['support'], $r['confidence'], $r['lift']]);
        $inserted++;
    }
}

echo "  → $inserted rules saved to mining_association_rules\n";

// ── Preview top 10 ───────────────────────────────────────────
echo "\n=== TOP 10 ASSOCIATION RULES BY LIFT ===\n";
$preview = $db->query("
    SELECT antecedent, consequent, support, confidence, lift
    FROM mining_association_rules ORDER BY lift DESC LIMIT 10
")->fetchAll();

foreach ($preview as $i => $r) {
    printf(
        "%2d. [%.4f | %.2f%% | Lift %.3f]  %s → %s\n",
        $i + 1,
        $r['support'],
        $r['confidence'] * 100,
        $r['lift'],
        mb_substr($r['antecedent'], 0, 40),
        mb_substr($r['consequent'], 0, 40)
    );
}

echo "\n✅ Selesai! Total " . count($rules) . " aturan asosiasi berhasil disimpan.\n";

<?php
require_once __DIR__ . '/../includes/config.php';
$stmt = $pdo->query("SELECT id, title, value, currency, stage, is_archived FROM deals");
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- ALL DEALS IN DATABASE ---\n";
foreach ($deals as $d) {
    echo "ID: {$d['id']} | Title: {$d['title']} | Value: {$d['value']} | Currency: '{$d['currency']}' | Stage: {$d['stage']} | Archived: {$d['is_archived']}\n";
}

echo "\n--- SUMS FOR CLOSED WON DEALS ---\n";
$wonUsd = 0;
$wonNgn = 0;
foreach ($deals as $d) {
    if ($d['stage'] === 'closed_won' && $d['is_archived'] == 0) {
        $c = strtoupper(trim($d['currency'] ?? 'USD'));
        if (strpos($c, 'NGN') !== false || strpos($c, '₦') !== false) {
            $wonNgn += floatval($d['value']);
        } else {
            $wonUsd += floatval($d['value']);
        }
    }
}
echo "Won USD Sum: $" . number_format($wonUsd, 2) . "\n";
echo "Won NGN Sum: ₦" . number_format($wonNgn, 2) . "\n";

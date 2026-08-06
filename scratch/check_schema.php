<?php
require_once __DIR__ . '/../includes/config.php';
$tables = ['leads', 'deals', 'customers', 'products', 'tasks', 'users'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    $stmt = $pdo->query("DESCRIBE $t");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']}) Null:{$row['Null']} Default:{$row['Default']}\n";
    }
    echo "\n";
}

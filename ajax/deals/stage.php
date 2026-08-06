<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('edit_deals')) sendResponse(false, 'Forbidden');

$input = getJsonInput();
$dealId = $input['deal_id'] ?? $_POST['deal_id'] ?? null;
$stage = $input['stage'] ?? $_POST['stage'] ?? null;

$validStages = ['qualification', 'value_proposition', 'proposal_quote', 'negotiation_review', 'closed_won', 'closed_lost'];

if (!$dealId || !$stage || !in_array($stage, $validStages)) {
    sendResponse(false, 'Invalid deal ID or stage value');
}

try {
    $stmt = $pdo->prepare("UPDATE deals SET stage = ? WHERE id = ?");
    $stmt->execute([$stage, $dealId]);
    sendResponse(true, 'Stage updated successfully', ['deal_id' => $dealId, 'stage' => $stage]);
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}

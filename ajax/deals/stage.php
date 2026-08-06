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
    // Fetch deal info prior to update
    $dealStmt = $pdo->prepare("SELECT id, title, assigned_to, value, currency FROM deals WHERE id = ?");
    $dealStmt->execute([$dealId]);
    $deal = $dealStmt->fetch(PDO::FETCH_ASSOC);

    if (!$deal) {
        sendResponse(false, 'Deal not found');
    }

    $stmt = $pdo->prepare("UPDATE deals SET stage = ? WHERE id = ?");
    $stmt->execute([$stage, $dealId]);

    // Trigger Notification & Email Alert
    $stageName = ucfirst(str_replace('_', ' ', $stage));
    $currSymbol = (strpos(strtoupper($deal['currency'] ?? ''), 'NGN') !== false || strpos($deal['currency'] ?? '', '₦') !== false) ? '₦' : '$';
    $formattedVal = $currSymbol . ' ' . number_format($deal['value'] ?? 0, 2);

    if ($deal['assigned_to']) {
        if ($stage === 'closed_won') {
            createNotification($pdo, $deal['assigned_to'], '🎉 Deal Won!', "Opportunity '{$deal['title']}' ({$formattedVal}) has been marked Closed Won!", '/crm/deals', 'success', true);
        } else if ($stage === 'closed_lost') {
            createNotification($pdo, $deal['assigned_to'], 'Deal Marked Closed Lost', "Opportunity '{$deal['title']}' was marked Closed Lost.", '/crm/deals', 'warning', true);
        } else {
            createNotification($pdo, $deal['assigned_to'], 'Deal Stage Advanced', "Opportunity '{$deal['title']}' advanced to stage: {$stageName}.", '/crm/deals', 'info', true);
        }
    }

    sendResponse(true, 'Stage updated successfully', ['deal_id' => $dealId, 'stage' => $stage]);
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}

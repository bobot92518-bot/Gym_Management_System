<?php
// admin/get_pending_subscriptions.php - API endpoint
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    if (!isset($_GET['member_id'])) {
        throw new Exception('Member ID required');
    }

    $member_id = intval($_GET['member_id']);
    $db = new Database();
    $conn = $db->connect();

    // Get pending subscriptions (status = 'Pending')
    $stmt = $conn->prepare("
            SELECT 
                s.id, 
                p.price AS amount_due,
                s.end_date,
                p.plan_name
        FROM subscriptions s
        JOIN membership_plans p ON s.plan_id = p.id
        WHERE s.member_id = ? AND s.status = ''
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$member_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['subscriptions' => $subscriptions]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

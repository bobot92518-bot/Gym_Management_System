<?php
header('Content-Type: application/json');

require_once '../config.php'; // adjust path if needed

try {
    if (!isset($_GET['id'])) {
        echo json_encode(['error' => 'No member ID provided']);
        exit;
    }

    $id = intval($_GET['id']);

    $db = new Database();
    $conn = $db->connect();

    $stmt = $conn->prepare("SELECT id, member_id, first_name, last_name FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        echo json_encode($member);
    } else {
        echo json_encode(['error' => 'Member not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

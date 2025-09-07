<?php
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$attachment_id = $input['attachment_id'] ?? null;
if (!$attachment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing attachment_id']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Load attachment
    $stmt = $db->prepare('SELECT * FROM Attachment WHERE Attachment_ID = ?');
    $stmt->execute([$attachment_id]);
    $att = $stmt->fetch();
    if (!$att) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Attachment not found']);
        exit();
    }

    // Verify item ownership
    $stmt = $db->prepare('SELECT Creator_ID FROM Item WHERE Item_ID = ?');
    $stmt->execute([$att['Item_ID']]);
    $item = $stmt->fetch();
    if (!$item || $item['Creator_ID'] !== $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized to delete this attachment']);
        exit();
    }

    // Remove file safely if it's inside uploads directory
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    $fileUrl = $att['File_URL'];
    // Construct absolute path from stored File_URL which may be relative
    $candidate = realpath(__DIR__ . '/../' . ltrim($fileUrl, '/\\'));
    if ($candidate && $uploadsDir && strpos($candidate, $uploadsDir) === 0 && is_file($candidate)) {
        @unlink($candidate);
    }

    // Delete DB record
    $stmt = $db->prepare('DELETE FROM Attachment WHERE Attachment_ID = ?');
    $stmt->execute([$attachment_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>

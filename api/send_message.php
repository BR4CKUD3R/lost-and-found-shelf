<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Checking if this is a multipart form request (with attachments)
if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    // Handle form data with attachments
    $conversation_id = $_POST['conversation_id'] ?? '';
    $message_text = trim($_POST['message'] ?? '');
    $attachments = $_FILES['attachments'] ?? [];
} else {
    // Handle JSON request (text only)
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = $input['conversation_id'] ?? '';
    $message_text = trim($input['message'] ?? '');
    $attachments = [];
}

if (empty($conversation_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing conversation ID']);
    exit();
}

if (empty($message_text) && empty($attachments)) {
    echo json_encode(['success' => false, 'message' => 'Message or attachment required']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify user is part of this conversation
    $stmt = $db->prepare("SELECT * FROM Conversation WHERE Conversation_ID = ? AND (Participant1_ID = ? OR Participant2_ID = ?)");
    $stmt->execute([$conversation_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation not found']);
        exit();
    }

    // Determine message type
    $message_type = 'text';
    if (!empty($attachments) && is_array($attachments['name'])) {
        $message_type = 'file';
    }

    // Insert message
    $message_id = 'msg_' . uniqid();
    $stmt = $db->prepare("INSERT INTO Chat_Message (Message_ID, Conversation_ID, Sender_ID, Message_Text, Message_Type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$message_id, $conversation_id, $_SESSION['user_id'], $message_text, $message_type]);

    // Handle file attachments
    if (!empty($attachments) && is_array($attachments['name'])) {
        $upload_dir = '../uploads/chat/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        for ($i = 0; $i < count($attachments['name']); $i++) {
            if ($attachments['error'][$i] == 0) {
                $file_name = $attachments['name'][$i];
                $file_tmp = $attachments['tmp_name'][$i];
                $file_type = $attachments['type'][$i];
                $file_size = $attachments['size'][$i];

                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_name;

                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Insert chat attachment record
                    $attachment_id = 'chat_att_' . uniqid();
                    $stmt = $db->prepare("INSERT INTO Chat_Attachment (Attachment_ID, Message_ID, File_URL, File_Name, File_Type, File_Size) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$attachment_id, $message_id, $file_path, $file_name, $file_type, $file_size]);
                }
            }
        }
    }

    // Update conversation timestamp
    $stmt = $db->prepare("UPDATE Conversation SET Updated_At = CURRENT_TIMESTAMP WHERE Conversation_ID = ?");
    $stmt->execute([$conversation_id]);

    // Create notification for the other participant
    $other_participant_id = ($conversation['Participant1_ID'] == $_SESSION['user_id']) ? $conversation['Participant2_ID'] : $conversation['Participant1_ID'];
    $notification_id = 'notif_' . uniqid();

    $notification_message = !empty($attachments) ? 'You received a new message with attachments.' : 'You received a new message in your conversation.';
    $stmt = $db->prepare("INSERT INTO Notification (Notification_ID, User_ID, Item_ID, Type, Title, Message) VALUES (?, ?, ?, 'message_received', 'New Message', ?)");
    $stmt->execute([$notification_id, $other_participant_id, $conversation['Item_ID'], $notification_message]);

    echo json_encode(['success' => true, 'message_id' => $message_id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
}

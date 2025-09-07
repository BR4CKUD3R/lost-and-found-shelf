<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json'); // ? server response to the JSON formatted strings

if (!isset($_SESSION['user_id'])) {              // checking if user is logged in or not
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['conversation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing conversation ID']);
    exit();         //  terminating the script immediately so that it prevents any further code from executing when the authentication check fails.
}

$conversation_id = $_GET['conversation_id'];

// ! try block is when potential errors or any exceptions may occur during the execution. If an exception is found then execution jumps to the catch block.

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verifing user based on item for conversation
    $stmt = $db->prepare("SELECT * 
                        FROM Conversation 
                        WHERE Conversation_ID = ? AND (Participant1_ID = ? OR Participant2_ID = ?)");
    $stmt->execute([$conversation_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $conversation = $stmt->fetch(); // * Fetch() method retrieves a single row from the result set

    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation not found']);
        exit();
    }

    // Get messages with attachments
    $stmt = $db->prepare("SELECT Message_ID, Sender_ID, Message_Text, Message_Type, Sent_At FROM Chat_Message WHERE Conversation_ID = ? ORDER BY Sent_At ASC");
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll();

    // Get attachments for each message
    foreach ($messages as &$message) {      // ! Here '&' is used to pass by reference so that any changes made to $message inside the loop will directly affect the corresponding element in our $messages array.
        $stmt = $db->prepare("SELECT File_URL, File_Name, File_Type FROM Chat_Attachment WHERE Message_ID = ?");
        $stmt->execute([$message['Message_ID']]);
        $message['attachments'] = $stmt->fetchAll(); //
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching messages: ' . $e->getMessage()]);
}

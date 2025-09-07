<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$claim_id = $_GET['id'];
$action = $_GET['action'];

// ! Claimable item details
$stmt = $db->prepare("SELECT c.*, i.Creator_ID, i.Description, u.Name as Claimant_Name
                      FROM Claim c
                      LEFT JOIN Item i ON c.Item_ID = i.Item_ID
                      LEFT JOIN User u ON c.User_ID = u.User_ID
                      WHERE c.Claim_ID = ?");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();

if (!$claim || $claim['Creator_ID'] != $_SESSION['user_id']) {
    header("Location: index.php");
    exit();
}

if ($action == 'approve') {
    $stmt = $db->prepare("UPDATE Claim SET Claim_Status = 'approved' WHERE Claim_ID = ?");
    $stmt->execute([$claim_id]);

    // ! Item status update
    $stmt = $db->prepare("UPDATE Item SET Status = 'claimed' WHERE Item_ID = ?");
    $stmt->execute([$claim['Item_ID']]);

    // TODO: Reject other pending claims for current item.
    $stmt = $db->prepare("UPDATE Claim SET Claim_Status = 'rejected' WHERE Item_ID = ? AND Claim_ID != ?");
    $stmt->execute([$claim['Item_ID'], $claim_id]);

    // ! Notification for claimant.
    $notif_id = uniqid('notif_', true);
    $message = "Your claim has been approved! You can now collect your item.";
    $stmt = $db->prepare("INSERT INTO Notification (Notification_ID, User_ID, Item_ID, Type, Message, Is_Read, Created_At) VALUES (?, ?, ?, 'claim_approved', ?, 0, NOW())");
    $stmt->execute([$notif_id, $claim['User_ID'], $claim['Item_ID'], $message]);

    $_SESSION['message'] = 'Claim approved successfully!';
} elseif ($action == 'reject') {
    $stmt = $db->prepare("UPDATE Claim SET Claim_Status = 'rejected' WHERE Claim_ID = ?");
    $stmt->execute([$claim_id]);

    // ! Notification for claimant.
    $notif_id = uniqid('notif_', true);
    $message = "Your claim has been rejected. Please contact the item owner if you believe this is an error.";
    $stmt = $db->prepare("INSERT INTO Notification (Notification_ID, User_ID, Item_ID, Type, Message, Is_Read, Created_At) VALUES (?, ?, ?, 'claim_rejected', ?, 0, NOW())");
    $stmt->execute([$notif_id, $claim['User_ID'], $claim['Item_ID'], $message]);

    $_SESSION['message'] = 'Claim rejected.';
}

header("Location: view_item.php?id=" . $claim['Item_ID']);
exit();

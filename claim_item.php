<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$item_id = $_GET['id'];

// Get item details
$stmt = $db->prepare("SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name
                      FROM Item i
                      LEFT JOIN Category c ON i.Category_ID = c.Category_ID
                      LEFT JOIN Location l ON i.Location_ID = l.Location_ID
                      LEFT JOIN User u ON i.Creator_ID = u.User_ID
                      WHERE i.Item_ID = ? AND i.Status = 'reported'");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item || $item['Creator_ID'] == $_SESSION['user_id']) {
    header("Location: browse_items.php");
    exit();
}

// Check if already claimed
$stmt = $db->prepare("SELECT * FROM Claim WHERE Item_ID = ? AND User_ID = ?");
$stmt->execute([$item_id, $_SESSION['user_id']]);
if ($stmt->fetch()) {
    header("Location: view_item.php?id=" . $item_id);
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $answer1 = trim($_POST['answer1']);
    $answer2 = trim($_POST['answer2']);
    $answer3 = trim($_POST['answer3']);

    if (empty($answer1) || empty($answer2) || empty($answer3)) {
        $error = 'Please answer all verification questions.';
    } else {
        // Create claim
        $claim_id = uniqid('claim_', true);
        $claim_date = date('Y-m-d H:i:s');

        $stmt = $db->prepare("INSERT INTO Claim (Claim_ID, Item_ID, User_ID, Claim_Status, Claim_Date) VALUES (?, ?, ?, 'pending', ?)");

        if ($stmt->execute([$claim_id, $item_id, $_SESSION['user_id'], $claim_date])) {
            // Add verification questions
            $questions = [
                "What specific details can you provide about this item that would prove it's yours?",
                "Where and when did you lose this item?",
                "Are there any unique marks, scratches, or identifiers on this item?"
            ];

            $answers = [$answer1, $answer2, $answer3];

            for ($i = 0; $i < 3; $i++) {
                $question_id = uniqid('q_', true);
                $stmt = $db->prepare("INSERT INTO Verification_Question (Question_ID, Claim_ID, Question_Text, Answer_Text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$question_id, $claim_id, $questions[$i], $answers[$i]]);
            }

            // Create conversation between claimer and item owner
            $conversation_id = 'conv_' . uniqid();
            $stmt = $db->prepare("INSERT INTO Conversation (Conversation_ID, Item_ID, Claim_ID, Participant1_ID, Participant2_ID, Status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$conversation_id, $item_id, $claim_id, $item['Creator_ID'], $_SESSION['user_id']]);

            // Create notification for item owner
            $notif_id = 'notif_' . uniqid();
            $message = "Someone has claimed your item: " . substr($item['Description'], 0, 50) . "...";
            $stmt = $db->prepare("INSERT INTO Notification (Notification_ID, User_ID, Item_ID, Type, Title, Message, Is_Read, Created_At) VALUES (?, ?, ?, 'claim_received', 'New Claim Received', ?, 0, ?)");
            $stmt->execute([$notif_id, $item['Creator_ID'], $item_id, $message, $claim_date]);

            $success = 'Your claim has been submitted successfully! You can now chat with the item owner.';
        } else {
            $error = 'Failed to submit claim. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Item - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Claim Item</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center mt-3">
                                <a href="chat.php" class="btn btn-primary">Start Chat</a>
                                <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn btn-outline-primary">View Item</a>
                                <a href="browse_items.php" class="btn btn-secondary">Back to Browse</a>
                            </div>
                        <?php else: ?>

                            <!-- Item Summary -->
                            <div class="bg-light p-3 rounded mb-4">
                                <h5>Item: <?php echo htmlspecialchars(substr($item['Description'], 0, 100)); ?>...</h5>
                                <p class="mb-0">
                                    <strong>Category:</strong> <?php echo htmlspecialchars($item['Category_Name']); ?> |
                                    <strong>Location:</strong> <?php echo htmlspecialchars($item['Location_Name']); ?>
                                </p>
                            </div>

                            <div class="alert alert-info">
                                <strong>Verification Required</strong><br>
                                To claim this item, please answer the following questions to verify. The item
                                owner will review your responses before approving the claim.
                            </div>

                            <form method="POST">
                                <div class="mb-4">
                                    <label for="answer1" class="form-label"><strong>Question 1:</strong> What specific
                                        details can you provide about the item that would prove it's you have found or lost it?</label>
                                    <textarea class="form-control" id="answer1" name="answer1" rows="3"
                                        placeholder="Provide specific details like brand, model, color, size, unique features, etc."
                                        required><?php echo isset($_POST['answer1']) ? htmlspecialchars($_POST['answer1']) : ''; ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="answer2" class="form-label"><strong>Question 2:</strong> Where and when did
                                        you Find / Lose this item?</label>
                                    <textarea class="form-control" id="answer2" name="answer2" rows="3"
                                        placeholder="Describe when and where you lost the item, what you were doing, etc."
                                        required><?php echo isset($_POST['answer2']) ? htmlspecialchars($_POST['answer2']) : ''; ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="answer3" class="form-label"><strong>Question 3:</strong> What other info can you specify that is already not in the website or image ?</label>
                                    <textarea class="form-control" id="answer3" name="answer3" rows="3"
                                        placeholder="Describe any scratches, dents, stickers, engravings, or other unique identifiers..."
                                        required><?php echo isset($_POST['answer3']) ? htmlspecialchars($_POST['answer3']) : ''; ?></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="view_item.php?id=<?php echo $item_id; ?>"
                                        class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-success">Submit Claim</button>
                                </div>
                            </form>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

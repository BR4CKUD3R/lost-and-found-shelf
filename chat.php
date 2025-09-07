<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ! Query to get user's conversations from DB
$stmt = $db->prepare("SELECT c.*, i.Description as Item_Description, i.Item_Type, i.Status as Item_Status,
                             CASE 
                                 WHEN c.Participant1_ID = ? THEN p2.Name 
                                 ELSE p1.Name 
                             END as Other_Participant_Name,
                             CASE 
                                 WHEN c.Participant1_ID = ? THEN p2.User_ID 
                                 ELSE p1.User_ID 
                             END as Other_Participant_ID,
                             (SELECT COUNT(*) FROM Chat_Message cm WHERE cm.Conversation_ID = c.Conversation_ID AND cm.Sender_ID != ? AND cm.Is_Read = FALSE) as Unread_Count,
                             (SELECT cm.Message_Text FROM Chat_Message cm WHERE cm.Conversation_ID = c.Conversation_ID ORDER BY cm.Sent_At DESC LIMIT 1) as Last_Message,
                             (SELECT cm.Sent_At FROM Chat_Message cm WHERE cm.Conversation_ID = c.Conversation_ID ORDER BY cm.Sent_At DESC LIMIT 1) as Last_Message_Time
                      FROM Conversation c
                      LEFT JOIN Item i ON c.Item_ID = i.Item_ID
                      LEFT JOIN User p1 ON c.Participant1_ID = p1.User_ID
                      LEFT JOIN User p2 ON c.Participant2_ID = p2.User_ID
                      WHERE c.Participant1_ID = ? OR c.Participant2_ID = ?
                      ORDER BY c.Updated_At DESC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$conversations = $stmt->fetchAll();

// ! Getting current conversation when necessary
$current_conversation = null;
$messages = [];
if (isset($_GET['conversation_id'])) {
    $conversation_id = $_GET['conversation_id'];

    // TODO: Query to Verifying other user is if part of same conversation or not
    $stmt = $db->prepare("SELECT c.*, i.Description as Item_Description, i.Item_Type, i.Status as Item_Status,
                                 CASE 
                                     WHEN c.Participant1_ID = ? THEN p2.Name 
                                     ELSE p1.Name 
                                 END as Other_Participant_Name,
                                 CASE 
                                     WHEN c.Participant1_ID = ? THEN p2.User_ID 
                                     ELSE p1.User_ID 
                                 END as Other_Participant_ID
                          FROM Conversation c
                          LEFT JOIN Item i ON c.Item_ID = i.Item_ID
                          LEFT JOIN User p1 ON c.Participant1_ID = p1.User_ID
                          LEFT JOIN User p2 ON c.Participant2_ID = p2.User_ID
                          WHERE c.Conversation_ID = ? AND (c.Participant1_ID = ? OR c.Participant2_ID = ?)");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $conversation_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $current_conversation = $stmt->fetch();

    if ($current_conversation) {
        // ? messages for the conversation
        $stmt = $db->prepare("SELECT cm.*, u.Name as Sender_Name
                              FROM Chat_Message cm
                              LEFT JOIN User u ON cm.Sender_ID = u.User_ID
                              WHERE cm.Conversation_ID = ?
                              ORDER BY cm.Sent_At ASC");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll();

        // * Mark messages as READ
        $stmt = $db->prepare("UPDATE Chat_Message SET Is_Read = TRUE WHERE Conversation_ID = ? AND Sender_ID != ?");
        $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 200px);
            min-height: 600px;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .conversation-list {
            height: 100%;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }

        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .conversation-item:hover {
            background-color: #e9ecef;
        }

        .conversation-item.active {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        .chat-area {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            background-color: white;
            flex-shrink: 0;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background-color: #fafafa;
            min-height: 300px;
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }

        .message.sent .message-bubble {
            background-color: #2196f3;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-bubble {
            background-color: white;
            color: #333;
            border: 1px solid #e1e5e9;
            border-bottom-left-radius: 4px;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .chat-input {
            border-top: 1px solid #dee2e6;
            padding: 1rem;
            background-color: white;
            flex-shrink: 0;
        }

        .chat-input-area {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .message-input-container {
            flex: 1;
            position: relative;
        }

        .attachment-btn {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .attachment-btn:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }

        .send-btn {
            padding: 0.5rem 1rem;
            border: none;
            background-color: #2196f3;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .send-btn:hover {
            background-color: #1976d2;
        }

        .unread-badge {
            background-color: #ff4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
        }

        .attachment-preview {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .attachment-item:last-child {
            margin-bottom: 0;
        }

        .attachment-remove {
            color: #dc3545;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .attachment-remove:hover {
            color: #c82333;
        }

        @media (max-width: 768px) {
            .chat-container {
                height: calc(100vh - 150px);
                min-height: 500px;
            }

            .message-bubble {
                max-width: 85%;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-comments me-2"></i>Chat</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="chat-container">
                            <div class="row g-0 h-100">
                                <!-- Conversation List -->
                                <div class="col-md-4">
                                    <div class="conversation-list">
                                        <?php if (count($conversations) > 0): ?>
                                            <?php foreach ($conversations as $conv): ?>
                                                <div class="conversation-item <?php echo ($current_conversation && $conv['Conversation_ID'] == $current_conversation['Conversation_ID']) ? 'active' : ''; ?>"
                                                    onclick="loadConversation('<?php echo $conv['Conversation_ID']; ?>')">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($conv['Other_Participant_Name']); ?></h6>
                                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars(substr($conv['Item_Description'], 0, 50)) . (strlen($conv['Item_Description']) > 50 ? '...' : ''); ?></p>
                                                            <?php if ($conv['Last_Message']): ?>
                                                                <p class="mb-0 small text-muted"><?php echo htmlspecialchars(substr($conv['Last_Message'], 0, 40)) . (strlen($conv['Last_Message']) > 40 ? '...' : ''); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-end">
                                                            <?php if ($conv['Unread_Count'] > 0): ?>
                                                                <span class="unread-badge"><?php echo $conv['Unread_Count']; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($conv['Last_Message_Time']): ?>
                                                                <small class="text-muted d-block"><?php echo date('M d, g:i A', strtotime($conv['Last_Message_Time'])); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="no-conversation">
                                                <div class="text-center">
                                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                                    <h5>No conversations yet</h5>
                                                    <p>Start a conversation by claiming an item or when someone claims your item.</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Chat Area -->
                                <div class="col-md-8">
                                    <?php if ($current_conversation): ?>
                                        <div class="chat-area">
                                            <!-- Chat Header -->
                                            <div class="chat-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="mb-0"><?php echo htmlspecialchars($current_conversation['Other_Participant_Name']); ?></h5>
                                                        <small class="text-muted"><?php echo htmlspecialchars($current_conversation['Item_Description']); ?></small>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-<?php echo $current_conversation['Item_Status'] == 'reported' ? 'warning' : 'success'; ?>">
                                                            <?php echo ucfirst($current_conversation['Item_Status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Messages -->
                                            <div class="chat-messages" id="chatMessages">
                                                <?php foreach ($messages as $message): ?>
                                                    <?php
                                                    // Attachments for selected users
                                                    $stmt_att = $db->prepare("SELECT * FROM Chat_Attachment WHERE Message_ID = ?");
                                                    $stmt_att->execute([$message['Message_ID']]);
                                                    $message_attachments = $stmt_att->fetchAll();
                                                    ?>
                                                    <div class="message <?php echo $message['Sender_ID'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>">
                                                        <div class="message-bubble">
                                                            <?php if (!empty($message['Message_Text'])): ?>
                                                                <div><?php echo nl2br(htmlspecialchars($message['Message_Text'])); ?></div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($message_attachments)): ?>
                                                                <div class="mt-2">
                                                                    <?php foreach ($message_attachments as $attachment): ?>
                                                                        <div class="attachment-item mb-1">
                                                                            <?php
                                                                            $attachment_url = getImageUrl($attachment['File_URL']);
                                                                            $is_image = strpos($attachment['File_Type'], 'image/') === 0;
                                                                            ?>
                                                                            <?php if ($is_image && $attachment_url): ?>
                                                                                <div class="d-flex align-items-center mb-2">
                                                                                    <img src="<?php echo htmlspecialchars($attachment_url); ?>"
                                                                                        class="me-2"
                                                                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                                                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                                                                    <i class="fas fa-image me-1" style="display: none;"></i>
                                                                                    <a href="<?php echo htmlspecialchars($attachment_url); ?>" target="_blank" class="text-decoration-none">
                                                                                        <?php echo htmlspecialchars($attachment['File_Name']); ?>
                                                                                    </a>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <div class="d-flex align-items-center">
                                                                                    <i class="<?php echo getFileIconClass($attachment['File_Type']); ?> me-1"></i>
                                                                                    <a href="<?php echo htmlspecialchars($attachment_url); ?>" target="_blank" class="text-decoration-none">
                                                                                        <?php echo htmlspecialchars($attachment['File_Name']); ?>
                                                                                    </a>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <div class="message-time">
                                                                <?php echo date('g:i A', strtotime($message['Sent_At'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- Chat Input -->
                                            <div class="chat-input">
                                                <form id="messageForm" enctype="multipart/form-data">
                                                    <div class="chat-input-area">
                                                        <div class="message-input-container">
                                                            <textarea class="form-control" id="messageInput" placeholder="Type your message..." rows="2" required></textarea>
                                                            <div class="attachment-preview" id="attachmentPreview" style="display: none;">
                                                                <div id="attachmentList"></div>
                                                            </div>
                                                        </div>
                                                        <div class="attachment-btn" onclick="document.getElementById('fileInput').click()" title="Attach File">
                                                            <i class="fas fa-paperclip"></i>
                                                        </div>
                                                        <input type="file" id="fileInput" style="display: none;" multiple accept="image/*,.pdf,.doc,.docx,.txt">
                                                        <button type="submit" class="send-btn">
                                                            <i class="fas fa-paper-plane"></i>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-conversation">
                                            <div class="text-center">
                                                <i class="fas fa-comment-dots fa-3x mb-3"></i>
                                                <h5>Select a conversation</h5>
                                                <p>Choose a conversation from the list to start chatting.</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedFiles = [];

        function loadConversation(conversationId) {
            window.location.href = 'chat.php?conversation_id=' + conversationId;
        }

        // ! Auto-scroll to bottom of messages when many messages
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // File selection for attachments with specific file types
        document.getElementById('fileInput')?.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            selectedFiles = [...selectedFiles, ...files];
            updateAttachmentPreview();
        });

        function updateAttachmentPreview() {
            const preview = document.getElementById('attachmentPreview');
            const list = document.getElementById('attachmentList');

            if (selectedFiles.length > 0) {
                preview.style.display = 'block';
                list.innerHTML = '';

                selectedFiles.forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'attachment-item';
                    item.innerHTML = `
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <span class="attachment-remove" onclick="removeAttachment(${index})">×</span>
                    `;
                    list.appendChild(item);
                });
            } else {
                preview.style.display = 'none';
            }
        }

        function removeAttachment(index) {
            selectedFiles.splice(index, 1);
            updateAttachmentPreview();
        }

        // ! Message form submission
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();

            if (message || selectedFiles.length > 0) {
                sendMessage(message, selectedFiles);
                messageInput.value = '';
                selectedFiles = [];
                updateAttachmentPreview();
            }
        });

        function sendMessage(message, files = []) {
            const conversationId = '<?php echo $current_conversation ? $current_conversation['Conversation_ID'] : ''; ?>';

            if (files.length > 0) {
                // ! Attachment sending
                const formData = new FormData();
                formData.append('conversation_id', conversationId);
                formData.append('message', message);

                files.forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });

                fetch('api/send_message.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addMessageToChat(message, true, files);
                            scrollToBottom();
                        } else {
                            alert('Error sending message: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error sending message');
                    });
            } else {
                // Send text only
                fetch('api/send_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            conversation_id: conversationId,
                            message: message
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addMessageToChat(message, true);
                            scrollToBottom();
                        } else {
                            alert('Error sending message: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error sending message');
                    });
            }
        }

        function addMessageToChat(message, isSent, files = []) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + (isSent ? 'sent' : 'received');

            const now = new Date();
            const timeString = now.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });

            let attachmentsHtml = '';
            if (files && files.length > 0) {
                attachmentsHtml = '<div class="mt-2"><small><i class="fas fa-paperclip me-1"></i>Attachments: ' + files.map(f => f.name).join(', ') + '</small></div>';
            }

            messageDiv.innerHTML = `
                <div class="message-bubble">
                    <div>${message.replace(/\n/g, '<br>')}</div>
                    ${attachmentsHtml}
                    <div class="message-time">${timeString}</div>
                </div>
            `;

            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        // TODO: Refresh messages page every 5 seconds
        setInterval(function() {
            const conversationId = '<?php echo $current_conversation ? $current_conversation['Conversation_ID'] : ''; ?>';
            if (conversationId) {
                fetch('api/get_messages.php?conversation_id=' + conversationId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.messages) {
                            updateMessages(data.messages);
                        }
                    })
                    .catch(error => console.error('Error fetching messages:', error));
            }
        }, 5000);

        function updateMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            const currentMessageCount = chatMessages.children.length;

            if (messages.length > currentMessageCount) {
                // Add new messages
                for (let i = currentMessageCount; i < messages.length; i++) {
                    const message = messages[i];
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'message ' + (message.sender_id == '<?php echo $_SESSION['user_id']; ?>' ? 'sent' : 'received');

                    const timeString = new Date(message.sent_at).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    let attachmentsHtml = '';
                    if (message.attachments && message.attachments.length > 0) {
                        attachmentsHtml = '<div class="mt-2">';
                        message.attachments.forEach(attachment => {
                            const isImage = attachment.file_type && attachment.file_type.startsWith('image/');
                            if (isImage) {
                                attachmentsHtml += `
                                    <div class="attachment-item mb-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="${attachment.file_url}" 
                                                 class="me-2" 
                                                 style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                            <i class="fas fa-image me-1" style="display: none;"></i>
                                            <a href="${attachment.file_url}" target="_blank" class="text-decoration-none">
                                                ${attachment.file_name}
                                            </a>
                                        </div>
                                    </div>
                                `;
                            } else {
                                const iconClass = attachment.file_type && attachment.file_type.includes('pdf') ? 'fas fa-file-pdf' :
                                    attachment.file_type && attachment.file_type.includes('word') ? 'fas fa-file-word' :
                                    attachment.file_type && attachment.file_type.includes('text') ? 'fas fa-file-alt' : 'fas fa-file';
                                attachmentsHtml += `
                                    <div class="attachment-item mb-1">
                                        <div class="d-flex align-items-center">
                                            <i class="${iconClass} me-1"></i>
                                            <a href="${attachment.file_url}" target="_blank" class="text-decoration-none">
                                                ${attachment.file_name}
                                            </a>
                                        </div>
                                    </div>
                                `;
                            }
                        });
                        attachmentsHtml += '</div>';
                    }

                    messageDiv.innerHTML = `
                        <div class="message-bubble">
                            ${message.message_text ? `<div>${message.message_text.replace(/\n/g, '<br>')}</div>` : ''}
                            ${attachmentsHtml}
                            <div class="message-time">${timeString}</div>
                        </div>
                    `;

                    chatMessages.appendChild(messageDiv);
                }
                scrollToBottom();
            }
        }

        // Auto-resize textarea when typing long messages 
        document.getElementById('messageInput')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Scroll to bottom on page load
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
        });
    </script>
</body>

</html>
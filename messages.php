<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';
include '../includes/maintenance_check.php';

$current_user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get recruiter info
$stmt = $pdo->prepare("SELECT company_name FROM recruiters WHERE user_id = ?");
$stmt->execute([$current_user_id]);
$recruiter_data = $stmt->fetch();
$recruiter_name = $recruiter_data['company_name'] ?? 'Recruiter';

// Get admin user ID to chat with
$admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$admin_user = $admin_stmt->fetch();
$admin_user_id = $admin_user['id'] ?? null;

if (!$admin_user_id) {
    $error = "No admin available for messaging.";
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message']) && $admin_user_id) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // Insert message
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$current_user_id, $admin_user_id, $message]);
        
        // Create notification for admin
        $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notify->execute([$admin_user_id, "New message from " . $recruiter_name]);
        
        $success = "Message sent successfully!";
    } else {
        $error = "Message cannot be empty.";
    }
}

// Mark messages from admin as read
if ($admin_user_id) {
    $mark_read = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $mark_read->execute([$admin_user_id, $current_user_id]);
}

// Fetch conversation with admin
$messages = [];
if ($admin_user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$current_user_id, $admin_user_id, $admin_user_id, $current_user_id]);
    $messages = $stmt->fetchAll();
}

// Get unread count
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->execute([$current_user_id]);
$unread_count = $unread_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Messages - Employee Recruitment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .chat-container {
            height: 500px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        .message-bubble {
            max-width: 70%;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            word-wrap: break-word;
        }
        .message-sent {
            background-color: #0d6efd;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }
        .message-received {
            background-color: white;
            border: 1px solid #dee2e6;
            margin-right: auto;
            border-bottom-left-radius: 0.25rem;
        }
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-chart-bar me-2"></i>Employee Recruitment System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="post_job.php">Post Job</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_jobs.php">Manage Jobs</a></li>
                    <li class="nav-item"><a class="nav-link active" href="messages.php">Messages</a></li>
                    <li class="nav-item"><a class="nav-link" href="feedback.php">Feedback</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-muted">
                            <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($recruiter_name); ?>
                        </span>
                    </li>
                    <li class="nav-item ms-3"><a class="btn btn-outline-danger btn-sm" href="../logout.php" onclick="return confirm('Are you sure you want to logout?');">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 80px;">
        <h2 class="fw-bold mb-4"><i class="fas fa-comments me-2"></i>Messages</h2>

        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($admin_user_id): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Chat with Administrator</h5>
            </div>
            <div class="card-body p-0">
                <!-- Chat Messages -->
                <div class="chat-container" id="chatContainer">
                    <?php if(count($messages) > 0): ?>
                        <?php foreach($messages as $msg): ?>
                            <div class="d-flex <?php echo $msg['sender_id'] == $current_user_id ? 'justify-content-end' : 'justify-content-start'; ?>">
                                <div class="message-bubble <?php echo $msg['sender_id'] == $current_user_id ? 'message-sent' : 'message-received'; ?>">
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    <div class="message-time text-end">
                                        <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>No messages yet. Start a conversation with the admin!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Message Input -->
                <div class="p-3 border-top">
                    <form method="POST" id="messageForm">
                        <div class="input-group">
                            <textarea class="form-control" name="message" id="messageInput" rows="2" 
                                      placeholder="Type your message here..." required></textarea>
                            <button type="submit" name="send_message" class="btn btn-primary px-4">
                                <i class="fas fa-paper-plane me-2"></i>Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
    <script>
        // Auto-scroll to bottom of chat
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Auto-refresh every 10 seconds
        setInterval(function() {
            location.reload();
        }, 10000);

        // Handle Enter key to send (Shift+Enter for new line)
        document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').submit();
            }
        });
    </script>
</body>
</html>

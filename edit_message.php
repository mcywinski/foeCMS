<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRank = $_SESSION['user_rank'];

$isAdmin = $userRank === 0;
$isLeader = $userRank === 1;

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT m.*, u.name FROM chat_messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message || (!$isAdmin && !$isLeader)) {
    header("Location: chat.php");
    exit;
}

$isEditingOwn = $message['user_id'] == $userId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newContent = trim($_POST['message']);
    if ($newContent !== '') {
        // Dodaj adnotację tylko jeśli edytujący ≠ autor
        if (!$isEditingOwn) {
            $now = date('d.m.Y H:i:s');
            $newContent .= "\n\n(Edytowane przez {$userName} {$now})";
        }
        $stmt = $pdo->prepare("UPDATE chat_messages SET message = ? WHERE id = ?");
        $stmt->execute([$newContent, $id]);
    }
    header("Location: chat.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Edytuj wiadomość</title>
    <link rel="stylesheet" href="style.css">
    </style>
</head>
<body>
<div class="container">
    <h2>Edytuj wiadomość</h2>
    <div class="form-box">
        <form method="post">
            <label for="message"><strong>Treść wiadomości:</strong></label><br>
            <textarea name="message" rows="6" required><?= htmlspecialchars($message['message']) ?></textarea><br>
            <div class="buttons">
                <button type="submit">💾 Zapisz zmiany</button>
                <a href="chat.php"><button type="button">✖ Anuluj</button></a>
            </div>
        </form>
    </div>
</div>
</body>
</html>

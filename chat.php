<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRank = $_SESSION['user_rank'];

$isAdmin = $userRank === 0;
$isLeader = $userRank === 1;

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Obsługa wysłania wiadomości
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $message]);
    }
    header("Location: chat.php");
    exit;
}

// Przypinanie / Odpinanie
if (($isAdmin || $isLeader) && isset($_GET['pin'])) {
    $msgId = (int)$_GET['pin'];
    $pdo->prepare("UPDATE chat_messages SET pinned = 1 WHERE id = ?")->execute([$msgId]);
    header("Location: chat.php");
    exit;
}

if (($isAdmin || $isLeader) && isset($_GET['unpin'])) {
    $msgId = (int)$_GET['unpin'];
    $pdo->prepare("UPDATE chat_messages SET pinned = 0 WHERE id = ?")->execute([$msgId]);
    header("Location: chat.php");
    exit;
}

// Usuwanie wiadomości
if (isset($_GET['delete'])) {
    $msgId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();

    if ($msg) {
        $canDelete = $isAdmin || ($isLeader && $msg['user_id'] == $userId);
        if ($canDelete) {
            $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$msgId]);
        }
    }

    header("Location: chat.php");
    exit;
}

// Pobierz przypięte + ostatnie 50 nieprzypiętych
$stmt = $pdo->query("
    (SELECT m.*, u.name FROM chat_messages m 
     JOIN users u ON m.user_id = u.id 
     WHERE m.pinned = 1
     ORDER BY m.created_at DESC)
    UNION
    (SELECT m.*, u.name FROM chat_messages m 
     JOIN users u ON m.user_id = u.id 
     WHERE m.pinned = 0
     ORDER BY m.created_at DESC
     LIMIT 50)
    ORDER BY pinned DESC, created_at DESC
");

$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Czat</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Czat</h1>
        <?php include 'menu.php'; ?>
		
		<form method="post" style="display: flex; align-items: center; gap: 10px; margin-top: 20px;">
            <input type="text" name="message" placeholder="Napisz wiadomość..." required style="flex: 1;">
            <button type="submit">Wyślij</button>
            <a href="chat_archive.php"><button type="button">Archiwum</button></a>
        </form>

        <ul style="list-style: none; padding: 0;">
            <?php foreach ($messages as $msg): ?>
                <li style="margin-bottom: 10px; background: <?= $msg['pinned'] ? '#fff4cc' : '#f5f5f5' ?>; padding: 10px;">
                    <strong><?= htmlspecialchars($msg['name']) ?>:</strong>
                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    <?php
                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $msg['created_at']);
                    $formattedDate = $dt ? $dt->format('d.m.Y H:i:s') : $msg['created_at'];
                    ?>
                    <small style="float: right;"><?= $formattedDate ?></small><br>

                    <?php if ($isAdmin || $isLeader): ?>
                        <?php if (!$msg['pinned']): ?>
                            <a href="chat.php?pin=<?= $msg['id'] ?>">📌 Przypnij</a>
                        <?php else: ?>
                            <a href="chat.php?unpin=<?= $msg['id'] ?>">❌ Odepnij</a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($isAdmin || $isLeader): ?>
                        <a href="edit_message.php?id=<?= $msg['id'] ?>">✏ Edytuj</a>
                    <?php endif; ?>

                    <?php if ($isAdmin || ($isLeader && $msg['user_id'] == $userId)): ?>
                        <a href="chat.php?delete=<?= $msg['id'] ?>" onclick="return confirm('Czy na pewno usunąć wiadomość?')">🗑 Usuń</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</body>
</html>

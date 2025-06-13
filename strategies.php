<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

// Sprawdzenie, czy użytkownik jest zalogowany i ma prawo do strategii (ranga 0 = admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rank']) || $_SESSION['user_rank'] != 0) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $stmt = $pdo->prepare("INSERT INTO strategies (author_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $message]);
        header("Location: strategies.php");
        exit;
    }
}

$messages = $pdo->query("SELECT s.message, s.created_at, u.name FROM strategies s JOIN users u ON s.author_id = u.id ORDER BY s.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Strategie</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Czat</h1>
    <form method="post">
        <textarea name="message" rows="4" cols="50" placeholder="Wpisz wiadomość..."></textarea><br>
        <button type="submit">Wyślij</button>
    </form>
    <hr>
    <div>
        <?php foreach ($messages as $msg): ?>
            <p><strong><?= htmlspecialchars($msg['name']) ?></strong> (<?= formatDateTime($msg['created_at']) ?>):<br>
            <?= nl2br(htmlspecialchars($msg['message'])) ?></p><hr>
        <?php endforeach; ?>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="dashboard.php"><button type="button">Powrót</button></a>
    </div>    
</div>
</body>
</html>

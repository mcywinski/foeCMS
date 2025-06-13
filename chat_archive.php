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

$isAdminOrLeader = $userRank === 0 || $userRank === 1;

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Pobierz wszystkie wiadomości
$stmt = $pdo->query("
    SELECT m.*, u.name 
    FROM chat_messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY pinned DESC, created_at DESC
");

$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Archiwum czatu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Archiwum czatu</h1>
    <?php include 'menu.php'; ?>

    <ul style="margin-top: 20px; list-style: none; padding: 0;">
        <?php foreach ($messages as $msg): ?>
            <li style="margin-bottom: 10px; background: <?= $msg['pinned'] ? '#fffae6' : '#f5f5f5' ?>; padding: 10px;">
                <strong><?= htmlspecialchars($msg['name']) ?>:</strong>
                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                <small style="float: right;">
                    <?= date('d.m.Y H:i:s', strtotime($msg['created_at'])) ?>
                </small><br>
                <?php if ($isAdminOrLeader && !$msg['pinned']): ?>
                    <a href="chat.php?pin=<?= $msg['id'] ?>" style="font-size: 0.9em;">📌 Przypnij</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>

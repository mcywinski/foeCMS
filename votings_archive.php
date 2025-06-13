<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_rank'] !== 0 && $_SESSION['user_rank'] !== 1)) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['user_rank'];
$isAdminOrLeader = $userRank === 0 || $userRank === 1;

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Obsługa przywracania głosowania z archiwum
if ($isAdminOrLeader && isset($_POST['restore_voting'])) {
    $votingId = (int)$_POST['restore_voting'];
    $stmt = $pdo->prepare("UPDATE votings SET archived = 0 WHERE id = ?");
    $stmt->execute([$votingId]);
    header("Location: votings_archive.php");
    exit;
}

// Obsługa usunięcia głosowania
if ($isAdminOrLeader && isset($_POST['delete_voting'])) {
    $votingId = (int)$_POST['delete_voting'];

    // Usuń powiązane komentarze i głosy
    $pdo->prepare("DELETE FROM voting_comments WHERE voting_id = ?")->execute([$votingId]);
    $pdo->prepare("DELETE FROM voting_votes WHERE voting_id = ?")->execute([$votingId]);

    // Usuń samo głosowanie
    $pdo->prepare("DELETE FROM votings WHERE id = ?")->execute([$votingId]);

    header("Location: votings_archive.php");
    exit;
}

// Pobierz zarchiwizowane głosowania
$votings = $pdo->query("
    SELECT v.*, u.name AS author,
        (SELECT COUNT(*) FROM voting_votes WHERE voting_id = v.id AND vote = 'za') AS za,
        (SELECT COUNT(*) FROM voting_votes WHERE voting_id = v.id AND vote = 'przeciw') AS przeciw
    FROM votings v
    JOIN users u ON v.created_by = u.id
    WHERE v.archived = 1
    ORDER BY v.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Archiwum głosowań</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Archiwum głosowań</h1>
    <?php include 'menu.php'; ?>

    <?php if (empty($votings)): ?>
        <p>Brak zarchiwizowanych głosowań.</p>
    <?php endif; ?>

    <?php foreach ($votings as $v): ?>
        <div class="voting-box">
            <h3><?= htmlspecialchars($v['title']) ?></h3>
            <p><?= nl2br(htmlspecialchars($v['description'])) ?></p>
            <small>Utworzył: <?= htmlspecialchars($v['author']) ?>, <?= $v['created_at'] ?></small><br>
            <?php if ($v['end_time']): ?>
                <small>Głosowanie do: <?= $v['end_time'] ?></small><br>
            <?php endif; ?>
            <p><strong>Wynik: ZA <?= $v['za'] ?>, PRZECIW <?= $v['przeciw'] ?></strong></p>
            <form method="post" style="display: inline;">
                <input type="hidden" name="restore_voting" value="<?= $v['id'] ?>">
                <button type="submit" class="btn restore-btn">Przywróć</button>
            </form>
            <form method="post" style="display: inline;" onsubmit="return confirm('Na pewno usunąć to głosowanie? Tej operacji nie można cofnąć.')">
                <input type="hidden" name="delete_voting" value="<?= $v['id'] ?>">
                <button type="submit" class="btn delete-btn">Usuń</button>
            </form>
            <a href="voting_view.php?id=<?= $v['id'] ?>"><button class="btn view-btn">Zobacz komentarze</button></a>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>

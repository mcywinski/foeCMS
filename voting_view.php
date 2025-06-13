<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['user_rank'];
$isAdminOrLeader = $userRank === 0 || $userRank === 1;
$votingId = (int)$_GET['id'];

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Pobierz głosowanie
$stmt = $pdo->prepare("SELECT v.*, u.name as author FROM votings v JOIN users u ON v.created_by = u.id WHERE v.id = ?");
$stmt->execute([$votingId]);
$voting = $stmt->fetch();

if (!$voting) {
    die("Głosowanie nie istnieje.");
}

$now = new DateTime();
$isClosed = $voting['closed'] || ($voting['end_time'] && $now > new DateTime($voting['end_time']));

// Zablokuj dostęp do zarchiwizowanych głosowań dla zwykłych użytkowników
if ($voting['closed'] && !$isAdminOrLeader) {
    header("Location: votings.php");
    exit;
}

// Zakończenie głosowania
if ($isAdminOrLeader && isset($_GET['close'])) {
    $pdo->prepare("UPDATE votings SET closed = 1 WHERE id = ?")->execute([$votingId]);
    header("Location: voting_view.php?id=$votingId");
    exit;
}

// Edycja komentarza
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment_id'], $_POST['edit_comment'])) {
    $commentId = (int)$_POST['edit_comment_id'];
    $stmt = $pdo->prepare("SELECT * FROM voting_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if ($comment && (($comment['user_id'] == $userId && !$isClosed) || $isAdminOrLeader)) {
        $stmt = $pdo->prepare("UPDATE voting_comments SET comment = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$_POST['edit_comment'], $commentId]);
    }

    header("Location: voting_view.php?id=$votingId");
    exit;
}

// Dodanie komentarza
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && !isset($_POST['edit_comment_id']) && (!$isClosed || $isAdminOrLeader)) {
    $stmt = $pdo->prepare("INSERT INTO voting_comments (voting_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$votingId, $userId, $_POST['comment']]);
    header("Location: voting_view.php?id=$votingId");
    exit;
}

// Usuwanie komentarza
if (isset($_GET['delete_comment'])) {
    $commentId = (int)$_GET['delete_comment'];
    $stmt = $pdo->prepare("SELECT * FROM voting_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if ($comment && ($comment['user_id'] == $userId || $isAdminOrLeader)) {
        $pdo->prepare("DELETE FROM voting_comments WHERE id = ?")->execute([$commentId]);
    }

    header("Location: voting_view.php?id=$votingId");
    exit;
}

// Obsługa głosowania
if (!$isClosed && isset($_GET['vote']) && in_array($_GET['vote'], ['za', 'przeciw'])) {
    $stmt = $pdo->prepare("SELECT id FROM voting_votes WHERE voting_id = ? AND user_id = ?");
    $stmt->execute([$votingId, $userId]);
    $existingVote = $stmt->fetchColumn();

    if ($existingVote) {
        $stmt = $pdo->prepare("UPDATE voting_votes SET vote = ?, voted_at = NOW() WHERE voting_id = ? AND user_id = ?");
        $stmt->execute([$_GET['vote'], $votingId, $userId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO voting_votes (voting_id, user_id, vote) VALUES (?, ?, ?)");
        $stmt->execute([$votingId, $userId, $_GET['vote']]);
    }
    header("Location: voting_view.php?id=$votingId");
    exit;
}

// Pobierz komentarze
$stmt = $pdo->prepare("
    SELECT vc.*, u.name FROM voting_comments vc
    JOIN users u ON vc.user_id = u.id
    WHERE vc.voting_id = ?
    ORDER BY vc.created_at ASC
");
$stmt->execute([$votingId]);
$comments = $stmt->fetchAll();

// Pobierz głos użytkownika
$stmt = $pdo->prepare("SELECT vote FROM voting_votes WHERE voting_id = ? AND user_id = ?");
$stmt->execute([$votingId, $userId]);
$userVote = $stmt->fetchColumn();

// Podsumowanie głosów
if ($isClosed || $isAdminOrLeader) {
    $stmt = $pdo->prepare("
        SELECT vote, COUNT(*) as total FROM voting_votes
        WHERE voting_id = ?
        GROUP BY vote
    ");
    $stmt->execute([$votingId]);
    $votes = ['za' => 0, 'przeciw' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $votes[$row['vote']] = $row['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - <?= htmlspecialchars($voting['title']) ?></title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleEdit(id) {
            document.getElementById('comment-text-' + id).style.display = 'none';
            document.getElementById('comment-edit-form-' + id).style.display = 'block';
        }
    </script>
</head>
<body>
<div class="container">
    <?php include 'menu.php'; ?>

    <h2><?= htmlspecialchars($voting['title']) ?></h2>
    <p><?= nl2br(htmlspecialchars($voting['description'])) ?></p>
    <small>Utworzył: <?= htmlspecialchars($voting['author']) ?>, <?= $voting['created_at'] ?></small><br>
    <?php if ($voting['end_time']): ?>
        <small>Głosowanie do: <?= (new DateTime($voting['end_time']))->format('d.m.Y H:i') ?></small>
    <?php endif; ?>

    <br><br>
    <?php if (!$isClosed): ?>
        <form method="get" style="display:inline;">
            <input type="hidden" name="id" value="<?= $votingId ?>">
            <!--<button name="vote" value="za" <?= $userVote === 'za' ? 'style="font-weight:bold;"' : '' ?>>👍 ZA</button>
            <button name="vote" value="przeciw" <?= $userVote === 'przeciw' ? 'style="font-weight:bold;"' : '' ?>>👎 PRZECIW</button>-->
        </form>
    <?php else: ?>
        <p style="color:red;"><strong>Głosowanie zakończone.</strong></p>
        <p><strong>ZA:</strong> <?= $votes['za'] ?? 0 ?>, <strong>PRZECIW:</strong> <?= $votes['przeciw'] ?? 0 ?></p>
    <?php endif; ?>

    <?php if ($isAdminOrLeader && !$isClosed): ?>
        <a href="voting_view.php?id=<?= $votingId ?>&close=1"><button>Zamknij głosowanie</button></a>
    <?php endif; ?>

    <h3>Komentarze</h3>
    <ul style="list-style: none; padding: 0;">
        <?php foreach ($comments as $c): ?>
            <li style="border-bottom: 1px solid #ccc; padding: 10px;">
                <strong><?= htmlspecialchars($c['name']) ?>:</strong>
                <div id="comment-text-<?= $c['id'] ?>">
                    <?= nl2br(htmlspecialchars($c['comment'])) ?>
                </div>
                <form method="post" id="comment-edit-form-<?= $c['id'] ?>" style="display:none;">
                    <input type="hidden" name="edit_comment_id" value="<?= $c['id'] ?>">
                    <input type="text" name="edit_comment" value="<?= htmlspecialchars($c['comment']) ?>" required>
                    <button type="submit">Zapisz</button>
                </form>
                <small style="float: right;">
                    <?= $c['created_at'] ?>
                    <?php if ($c['updated_at']): ?>
                        (edytowano: <?= $c['updated_at'] ?>)
                    <?php endif; ?>
                </small>
                <div style="margin-top: 5px;">
                    <?php if (($c['user_id'] == $userId && !$isClosed) || $isAdminOrLeader): ?>
                        <button onclick="toggleEdit(<?= $c['id'] ?>)">Edytuj</button>
                        <a href="?id=<?= $votingId ?>&delete_comment=<?= $c['id'] ?>" onclick="return confirm('Usunąć komentarz?')">🗑 Usuń</a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$isClosed || $isAdminOrLeader): ?>
        <form method="post" style="margin-top: 20px;">
            <textarea name="comment" required placeholder="Dodaj komentarz..."></textarea><br>
            <button type="submit">Dodaj komentarz</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

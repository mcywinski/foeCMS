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

// Dodanie nowego głosowania
if ($isAdminOrLeader && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['description'])) {
    $endDateTime = null;
    if (!empty($_POST['end_time']) && empty($_POST['no_end'])) {
        $dateTime = DateTime::createFromFormat('d.m.Y H:i', $_POST['end_time']);
        if ($dateTime) {
            $endDateTime = $dateTime->format('Y-m-d H:i:s');
        }
    }
    $stmt = $pdo->prepare("INSERT INTO votings (title, description, created_by, end_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['title'], $_POST['description'], $userId, $endDateTime]);
    header("Location: votings.php");
    exit;
}

// Archiwizacja zakończonego głosowania
if ($isAdminOrLeader && isset($_POST['archive_voting'])) {
    $votingId = (int)$_POST['archive_voting'];
    $stmt = $pdo->prepare("SELECT closed, end_time FROM votings WHERE id = ?");
    $stmt->execute([$votingId]);
    $voting = $stmt->fetch();

    $closed = $voting['closed'] || ($voting['end_time'] && strtotime($voting['end_time']) < time());
    if ($closed) {
        $stmt = $pdo->prepare("UPDATE votings SET archived = 1 WHERE id = ?");
        $stmt->execute([$votingId]);
    }
    header("Location: votings.php");
    exit;
}

// Głosowanie
if (isset($_GET['vote'], $_GET['voting'])) {
    $vote = $_GET['vote'] === 'za' ? 'za' : 'przeciw';
    $votingId = (int)$_GET['voting'];

    $stmt = $pdo->prepare("SELECT end_time, closed FROM votings WHERE id = ?");
    $stmt->execute([$votingId]);
    $voting = $stmt->fetch();

    if ($voting && !$voting['closed'] && (is_null($voting['end_time']) || strtotime($voting['end_time']) > time())) {
        $stmt = $pdo->prepare("SELECT vote FROM voting_votes WHERE voting_id = ? AND user_id = ?");
        $stmt->execute([$votingId, $userId]);
        $existing = $stmt->fetchColumn();

        if ($existing === $vote) {
            $pdo->prepare("DELETE FROM voting_votes WHERE voting_id = ? AND user_id = ?")->execute([$votingId, $userId]);
        } else {
            $pdo->prepare("REPLACE INTO voting_votes (voting_id, user_id, vote) VALUES (?, ?, ?)")->execute([$votingId, $userId, $vote]);
        }
    }
    header("Location: votings.php");
    exit;
}

$votings = $pdo->query("
    SELECT v.*, u.name AS author,
        (SELECT vote FROM voting_votes WHERE voting_id = v.id AND user_id = {$userId}) AS user_vote,
        (SELECT COUNT(*) FROM voting_votes WHERE voting_id = v.id AND vote = 'za') AS za,
        (SELECT COUNT(*) FROM voting_votes WHERE voting_id = v.id AND vote = 'przeciw') AS przeciw
    FROM votings v
    JOIN users u ON v.created_by = u.id
    WHERE v.archived = 0
    ORDER BY v.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Głosowania</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
<div class="container">
    <h1>Głosowania</h1>
    <?php include 'menu.php'; ?>

    <?php if ($isAdminOrLeader): ?>
        <form method="post" style="margin-bottom: 20px;">
            <input type="text" style="margin-bottom: 10px;" name="title" placeholder="Tytuł" required>
            <textarea name="description" placeholder="Opis" required></textarea>
            <label><input type="checkbox" name="no_end" id="no_end" checked onchange="document.getElementById('end_time').disabled = this.checked"> Bez terminu zakończenia</label><br>
            <input type="text" style="margin-bottom: 10px;" name="end_time" id="end_time" placeholder="dd.mm.rrrr hh:mm" disabled>
            <button type="submit" class="add-voting-btn">Dodaj głosowanie</button>
            <a href="votings_archive.php"><button type="button" class="archive-btn">Przejdź do archiwum</button></a>
        </form>
    <?php endif; ?>

    <?php if (empty($votings)): ?>
        <p>Aktualnie brak wyborów.</p>
    <?php endif; ?>

    <?php foreach ($votings as $v): ?>
        <div style="border: 1px solid #ccc; margin-bottom: 15px; padding: 10px;">
            <h3><?= htmlspecialchars($v['title']) ?></h3>
            <p><?= nl2br(htmlspecialchars($v['description'])) ?></p>
            <small>Utworzył: <?= htmlspecialchars($v['author']) ?>, <?= $v['created_at'] ?></small>
            <?php if ($v['end_time']): ?><br><small>Głosowanie do: <?= $v['end_time'] ?></small><?php endif; ?>

            <?php
            $closed = $v['closed'] || ($v['end_time'] && strtotime($v['end_time']) < time());
            if ($closed): ?>
                <p><strong>Głosowanie zakończone.</strong></p>
                <div class="results">ZA: <?= $v['za'] ?>, PRZECIW: <?= $v['przeciw'] ?></div>

                <?php if ($isAdminOrLeader): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="archive_voting" value="<?= $v['id'] ?>">
                        <button type="submit">Przenieś do archiwum</button>
                    </form>
                    <div class="detailed-box">
                        <strong>Głosy szczegółowe:</strong><br>
                        <?php
                        $stmt = $pdo->prepare("SELECT u.name, vv.vote FROM voting_votes vv JOIN users u ON vv.user_id = u.id WHERE vv.voting_id = ?");
                        $stmt->execute([$v['id']]);
                        foreach ($stmt as $voteRow) {
                            echo htmlspecialchars($voteRow['name']) . ": <strong>" . strtoupper($voteRow['vote']) . "</strong><br>";
                        }
                        ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="vote-buttons">
                    <form method="get" style="display:inline;">
                        <input type="hidden" name="voting" value="<?= $v['id'] ?>">
                        <button name="vote" value="za" style="<?= $v['user_vote'] === 'za' ? 'font-weight: bold;' : '' ?>">👍 ZA</button>
                        <button name="vote" value="przeciw" style="<?= $v['user_vote'] === 'przeciw' ? 'font-weight: bold;' : '' ?>">👎 PRZECIW</button>
                    </form>
                    <a href="voting_view.php?id=<?= $v['id'] ?>"><button>Komentarze</button></a>
                </div>
                <?php if ($isAdminOrLeader): ?>
                    <div class="results">ZA: <?= $v['za'] ?>, PRZECIW: <?= $v['przeciw'] ?></div>
                    <div class="detailed-box">
                        <strong>Głosy szczegółowe:</strong><br>
                        <?php
                        $stmt = $pdo->prepare("SELECT u.name, vv.vote FROM voting_votes vv JOIN users u ON vv.user_id = u.id WHERE vv.voting_id = ?");
                        $stmt->execute([$v['id']]);
                        foreach ($stmt as $voteRow) {
                            echo htmlspecialchars($voteRow['name']) . ": <strong>" . strtoupper($voteRow['vote']) . "</strong><br>";
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr("#end_time", {
        enableTime: true,
        dateFormat: "d.m.Y H:i",
        time_24hr: true
    });
</script>
</body>
</html>
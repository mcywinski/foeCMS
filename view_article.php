<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Pobierz ID artykułu z parametru GET i zwaliduj
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    // Niepoprawne ID - przekieruj lub pokaż błąd
    header("Location: articles.php");
    exit;
}

// Pobierz artykuł z bazy
$stmt = $pdo->prepare("SELECT a.*, u.name AS author_name FROM articles a LEFT JOIN users u ON a.author_id = u.id WHERE a.id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    // Artykuł nie istnieje
    echo "<p>Artykuł nie został znaleziony.</p>";
    exit;
}

// Jeśli artykuł nie jest zatwierdzony, a użytkownik nie jest adminem/liderem, zablokuj dostęp
$userRank = $_SESSION['user_rank'] ?? 2; // 2 = zwykły użytkownik
if ($article['approved_by'] === null && $userRank > 1) {
    echo "<p>Ten artykuł nie został jeszcze zatwierdzony.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - <?= htmlspecialchars($article['title']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1><?= htmlspecialchars($article['title']) ?></h1>
    <?php include 'menu.php'; ?>
    <p><em>Autor: <?= htmlspecialchars($article['author_name'] ?? 'Nieznany') ?> | Data: <?= htmlspecialchars($article['created_at']) ?></em></p>
    <div>
        <?= $article['content'] ?>
    </div>
    <p><a href="articles.php">« Powrót do listy artykułów</a></p>
</div>
</body>
</html>

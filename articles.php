<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['user_rank'];

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Definiujemy, które rangi mogą zatwierdzać
$canApprove = ($userRank == 0 || $userRank == 1);

// Obsługa zatwierdzania artykułu
if ($canApprove && isset($_GET['approve'])) {
    $articleId = (int)$_GET['approve'];
    $stmt = $pdo->prepare("UPDATE articles SET approved_by = ?, approved_at = NOW() WHERE id = ?");
    $stmt->execute([$userId, $articleId]);
    header("Location: articles.php");
    exit;
}

// Obsługa usuwania artykułu
if ($canApprove && isset($_GET['delete'])) {
    $articleId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
    $stmt->execute([$articleId]);
    header("Location: articles.php");
    exit;
}

// Obsługa cofnięcia zatwierdzenia
if ($canApprove && isset($_GET['unapprove'])) {
    $articleId = (int)$_GET['unapprove'];
    $stmt = $pdo->prepare("UPDATE articles SET approved_by = NULL, approved_at = NULL WHERE id = ?");
    $stmt->execute([$articleId]);
    header("Location: articles.php");
    exit;
}

// Pobieranie artykułów
if ($canApprove) {
    // Admin i lider widzą wszystkie artykuły
    $stmt = $pdo->query("SELECT id, title, excerpt, author_id, approved_by FROM articles ORDER BY created_at DESC");
} else {
    // Zwykły użytkownik widzi tylko zatwierdzone
    $stmt = $pdo->query("SELECT id, title, excerpt, author_id, approved_by FROM articles WHERE approved_by IS NOT NULL ORDER BY created_at DESC");
}

$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Artykuły</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>Artykuły</h1>
        <?php include 'menu.php'; ?>
        <table>
            <thead>
                <tr>
                    <th>Tytuł</th>
                    <th>Opis</th>
                    <?php if ($canApprove): ?>
                        <th>Status</th>
                        <th>Akcje</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $article): ?>
                    <tr>
                        <td><a href="view_article.php?id=<?= $article['id'] ?>"><?= htmlspecialchars($article['title']) ?></a></td>
                        <td><?= htmlspecialchars($article['excerpt']) ?></td>

                        <?php if ($canApprove): ?>
                            <td>
                                <?= $article['approved_by'] !== null
                                    ? '<span style="color:green;">Zatwierdzony</span>'
                                    : '<span style="color:orange;">Nie zatwierdzony</span>' ?>
                            </td>
                            <td>
                                <?php if ($userRank <= 1 || $article['author_id'] == $userId): ?>
                                    <a class="btn-edit" href="edit_article.php?id=<?= $article['id'] ?>">Edytuj</a>
                                <?php endif; ?>

                                <?php if ($article['approved_by'] === null): ?>
                                    <a class="btn-approve" href="articles.php?approve=<?= $article['id'] ?>" onclick="return confirm('Czy na pewno zatwierdzić artykuł?')">Zatwierdź</a>
                                <?php else: ?>
                                    <a class="btn-approve" href="articles.php?unapprove=<?= $article['id'] ?>" onclick="return confirm('Czy na pewno cofnąć zatwierdzenie artykułu?')">Cofnij zatwierdzenie</a>
                                <?php endif; ?>


                                <a class="btn-delete" href="articles.php?delete=<?= $article['id'] ?>" onclick="return confirm('Czy na pewno usunąć artykuł?')">Usuń</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
            <a href="create_article.php"><button>Utwórz nowy artykuł</button></a>
        </div>
    </div>
</body>

</html>
<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Sprawdzenie czy użytkownik jest zalogowany (dowolny user może dodawać artykuły)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$title) {
        $errors[] = "Tytuł jest wymagany.";
    }
    if (!$excerpt) {
        $errors[] = "Opis jest wymagany.";
    }
    if (!$content) {
        $errors[] = "Treść artykułu jest wymagana.";
    }

    if (!$errors) {
        // approved_by NULL, bo czeka na zatwierdzenie
        $stmt = $pdo->prepare("INSERT INTO articles (title, excerpt, content, author_id, created_at, approved_by) VALUES (?, ?, ?, ?, NOW(), NULL)");
        $stmt->execute([$title, $excerpt, $content, $_SESSION['user_id']]);
        header("Location: articles.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Dodaj nowy artykuł</title>
    <link rel="stylesheet" href="style.css">

    <!-- Trumbowyg CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/ui/trumbowyg.min.css">

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

    <!-- Trumbowyg JS -->
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/trumbowyg.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#content').trumbowyg();
        });
    </script>

    <style>
        .error { background-color: #fdd; padding: 10px; margin-bottom: 15px; border: 1px solid red; }
        label { font-weight: bold; margin-top: 10px; display: block; }
        input[type="text"], textarea { width: 100%; max-width: 600px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Nowy artykuł</h1>
    <?php include 'menu.php'; ?>

    <?php if ($errors): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="title">Tytuł:</label><br>
        <input type="text" name="title" id="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required><br>

        <label for="excerpt">Opis (krótkie streszczenie):</label><br>
        <textarea name="excerpt" id="excerpt" rows="3" required><?= htmlspecialchars($_POST['excerpt'] ?? '') ?></textarea><br>

        <label for="content">Treść artykułu:</label><br>
        <textarea name="content" id="content" rows="10" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea><br>

        <button type="submit">Dodaj artykuł</button>
        <a href="articles.php"><button type="button">Anuluj</button></a>
    </form>
</div>
</body>
</html>

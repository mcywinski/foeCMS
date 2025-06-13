<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: text/html; charset=utf-8');

if (file_exists('db.php')) {
    require_once 'db.php';

    if (!$pdo) {
        die('ERROR: Błąd połączenia z bazą MySQL');
    }
} else {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    } else {
        die('ERROR: Pliki na serwerze są niekompletne');
    }
}

// Jeśli użytkownik jest zalogowany, przekieruj na dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

// Pobierz ustawienia strony z bazy
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

$header = $settings['site_header'] ?? 'foeCMS - System zarządzania gildią';
$description = $settings['site_description'] ?? 'Witamy w systemie zarządzania gildią w grze Forge of Empires';
$footer = $settings['site_footer'] ?? 'foeCMS';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Portal zarządzania gilią FoE</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($header) ?></h1>
        <p><?= nl2br(htmlspecialchars($description)) ?></p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <label for="login">Login:</label><br>
            <input type="text" name="login" id="login" required><br>
            <label for="password">Hasło:</label><br>
            <input type="password" name="password" id="password" required><br><br>
            <button type="submit">Zaloguj się</button>
        </form>
    </div>
    <footer><?= htmlspecialchars($footer) ?></footer>
</body>
</html>
<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['user_rank'];

$hasPermission = function($perm) use ($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE user_id = ? AND permission = ?");
    $stmt->execute([$userId, $perm]);
    return $stmt->fetchColumn() > 0;
};

if (!($userRank === 0 || $userRank === 1 || $hasPermission('create_user'))) {
    header("Location: dashboard.php");
    exit;
}

$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $name = trim($_POST['name']);
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'];
    $joined = $_POST['joined'];

    if ($password1 === $password2 && $login && $name && $joined) {
        try {
            // Hashowanie hasła
            $hash = password_hash($password1, PASSWORD_DEFAULT);

            // Dodanie użytkownika bez pola 'status'
            $stmt = $pdo->prepare("INSERT INTO users (login, name, password, joined, `rank`) VALUES (?, ?, ?, ?, 2)");
            $stmt->execute([$login, $name, $hash, $joined]);

            // Pobierz ID nowego użytkownika
            $newUserId = $pdo->lastInsertId();

            // Logowanie utworzenia użytkownika
            $now = date('Y-m-d H:i:s');
            $creator = $_SESSION['user_name'] ?? 'Nieznany';
            $logText = "$creator utworzył nowego użytkownika $name (login: $login, ID: $newUserId)";

            $logStmt = $pdo->prepare("INSERT INTO logs (user_id, log_text, created_at, target_user_id) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$userId, $logText, $now, $newUserId]);

            $info = '✅ Użytkownik został dodany.';

        } catch (PDOException $e) {
            $info = '❌ Błąd przy zapisie: ' . $e->getMessage();
        }
    } else {
        $info = '❌ Błąd: nieprawidłowe dane lub hasła się nie zgadzają.';
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Dodaj użytkownika</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Nowe konto</h1>
    <?php include 'menu.php'; ?>
    <?php if ($info): ?><p><?= htmlspecialchars($info) ?></p><?php endif; ?>
    <form method="post">
        <label>Login:</label><br>
        <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required><br>

        <label>Nazwa gracza:</label><br>
        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required><br>

        <label>Hasło:</label><br>
        <input type="password" name="password1" required><br>

        <label>Powtórz hasło:</label><br>
        <input type="password" name="password2" required><br>

        <label>Data dołączenia:</label><br>
        <input type="date" name="joined" value="<?= htmlspecialchars($_POST['joined'] ?? date('Y-m-d')) ?>" required><br>

        <div style="display: flex; gap: 10px;">
            <button type="submit">Utwórz konto</button>
            <a href="dashboard.php"><button type="button">Powrót</button></a>
        </div>
    </form>
</div>
</body>
</html>

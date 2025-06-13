<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_rank'] !== 0) {
    header("Location: index.php");
    exit;
}

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT logs.*, u1.name AS admin_name, u2.name AS target_name
                       FROM logs
                       LEFT JOIN users u1 ON logs.user_id = u1.id
                       LEFT JOIN users u2 ON logs.target_user_id = u2.id
                       ORDER BY created_at DESC");
$stmt->execute();
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Logi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Wszystkie logi</h1>
    <?php include 'menu.php'; ?>
    <pre style="background: #f0f0f0; padding: 10px; max-height: 80vh; overflow-y: scroll;">
<?php foreach ($logs as $log): ?>
[<?= date('d.m.Y H:i', strtotime($log['created_at'])) ?>] <?= htmlspecialchars($log['log_text']) ?>

<?php endforeach; ?>
    </pre>
    <a href="dashboard.php"><button>Powrót</button></a>
</div>
</body>
</html>

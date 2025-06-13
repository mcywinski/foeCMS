<?php
if (!isset($_SESSION)) session_start();

require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRank = $_SESSION['user_rank'];

$hasPermission = function($perm) use ($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE user_id = ? AND permission = ?");
    $stmt->execute([$userId, $perm]);
    return $stmt->fetchColumn() > 0;
};
?>
<nav>
    <ul>
        <li><a href="dashboard.php">Start</a></li>
        <li><a href="articles.php">Artykuły</a></li>
        <li><a href="chat.php">Czat</a></li>
        <li><a href="votings.php">Głosowania</a></li>

        <?php if ($userRank === 0 || $hasPermission('settings')): ?>
            <li><a href="settings.php">Ustawienia</a></li>
            <li><a href="logs.php">Logi</a></li>
        <?php endif; ?>

        <li><a href="logout.php">Wyloguj się</a></li>
    </ul>
</nav>

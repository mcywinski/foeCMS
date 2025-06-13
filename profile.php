<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = (int)($_GET['id'] ?? $_SESSION['user_id']);
$currentUserId = $_SESSION['user_id'];
$currentUserRank = $_SESSION['user_rank'];
$currentUserName = $_SESSION['user_name'] ?? 'Nieznany';

// Pobieranie ustawień z tabeli settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$pointsEnabled = isset($settings['points_enabled']) ? (bool)$settings['points_enabled'] : true;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: dashboard.php");
    exit;
}

$isOwnProfile = $userId === $currentUserId;
$isTargetAdmin = (int)$user['rank'] === 0;

$canEditAll = ($currentUserRank === 0) || ($currentUserRank === 1 && hasPermission($currentUserId, 'manage_users'));

// Lider nie może edytować administratora
if ($currentUserRank === 1 && $isTargetAdmin) {
    $canEditAll = false;
}

$info = '';

$pointsEnabled = $settings ? (bool)$settings['points_enabled'] : true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    if (!$canEditAll) {
        $info = 'Brak uprawnień do edycji danych tego użytkownika.';
    } else {
        $newName = trim($_POST['name']);
        $newDesc = trim($_POST['description']);
        $newJoined = $_POST['joined'];
        $newRank = (int)$_POST['rank'];

        if ($newRank === 3) {
            $newLeft = $user['left_date'] ?: date('Y-m-d');
        } else {
            $newLeft = null;
        }

        if ($currentUserRank === 1 && $newRank === 0) {
            $info = 'Lider nie może nadawać rangi Administratora.';
        } elseif ($newRank < $currentUserRank) {
            $info = 'Nie możesz nadać wyższej rangi niż Twoja.';
        } else {
            try {
                $changes = [];

                if ($user['name'] !== $newName) {
                    $changes[] = "Nazwa: '{$user['name']}' → '$newName'";
                }
                if (($user['description'] ?? '') !== $newDesc) {
                    $changes[] = "Opis: '" . ($user['description'] ?? '') . "' → '$newDesc'";
                }
                if ($user['joined'] !== $newJoined) {
                    $changes[] = "Data dołączenia: {$user['joined']} → $newJoined";
                }
                $oldLeft = $user['left_date'] ?? null;
                if ($oldLeft !== $newLeft) {
                    $changes[] = "Data opuszczenia: " . ($oldLeft ?? 'brak') . " → " . ($newLeft ?? 'brak');
                }
                if ((int)$user['rank'] !== $newRank) {
                    $ranks = [0 => 'Administrator', 1 => 'Lider', 2 => 'Gracz', 3 => 'Ban'];
                    $oldRankText = $ranks[$user['rank']] ?? $user['rank'];
                    $newRankText = $ranks[$newRank] ?? $newRank;
                    $changes[] = "Ranga: $oldRankText → $newRankText";
                }

                $stmt = $pdo->prepare("UPDATE users SET name = ?, description = ?, joined = ?, left_date = ?, `rank` = ? WHERE id = ?");
                $stmt->execute([$newName, $newDesc, $newJoined, $newLeft, $newRank, $userId]);

                if (count($changes) > 0) {
                    $logText = "$currentUserName → {$user['name']} " . implode('; ', $changes);
                    addLog($pdo, $currentUserId, $logText, $userId);
                }

                $info = 'Dane użytkownika zostały zapisane.';

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

            } catch (PDOException $e) {
                $info = 'Błąd przy zapisie: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!$isOwnProfile) {
        $info = 'Nie możesz zmieniać hasła innego użytkownika.';
    } else {
        $oldPass = $_POST['old_pass'] ?? '';
        $newPass1 = $_POST['new_pass1'] ?? '';
        $newPass2 = $_POST['new_pass2'] ?? '';

        if (!password_verify($oldPass, $user['password'])) {
            $info = 'Aktualne hasło jest niepoprawne.';
        } elseif ($newPass1 !== $newPass2) {
            $info = 'Nowe hasła nie są takie same.';
        } elseif (strlen($newPass1) < 6) {
            $info = 'Nowe hasło jest za krótkie (minimum 6 znaków).';
        } else {
            $hash = password_hash($newPass1, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            $info = 'Hasło zostało zmienione.';
            addLog($pdo, $currentUserId, "$currentUserName zmienił swoje hasło", $currentUserId);
        }
    }
}

function hasPermission($userId, $permission) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE user_id = ? AND permission = ?");
    $stmt->execute([$userId, $permission]);
    return $stmt->fetchColumn() > 0;
}

$logs = '';
if (!$isTargetAdmin || $currentUserRank === 0 || $isOwnProfile) {
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE target_user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$userId]);
    $logsArr = $stmt->fetchAll();

    foreach ($logsArr as $log) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $log['created_at']);
        $formattedDate = $dt ? $dt->format('d.m.Y H:i') : $log['created_at'];
        $logs .= "[{$formattedDate}] {$log['log_text']}\n";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Edytuj profil</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Profil: <?= htmlspecialchars($user['name']) ?></h1>
    <?php include 'menu.php'; ?>
    <?php if ($info): ?>
        <p style="color: red;"><?= htmlspecialchars($info) ?></p>
    <?php endif; ?>

    <?php if ($isOwnProfile): ?>
        <h2>Zmień hasło</h2>
        <form method="post" style="margin-bottom: 20px;">
            <label>Aktualne hasło:</label><br>
            <input type="password" name="old_pass" required><br>
            <label>Nowe hasło:</label><br>
            <input type="password" name="new_pass1" required><br>
            <label>Powtórz nowe hasło:</label><br>
            <input type="password" name="new_pass2" required><br>
            <button type="submit" name="change_password">Zmień hasło</button>
        </form>
    <?php endif; ?>

    <?php if ($canEditAll): ?>
        <h2>Edycja danych użytkownika</h2>
        <form method="post">
            <label>Nazwa gracza:</label><br>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required><br>

            <label>Opis:</label><br>
            <textarea name="description"><?= htmlspecialchars($user['description'] ?? '') ?></textarea><br>

            <label>Data dołączenia:</label><br>
            <input type="date" name="joined" value="<?= htmlspecialchars($user['joined']) ?>" required><br>

            <label>Ranga:</label><br>
            <select name="rank">
                <option value="0" <?= $user['rank'] == 0 ? 'selected' : '' ?>>Administrator</option>
                <option value="1" <?= $user['rank'] == 1 ? 'selected' : '' ?>>Lider</option>
                <option value="2" <?= $user['rank'] == 2 ? 'selected' : '' ?>>Gracz</option>
                <option value="3" <?= $user['rank'] == 3 ? 'selected' : '' ?>>Ban</option>
            </select><br>

            <br><button type="submit" name="save_profile">Zapisz zmiany</button>
        </form>
    <?php endif; ?>

    <?php if ($logs): ?>
        <h3>Logi użytkownika</h3>
        <pre style="background: #f0f0f0; padding: 10px; max-height: 300px; overflow-y: scroll;"><?= htmlspecialchars($logs) ?></pre>
    <?php endif; ?>
</div>
</body>
</html>

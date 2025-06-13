<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserRank = $_SESSION['user_rank'];

if (!in_array($currentUserRank, [0, 1])) {
    die('Brak dostępu.');
}

// Pobierz nazwę aktualnego użytkownika (dodającego punkty)
$currentUserNameStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$currentUserNameStmt->execute([$currentUserId]);
$currentUserName = $currentUserNameStmt->fetchColumn();

$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
$wgPoints = json_decode($settings['wg_points'] ?? '{}', true);

// Pobierz użytkowników
$users = $pdo->query("SELECT id, name, last_wg FROM users WHERE `rank` != 3 ORDER BY name ASC")->fetchAll();
$userMap = [];
foreach ($users as $user) {
    $userMap[$user['name']] = $user;
}

$walkData = [];
$missingPlayers = [];
$error = '';

// --- Import CSV ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    $file = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($file, 0, ';');

    $nameIndex = array_search('player', $header);
    $walksIndex = array_search('solvedEncounters', $header);

    if ($nameIndex === false || $walksIndex === false) {
        $error = "Błąd: Nieprawidłowy format pliku CSV.";
    } else {
        while (($row = fgetcsv($file, 0, ';')) !== false) {
            $playerName = trim($row[$nameIndex]);
            $walks = (int)trim($row[$walksIndex]);

            if (isset($userMap[$playerName])) {
                $walkData[$userMap[$playerName]['id']] = $walks;
            } else {
                $missingPlayers[] = $playerName;
            }
        }
    }

    fclose($file);

    if (!empty($missingPlayers)) {
        $error = "Błąd: Nie znaleziono graczy " . implode(', ', $missingPlayers);
        $walkData = []; // Anuluj import danych
    }
}

// --- Zapis danych ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['walks'])) {
    foreach ($_POST['walks'] as $targetUserId => $walkCountRaw) {
        if ($walkCountRaw === '' || !is_numeric($walkCountRaw)) {
            continue;
        }

        $walkCount = intval($walkCountRaw);
        $bonusPoints = isset($_POST['bonus'][$targetUserId]) && is_numeric($_POST['bonus'][$targetUserId])
            ? floatval($_POST['bonus'][$targetUserId])
            : 0;

        // Oblicz punkty wg
        if ($walkCount < 16) {
            $wgPoint = $wgPoints['below16'] ?? 0;
        } else {
            $wgPoint = 0;
            $thresholds = array_filter(array_keys($wgPoints), 'is_numeric');
            rsort($thresholds);
            foreach ($thresholds as $threshold) {
                if ($walkCount >= $threshold) {
                    $wgPoint = $wgPoints[$threshold];
                    break;
                }
            }
        }

        $totalPoints = $wgPoint + $bonusPoints;

        $stmt = $pdo->prepare("UPDATE users SET last_wg = ?, season_wg = COALESCE(season_wg, 0) + ? WHERE id = ?");
        $stmt->execute([$walkCount, $totalPoints, $targetUserId]);

        $playerName = '';
        foreach ($users as $u) {
            if ($u['id'] == $targetUserId) {
                $playerName = $u['name'];
                break;
            }
        }

        $logText = "{$currentUserName} → {$playerName} WG: dodano {$walkCount} walk i {$bonusPoints} pkt; Uzyskano łączie {$totalPoints} pkt";
        addLog($pdo, $currentUserId, $logText, $targetUserId);
    }

    header("Location: dashboard.php?wg_updated=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Aktualizuj WG</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ccc; }
        th { background-color: #f0f0f0; }
        input[type=number] { width: 70px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Aktualizuj WG</h1>
    <?php include 'menu.php'; ?>

    <?php if (!empty($error)): ?>
        <p style="color:red;"><strong><?= htmlspecialchars($error) ?></strong></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label for="csv">Importuj plik Participation.csv generowany z FoE Helper:</label>
        <input type="file" name="csv" id="csv" accept=".csv">
        <button type="submit">Wczytaj dane</button>
    </form>

    <br><hr><br>

    <form method="post" action="update_wg.php">
        <table>
            <thead>
            <tr>
                <th>Nazwa gracza</th>
                <th>Ilość walk</th>
                <th>Dodatkowe pkt</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><input type="number" name="walks[<?= $user['id'] ?>]" min="0"
                               value="<?= isset($walkData[$user['id']]) ? $walkData[$user['id']] : '' ?>"></td>
                    <td><input type="number" name="bonus[<?= $user['id'] ?>]" step="0.1" value="0"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <button type="submit">Zapisz zmiany</button>
    </form>
</div>
</body>
</html>

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
$currentUserName = $_SESSION['user_name'];

if (!in_array($currentUserRank, [0, 1])) {
    die('Brak dostępu.');
}

$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
$pchThresholds = json_decode($settings['pch_thresholds'] ?? '[]', true);

$users = $pdo->query("SELECT id, name, last_pch FROM users WHERE `rank` != 3 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$userMap = [];
foreach ($users as $user) {
    $userMap[$user['name']] = $user;
}

$walksData = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        $csvFile = $_FILES['csv']['tmp_name'];
        $file = fopen($csvFile, 'r');
        $header = fgetcsv($file, 0, ';');
        $playerIndex = array_search('Player', $header);
        $fightsIndex = array_search('Fights', $header);

        if ($playerIndex === false || $fightsIndex === false) {
            $errors[] = "Nieprawidłowy format pliku CSV.";
        } else {
            $missingPlayers = [];
            $tempData = [];

            while (($row = fgetcsv($file, 0, ';')) !== false) {
                $playerName = $row[$playerIndex];
                $fights = (int)$row[$fightsIndex];

                if (!isset($userMap[$playerName])) {
                    $missingPlayers[] = $playerName;
                } else {
                    $userId = $userMap[$playerName]['id'];
                    $tempData[$userId] = $fights;
                }
            }

            fclose($file);

            if (!empty($missingPlayers)) {
                $errors[] = "Błąd: Nie znaleziono graczy: " . implode(', ', $missingPlayers);
            } else {
                $walksData = $tempData;
            }
        }
    } elseif (isset($_POST['walks'])) {
        foreach ($_POST['walks'] as $targetUserId => $walkCountRaw) {
            if ($walkCountRaw === '' || !is_numeric($walkCountRaw)) {
                continue;
            }

            $walkCount = intval($walkCountRaw);
            $bonusPoints = isset($_POST['bonus'][$targetUserId]) && is_numeric($_POST['bonus'][$targetUserId])
                ? floatval($_POST['bonus'][$targetUserId])
                : 0;

            usort($pchThresholds, fn($a, $b) => $a['walks'] <=> $b['walks']);

            $pchPoints = 0;
            $matchedThreshold = null;

            foreach ($pchThresholds as $threshold) {
                if ($walkCount >= $threshold['walks']) {
                    $matchedThreshold = $threshold;
                } else {
                    break;
                }
            }

            if ($matchedThreshold) {
                $pchPoints = $matchedThreshold['points'];
                $lastWalkValue = $matchedThreshold['walks'];

                if (
                    isset($matchedThreshold['multiplier']) && $matchedThreshold['multiplier'] &&
                    isset($matchedThreshold['multiplier_value'], $matchedThreshold['multiplier_points']) &&
                    $walkCount > $lastWalkValue
                ) {
                    $extraWalks = $walkCount - $lastWalkValue;
                    $steps = floor($extraWalks / $matchedThreshold['multiplier_value']);
                    $pchPoints += $steps * $matchedThreshold['multiplier_points'];
                }
            }

            $totalPoints = $pchPoints + $bonusPoints;

            $stmt = $pdo->prepare("UPDATE users SET last_pch = ?, season_pch = COALESCE(season_pch, 0) + ? WHERE id = ?");
            $stmt->execute([$walkCount, $totalPoints, $targetUserId]);

            // pobierz nazwę gracza
            $playerName = '';
            foreach ($users as $user) {
                if ($user['id'] == $targetUserId) {
                    $playerName = $user['name'];
                    break;
                }
            }

            $logText = "{$currentUserName} → {$playerName} PCH: dodano {$walkCount} walk i {$bonusPoints} pkt; Uzyskano łącznie {$totalPoints} pkt";
            addLog($pdo, $currentUserId, $logText, $targetUserId);
        }

        header("Location: dashboard.php?pch_updated=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Aktualizuj PCH</title>
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
    <h1>Aktualizuj PCH</h1>
    <?php include 'menu.php'; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red; margin-bottom: 10px;">
            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Importuj plik GBG-PlayerList.csv generowany z FoE Helper: <input type="file" name="csv" accept=".csv"></label>
        <button type="submit">Wczytaj dane</button>
    </form>

    <br><br>

    <form method="post">
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
                    <td>
                        <input type="number" name="walks[<?= $user['id'] ?>]" min="0"
                               value="<?= isset($walksData[$user['id']]) ? intval($walksData[$user['id']]) : '' ?>">
                    </td>
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

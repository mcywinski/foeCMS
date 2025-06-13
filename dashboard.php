<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRank = $_SESSION['user_rank'];

$hasPermission = function ($perm) use ($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE user_id = ? AND permission = ?");
    $stmt->execute([$userId, $perm]);
    return $stmt->fetchColumn() > 0;
};

$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();

$showPoints = $settings && $settings['points_enabled'];

$raceEnabled = $settings && $settings['race_enabled'];
$raceWGEnabled = $raceEnabled && $settings['race_wg_enabled'];
$racePCHEnabled = $raceEnabled && $settings['race_pch_enabled'];
$raceVisibility = $settings['race_visibility'];

function canViewRaceData($userRank, $raceVisibility)
{
    if ($raceVisibility === 'admin' && $userRank === 0) return true;
    if ($raceVisibility === 'leader' && ($userRank === 0 || $userRank === 1)) return true;
    if ($raceVisibility === 'all') return true;
    return false;
}

$showPointsToAll = $raceEnabled;
$showRace = $raceEnabled && canViewRaceData($userRank, $raceVisibility);

if ($userRank === 0 || $userRank === 1) {
    $stmt = $pdo->query("SELECT * FROM users");
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE `rank` != 3");
    $stmt->execute();
}

$activeUsers = [];
$bannedUsers = [];

while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $user['points'] = intval($user['season_wg']) + intval($user['season_pch']);
    if ((int)$user['rank'] === 3) {
        $bannedUsers[] = $user;
    } else {
        $activeUsers[] = $user;
    }
}

usort($activeUsers, fn($a, $b) => $b['points'] <=> $a['points']);
usort($bannedUsers, fn($a, $b) => $b['points'] <=> $a['points']);

$sortedUsers = array_merge($activeUsers, $bannedUsers);

// Generowanie raportu
$generatedReport = '';
if (isset($_POST['generate_report']) && ($userRank === 0 || $userRank === 1)) {
    $generatedReport = "Ostatnia aktualizacja: " . date('d.m.Y') . "\n";
    if (!empty($settings['race_end_date'])) {
        $generatedReport .= "Koniec wyścigu: " . date('d.m.Y', strtotime($settings['race_end_date'])) . "\n";
    }
    $generatedReport .= "\n";
    foreach ($activeUsers as $user) {
        $generatedReport .= "{$user['points']} pkt :: {$user['name']}\n";
    }
}

// Zakończenie sezonu
if (isset($_POST['end_season']) && $userRank === 0) {
    $now = date('YmdHis');
    $filename = __DIR__ . "/seasons_summary/$now.txt";
    $summary = "Podsumowanie sezonu z dnia " . date('d.m.Y H:i:s') . "\n\n";

    foreach ($sortedUsers as $u) {
        $total = intval($u['season_wg']) + intval($u['season_pch']);
        $summary .= $u['name'] . ": WG: {$u['season_wg']}, PCH: {$u['season_pch']}, SUMA: $total\n";
    }

    file_put_contents($filename, $summary);

    $pdo->exec("UPDATE users SET last_wg = 0, season_wg = 0, last_pch = 0, season_pch = 0");
    header("Location: dashboard.php?season_reset=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Panel Główny</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>Jesteś zalogowany jako <?= htmlspecialchars($userName) ?></h1>
        <?php include 'menu.php'; ?>

        <?php if (isset($_GET['season_reset'])): ?>
            <p style="color: green; font-weight: bold;">Sezon został zakończony i dane zostały wyczyszczone.</p>
        <?php endif; ?>

        <form method="post">
            <?php if ($showPointsToAll): ?>
                <?php if ($userRank === 0 || $userRank === 1): ?>
                    <a href="update_wg.php"><button type="button">Aktualizuj WG</button></a>
                    <a href="update_pch.php"><button type="button">Aktualizuj PCH</button></a>
                    <button type="submit" name="generate_report">Generuj raport</button>
                <?php endif; ?>
                <?php if ($userRank === 0): ?>
                    <button type="submit" name="end_season" onclick="return confirm('Czy na pewno chcesz zakończyć sezon i wyczyścić punktację?')">Zakończ Sezon</button>
                <?php endif; ?>
            <?php endif; ?>
        </form><br>

        <?php if (!empty($generatedReport)): ?>
            <h3>Raport:</h3>
            <center><textarea rows="15" cols="60" style="width:100%; max-width:800px;"><?= htmlspecialchars($generatedReport) ?></textarea></center><br>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nazwa gracza</th>
                    <th>Ranga</th>
                    <?php if ($showRace && $raceWGEnabled): ?>
                        <th>Ostatnie WG<br>walki</th>
                        <th>Sezon WG<br>punkty</th>
                    <?php endif; ?>
                    <?php if ($showRace && $racePCHEnabled): ?>
                        <th>Ostatnie PCH<br>walki</th>
                        <th>Sezon PCH<br>punkty</th>
                    <?php endif; ?>
                    <?php if ($showPointsToAll): ?>
                        <th>Punkty</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $index = 1;
                foreach ($activeUsers as $user) {
                    $own = ($user['id'] == $userId);
                    $isTargetAdmin = (int)$user['rank'] === 0;

                    echo "<tr>";
                    echo "<td>" . $index++ . "</td>";
                    echo "<td>";
                    if ($own) {
                        echo "<a href='profile.php?id=" . $userId . "'>" . htmlspecialchars($user['name']) . "</a>";
                    } elseif ($userRank === 0 || ($userRank === 1 && !$isTargetAdmin)) {
                        echo "<a href='profile.php?id=" . $user['id'] . "'>" . htmlspecialchars($user['name']) . "</a>";
                    } else {
                        echo htmlspecialchars($user['name']);
                    }
                    echo "</td>";
                    echo "<td>" . rankToStr($user['rank']) . "</td>";

                    if ($showRace && $raceWGEnabled) {
                        echo "<td>" . intval($user['last_wg']) . "</td>";
                        echo "<td>" . intval($user['season_wg']) . "</td>";
                    }

                    if ($showRace && $racePCHEnabled) {
                        echo "<td>" . intval($user['last_pch']) . "</td>";
                        echo "<td>" . intval($user['season_pch']) . "</td>";
                    }

                    if ($showPointsToAll) {
                        echo "<td>" . $user['points'] . "</td>";
                    }

                    echo "</tr>";
                }

                if (!empty($bannedUsers)) {
                    echo "<tr><td colspan='100%' style='font-weight:bold; color:red;'>ZABLOKOWANI:</td></tr>";
                    $index = 1;
                    foreach ($bannedUsers as $user) {
                        $own = ($user['id'] == $userId);
                        $isTargetAdmin = (int)$user['rank'] === 0;

                        echo "<tr>";
                        echo "<td>" . $index++ . "</td>";
                        echo "<td>";
                        if ($own) {
                            echo "<a href='profile.php?id=" . $userId . "'>" . htmlspecialchars($user['name']) . "</a>";
                        } elseif ($userRank === 0 || ($userRank === 1 && !$isTargetAdmin)) {
                            echo "<a href='profile.php?id=" . $user['id'] . "'>" . htmlspecialchars($user['name']) . "</a>";
                        } else {
                            echo htmlspecialchars($user['name']);
                        }
                        echo "</td>";
                        echo "<td>" . rankToStr($user['rank']) . "</td>";

                        if ($showRace && $raceWGEnabled) {
                            echo "<td>" . intval($user['last_wg']) . "</td>";
                            echo "<td>" . intval($user['season_wg']) . "</td>";
                        }

                        if ($showRace && $racePCHEnabled) {
                            echo "<td>" . intval($user['last_pch']) . "</td>";
                            echo "<td>" . intval($user['season_pch']) . "</td>";
                        }

                        if ($showPointsToAll) {
                            echo "<td>" . $user['points'] . "</td>";
                        }

                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <?php if ($userRank === 0 || $userRank === 1 || $hasPermission('create_user')): ?>
            <a href="create_user.php"><button>Utwórz nowe konto</button></a>
        <?php endif; ?>
    </div>
</body>

</html>
<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_rank']) || $_SESSION['user_rank'] != 0) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guildName = trim($_POST['guild_name'] ?? '');
    $header = trim($_POST['site_header'] ?? '');
    $description = trim($_POST['site_description'] ?? '');
    $footer = trim($_POST['site_footer'] ?? '');

    $raceEnabled = isset($_POST['race_enabled']) ? 1 : 0;
    $raceWGEnabled = isset($_POST['race_wg_enabled']) ? 1 : 0;
    $racePCHEnabled = isset($_POST['race_pch_enabled']) ? 1 : 0;
    $raceVisibility = in_array($_POST['race_visibility'] ?? '', ['admin', 'leader', 'all']) ? $_POST['race_visibility'] : 'admin';

    $raceEndDate = trim($_POST['race_end_date'] ?? '');

    $wgPoints = json_encode([
        'below16' => floatval($_POST['wg_below16'] ?? 0),
        16 => floatval($_POST['wg_16'] ?? 0),
        32 => floatval($_POST['wg_32'] ?? 0),
        48 => floatval($_POST['wg_48'] ?? 0),
        64 => floatval($_POST['wg_64'] ?? 0),
        80 => floatval($_POST['wg_80'] ?? 0)
    ]);

    $pchThresholds = json_encode([
        ['walks' => intval($_POST['pch_1_walks']), 'points' => floatval($_POST['pch_1_points'])],
        ['walks' => intval($_POST['pch_2_walks']), 'points' => floatval($_POST['pch_2_points'])],
        ['walks' => intval($_POST['pch_3_walks']), 'points' => floatval($_POST['pch_3_points'])],
        [
            'walks' => intval($_POST['pch_4_walks']),
            'points' => floatval($_POST['pch_4_points']),
            'multiplier' => isset($_POST['pch_4_multiplier']) ? 1 : 0,
            'multiplier_value' => intval($_POST['pch_4_multiplier_value'] ?? 0),
            'multiplier_points' => floatval($_POST['pch_4_multiplier_points'] ?? 0)
        ],
    ]);

    $stmt = $pdo->prepare("UPDATE settings SET 
        guild_name = ?, site_header = ?, site_description = ?, site_footer = ?, 
        race_enabled = ?, race_wg_enabled = ?, race_pch_enabled = ?, race_visibility = ?,
        race_end_date = ?, wg_points = ?, pch_thresholds = ?
        WHERE id = 1");

    if (!$stmt->execute([
        $guildName,
        $header,
        $description,
        $footer,
        $raceEnabled,
        $raceWGEnabled,
        $racePCHEnabled,
        $raceVisibility,
        $raceEndDate,
        $wgPoints,
        $pchThresholds
    ])) {
        $errors[] = "Błąd zapisu ustawień.";
    } else {
        header("Location: settings.php?success=1");
        exit;
    }
}

$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
$wgPoints = json_decode($settings['wg_points'] ?? '{}', true);
$pchThresholds = json_decode($settings['pch_thresholds'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['guild_name'] ?? 'Gildia') ?> - Ustawienia</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>Ustawienia strony</h1>
        <?php include 'menu.php'; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?>
                    <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <p class="success-msg">Ustawienia zostały zapisane.</p>
        <?php endif; ?>

        <form method="post" class="form-section">
            <label>Nazwa gildii:</label>
            <input type="text" name="guild_name" value="<?= htmlspecialchars($settings['guild_name'] ?? '') ?>">

            <label>Nagłówek:</label>
            <input type="text" name="site_header" value="<?= htmlspecialchars($settings['site_header'] ?? '') ?>">

            <label>Opis:</label>
            <textarea name="site_description" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>

            <label>Stopka:</label>
            <input type="text" name="site_footer" value="<?= htmlspecialchars($settings['site_footer'] ?? '') ?>">

            <fieldset>
                <legend>Wyścig</legend>

                <label><input type="checkbox" name="race_enabled" value="1" <?= $settings['race_enabled'] ? 'checked' : '' ?>> Włącz rubryki wyścigu</label><br><br>
                <label><input type="checkbox" name="race_wg_enabled" value="1" <?= $settings['race_wg_enabled'] ? 'checked' : '' ?>> Wyprawy Gildijne (WG)</label><br>
                <label><input type="checkbox" name="race_pch_enabled" value="1" <?= $settings['race_pch_enabled'] ? 'checked' : '' ?>> Gildijne Pola Chwały (GPC/PCh)</label>
                <br><br>
                <label>Widoczność:</label>
                <select name="race_visibility">
                    <option value="admin" <?= ($settings['race_visibility'] === 'admin') ? 'selected' : '' ?>>Tylko administrator</option>
                    <option value="leader" <?= ($settings['race_visibility'] === 'leader') ? 'selected' : '' ?>>Administrator i lider</option>
                    <option value="all" <?= ($settings['race_visibility'] === 'all') ? 'selected' : '' ?>>Wszyscy</option>
                </select>

                <label>Data zakończenia wyścigu:</label>
                <input type="date" name="race_end_date" value="<?= htmlspecialchars($settings['race_end_date'] ?? '') ?>">

                <div class="subsection">
                    <h3>WG – Punkty za konflikty</h3>
                    <label>Mniej niż 16 konfliktów:</label>
                    <input type="number" step="0.1" name="wg_below16" value="<?= $wgPoints['below16'] ?? 0 ?>">

                    <?php foreach ([16, 32, 48, 64, 80] as $val): ?>
                        <label>Za <?= $val ?> konfliktów:</label>
                        <input type="number" step="0.1" name="wg_<?= $val ?>" value="<?= $wgPoints[$val] ?? 0 ?>">
                    <?php endforeach; ?>
                </div>

                <div class="subsection">
                    <h3>PCH – Progi</h3>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <label>Próg <?= $i ?> – Ilość walk:</label>
                        <input type="number" name="pch_<?= $i ?>_walks" value="<?= $pchThresholds[$i - 1]['walks'] ?? 0 ?>">
                        <label>Próg <?= $i ?> – Punkty:</label>
                        <input type="number" step="0.1" name="pch_<?= $i ?>_points" value="<?= $pchThresholds[$i - 1]['points'] ?? 0 ?>">
                        <hr>
                        <?php if ($i === 4): ?>
                            <label><input type="checkbox" name="pch_4_multiplier" <?= !empty($pchThresholds[3]['multiplier']) ? 'checked' : '' ?>> Użyj mnożnika</label>
                            <br><br>
                            <label>Ilość walk dla mnożnika:</label>
                            <input type="number" name="pch_4_multiplier_value" value="<?= $pchThresholds[3]['multiplier_value'] ?? 0 ?>">
                            <label>Punkty za każdy nadmiarowy próg z mnożnikiem:</label>
                            <input type="number" step="0.1" name="pch_4_multiplier_points" value="<?= $pchThresholds[3]['multiplier_points'] ?? 0 ?>">
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </fieldset><br>

            <button type="submit">Zapisz ustawienia</button>
        </form>
    </div>
</body>

</html>
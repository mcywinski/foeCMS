<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host']);
    $dbName = trim($_POST['db_name']);
    $dbUser = trim($_POST['db_user']);
    $dbPass = trim($_POST['db_pass']);

    $adminLogin = trim($_POST['admin_login']);
    $adminPass = trim($_POST['admin_pass']);
    $guildName = trim($_POST['guild_name']);

    if (!$host || !$dbName || !$dbUser || !$adminLogin || !$adminPass || !$guildName) {
        $error = "Wszystkie pola są wymagane.";
    } else {
        $dbContent = "<?php
\$host = '$host';
\$db   = '$dbName';
\$user = '$dbUser';
\$pass = '$dbPass';
\$charset = 'utf8mb4';

\$dsn = \"mysql:host=\$host;dbname=\$db;charset=\$charset\";
\$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (PDOException \$e) {
    throw new PDOException(\$e->getMessage(), (int)\$e->getCode());
}";

        file_put_contents('db.php', $dbContent);
        require 'db.php';

        try {
            // Struktura bazy
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `articles` (
      `id` int NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL DEFAULT '',
      `excerpt` text NOT NULL,
      `content` text NOT NULL,
      `author_id` int DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT NULL,
      `approved_by` int DEFAULT NULL,
      `approved_at` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `author_id` (`author_id`),
      KEY `approved_by` (`approved_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin2;

    CREATE TABLE IF NOT EXISTS `chat_messages` (
      `id` int NOT NULL AUTO_INCREMENT,
      `user_id` int NOT NULL,
      `message` text NOT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `pinned` tinyint(1) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin2;

    CREATE TABLE IF NOT EXISTS `logs` (
      `id` int NOT NULL AUTO_INCREMENT,
      `user_id` int DEFAULT NULL,
      `target_user_id` int DEFAULT NULL,
      `log_text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `logs_ibfk_2` (`target_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    CREATE TABLE IF NOT EXISTS `permissions` (
      `id` int NOT NULL AUTO_INCREMENT,
      `user_id` int NOT NULL,
      `permission` varchar(100) NOT NULL DEFAULT '',
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

    CREATE TABLE IF NOT EXISTS `settings` (
      `id` int NOT NULL AUTO_INCREMENT,
      `site_header` varchar(255) NOT NULL DEFAULT '',
      `site_description` text NOT NULL,
      `site_footer` varchar(255) NOT NULL DEFAULT '',
      `points_enabled` tinyint(1) NOT NULL DEFAULT 0,
      `guild_name` varchar(255) DEFAULT NULL,
      `race_enabled` tinyint(1) NOT NULL DEFAULT 0,
      `race_wg_enabled` tinyint(1) NOT NULL DEFAULT 0,
      `race_pch_enabled` tinyint(1) NOT NULL DEFAULT 0,
      `race_visibility` enum('admin','leader','all') NOT NULL DEFAULT 'admin',
      `race_end_date` varchar(10) DEFAULT NULL,
      `wg_points` text DEFAULT NULL,
      `pch_thresholds` text DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin2;

    INSERT INTO `settings` (`id`, `site_header`, `site_description`, `site_footer`, `points_enabled`)
    VALUES (1, 'foeCMS - System zarządzania gildią', 'Witamy w systemie zarządzania gildią w grze Forge of Empires', 'foeCMS', 1)
    ON DUPLICATE KEY UPDATE id = id;

    CREATE TABLE IF NOT EXISTS `strategies` (
      `id` int NOT NULL AUTO_INCREMENT,
      `author_id` int DEFAULT NULL,
      `message` text NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `author_id` (`author_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin2;

    CREATE TABLE IF NOT EXISTS `users` (
      `id` int NOT NULL AUTO_INCREMENT,
      `login` varchar(50) NOT NULL,
      `name` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `rank` tinyint NOT NULL DEFAULT '2',
      `status` enum('active','banned') CHARACTER SET latin2 COLLATE latin2_general_ci NOT NULL DEFAULT 'active',
      `joined` date DEFAULT NULL,
      `left_date` date DEFAULT NULL,
      `description` text NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `last_wg` INT DEFAULT NULL,
      `season_wg` INT DEFAULT NULL,
      `last_pch` INT DEFAULT NULL,
      `season_pch` INT DEFAULT NULL,
      `rating` VARCHAR(10) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `login` (`login`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin2;

    CREATE TABLE IF NOT EXISTS `votings` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `title` VARCHAR(255) NOT NULL,
      `description` TEXT NOT NULL,
      `created_by` INT NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `end_time` DATETIME DEFAULT NULL,
      `closed` TINYINT(1) DEFAULT 0,
      `archived` TINYINT(1) DEFAULT 0,
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    );

    CREATE TABLE IF NOT EXISTS `voting_votes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `voting_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `vote` ENUM('za', 'przeciw') NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY (`voting_id`, `user_id`),
      FOREIGN KEY (`voting_id`) REFERENCES `votings`(`id`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    );

    CREATE TABLE IF NOT EXISTS `voting_comments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `voting_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `comment` TEXT NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT NULL,
      FOREIGN KEY (`voting_id`) REFERENCES `votings`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    );

    ALTER TABLE `articles`
      ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
      ADD CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

    ALTER TABLE `chat_messages`
      ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

    ALTER TABLE `logs`
      ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
      ADD CONSTRAINT `logs_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`);

    ALTER TABLE `permissions`
      ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

    ALTER TABLE `strategies`
      ADD CONSTRAINT `strategies_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);
    
      INSERT INTO settings (id, site_header, site_description, site_footer, points_enabled, guild_name)
                VALUES (1, 'foeCMS - System zarządzania gildią', 'Witamy w systemie zarządzania gildią w grze Forge of Empires', 'foeCMS', 1, '$guildName')
                ON DUPLICATE KEY UPDATE guild_name = VALUES(guild_name);
            ");

            // Dodanie administratora
            $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);

            // Sprawdzenie czy użytkownik admin już istnieje
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
            $stmt->execute([$adminLogin]);

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("INSERT INTO users (login, name, password, `rank`, status, joined, description, created_at)
                                       VALUES (?, 'Administrator', ?, 0, 'active', CURDATE(), '', NOW())");
                $stmt->execute([$adminLogin, $passwordHash]);
            }

            unlink('install.php');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = "Błąd podczas tworzenia bazy danych: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Instalacja systemu</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>Instalacja systemu</h1>

        <?php if (isset($error)): ?>
            <div class="error-box">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" class="form-section">
            <h2>Dane bazy danych</h2>
            <label>Host:</label>
            <input type="text" name="db_host" required>

            <label>Nazwa bazy danych:</label>
            <input type="text" name="db_name" required>

            <label>Użytkownik bazy:</label>
            <input type="text" name="db_user" required>

            <label>Hasło bazy:</label>
            <input type="password" name="db_pass">

            <h2>Dane administratora</h2>
            <label>Login administratora:</label>
            <input type="text" name="admin_login" required>

            <label>Hasło administratora:</label>
            <input type="password" name="admin_pass" required>

            <h2>Dane gildii</h2>
            <label>Nazwa gildii:</label>
            <input type="text" name="guild_name" required>

            <button type="submit">Zainstaluj</button>
        </form>
    </div>
</body>

</html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';

if ($login && $password) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['rank'] == 3 || $user['blocked']) {
            if (!empty($user['left_date']) && $user['left_date'] !== '0000-00-00') {
                $formattedDate = date("d.m.Y", strtotime($user['left_date']));
                $_SESSION['error'] = "Konto zablokowane od $formattedDate";
            } else {
                $_SESSION['error'] = "Konto zablokowane";
            }
            header("Location: index.php");
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_rank'] = $user['rank'];
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = "Login lub hasło jest niepoprawne";
        header("Location: index.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Wypełnij wszystkie pola";
    header("Location: index.php");
    exit;
}
?>

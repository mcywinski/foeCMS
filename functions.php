<?php
function rankToStr($rank) {
    return match ($rank) {
        0 => 'Administrator',
        1 => 'Lider',
        2 => 'Gracz',
        3 => 'Zablokowany',
        default => 'Nieznany'
    };
}

function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d.m.Y H:i', strtotime($datetime));
}

function addLog($pdo, $userId, $logText, $targetUserId = null)
{
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, log_text, target_user_id, created_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $logText, $targetUserId, $now]);
}


<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/activity_logger.php';

if (is_logged_in()) {
    $user = get_logged_user();
    log_activity('logout', 'user', $user['id'], "Logout: {$user['full_name']}", 'success');
}

session_destroy();
header('Location: /login.php');
exit;
?>

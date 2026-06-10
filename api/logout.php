<?php
// api/logout.php — Logout & hapus session
session_start();
$wasAdmin = !empty($_SESSION['is_admin']);
session_unset();
session_destroy();

// Kalau dipanggil via fetch (AJAX dari user side), kembalikan JSON
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
       || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logout berhasil']);
} else {
    // Redirect admin ke halaman login admin, user biasa ke index
    header('Location: ' . ($wasAdmin ? '../admin_login.php' : '../index.html'));
}
exit;

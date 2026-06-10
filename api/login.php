<?php
// ============================================================
// api/login.php — Proses Login
// ============================================================

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Ambil data dari request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

// Validasi
if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email dan password wajib diisi']);
    exit;
}

// Cari user berdasarkan email
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Cek password
if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email atau password salah']);
    exit;
}

// Simpan session (penting untuk admin.php)
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_nama'] = $user['nama'];
$_SESSION['is_admin']  = (bool)$user['is_admin'];

// Hapus password dari response
unset($user['password']);

echo json_encode([
    'success' => true,
    'message' => 'Login berhasil!',
    'user'    => $user
]);

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Method tidak diizinkan']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$nama     = trim($data['nama']     ?? '');
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

if (!$nama || !$email || !$password) { echo json_encode(['success'=>false,'message'=>'Semua kolom wajib diisi']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Format email tidak valid']); exit; }
if (strlen($password) < 6) { echo json_encode(['success'=>false,'message'=>'Password minimal 6 karakter']); exit; }

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) { echo json_encode(['success'=>false,'message'=>'Email sudah terdaftar']); exit; }

$username_base = '@' . strtolower(str_replace(' ', '.', $nama));
$username = $username_base; $counter = 1;
while (true) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if (!$stmt->fetch()) break;
    $username = $username_base . $counter++;
}

$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('INSERT INTO users (nama, username, email, password) VALUES (?, ?, ?, ?)');
$stmt->execute([$nama, $username, $email, $hashed]);
$user_id = $pdo->lastInsertId();

$stmt = $pdo->prepare('SELECT id, nama, username, email, avatar, lokasi, bio FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

echo json_encode(['success'=>true,'message'=>'Akun berhasil dibuat!','user'=>$user]);

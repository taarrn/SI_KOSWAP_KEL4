<?php
// ============================================================
// api/update_profil.php — Update profil user
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId   = (int)($data['user_id']  ?? 0);
$nama     = trim($data['nama']      ?? '');
$username = trim($data['username']  ?? '');
$bio      = trim($data['bio']       ?? '');
$lokasi   = trim($data['lokasi']    ?? '');
$avatar   = trim($data['avatar']    ?? '');

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit;
}

if (!$nama) {
    echo json_encode(['success' => false, 'message' => 'Nama tidak boleh kosong']);
    exit;
}

// Cek username unik (kecuali milik diri sendiri)
if ($username) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username sudah dipakai orang lain']);
        exit;
    }
}

// Update
$stmt = $pdo->prepare('
    UPDATE users SET nama = ?, username = ?, bio = ?, lokasi = ?, avatar = ?
    WHERE id = ?
');
$stmt->execute([$nama, $username, $bio, $lokasi, $avatar, $userId]);

// Ambil data terbaru
$stmt = $pdo->prepare('SELECT id, nama, username, email, avatar, bio, lokasi FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

echo json_encode([
    'success' => true,
    'message' => 'Profil berhasil diperbarui!',
    'user'    => $user,
]);

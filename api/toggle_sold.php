<?php
// ============================================================
// api/toggle_sold.php — Toggle status Sold Out produk
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

session_start();
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$produkId = (int)($data['produk_id'] ?? 0);
$userId   = (int)($data['user_id']   ?? 0);

if (!$produkId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Pastikan produk milik user ybs (atau admin)
$stmt = $pdo->prepare('SELECT user_id, sold_out FROM produk WHERE id = ?');
$stmt->execute([$produkId]);
$produk = $stmt->fetch();

if (!$produk) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    exit;
}

// Cek kepemilikan
$isAdmin = false;
if ($userId) {
    $uStmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
    $uStmt->execute([$userId]);
    $u = $uStmt->fetch();
    $isAdmin = (bool)($u['is_admin'] ?? false);
}

if ($produk['user_id'] !== $userId && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Tidak punya akses ke produk ini']);
    exit;
}

// Toggle
$newStatus = $produk['sold_out'] ? 0 : 1;
$pdo->prepare('UPDATE produk SET sold_out = ? WHERE id = ?')->execute([$newStatus, $produkId]);

echo json_encode([
    'success'   => true,
    'sold_out'  => (bool)$newStatus,
    'message'   => $newStatus ? 'Produk ditandai SOLD OUT' : 'Produk aktif kembali',
]);

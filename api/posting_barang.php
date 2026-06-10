<?php
// ============================================================
// api/posting_barang.php — Simpan produk baru ke database
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$userId   = (int)($data['user_id']   ?? 0);
$nama     = trim($data['nama']       ?? '');
$harga    = (int)($data['harga']     ?? 0);
$kategori = trim($data['kategori']   ?? '');
$deskripsi= trim($data['deskripsi']  ?? '');
$img      = trim($data['img']        ?? '');

if (!$userId || !$nama || !$harga || !$kategori) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$stmt = $pdo->prepare('
    INSERT INTO produk (user_id, nama, harga, kategori, deskripsi, img, sold_out)
    VALUES (?, ?, ?, ?, ?, ?, 0)
');
$stmt->execute([$userId, $nama, $harga, $kategori, $deskripsi, $img]);
$produkId = $pdo->lastInsertId();

// Ambil data lengkap produk yang baru dibuat
$stmt = $pdo->prepare("
    SELECT p.id, p.nama, p.harga, p.kategori, p.deskripsi, p.img, p.sold_out,
           u.id AS penjual_id, u.nama AS penjual_nama, u.username AS penjual_username,
           u.avatar AS penjual_avatar
    FROM produk p JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$produkId]);
$r = $stmt->fetch();

echo json_encode([
    'success' => true,
    'message' => 'Barang berhasil diposting!',
    'produk'  => [
        'id'       => (int)$r['id'],
        'nama'     => $r['nama'],
        'harga'    => (int)$r['harga'],
        'kategori' => $r['kategori'],
        'deskripsi'=> $r['deskripsi'] ?? '',
        'img'      => $r['img'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&q=80',
        'soldOut'  => false,
        'penjual'  => [
            'id'      => (int)$r['penjual_id'],
            'nama'    => $r['penjual_nama'],
            'username'=> $r['penjual_username'],
            'avatar'  => $r['penjual_avatar'] ?: 'https://i.pravatar.cc/100',
        ],
    ],
]);

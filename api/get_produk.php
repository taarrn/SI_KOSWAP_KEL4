<?php
// ============================================================
// api/get_produk.php — Ambil semua produk dari database
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db.php';

$kategori = trim($_GET['kategori'] ?? '');
$search   = trim($_GET['search']   ?? '');

$sql = "
    SELECT p.id, p.nama, p.harga, p.kategori, p.deskripsi, p.img, p.sold_out,
           u.id AS penjual_id, u.nama AS penjual_nama, u.username AS penjual_username,
           u.avatar AS penjual_avatar
    FROM produk p
    JOIN users u ON p.user_id = u.id
    WHERE u.is_admin = 0
";
$params = [];

if ($kategori && $kategori !== 'Semua') {
    $sql .= " AND p.kategori = ?";
    $params[] = $kategori;
}
if ($search) {
    $sql .= " AND (p.nama LIKE ? OR p.kategori LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Format sesuai struktur JS yang sudah ada
$produk = array_map(function($r) {
    return [
        'id'       => (int)$r['id'],
        'nama'     => $r['nama'],
        'harga'    => (int)$r['harga'],
        'kategori' => $r['kategori'],
        'deskripsi'=> $r['deskripsi'] ?? '',
        'img'      => $r['img'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&q=80',
        'soldOut'  => (bool)$r['sold_out'],
        'penjual'  => [
            'id'      => (int)$r['penjual_id'],
            'nama'    => $r['penjual_nama'],
            'username'=> $r['penjual_username'],
            'avatar'  => $r['penjual_avatar'] ?: 'https://i.pravatar.cc/100',
        ],
    ];
}, $rows);

echo json_encode(['success' => true, 'data' => $produk]);

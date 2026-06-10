<?php
// ============================================================
// api/catat_deal.php — Catat transaksi deal ke tabel penjualan
// Dipanggil otomatis saat seller klik "Konfirmasi Deal"
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true) ?? [];
$produkId  = (int)($data['produk_id']  ?? 0);
$sellerId  = (int)($data['seller_id']  ?? 0);
$buyerId   = (int)($data['buyer_id']   ?? 0);
$hargaDeal = (int)($data['harga_deal'] ?? 0);

if (!$produkId || !$sellerId) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap (produk_id atau seller_id kosong)']);
    exit;
}

// Jika harga_deal tidak dikirim / 0, fallback ke harga produk
if (!$hargaDeal) {
    $rowHarga = $pdo->prepare('SELECT harga FROM produk WHERE id = ?');
    $rowHarga->execute([$produkId]);
    $hargaDeal = (int)($rowHarga->fetchColumn() ?: 0);
}

// Cek apakah deal ini sudah dicatat sebelumnya (hindari duplikat)
$cek = $pdo->prepare('SELECT id FROM penjualan WHERE produk_id = ? AND seller_id = ? AND status = "deal" ORDER BY created_at DESC LIMIT 1');
$cek->execute([$produkId, $sellerId]);
if ($cek->fetch()) {
    echo json_encode(['success' => true, 'message' => 'Deal sudah tercatat sebelumnya']);
    exit;
}

// Catat ke tabel penjualan
$stmt = $pdo->prepare('
    INSERT INTO penjualan (produk_id, seller_id, buyer_id, harga_deal, status)
    VALUES (?, ?, ?, ?, "deal")
');
$stmt->execute([$produkId, $sellerId, $buyerId ?: null, $hargaDeal]);

// Tandai produk sebagai sold out
$pdo->prepare('UPDATE produk SET sold_out = 1 WHERE id = ?')->execute([$produkId]);

echo json_encode([
    'success' => true,
    'message' => 'Deal berhasil dicatat!',
    'penjualan_id' => $pdo->lastInsertId(),
]);

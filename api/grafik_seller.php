<?php
header('Content-Type: application/json');
require_once '../db.php';

$sellerId = (int)($_GET['seller_id'] ?? 0);
if (!$sellerId) { echo json_encode(['success'=>false]); exit; }

$bulanList = [];
for ($i = 11; $i >= 0; $i--) $bulanList[] = date('Y-m', strtotime("-$i months"));

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS bulan,
           COUNT(*) AS jumlah,
           COALESCE(SUM(harga_deal),0) AS omzet
    FROM penjualan
    WHERE seller_id = ? AND status = 'deal'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY bulan ORDER BY bulan ASC
");
$stmt->execute([$sellerId]);
$raw = [];
foreach ($stmt->fetchAll() as $r) $raw[$r['bulan']] = $r;

$labels = $jumlah = $omzet = [];
foreach ($bulanList as $b) {
    $dt = DateTime::createFromFormat('Y-m', $b);
    $labels[] = $dt ? $dt->format('M Y') : $b;
    $jumlah[] = isset($raw[$b]) ? (int)$raw[$b]['jumlah'] : 0;
    $omzet[]  = isset($raw[$b]) ? (int)$raw[$b]['omzet']  : 0;
}

echo json_encode(['success'=>true, 'labels'=>$labels, 'jumlah'=>$jumlah, 'omzet'=>$omzet,
    'total_deal' => array_sum($jumlah), 'total_omzet' => array_sum($omzet)]);
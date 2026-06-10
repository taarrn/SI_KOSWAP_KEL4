<?php
// ============================================================
// api/chat.php — Semua operasi chat (get list, get messages, send)
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');

require_once '../db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET DAFTAR CHAT ──────────────────────────────────────────
if ($action === 'get_list') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['success'=>false,'message'=>'User ID diperlukan']); exit; }

    // Ambil semua chat milik user ini (sebagai buyer atau seller)
    $stmt = $pdo->prepare("
        SELECT c.id, c.buyer_id, c.seller_id, c.produk_id,
               ub.nama AS buyer_nama, ub.username AS buyer_username, ub.avatar AS buyer_avatar,
               us.nama AS seller_nama, us.username AS seller_username, us.avatar AS seller_avatar,
               p.nama AS produk_nama, p.harga AS produk_harga, p.img AS produk_img,
               (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread
        FROM chats c
        JOIN users ub ON c.buyer_id  = ub.id
        JOIN users us ON c.seller_id = us.id
        JOIN produk p  ON c.produk_id = p.id
        WHERE c.buyer_id = ? OR c.seller_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $chats = $stmt->fetchAll();

    $result = [];
    foreach ($chats as $c) {
        $role = ($c['buyer_id'] == $userId) ? 'buyer' : 'seller';
        $contact = ($role === 'buyer')
            ? ['id'=>(int)$c['seller_id'], 'nama'=>$c['seller_nama'], 'username'=>$c['seller_username'], 'avatar'=>$c['seller_avatar']]
            : ['id'=>(int)$c['buyer_id'],  'nama'=>$c['buyer_nama'],  'username'=>$c['buyer_username'],  'avatar'=>$c['buyer_avatar']];

        // Ambil pesan terakhir
        $lastStmt = $pdo->prepare("SELECT type, content, tawar_harga FROM messages WHERE chat_id = ? ORDER BY created_at DESC LIMIT 1");
        $lastStmt->execute([$c['id']]);
        $last = $lastStmt->fetch();

        $result[] = [
            'chat_id'      => (int)$c['id'],
            'role'         => $role,
            'unread'       => (int)$c['unread'],
            'contact'      => $contact,
            'produk'       => ['id'=>(int)$c['produk_id'], 'nama'=>$c['produk_nama'], 'harga'=>(int)$c['produk_harga'], 'img'=>$c['produk_img']],
            'last_message' => $last ? ($last['type']==='nego' ? 'Nego: Rp '.number_format($last['tawar_harga'],0,',','.') : $last['content']) : '',
        ];
    }
    echo json_encode(['success'=>true, 'data'=>$result]);
    exit;
}

// ── GET MESSAGES ─────────────────────────────────────────────
if ($action === 'get_messages') {
    $chatId = (int)($_GET['chat_id'] ?? 0);
    $userId = (int)($_GET['user_id'] ?? 0);
    if (!$chatId) { echo json_encode(['success'=>false,'message'=>'Chat ID diperlukan']); exit; }

    // Tandai pesan sebagai sudah dibaca
    if ($userId) {
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE chat_id=? AND sender_id != ?")->execute([$chatId, $userId]);
    }

    $stmt = $pdo->prepare("SELECT m.*, u.nama AS sender_nama FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.chat_id = ? ORDER BY m.created_at ASC");
    $stmt->execute([$chatId]);
    $msgs = $stmt->fetchAll();

    $result = array_map(function($m) use ($userId) {
        return [
            'id'          => (int)$m['id'],
            'type'        => $m['type'],
            'from'        => ($m['sender_id'] == $userId) ? 'outgoing' : 'incoming',
            'text'        => $m['content'],
            'tawarHarga'  => (int)$m['tawar_harga'],
            'produkId'    => (int)$m['produk_id_ref'],
            'nego_status' => $m['nego_status'],
            'deal_status' => $m['deal_status'],
            'time'        => date('H:i', strtotime($m['created_at'])),
            'sender_id'   => (int)$m['sender_id'],
        ];
    }, $msgs);

    echo json_encode(['success'=>true, 'data'=>$result]);
    exit;
}

// ── SEND MESSAGE ─────────────────────────────────────────────
if ($action === 'send') {
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $chatId   = (int)($data['chat_id']   ?? 0);
    $senderId = (int)($data['sender_id'] ?? 0);
    $type     = $data['type']     ?? 'text';
    $content  = trim($data['content']  ?? '');
    $tawar    = (int)($data['tawar_harga'] ?? 0);
    $produkIdRef = (int)($data['produk_id_ref'] ?? 0);
    $negoStatus  = $data['nego_status'] ?? null;
    $dealStatus  = $data['deal_status'] ?? null;

    if (!$chatId || !$senderId) { echo json_encode(['success'=>false,'message'=>'Data tidak lengkap']); exit; }

    $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, type, content, tawar_harga, produk_id_ref, nego_status, deal_status) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$chatId, $senderId, $type, $content ?: null, $tawar ?: null, $produkIdRef ?: null, $negoStatus, $dealStatus]);

    echo json_encode(['success'=>true, 'message_id'=>$pdo->lastInsertId()]);
    exit;
}

// ── BUAT CHAT BARU ───────────────────────────────────────────
if ($action === 'create') {
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $buyerId  = (int)($data['buyer_id']  ?? 0);
    $sellerId = (int)($data['seller_id'] ?? 0);
    $produkId = (int)($data['produk_id'] ?? 0);

    if (!$buyerId || !$sellerId || !$produkId) { echo json_encode(['success'=>false,'message'=>'Data tidak lengkap']); exit; }
    if ($buyerId === $sellerId) { echo json_encode(['success'=>false,'message'=>'Tidak bisa chat dengan diri sendiri']); exit; }

    // Cek apakah chat sudah ada
    $stmt = $pdo->prepare("SELECT id FROM chats WHERE buyer_id=? AND seller_id=? AND produk_id=?");
    $stmt->execute([$buyerId, $sellerId, $produkId]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo json_encode(['success'=>true, 'chat_id'=>(int)$existing['id'], 'is_new'=>false]);
    } else {
        $pdo->prepare("INSERT INTO chats (buyer_id, seller_id, produk_id) VALUES (?,?,?)")->execute([$buyerId, $sellerId, $produkId]);
        echo json_encode(['success'=>true, 'chat_id'=>(int)$pdo->lastInsertId(), 'is_new'=>true]);
    }
    exit;
}

// ── UPDATE NEGO STATUS ───────────────────────────────────────
if ($action === 'update_nego') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $msgId     = (int)($data['message_id'] ?? 0);
    $status    = $data['status'] ?? '';
    if (!$msgId || !in_array($status, ['accepted','rejected'])) { echo json_encode(['success'=>false]); exit; }
    $pdo->prepare("UPDATE messages SET nego_status=? WHERE id=?")->execute([$status, $msgId]);
    echo json_encode(['success'=>true]);
    exit;
}

// ── UPDATE DEAL STATUS ───────────────────────────────────────
if ($action === 'update_deal') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $msgId     = (int)($data['message_id'] ?? 0);
    $status    = $data['deal_status'] ?? '';
    if (!$msgId) { echo json_encode(['success'=>false]); exit; }
    $pdo->prepare("UPDATE messages SET deal_status=? WHERE id=?")->execute([$status, $msgId]);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false, 'message'=>'Action tidak dikenali']);

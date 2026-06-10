<?php
// ============================================================
// api/upload_foto.php — Upload Foto Produk & Avatar
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Folder tujuan upload (relatif dari root project)
$uploadDir = __DIR__ . '/../uploads/';

// Buat folder jika belum ada
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['foto']['error'] ?? -1;
    $errMsg  = [
        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi batas server)',
        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar',
        UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diupload',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temp tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file',
    ][$errCode] ?? 'Error tidak diketahui';

    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

$file     = $_FILES['foto'];
$tmpPath  = $file['tmp_name'];
$origName = $file['name'];
$fileSize = $file['size'];

// Validasi ukuran (max 5MB)
if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']);
    exit;
}

// Validasi tipe MIME
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Format file harus JPG, PNG, GIF, atau WEBP']);
    exit;
}

// Tentukan ekstensi
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$ext      = $extMap[$mimeType];

// Nama file unik: timestamp_random.ext
$newName  = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $newName;

// Pindahkan file
if (!move_uploaded_file($tmpPath, $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke server']);
    exit;
}

// Kembalikan URL relatif yang bisa diakses dari browser
$baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/');

$fileUrl  = $baseUrl . '/uploads/' . $newName;

echo json_encode([
    'success'  => true,
    'message'  => 'Upload berhasil!',
    'url'      => $fileUrl,
    'filename' => $newName,
]);

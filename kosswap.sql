-- ============================================================
-- kosswap.sql — Updated Schema KoSwap
-- ============================================================

CREATE DATABASE IF NOT EXISTS kosswap_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kosswap_db;

-- USERS TABLE (tambah kolom bio, is_admin)
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100)  NOT NULL,
    username    VARCHAR(50)   NOT NULL UNIQUE,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    avatar      VARCHAR(500)  DEFAULT '',
    bio         TEXT          DEFAULT NULL,
    lokasi      VARCHAR(200)  DEFAULT '',
    is_admin    TINYINT(1)    DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PRODUK TABLE (tambah kolom sold_out, foto_url)
CREATE TABLE IF NOT EXISTS produk (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    nama        VARCHAR(200)  NOT NULL,
    harga       INT           NOT NULL,
    kategori    VARCHAR(50)   NOT NULL,
    deskripsi   TEXT,
    img         VARCHAR(500)  DEFAULT '',
    sold_out    TINYINT(1)    DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- SAVED ITEMS TABLE
CREATE TABLE IF NOT EXISTS saved_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    produk_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_saved (user_id, produk_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id)  ON DELETE CASCADE
);

-- CHATS TABLE
CREATE TABLE IF NOT EXISTS chats (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id     INT NOT NULL,
    seller_id    INT NOT NULL,
    produk_id    INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_chat (buyer_id, seller_id, produk_id),
    FOREIGN KEY (buyer_id)  REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id)  ON DELETE CASCADE
);

-- MESSAGES TABLE
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    chat_id     INT NOT NULL,
    sender_id   INT NOT NULL,
    type        ENUM('text','nego') DEFAULT 'text',
    content     TEXT,
    tawar_harga INT  DEFAULT NULL,
    nego_status ENUM('pending','accepted','rejected') NULL DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id)   REFERENCES chats(id)   ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)   ON DELETE CASCADE
);

-- PENJUALAN TABLE (untuk tracking transaksi yang deal)
CREATE TABLE IF NOT EXISTS penjualan (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    produk_id   INT NOT NULL,
    seller_id   INT NOT NULL,
    buyer_id    INT NOT NULL,
    harga_deal  INT NOT NULL,
    status      ENUM('deal','pending','batal') DEFAULT 'deal',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (buyer_id)  REFERENCES users(id)  ON DELETE CASCADE
);

-- ======================== SAMPLE DATA ========================

-- Admin user (password: password)
INSERT IGNORE INTO users (id, nama, username, email, password, avatar, lokasi, is_admin) VALUES
(1, 'Admin KoSwap', '@admin', 'admin@kosswap.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://i.pinimg.com/1200x/5d/59/86/5d5986b284b1e5cbfb0773e04d6cfde6.jpg',
 'Banda Aceh', 1);

-- User biasa
INSERT IGNORE INTO users (id, nama, username, email, password, avatar, lokasi, is_admin) VALUES
(2, 'Angel', '@angel_kos', 'angel@example.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://i.pinimg.com/1200x/5d/59/86/5d5986b284b1e5cbfb0773e04d6cfde6.jpg',
 'Kp. Jeulingke, Banda Aceh', 0),
(3, 'Rin', '@rin_kos', 'rin@example.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://i.pinimg.com/1200x/30/1c/18/301c18e515f7a90e67af12162670e1bf.jpg',
 'Darussalam, Banda Aceh', 0);

-- Sample produk
INSERT IGNORE INTO produk (id, user_id, nama, harga, kategori, deskripsi, img, sold_out) VALUES
(1, 3, 'Rice Cooker Green Life 1.8L', 100000, 'Alat Masak',
 'Masih berfungsi normal, kabel lengkap.',
 'https://i.pinimg.com/1200x/01/20/a6/0120a619c59e706fcbea44c4cedb5d94.jpg', 0),
(2, 2, 'Kompor Portable', 150000, 'Alat Masak',
 'Kondisi bagus, masih ada gasnya. Dijual karena mau pulang kampung.',
 'https://i.pinimg.com/736x/7a/57/f1/7a57f1a82f4872185152e470db9693ec.jpg', 1);

-- Sample penjualan (bulan Jan-Apr 2026)
INSERT IGNORE INTO penjualan (produk_id, seller_id, buyer_id, harga_deal, status, created_at) VALUES
(2, 2, 3, 130000, 'deal', '2026-01-15 10:00:00'),
(1, 3, 2, 90000,  'deal', '2026-02-20 14:00:00'),
(2, 2, 3, 150000, 'deal', '2026-03-05 09:00:00'),
(1, 3, 2, 95000,  'deal', '2026-04-01 11:00:00');

-- ======================== MIGRASI (jalankan jika DB sudah ada) ========================
-- Tambah kolom bio jika belum ada
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL;
-- Tambah kolom sold_out jika belum ada
ALTER TABLE produk ADD COLUMN IF NOT EXISTS sold_out TINYINT(1) DEFAULT 0;
-- Pastikan admin selalu ada (update jika email sudah ada)
INSERT INTO users (id, nama, username, email, password, avatar, lokasi, is_admin) VALUES
(1, 'Admin KoSwap', '@admin', 'admin@kosswap.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://i.pinimg.com/1200x/5d/59/86/5d5986b284b1e5cbfb0773e04d6cfde6.jpg',
 'Banda Aceh', 1)
ON DUPLICATE KEY UPDATE is_admin = 1;

-- ── Tambah kolom untuk chat API ──────────────────────────────
ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_read      TINYINT(1) DEFAULT 0;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS produk_id_ref INT DEFAULT NULL;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS deal_status  VARCHAR(30) DEFAULT NULL;
ALTER TABLE messages MODIFY COLUMN type ENUM('text','nego','deal') DEFAULT 'text';
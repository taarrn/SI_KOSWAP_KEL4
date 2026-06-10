<?php
// ============================================================
// admin.php — Dashboard Admin KoSwap
// Akses: hanya user dengan is_admin = 1
// ============================================================

session_start();
require_once 'db.php';

// Cek login & akses admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: admin_login.php');
    exit;
}

// ---- HELPER ----
function formatRp(int $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ---- DATA: Statistik ----
$totalProduk  = $pdo->query('SELECT COUNT(*) FROM produk p JOIN users u ON p.user_id = u.id WHERE u.is_admin = 0')->fetchColumn();
$totalUser    = $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 0')->fetchColumn();
$totalTerjual = $pdo->query('SELECT COUNT(*) FROM penjualan WHERE status = "deal"')->fetchColumn();
$totalOmzet   = $pdo->query('SELECT COALESCE(SUM(harga_deal),0) FROM penjualan WHERE status = "deal"')->fetchColumn();

// Penjualan bulan ini
$penjualanBulanIni = $pdo->query('SELECT COUNT(*) FROM penjualan WHERE status = "deal" AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())')->fetchColumn();
$omzetBulanIni     = $pdo->query('SELECT COALESCE(SUM(harga_deal),0) FROM penjualan WHERE status = "deal" AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())')->fetchColumn();

// ---- DATA: Grafik penjualan per bulan (12 bulan terakhir) ----
// Buat array 12 bulan terakhir dulu (agar bulan tanpa transaksi tetap muncul sebagai 0)
$bulanList = [];
for ($i = 11; $i >= 0; $i--) {
    $bulanList[] = date('Y-m', strtotime("-$i months"));
}

$grafikStmt = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS bulan,
           COUNT(*) AS jumlah,
           SUM(harga_deal) AS omzet
    FROM penjualan
    WHERE status = 'deal'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY bulan
    ORDER BY bulan ASC
");
$grafikRaw = [];
foreach ($grafikStmt->fetchAll() as $row) {
    $grafikRaw[$row['bulan']] = $row;
}

// Label & value — isi 0 untuk bulan yang tidak ada transaksi
$chartLabels = [];
$chartJumlah = [];
$chartOmzet  = [];
foreach ($bulanList as $bln) {
    $dt = DateTime::createFromFormat('Y-m', $bln);
    $chartLabels[] = $dt ? $dt->format('M Y') : $bln;
    $chartJumlah[] = isset($grafikRaw[$bln]) ? (int)$grafikRaw[$bln]['jumlah'] : 0;
    $chartOmzet[]  = isset($grafikRaw[$bln]) ? (int)$grafikRaw[$bln]['omzet']  : 0;
}

// ---- DATA: Produk (dengan info penjual, exclude produk admin) ----
$produkList = $pdo->query("
    SELECT p.*, u.nama AS nama_penjual, u.username
    FROM produk p
    JOIN users u ON p.user_id = u.id
    WHERE u.is_admin = 0
    ORDER BY p.created_at DESC
")->fetchAll();

// ---- DATA: Penjualan (LEFT JOIN buyer karena buyer_id bisa NULL) ----
$penjualanList = $pdo->query("
    SELECT pj.*,
           p.nama AS nama_produk, p.img,
           us.nama AS nama_seller,
           COALESCE(ub.nama, 'Pembeli (COD)') AS nama_buyer
    FROM penjualan pj
    JOIN produk p       ON pj.produk_id = p.id
    JOIN users us       ON pj.seller_id = us.id
    LEFT JOIN users ub  ON pj.buyer_id  = ub.id
    ORDER BY pj.created_at DESC
    LIMIT 50
")->fetchAll();

// ---- DATA: User ----
$userList = $pdo->query("
    SELECT u.*,
           COUNT(DISTINCT p.id) AS jumlah_produk
    FROM users u
    LEFT JOIN produk p ON p.user_id = u.id
    WHERE u.is_admin = 0
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

// ---- ACTION: Toggle sold_out ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_sold'])) {
    $pid = (int)$_POST['toggle_sold'];
    $pdo->prepare('UPDATE produk SET sold_out = NOT sold_out WHERE id = ?')->execute([$pid]);
    header('Location: admin.php?tab=produk&msg=updated');
    exit;
}

// ---- ACTION: Hapus produk ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_produk'])) {
    $pid = (int)$_POST['hapus_produk'];
    $pdo->prepare('DELETE FROM produk WHERE id = ?')->execute([$pid]);
    header('Location: admin.php?tab=produk&msg=deleted');
    exit;
}

// ---- ACTION: Hapus user ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_user'])) {
    $uid = (int)$_POST['hapus_user'];
    $pdo->prepare('DELETE FROM users WHERE id = ? AND is_admin = 0')->execute([$uid]);
    header('Location: admin.php?tab=user&msg=deleted');
    exit;
}

$activeTab = $_GET['tab'] ?? 'dashboard';
$flashMsg  = $_GET['msg']  ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Panel — KoSwap</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --orange: #F47B20; --orange-dark: #d96a10; --orange-light: #FFF0E0;
  --cream: #F7F0E3; --cream2: #EDE3D0; --white: #FFFFFF;
  --text-dark: #222; --text-mid: #555; --text-light: #999;
  --border: #E8DDD0; --radius: 16px; --shadow: 0 4px 20px rgba(0,0,0,0.08);
  --font: 'Nunito', sans-serif;
  --sidebar-w: 240px;
}
body { font-family: var(--font); background: var(--cream); color: var(--text-dark); display: flex; min-height: 100vh; }
a { text-decoration: none; color: inherit; }

/* SIDEBAR */
.sidebar {
  width: var(--sidebar-w); flex-shrink: 0; background: var(--white);
  border-right: 1px solid var(--border); display: flex; flex-direction: column;
  padding: 24px 0; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100;
}
.sidebar-logo { padding: 0 24px 24px; font-size: 1.5rem; font-weight: 900; color: var(--orange); border-bottom: 1px solid var(--border); margin-bottom: 16px; }
.sidebar-logo span { color: var(--text-dark); }
.sidebar-badge { font-size: 0.65rem; font-weight: 900; background: #e53935; color: #fff; padding: 2px 8px; border-radius: 50px; margin-left: 6px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 11px 24px; font-weight: 700; font-size: 0.92rem; color: var(--text-mid); transition: all 0.18s; border-left: 3px solid transparent; }
.nav-item:hover { background: var(--cream); color: var(--text-dark); }
.nav-item.active { background: var(--orange-light); color: var(--orange); border-left-color: var(--orange); }
.nav-item svg { width: 18px; height: 18px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.sidebar-footer { margin-top: auto; padding: 16px 24px; border-top: 1px solid var(--border); font-size: 0.82rem; color: var(--text-light); }
.sidebar-footer a { color: var(--orange); font-weight: 700; }

/* MAIN CONTENT */
.main { margin-left: var(--sidebar-w); flex: 1; padding: 32px; min-height: 100vh; }

/* TOPBAR */
.topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.topbar h1 { font-size: 1.6rem; font-weight: 900; }
.topbar-right { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; color: var(--text-mid); }
.badge-admin { background: var(--orange); color: #fff; font-size: 0.72rem; font-weight: 900; padding: 4px 12px; border-radius: 50px; }

/* STAT CARDS */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--white); border-radius: var(--radius); padding: 22px 20px; box-shadow: var(--shadow); display: flex; flex-direction: column; gap: 6px; }
.stat-card-icon { font-size: 1.8rem; }
.stat-card-val { font-size: 1.8rem; font-weight: 900; color: var(--text-dark); }
.stat-card-lbl { font-size: 0.75rem; font-weight: 800; letter-spacing: 1px; color: var(--text-light); }
.stat-card.accent { background: var(--orange); }
.stat-card.accent .stat-card-val, .stat-card.accent .stat-card-lbl { color: #fff; }

/* CHART */
.chart-card { background: var(--white); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); margin-bottom: 28px; }
.chart-card h3 { font-size: 1rem; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.chart-wrap { position: relative; height: 280px; }

/* TABLE */
.table-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 28px; }
.table-card-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid var(--border); }
.table-card-header h3 { font-size: 1rem; font-weight: 800; }
table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
thead { background: var(--cream); }
th { padding: 10px 14px; text-align: left; font-size: 0.72rem; font-weight: 800; letter-spacing: 1px; color: var(--text-mid); }
td { padding: 12px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }
.td-img { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; display: block; }
.td-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }

/* BADGES / PILLS */
.pill { display: inline-block; font-size: 0.7rem; font-weight: 900; padding: 3px 10px; border-radius: 50px; }
.pill-sold { background: #fde8e8; color: #c62828; }
.pill-avail { background: #e8f5e9; color: #2e7d32; }
.pill-deal { background: #e8f5e9; color: #2e7d32; }
.pill-pending { background: #fff3cd; color: #856404; }
.pill-batal { background: #fde8e8; color: #c62828; }

/* BUTTONS */
.btn { cursor: pointer; font-family: var(--font); border: none; background: none; font-weight: 700; font-size: 0.82rem; padding: 6px 14px; border-radius: 8px; transition: all 0.18s; }
.btn-danger { background: #fde8e8; color: #c62828; }
.btn-danger:hover { background: #c62828; color: #fff; }
.btn-warn { background: #fff3cd; color: #856404; }
.btn-warn:hover { background: #856404; color: #fff; }
.btn-primary { background: var(--orange-light); color: var(--orange); }
.btn-primary:hover { background: var(--orange); color: #fff; }

/* FLASH MESSAGE */
.flash { padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 0.88rem; margin-bottom: 20px; }
.flash-ok { background: #e8f5e9; color: #2e7d32; }
.flash-err { background: #fde8e8; color: #c62828; }

/* SECTION TABS */
.section { display: none; }
.section.active { display: block; }

/* SEARCH INPUT */
.tbl-search { padding: 8px 14px; border: 2px solid var(--border); border-radius: 10px; font-family: var(--font); font-size: 0.88rem; outline: none; width: 220px; }
.tbl-search:focus { border-color: var(--orange); }

/* RESPONSIVE STAT */
@media (max-width: 900px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">Ko<span>Swap</span> <span class="sidebar-badge">ADMIN</span></div>

  <a href="admin.php?tab=dashboard" class="nav-item <?= $activeTab==='dashboard'?'active':'' ?>">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a href="admin.php?tab=produk" class="nav-item <?= $activeTab==='produk'?'active':'' ?>">
    <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    Produk
  </a>
  <a href="admin.php?tab=penjualan" class="nav-item <?= $activeTab==='penjualan'?'active':'' ?>">
    <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    Penjualan
  </a>
  <a href="admin.php?tab=user" class="nav-item <?= $activeTab==='user'?'active':'' ?>">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User
  </a>

  <div class="sidebar-footer">
    Login sebagai <strong><?= htmlspecialchars($_SESSION['user_nama'] ?? 'Admin') ?></strong><br>
    <span style="font-size:0.75rem;color:var(--text-light)">Administrator KoSwap</span><br><br>
    <a href="api/logout.php" style="color:#e53935;font-weight:800">🚪 Logout Admin</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <h1>
      <?php
        $titles = ['dashboard'=>'📊 Dashboard', 'produk'=>'📦 Manajemen Produk', 'penjualan'=>'💰 Penjualan', 'user'=>'👥 Manajemen User'];
        echo $titles[$activeTab] ?? 'Dashboard';
      ?>
    </h1>
    <div class="topbar-right">
      <span class="badge-admin">ADMIN</span>
      <?= date('d M Y') ?>
    </div>
  </div>

  <?php if ($flashMsg === 'updated'): ?>
    <div class="flash flash-ok">✓ Data berhasil diperbarui</div>
  <?php elseif ($flashMsg === 'deleted'): ?>
    <div class="flash flash-ok">✓ Data berhasil dihapus</div>
  <?php endif; ?>

  <!-- ========== DASHBOARD ========== -->
  <?php if ($activeTab === 'dashboard'): ?>

  <!-- Info alur COD -->
  <div style="background:#fff3cd;border:1px solid #f5c842;border-radius:12px;padding:12px 18px;margin-bottom:20px;font-size:0.85rem;font-weight:700;color:#856404;display:flex;align-items:center;gap:10px">
    💡 <span>Data grafik & penjualan masuk otomatis saat penjual klik <strong>"Konfirmasi Deal — COD Selesai"</strong> di halaman chat.</span>
  </div>

  <!-- Stat Cards: 2 baris — total & bulan ini -->
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card">
      <div class="stat-card-icon">📦</div>
      <div class="stat-card-val"><?= $totalProduk ?></div>
      <div class="stat-card-lbl">TOTAL PRODUK</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">👥</div>
      <div class="stat-card-val"><?= $totalUser ?></div>
      <div class="stat-card-lbl">TOTAL USER</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">🛍️</div>
      <div class="stat-card-val"><?= $totalTerjual ?></div>
      <div class="stat-card-lbl">TOTAL TERJUAL</div>
    </div>
    <div class="stat-card accent">
      <div class="stat-card-icon">💰</div>
      <div class="stat-card-val" style="font-size:1.1rem"><?= formatRp((int)$totalOmzet) ?></div>
      <div class="stat-card-lbl">TOTAL OMZET</div>
    </div>
  </div>

  <!-- Stat bulan ini -->
  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-top:-10px">
    <div class="stat-card" style="border-left:4px solid var(--orange)">
      <div class="stat-card-icon">📅</div>
      <div class="stat-card-val"><?= date('F Y') ?></div>
      <div class="stat-card-lbl">BULAN BERJALAN</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #4caf50">
      <div class="stat-card-icon">✅</div>
      <div class="stat-card-val"><?= $penjualanBulanIni ?></div>
      <div class="stat-card-lbl">DEAL BULAN INI</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #1565c0">
      <div class="stat-card-icon">💵</div>
      <div class="stat-card-val" style="font-size:1.1rem"><?= formatRp((int)$omzetBulanIni) ?></div>
      <div class="stat-card-lbl">OMZET BULAN INI</div>
    </div>
  </div>

  <!-- Chart gabungan transaksi + omzet -->
  <div class="chart-card">
    <h3>📈 Grafik Penjualan COD Per Bulan (12 Bulan Terakhir)</h3>
    <p style="font-size:0.8rem;color:var(--text-light);margin-bottom:16px;margin-top:-10px">Data masuk setelah penjual konfirmasi deal di halaman chat</p>
    <div class="chart-wrap" style="height:300px">
      <canvas id="grafikGabungan"></canvas>
    </div>
  </div>

  <!-- Chart omzet line -->
  <div class="chart-card">
    <h3>💰 Tren Omzet Per Bulan</h3>
    <div class="chart-wrap" style="height:240px">
      <canvas id="grafikOmzetLine"></canvas>
    </div>
  </div>

  <?php if (array_sum($chartJumlah) === 0): ?>
  <div style="background:var(--white);border-radius:var(--radius);padding:40px;text-align:center;box-shadow:var(--shadow);margin-bottom:28px">
    <div style="font-size:3rem;margin-bottom:12px">📊</div>
    <h3 style="font-weight:800;margin-bottom:8px">Belum Ada Data Penjualan</h3>
    <p style="color:var(--text-mid);font-size:0.9rem">Grafik akan muncul otomatis setelah ada transaksi COD yang dikonfirmasi oleh penjual.</p>
  </div>
  <?php endif; ?>

  <script>
    const labels = <?= json_encode($chartLabels) ?>;
    const jumlah = <?= json_encode($chartJumlah) ?>;
    const omzet  = <?= json_encode($chartOmzet)  ?>;

    // Chart 1: Bar jumlah + Line omzet gabungan
    new Chart(document.getElementById('grafikGabungan'), {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Jumlah Deal COD',
            data: jumlah,
            backgroundColor: 'rgba(244,123,32,0.8)',
            borderColor: '#F47B20',
            borderWidth: 2,
            borderRadius: 8,
            yAxisID: 'y',
          },
          {
            label: 'Omzet (Rp)',
            data: omzet,
            type: 'line',
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76,175,80,0.08)',
            borderWidth: 2.5,
            tension: 0.35,
            fill: true,
            pointBackgroundColor: '#4caf50',
            pointRadius: 4,
            yAxisID: 'y2',
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'top' },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                if (ctx.datasetIndex === 1) return 'Omzet: Rp ' + ctx.raw.toLocaleString('id-ID');
                return 'Deal: ' + ctx.raw + ' transaksi';
              }
            }
          }
        },
        scales: {
          y:  { beginAtZero: true, position: 'left',  ticks: { stepSize: 1 }, title: { display: true, text: 'Jumlah Deal' } },
          y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false },
                ticks: { callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k' },
                title: { display: true, text: 'Omzet' } }
        }
      }
    });

    // Chart 2: Tren omzet saja
    new Chart(document.getElementById('grafikOmzetLine'), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Omzet (Rp)',
          data: omzet,
          borderColor: '#F47B20',
          backgroundColor: 'rgba(244,123,32,0.10)',
          borderWidth: 2.5,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#F47B20',
          pointRadius: 5,
          pointHoverRadius: 7,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } }
        }
      }
    });
  </script>

  <!-- ========== PRODUK ========== -->
  <?php elseif ($activeTab === 'produk'): ?>

  <div class="table-card">
    <div class="table-card-header">
      <h3>Semua Produk (<?= count($produkList) ?>)</h3>
      <input type="text" class="tbl-search" placeholder="🔍 Cari produk..." oninput="filterTable(this,'tbl-produk')"/>
    </div>
    <table id="tbl-produk">
      <thead>
        <tr><th>FOTO</th><th>NAMA PRODUK</th><th>KATEGORI</th><th>HARGA</th><th>PENJUAL</th><th>STATUS</th><th>AKSI</th></tr>
      </thead>
      <tbody>
        <?php foreach ($produkList as $p): ?>
        <tr>
          <td><?php if($p['img']): ?><img src="<?= htmlspecialchars($p['img']) ?>" class="td-img" alt=""/><?php else: ?>—<?php endif; ?></td>
          <td><strong><?= htmlspecialchars($p['nama']) ?></strong><br><small style="color:var(--text-light)"><?= date('d M Y', strtotime($p['created_at'])) ?></small></td>
          <td><?= htmlspecialchars($p['kategori']) ?></td>
          <td><?= formatRp($p['harga']) ?></td>
          <td><?= htmlspecialchars($p['nama_penjual']) ?><br><small><?= htmlspecialchars($p['username']) ?></small></td>
          <td>
            <?php if ($p['sold_out']): ?>
              <span class="pill pill-sold">SOLD OUT</span>
            <?php else: ?>
              <span class="pill pill-avail">Tersedia</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <form method="POST">
              <input type="hidden" name="toggle_sold" value="<?= $p['id'] ?>"/>
              <button class="btn btn-warn" type="submit"><?= $p['sold_out'] ? '🔓 Aktifkan' : '🔒 Sold Out' ?></button>
            </form>
            <form method="POST" onsubmit="return confirm('Hapus produk ini?')">
              <input type="hidden" name="hapus_produk" value="<?= $p['id'] ?>"/>
              <button class="btn btn-danger" type="submit">🗑 Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ========== PENJUALAN ========== -->
  <?php elseif ($activeTab === 'penjualan'): ?>

  <div style="background:#e8f5e9;border:1px solid #4caf50;border-radius:12px;padding:12px 18px;margin-bottom:20px;font-size:0.85rem;font-weight:700;color:#2e7d32;display:flex;align-items:center;gap:10px">
    🤝 <span>Sistem pembayaran <strong>COD (Cash on Delivery)</strong> — data masuk setelah penjual klik konfirmasi deal di chat.</span>
  </div>

  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card"><div class="stat-card-icon">✅</div><div class="stat-card-val"><?= $totalTerjual ?></div><div class="stat-card-lbl">TOTAL DEAL</div></div>
    <div class="stat-card accent"><div class="stat-card-icon">💰</div><div class="stat-card-val" style="font-size:1.1rem"><?= formatRp((int)$totalOmzet) ?></div><div class="stat-card-lbl">TOTAL OMZET</div></div>
    <div class="stat-card" style="border-left:4px solid #4caf50"><div class="stat-card-icon">📅</div><div class="stat-card-val"><?= $penjualanBulanIni ?></div><div class="stat-card-lbl">DEAL BULAN INI</div></div>
    <div class="stat-card" style="border-left:4px solid #1565c0"><div class="stat-card-icon">💵</div><div class="stat-card-val" style="font-size:1rem"><?= formatRp((int)$omzetBulanIni) ?></div><div class="stat-card-lbl">OMZET BULAN INI</div></div>
  </div>

  <div class="chart-card">
    <h3>📈 Grafik Penjualan COD Per Bulan</h3>
    <div class="chart-wrap" style="height:280px"><canvas id="grafikPenjualanPage"></canvas></div>
  </div>
  <script>
    new Chart(document.getElementById('grafikPenjualanPage'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
          { label: 'Jumlah Deal', data: <?= json_encode($chartJumlah) ?>, backgroundColor: 'rgba(244,123,32,0.8)', borderColor:'#F47B20', borderWidth:2, borderRadius:8, yAxisID:'y' },
          { label: 'Omzet (Rp)', data: <?= json_encode($chartOmzet) ?>, type:'line', borderColor:'#4caf50', backgroundColor:'rgba(76,175,80,0.08)', borderWidth:2.5, tension:0.4, fill:true, pointBackgroundColor:'#4caf50', pointRadius:4, yAxisID:'y2' }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins: { legend:{ display:true, position:'top' } },
        scales: {
          y:  { beginAtZero:true, position:'left',  ticks:{stepSize:1}, title:{display:true,text:'Jumlah Deal'} },
          y2: { beginAtZero:true, position:'right', grid:{drawOnChartArea:false}, ticks:{callback:v=>'Rp '+v.toLocaleString('id-ID')}, title:{display:true,text:'Omzet'} }
        }
      }
    });
  </script>

  <div class="table-card">
    <div class="table-card-header">
      <h3>Riwayat Penjualan COD (<?= count($penjualanList) ?>)</h3>
      <input type="text" class="tbl-search" placeholder="🔍 Cari..." oninput="filterTable(this,'tbl-penjualan')"/>
    </div>
    <table id="tbl-penjualan">
      <thead>
        <tr><th>PRODUK</th><th>PENJUAL</th><th>PEMBELI</th><th>HARGA DEAL</th><th>STATUS</th><th>TANGGAL</th></tr>
      </thead>
      <tbody>
        <?php foreach ($penjualanList as $pj): ?>
        <tr>
          <td style="display:flex;align-items:center;gap:10px">
            <?php if ($pj['img']): ?><img src="<?= htmlspecialchars($pj['img']) ?>" class="td-img" alt=""/><?php endif; ?>
            <strong><?= htmlspecialchars($pj['nama_produk']) ?></strong>
          </td>
          <td><?= htmlspecialchars($pj['nama_seller']) ?></td>
          <td><?= htmlspecialchars($pj['nama_buyer']) ?></td>
          <td><strong><?= formatRp($pj['harga_deal']) ?></strong></td>
          <td>
            <?php
              $pillClass = ['deal'=>'pill-deal','pending'=>'pill-pending','batal'=>'pill-batal'][$pj['status']] ?? 'pill-pending';
              $label = strtoupper($pj['status']);
            ?>
            <span class="pill <?= $pillClass ?>"><?= $label ?></span>
          </td>
          <td><?= date('d M Y', strtotime($pj['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ========== USER ========== -->
  <?php elseif ($activeTab === 'user'): ?>

  <div class="table-card">
    <div class="table-card-header">
      <h3>Semua User (<?= count($userList) ?>)</h3>
      <input type="text" class="tbl-search" placeholder="🔍 Cari user..." oninput="filterTable(this,'tbl-user')"/>
    </div>
    <table id="tbl-user">
      <thead>
        <tr><th>AVATAR</th><th>NAMA</th><th>USERNAME</th><th>EMAIL</th><th>PRODUK</th><th>BERGABUNG</th><th>AKSI</th></tr>
      </thead>
      <tbody>
        <?php foreach ($userList as $u): ?>
        <tr>
          <td><?php if($u['avatar']): ?><img src="<?= htmlspecialchars($u['avatar']) ?>" class="td-avatar" alt=""/><?php else: ?>👤<?php endif; ?></td>
          <td><strong><?= htmlspecialchars($u['nama']) ?></strong></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= $u['jumlah_produk'] ?> produk</td>
          <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Hapus user <?= htmlspecialchars($u['nama']) ?>?')">
              <input type="hidden" name="hapus_user" value="<?= $u['id'] ?>"/>
              <button class="btn btn-danger" type="submit">🗑 Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; ?>

</main>

<script>
// Filter tabel
function filterTable(input, tableId) {
  const q   = input.value.toLowerCase();
  const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>

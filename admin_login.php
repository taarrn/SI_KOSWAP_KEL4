<?php
// ============================================================
// admin_login.php — Halaman Login Khusus Admin KoSwap
// ============================================================
session_start();

// Kalau sudah login sebagai admin, langsung redirect ke admin panel
if (isset($_SESSION['user_id']) && $_SESSION['is_admin']) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_admin = 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            $error = 'Email atau password salah, atau akun bukan admin.';
        } else {
            $_SESSION['user_id']   = $admin['id'];
            $_SESSION['user_nama'] = $admin['nama'];
            $_SESSION['is_admin']  = true;
            header('Location: admin.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Login — KoSwap</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --orange: #F47B20; --orange-dark: #d96a10; --orange-light: #FFF0E0;
  --cream: #F7F0E3; --white: #FFFFFF;
  --text-dark: #222; --text-mid: #555; --border: #E8DDD0;
  --font: 'Nunito', sans-serif;
}
body {
  font-family: var(--font);
  background: var(--cream);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.login-wrap {
  background: var(--white);
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.12);
  width: 100%;
  max-width: 860px;
  display: flex;
  min-height: 480px;
}
.login-left {
  background: var(--orange);
  flex: 1;
  padding: 50px 40px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  justify-content: center;
}
.logo { font-size: 2rem; font-weight: 900; color: #fff; }
.logo span { color: rgba(255,255,255,0.75); }
.admin-badge {
  display: inline-block;
  background: rgba(255,255,255,0.25);
  color: #fff;
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 2px;
  padding: 5px 14px;
  border-radius: 50px;
  width: fit-content;
}
.tagline {
  font-size: 1.4rem;
  font-weight: 800;
  color: #fff;
  line-height: 1.4;
  margin-top: 8px;
}
.tagline-sub { font-size: 0.9rem; color: rgba(255,255,255,0.8); line-height: 1.6; }
.login-right {
  flex: 1;
  padding: 50px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.login-right h2 { font-size: 1.8rem; font-weight: 900; margin-bottom: 8px; }
.login-right p.subtitle { color: var(--text-mid); font-size: 0.9rem; margin-bottom: 32px; }
.form-group { margin-bottom: 20px; }
.form-group label {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 1.5px;
  color: var(--text-mid);
  margin-bottom: 8px;
}
.form-group input {
  width: 100%;
  padding: 14px 18px;
  border: 2px solid var(--border);
  border-radius: 12px;
  font-size: 0.95rem;
  font-family: var(--font);
  background: var(--cream);
  outline: none;
  transition: border-color 0.2s;
  color: var(--text-dark);
}
.form-group input:focus { border-color: var(--orange); background: #fff; }
.btn-login {
  width: 100%;
  padding: 14px;
  background: var(--orange);
  color: #fff;
  font-family: var(--font);
  font-size: 1rem;
  font-weight: 800;
  border: none;
  border-radius: 50px;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}
.btn-login:hover { background: var(--orange-dark); transform: translateY(-1px); }
.error-msg {
  background: #fde8e8;
  border: 2px solid #e53935;
  color: #c62828;
  border-radius: 12px;
  padding: 12px 16px;
  font-weight: 700;
  font-size: 0.88rem;
  margin-bottom: 20px;
}
.back-link {
  display: block;
  text-align: center;
  margin-top: 20px;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-mid);
  text-decoration: none;
}
.back-link:hover { color: var(--orange); }
.hint-box {
  background: var(--orange-light);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.82rem;
  color: var(--text-mid);
  margin-top: 16px;
  line-height: 1.6;
}
.hint-box strong { color: var(--orange); }
@media (max-width: 640px) {
  .login-left { display: none; }
  .login-right { padding: 40px 28px; }
}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-left">
    <div class="logo">Ko<span>Swap</span></div>
    <div class="admin-badge">PANEL ADMIN</div>
    <p class="tagline">Dashboard Kontrol<br>KoSwap Admin</p>
    <p class="tagline-sub">Kelola produk, user, dan pantau<br>statistik penjualan platform.</p>
  </div>
  <div class="login-right">
    <h2>Login Admin</h2>
    <p class="subtitle">Akses khusus untuk administrator KoSwap</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>ALAMAT EMAIL ADMIN</label>
        <input type="email" name="email" placeholder="admin@kosswap.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
      </div>
      <div class="form-group">
        <label>KATA SANDI</label>
        <input type="password" name="password" placeholder="••••••••" required/>
      </div>
      <button type="submit" class="btn-login">🔐 Masuk sebagai Admin</button>
    </form>

    <div class="hint-box">
      💡 Default akun admin:<br>
      Email: <strong>admin@kosswap.com</strong><br>
      Password: <strong>password</strong>
    </div>

    <a href="index.html" class="back-link">← Kembali ke KoSwap</a>
  </div>
</div>
</body>
</html>

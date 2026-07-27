<?php
// ============================================================
//  Auth View — Register / Signup Page
// ============================================================

$regAlert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $regAlert = ['type' => 'error', 'msg' => 'Semua kolom wajib diisi.'];
    } elseif ($password !== $confirm) {
        $regAlert = ['type' => 'error', 'msg' => 'Konfirmasi password tidak cocok.'];
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $regAlert = ['type' => 'error', 'msg' => 'Email sudah terdaftar. Silakan login.'];
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')")->execute([$name, $email, $hash]);
            $regAlert = ['type' => 'success', 'msg' => 'Registrasi berhasil! Silakan login dengan akun Anda.'];
        }
    }
}
?>

<div style="display:flex; justify-content:center; align-items:center; min-height: calc(100vh - 180px); padding: 24px 0;">
    <div class="panel" style="width: 100%; max-width: 440px; background: rgba(30, 37, 53, 0.85); backdrop-filter: blur(16px); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.4);">
        
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">✍️</div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
                Registrasi Akun BI
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Buat akun pengguna baru untuk mengelola platform
            </p>
        </div>

        <?php if ($regAlert): ?>
        <div class="alert alert-<?= $regAlert['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 20px;">
            <?= $regAlert['type'] === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($regAlert['msg']) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="register_submit" value="1">

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Nama Lengkap</label>
                <input type="text" name="name" required style="width:100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-size: 0.9rem;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Email Address</label>
                <input type="email" name="email" required style="width:100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-size: 0.9rem;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Password</label>
                <input type="password" name="password" required style="width:100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-size: 0.9rem;">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Konfirmasi Password</label>
                <input type="password" name="confirm_password" required style="width:100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-size: 0.9rem;">
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-bottom: 20px; border-radius: 8px;">
                ✨ Daftar Sekarang
            </button>
        </form>

        <div style="text-align: center; font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 16px;">
            Sudah punya akun? <a href="?page=login" style="color: var(--accent-teal); font-weight: 600;">Login Kembali</a>
        </div>
    </div>
</div>

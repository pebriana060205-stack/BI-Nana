<?php
// ============================================================
//  Auth View — Login Page
// ============================================================

$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $loginError = 'Email dan password wajib diisi.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            header('Location: ?page=dashboard');
            exit;
        } else {
            $loginError = 'Email atau password salah. Coba demo login!';
        }
    }
}
?>

<div style="display:flex; justify-content:center; align-items:center; min-height: calc(100vh - 180px); padding: 24px 0;">
    <div class="panel" style="width: 100%; max-width: 420px; background: rgba(30, 37, 53, 0.85); backdrop-filter: blur(16px); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.4);">
        
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">🔑</div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
                Login Sistem BI
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Masuk untuk mengakses Dashboard & Fitur Analytics
            </p>
        </div>

        <?php if ($loginError): ?>
        <div class="alert alert-error" style="margin-bottom: 20px;">
            ❌ <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="login_submit" value="1">

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Email Address</label>
                <input type="email" name="email" id="emailInput" value="admin@bi.com" required 
                       style="width:100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-size: 0.9rem;">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Password</label>
                <input type="password" name="password" id="passwordInput" value="admin123" required 
                       style="width:100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-size: 0.9rem;">
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-bottom: 16px; border-radius: 8px;">
                🚀 Masuk Ke Dashboard
            </button>

            <!-- Quick Demo Login Button -->
            <button type="button" onclick="fillDemoCredentials()" class="btn btn-secondary" style="width: 100%; margin-bottom: 20px; font-size: 0.85rem;">
                ⚡ Quick Demo Login (admin@bi.com)
            </button>
        </form>

        <div style="text-align: center; font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 16px;">
            Belum punya akun? <a href="?page=register" style="color: var(--accent-teal); font-weight: 600;">Daftar Akun Baru</a>
        </div>
    </div>
</div>

<script>
function fillDemoCredentials() {
    document.getElementById('emailInput').value = 'admin@bi.com';
    document.getElementById('passwordInput').value = 'admin123';
}
</script>

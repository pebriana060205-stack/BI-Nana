<?php
// ============================================================
//  Landing Page — Full-Screen Premium BI Presentation
// ============================================================

// Fetch live stats from DB
try {
    $db = getDB();
    $statTx    = number_format((int)$db->query("SELECT COUNT(*) FROM transactions")->fetchColumn());
    $statCust  = number_format((int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn());
    $statProd  = number_format((int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn());
    $statRules = number_format((int)$db->query("SELECT COUNT(*) FROM mining_association_rules")->fetchColumn());
    $topCountry = $db->query("SELECT co.country_name, SUM(t.total_amount) AS rev
        FROM transactions t JOIN countries co ON co.country_id = t.country_id
        GROUP BY co.country_name ORDER BY rev DESC LIMIT 1")->fetch();
} catch (Exception $e) {
    $statTx = $statCust = $statProd = $statRules = '—';
    $topCountry = null;
}

$isLoggedIn = isset($_SESSION['user']);
?>

<!-- ═══════ FULL-SCREEN LANDING (overrides sidebar layout padding) ═══════ -->
<style>
/* Override main content padding for full landing experience */
.content { padding: 0 !important; overflow-x: hidden; }

/* ── Animations ── */
@keyframes fadeUp   { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn   { from { opacity:0; } to { opacity:1; } }
@keyframes float    { 0%,100%{transform:translateY(0px)} 50%{transform:translateY(-12px)} }
@keyframes shimmer  { 0%{background-position:-200% center} 100%{background-position:200% center} }
@keyframes pulse-glow { 0%,100%{box-shadow:0 0 20px rgba(20,184,166,.2)} 50%{box-shadow:0 0 40px rgba(20,184,166,.5)} }
@keyframes orbit { from{transform:rotate(0deg) translateX(80px) rotate(0deg)} to{transform:rotate(360deg) translateX(80px) rotate(-360deg)} }
@keyframes counter { from{opacity:0;transform:scale(.7)} to{opacity:1;transform:scale(1)} }
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
@keyframes spin-slow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

/* ── Hero Section ── */
.lp-hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #0a0d14;
    padding: 60px 32px;
}

/* Animated grid background */
.lp-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(20,184,166,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(20,184,166,.06) 1px, transparent 1px);
    background-size: 40px 40px;
    animation: fadeIn 2s ease;
}

/* Radial glow center */
.lp-hero::after {
    content: '';
    position: absolute;
    width: 700px; height: 700px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(20,184,166,.08) 0%, rgba(99,102,241,.06) 40%, transparent 70%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
}

/* Floating orbs */
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.4;
    animation: float 6s ease-in-out infinite;
    pointer-events: none;
}

.lp-content { position: relative; z-index: 2; text-align: center; max-width: 860px; }

/* Gradient headline */
.lp-title {
    font-size: clamp(2.4rem, 5vw, 4rem);
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 24px;
    background: linear-gradient(135deg, #f1f5f9 0%, #14b8a6 40%, #6366f1 80%, #f1f5f9 100%);
    background-size: 300% 300%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: gradientShift 5s ease infinite, fadeUp .8s ease both;
}

.lp-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(20,184,166,.1);
    border: 1px solid rgba(20,184,166,.3);
    color: #14b8a6;
    padding: 7px 18px; border-radius: 999px;
    font-size: .82rem; font-weight: 600;
    margin-bottom: 28px;
    animation: fadeUp .6s ease both;
}

.lp-sub {
    font-size: 1.05rem; color: #94a3b8; max-width: 640px;
    margin: 0 auto 40px; line-height: 1.75;
    animation: fadeUp 1s ease .2s both;
}

/* CTA Buttons */
.lp-cta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; animation: fadeUp 1s ease .4s both; margin-bottom: 60px; }

.btn-cta-primary {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 15px 32px; border-radius: 12px; font-size: 1rem; font-weight: 700;
    background: linear-gradient(135deg, #14b8a6, #0d9488);
    color: #fff; border: none; cursor: pointer; text-decoration: none;
    box-shadow: 0 4px 24px rgba(20,184,166,.4);
    transition: all .25s; animation: pulse-glow 3s ease-in-out infinite;
}
.btn-cta-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(20,184,166,.55); }

.btn-cta-secondary {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 15px 32px; border-radius: 12px; font-size: 1rem; font-weight: 700;
    background: rgba(255,255,255,.06); color: #f1f5f9;
    border: 1px solid rgba(255,255,255,.15); cursor: pointer; text-decoration: none;
    backdrop-filter: blur(10px); transition: all .25s;
}
.btn-cta-secondary:hover { background: rgba(255,255,255,.12); transform: translateY(-2px); }

/* ── Stats Row ── */
.lp-stats {
    display: flex; gap: 0; background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.07); border-radius: 16px;
    overflow: hidden; animation: fadeUp 1s ease .6s both;
}
.lp-stat { flex: 1; padding: 20px 16px; text-align: center; border-right: 1px solid rgba(255,255,255,.07); transition: background .2s; }
.lp-stat:last-child { border-right: none; }
.lp-stat:hover { background: rgba(255,255,255,.04); }
.lp-stat-val { font-size: 1.8rem; font-weight: 800; color: #f1f5f9; display: block; }
.lp-stat-lbl { font-size: .72rem; color: #64748b; margin-top: 3px; text-transform: uppercase; letter-spacing: .06em; }

/* ── Section Titles ── */
.lp-section { padding: 80px 32px; max-width: 1100px; margin: 0 auto; }
.lp-section-badge { display: inline-block; background: rgba(99,102,241,.12); border: 1px solid rgba(99,102,241,.25); color: #818cf8; padding: 5px 14px; border-radius: 999px; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 14px; }
.lp-section-title { font-size: 2.2rem; font-weight: 800; color: #f1f5f9; margin-bottom: 12px; line-height: 1.2; }
.lp-section-sub { font-size: 1rem; color: #64748b; line-height: 1.7; max-width: 600px; }

/* ── Feature Cards ── */
.feat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 48px; }
.feat-card {
    position: relative; padding: 28px;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 16px; overflow: hidden;
    transition: all .3s cubic-bezier(.4,0,.2,1);
    cursor: default;
}
.feat-card::before {
    content: ''; position: absolute; inset: 0;
    opacity: 0; transition: opacity .3s;
    border-radius: 16px;
}
.feat-card:hover { transform: translateY(-6px); border-color: rgba(255,255,255,.12); box-shadow: 0 20px 40px rgba(0,0,0,.3); }
.feat-card:hover::before { opacity: 1; }
.feat-icon { font-size: 2.4rem; margin-bottom: 16px; display: block; }
.feat-title { font-size: 1rem; font-weight: 700; margin-bottom: 8px; }
.feat-desc { font-size: .83rem; color: #64748b; line-height: 1.65; }
.feat-tag { display: inline-block; margin-top: 14px; padding: 3px 10px; border-radius: 20px; font-size: .68rem; font-weight: 700; letter-spacing: .04em; }

/* ── Algorithm Cards ── */
.algo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 48px; }
.algo-card { padding: 28px; border-radius: 16px; border: 1px solid; transition: transform .3s; }
.algo-card:hover { transform: translateY(-4px); }
.algo-formula { font-family: 'Courier New', monospace; font-size: .78rem; padding: 12px 14px; border-radius: 8px; margin-top: 14px; line-height: 1.8; }

/* ── CTA Bottom Section ── */
.lp-cta-bottom {
    margin: 0; padding: 80px 32px;
    background: linear-gradient(135deg, rgba(20,184,166,.06) 0%, rgba(99,102,241,.06) 100%);
    border-top: 1px solid rgba(255,255,255,.06);
    text-align: center;
}

/* ── Footer ── */
.lp-footer {
    background: #070a0f;
    padding: 32px;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,.04);
    font-size: .8rem; color: #334155;
}

/* ── Divider ── */
.lp-divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,.07), transparent); margin: 0; }

/* Scroll indicator */
.scroll-indicator { position: absolute; bottom: 32px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center; gap: 8px; color: #475569; font-size: .72rem; animation: fadeIn 2s ease 1s both; }
.scroll-dot { width: 6px; height: 6px; border-radius: 50%; background: #14b8a6; animation: float 1.5s ease-in-out infinite; }
</style>

<!-- ═══════════════ SECTION 1: HERO ══════════════════════════ -->
<section class="lp-hero">
    <!-- Orbs -->
    <div class="orb" style="width:400px;height:400px;background:radial-gradient(circle,#14b8a6,transparent);top:-100px;left:-150px;animation-delay:0s;"></div>
    <div class="orb" style="width:300px;height:300px;background:radial-gradient(circle,#6366f1,transparent);bottom:-80px;right:-100px;animation-delay:2s;"></div>
    <div class="orb" style="width:200px;height:200px;background:radial-gradient(circle,#8b5cf6,transparent);top:60%;left:10%;animation-delay:4s;opacity:.25;"></div>

    <div class="lp-content">
        <div class="lp-badge">
            🚀 &nbsp;Platform Business Intelligence E-Commerce
        </div>

        <h1 class="lp-title">
            Online Retail<br>Analytics & Data Mining
        </h1>

        <p class="lp-sub">
            Sistem analisis keputusan bisnis berbasis <strong style="color:#f1f5f9;">PHP & MySQL</strong>
            yang mengolah <strong style="color:#14b8a6;">20.000+ baris data transaksi</strong> internasional
            dengan algoritma RFM, K-Means, Pareto ABC, dan Market Basket Analysis.
        </p>

        <div class="lp-cta">
            <?php if ($isLoggedIn): ?>
            <a href="?page=dashboard" class="btn-cta-primary">
                📊 Buka Dashboard →
            </a>
            <a href="?page=datamining" class="btn-cta-secondary">
                🧠 Data Mining Center
            </a>
            <?php else: ?>
            <a href="?page=login" class="btn-cta-primary">
                🔑 Masuk ke Sistem →
            </a>
            <a href="?page=register" class="btn-cta-secondary">
                ✍️ Daftar Akun Baru
            </a>
            <?php endif; ?>
        </div>

        <!-- Live Stats Bar -->
        <div class="lp-stats">
            <div class="lp-stat">
                <span class="lp-stat-val" style="color:#14b8a6;"><?= $statTx ?></span>
                <span class="lp-stat-lbl">Transaksi</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-val" style="color:#818cf8;"><?= $statCust ?></span>
                <span class="lp-stat-lbl">Pelanggan</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-val" style="color:#f59e0b;"><?= $statProd ?></span>
                <span class="lp-stat-lbl">Produk SKU</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-val" style="color:#10b981;"><?= $statRules ?></span>
                <span class="lp-stat-lbl">Assoc. Rules</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-val" style="color:#f43f5e;">4</span>
                <span class="lp-stat-lbl">K-Means Cluster</span>
            </div>
        </div>
    </div>

    <!-- Scroll indicator -->
    <div class="scroll-indicator">
        <span>scroll</span>
        <div class="scroll-dot"></div>
    </div>
</section>

<div class="lp-divider"></div>

<!-- ═══════════════ SECTION 2: BI FEATURES ═══════════════════ -->
<section style="padding:80px 32px; background:#0a0d14;">
<div style="max-width:1100px; margin:0 auto;">
    <div style="text-align:center; margin-bottom:52px;">
        <span class="lp-section-badge">🎯 Fitur Utama</span>
        <h2 class="lp-section-title" style="margin:10px auto 12px;">5 Business Intelligence Features</h2>
        <p class="lp-section-sub" style="margin:0 auto;">Diimplementasikan penuh dari layer OLTP hingga laporan eksekutif</p>
    </div>

    <div class="feat-grid">
        <!-- 1 Analysis Services -->
        <div class="feat-card" style="border-color:rgba(20,184,166,.15);">
            <div style="position:absolute;top:0;right:0;width:100px;height:100px;background:radial-gradient(circle at 100% 0%,rgba(20,184,166,.08),transparent);border-radius:0 16px;"></div>
            <span class="feat-icon">📊</span>
            <div class="feat-title" style="color:#14b8a6;">1. Analysis Services</div>
            <p class="feat-desc">Kalkulasi skor <strong style="color:#94a3b8;">RFM (Recency, Frequency, Monetary)</strong> per pelanggan untuk segmentasi: Champions, Loyal, Potential, At Risk, dan Lost Customers.</p>
            <span class="feat-tag" style="background:rgba(20,184,166,.12);color:#14b8a6;border:1px solid rgba(20,184,166,.2);">RFM Scoring</span>
            <a href="<?= $isLoggedIn ? '?page=customers' : '?page=login' ?>" style="display:block;margin-top:16px;font-size:.78rem;color:#14b8a6;font-weight:600;">Lihat Analisis Pelanggan →</a>
        </div>

        <!-- 2 Integration Services -->
        <div class="feat-card" style="border-color:rgba(99,102,241,.15);">
            <div style="position:absolute;top:0;right:0;width:100px;height:100px;background:radial-gradient(circle at 100% 0%,rgba(99,102,241,.08),transparent);border-radius:0 16px;"></div>
            <span class="feat-icon">🔄</span>
            <div class="feat-title" style="color:#818cf8;">2. Integration Services</div>
            <p class="feat-desc">Pipeline <strong style="color:#94a3b8;">ETL otomatis</strong> untuk import CSV 20.000 baris, pembersihan data di etl_staging, dan audit log setiap proses import ke etl_log.</p>
            <span class="feat-tag" style="background:rgba(99,102,241,.12);color:#818cf8;border:1px solid rgba(99,102,241,.2);">ETL Pipeline</span>
            <a href="<?= $isLoggedIn ? '?page=import' : '?page=login' ?>" style="display:block;margin-top:16px;font-size:.78rem;color:#818cf8;font-weight:600;">Import Data CSV →</a>
        </div>

        <!-- 3 Data Mining -->
        <div class="feat-card" style="border-color:rgba(245,158,11,.15);">
            <div style="position:absolute;top:0;right:0;width:100px;height:100px;background:radial-gradient(circle at 100% 0%,rgba(245,158,11,.08),transparent);border-radius:0 16px;"></div>
            <span class="feat-icon">⛏️</span>
            <div class="feat-title" style="color:#f59e0b;">3. Data Mining</div>
            <p class="feat-desc">Klasifikasi <strong style="color:#94a3b8;">Pareto ABC</strong> produk (A=top 80% revenue) dan <strong style="color:#94a3b8;">Market Basket Analysis</strong> dengan algoritma Apriori untuk rekomendasi bundling produk.</p>
            <span class="feat-tag" style="background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.2);">Apriori · ABC</span>
            <a href="<?= $isLoggedIn ? '?page=datamining' : '?page=login' ?>" style="display:block;margin-top:16px;font-size:.78rem;color:#f59e0b;font-weight:600;">Data Mining Center →</a>
        </div>

        <!-- 4 Reporting Services -->
        <div class="feat-card" style="border-color:rgba(16,185,129,.15);">
            <div style="position:absolute;top:0;right:0;width:100px;height:100px;background:radial-gradient(circle at 100% 0%,rgba(16,185,129,.08),transparent);border-radius:0 16px;"></div>
            <span class="feat-icon">📈</span>
            <div class="feat-title" style="color:#10b981;">4. Reporting Services</div>
            <p class="feat-desc">Laporan tren <strong style="color:#94a3b8;">revenue bulanan</strong>, distribusi penjualan per negara, 5 grafik interaktif Chart.js, dan fitur <strong style="color:#94a3b8;">export CSV</strong> untuk laporan eksekutif.</p>
            <span class="feat-tag" style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.2);">Chart.js · CSV Export</span>
            <a href="<?= $isLoggedIn ? '?page=reports' : '?page=login' ?>" style="display:block;margin-top:16px;font-size:.78rem;color:#10b981;font-weight:600;">Lihat Laporan →</a>
        </div>

        <!-- 5 Clustering -->
        <div class="feat-card" style="border-color:rgba(139,92,246,.15); grid-column: span 2;">
            <div style="position:absolute;top:0;right:0;width:200px;height:100%;background:radial-gradient(circle at 100% 50%,rgba(139,92,246,.06),transparent);border-radius:0 16px 16px 0;"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center;">
                <div>
                    <span class="feat-icon">🔮</span>
                    <div class="feat-title" style="color:#8b5cf6;">5. Clustering Support</div>
                    <p class="feat-desc">Algoritma <strong style="color:#94a3b8;">K-Means (k=4)</strong> berbasis Euclidean Distance dengan Min-Max Scaling pada fitur RFM, mengelompokkan pelanggan ke 4 klaster homogen.</p>
                    <span class="feat-tag" style="background:rgba(139,92,246,.12);color:#8b5cf6;border:1px solid rgba(139,92,246,.2);">K-Means · ML</span>
                    <a href="<?= $isLoggedIn ? '?page=clustering' : '?page=login' ?>" style="display:block;margin-top:16px;font-size:.78rem;color:#8b5cf6;font-weight:600;">Lihat Clustering →</a>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <?php
                    $clusterDefs = [
                        ['C0','Regular','#8b5cf6'],
                        ['C1','VIP','#14b8a6'],
                        ['C2','At Risk','#f43f5e'],
                        ['C3','Dormant','#f59e0b'],
                    ];
                    foreach ($clusterDefs as $cl): ?>
                    <div style="padding:14px; background:rgba(139,92,246,.06); border:1px solid rgba(139,92,246,.15); border-radius:10px; text-align:center;">
                        <div style="font-size:1.3rem; font-weight:800; color:<?= $cl[2] ?>;"><?= $cl[0] ?></div>
                        <div style="font-size:.72rem; color:#94a3b8; margin-top:3px;"><?= $cl[1] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

<div class="lp-divider"></div>

<!-- ═══════════════ SECTION 3: ALGORITHMS ════════════════════ -->
<section style="padding:80px 32px; background:#080b11;">
<div style="max-width:1100px; margin:0 auto;">
    <div style="text-align:center; margin-bottom:52px;">
        <span class="lp-section-badge" style="background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.2);color:#f59e0b;">⚙️ Algoritma</span>
        <h2 class="lp-section-title" style="margin:10px auto 12px;">Detail Algoritma yang Digunakan</h2>
        <p class="lp-section-sub" style="margin:0 auto;">Formula matematika yang diimplementasikan secara nyata dalam codebase PHP & MySQL</p>
    </div>

    <div class="algo-grid">
        <!-- RFM -->
        <div class="algo-card" style="background:rgba(129,140,248,.05); border-color:rgba(129,140,248,.15);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                <span style="font-size:1.5rem;">📈</span>
                <div>
                    <div style="font-weight:700; color:#818cf8;">RFM Segmentation</div>
                    <div style="font-size:.72rem; color:#64748b;">Analysis Services</div>
                </div>
            </div>
            <p style="font-size:.82rem; color:#94a3b8; line-height:1.6;">Menilai nilai pelanggan berdasarkan 3 dimensi perilaku belanja. Setiap dimensi diberi skor 1–5 menggunakan quantile-based binning.</p>
            <div class="algo-formula" style="background:rgba(0,0,0,.3); color:#a5b4fc;">
                R = NOW() − MAX(invoice_date)<br>
                F = COUNT(DISTINCT invoice_no)<br>
                M = SUM(qty × unit_price)<br>
                Score = CONCAT(R_bin, F_bin, M_bin)
            </div>
        </div>

        <!-- K-Means -->
        <div class="algo-card" style="background:rgba(139,92,246,.05); border-color:rgba(139,92,246,.15);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                <span style="font-size:1.5rem;">🔮</span>
                <div>
                    <div style="font-weight:700; color:#8b5cf6;">K-Means Clustering (k=4)</div>
                    <div style="font-size:.72rem; color:#64748b;">Clustering Support</div>
                </div>
            </div>
            <p style="font-size:.82rem; color:#94a3b8; line-height:1.6;">Pengelompokan pelanggan ke k=4 klaster berdasarkan jarak Euclidean terhadap centroid. Iterasi hingga konvergen (δ &lt; ε).</p>
            <div class="algo-formula" style="background:rgba(0,0,0,.3); color:#c4b5fd;">
                d(x,μ) = √[(R-R̄)² + (F-F̄)² + (M-M̄)²]<br>
                μₖ = (1/|Cₖ|) × Σ xᵢ ∀ xᵢ ∈ Cₖ<br>
                Min-Max: x' = (x-xmin)/(xmax-xmin)
            </div>
        </div>

        <!-- Pareto ABC -->
        <div class="algo-card" style="background:rgba(20,184,166,.05); border-color:rgba(20,184,166,.15);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                <span style="font-size:1.5rem;">📊</span>
                <div>
                    <div style="font-weight:700; color:#14b8a6;">Pareto ABC Analysis</div>
                    <div style="font-size:.72rem; color:#64748b;">Data Mining</div>
                </div>
            </div>
            <p style="font-size:.82rem; color:#94a3b8; line-height:1.6;">Mengklasifikasikan produk berdasarkan kontribusi kumulatif revenue. Kelas A mewakili 80% pendapatan total (Prinsip Pareto 80/20).</p>
            <div class="algo-formula" style="background:rgba(0,0,0,.3); color:#5eead4;">
                Revenue_pct(i) = Revenue(i) / Σ Revenue<br>
                Cum_pct(i) = Σ Revenue_pct(1..i)<br>
                A: Cum ≤ 80%  |  B: ≤ 95%  |  C: ≤ 100%
            </div>
        </div>

        <!-- Apriori -->
        <div class="algo-card" style="background:rgba(245,158,11,.05); border-color:rgba(245,158,11,.15);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                <span style="font-size:1.5rem;">⛏️</span>
                <div>
                    <div style="font-weight:700; color:#f59e0b;">Apriori (Market Basket)</div>
                    <div style="font-size:.72rem; color:#64748b;">Association Rules</div>
                </div>
            </div>
            <p style="font-size:.82rem; color:#94a3b8; line-height:1.6;">Menemukan pasangan produk yang sering dibeli bersamaan. Rules yang kuat memiliki Support, Confidence, dan Lift tinggi.</p>
            <div class="algo-formula" style="background:rgba(0,0,0,.3); color:#fcd34d;">
                Support(A→B) = freq(A∪B) / N<br>
                Confidence(A→B) = freq(A∪B) / freq(A)<br>
                Lift(A→B) = Confidence / Support(B)
            </div>
        </div>
    </div>
</div>
</section>

<div class="lp-divider"></div>

<!-- ═══════════════ SECTION 4: TECH STACK ════════════════════ -->
<section style="padding:80px 32px; background:#0a0d14;">
<div style="max-width:1100px; margin:0 auto;">
    <div style="text-align:center; margin-bottom:52px;">
        <span class="lp-section-badge" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.2);color:#10b981;">🛠️ Tech Stack</span>
        <h2 class="lp-section-title" style="margin:10px auto 12px;">Arsitektur & Teknologi</h2>
        <p class="lp-section-sub" style="margin:0 auto;">Dibangun dengan stack yang ringan, cepat, dan mudah di-deploy di XAMPP local server</p>
    </div>

    <!-- Architecture diagram ASCII-style -->
    <div style="background:rgba(0,0,0,.35); border:1px solid rgba(20,184,166,.15); border-radius:16px; padding:28px; margin-bottom:40px; font-family:'Courier New',monospace; font-size:.78rem; line-height:2; color:#64748b; overflow-x:auto;">
        <div style="color:#14b8a6; font-weight:700; margin-bottom:12px;">// Arsitektur MVC Sistem BI Online Retail</div>
        <span style="color:#94a3b8;">[ CSV File ]</span>  →  
        <span style="color:#818cf8;">[ ETL Pipeline ]</span>  →  
        <span style="color:#f59e0b;">[ etl_staging ]</span>  →  
        <span style="color:#10b981;">[ MySQL 3NF OLTP ]</span><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;↓<br>
        <span style="color:#94a3b8;">[ Dashboard UI ]</span>  ←  
        <span style="color:#f43f5e;">[ PHP Views ]</span>  ←  
        <span style="color:#8b5cf6;">[ BI Analytics ]</span>  ←  
        <span style="color:#14b8a6;">[ Star Schema OLAP ]</span>
    </div>

    <!-- Tech badges grid -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px;">
        <?php
        $techStack = [
            ['icon'=>'🐘','name'=>'PHP 8.2','desc'=>'Backend MVC Native','color'=>'#818cf8'],
            ['icon'=>'🗄️','name'=>'MySQL 8.x','desc'=>'OLTP 3NF + OLAP Star','color'=>'#14b8a6'],
            ['icon'=>'📊','name'=>'Chart.js 4.x','desc'=>'5 Grafik Interaktif','color'=>'#f59e0b'],
            ['icon'=>'📋','name'=>'DataTables.js','desc'=>'Tabel Sortable + Search','color'=>'#10b981'],
            ['icon'=>'🎨','name'=>'Vanilla CSS3','desc'=>'Dark Glassmorphism','color'=>'#f43f5e'],
            ['icon'=>'🔐','name'=>'PHP Sessions','desc'=>'Auth & User Management','color'=>'#8b5cf6'],
        ];
        foreach ($techStack as $t): ?>
        <div style="display:flex; align-items:center; gap:14px; padding:16px 18px;
                    background:rgba(255,255,255,.025); border:1px solid rgba(255,255,255,.06);
                    border-radius:12px; transition:all .2s;"
             onmouseover="this.style.borderColor='<?= $t['color'] ?>44'"
             onmouseout="this.style.borderColor='rgba(255,255,255,.06)'">
            <span style="font-size:1.8rem;"><?= $t['icon'] ?></span>
            <div>
                <div style="font-size:.88rem; font-weight:700; color:<?= $t['color'] ?>;"><?= $t['name'] ?></div>
                <div style="font-size:.72rem; color:#475569;"><?= $t['desc'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</section>

<div class="lp-divider"></div>

<!-- ═══════════════ SECTION 5: BOTTOM CTA ════════════════════ -->
<div class="lp-cta-bottom">
    <div style="max-width:600px; margin:0 auto;">
        <div style="font-size:3rem; margin-bottom:16px;">🚀</div>
        <h2 style="font-size:2rem; font-weight:800; color:#f1f5f9; margin-bottom:14px; line-height:1.2;">
            Siap Mulai Analisis Bisnis?
        </h2>
        <p style="font-size:.95rem; color:#64748b; margin-bottom:32px; line-height:1.7;">
            Login ke sistem untuk mengakses dashboard lengkap, data mining, laporan, dan semua fitur Business Intelligence.
        </p>
        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
            <?php if ($isLoggedIn): ?>
            <a href="?page=dashboard" class="btn-cta-primary">📊 Buka Dashboard →</a>
            <a href="?page=datamining" class="btn-cta-secondary">🧠 Data Mining</a>
            <?php else: ?>
            <a href="?page=login" class="btn-cta-primary">🔑 Login ke Sistem →</a>
            <a href="?page=register" class="btn-cta-secondary">✍️ Daftar Gratis</a>
            <?php endif; ?>
        </div>

        <!-- Akun Demo -->
        <?php if (!$isLoggedIn): ?>
        <div style="margin-top:28px; padding:16px 20px; background:rgba(20,184,166,.06); border:1px solid rgba(20,184,166,.15); border-radius:12px; display:inline-block;">
            <div style="font-size:.75rem; color:#64748b; margin-bottom:6px;">🔑 Akun Demo Admin:</div>
            <code style="font-size:.82rem; color:#14b8a6; font-weight:700;">admin@bi.com</code>
            <span style="color:#334155; margin:0 8px;">|</span>
            <code style="font-size:.82rem; color:#14b8a6; font-weight:700;">admin123</code>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════ FOOTER ════════════════════════════════════ -->
<div class="lp-footer">
    <div style="display:flex; justify-content:center; align-items:center; gap:20px; flex-wrap:wrap; margin-bottom:12px;">
        <span style="color:#1e293b; font-size:1.2rem;">📊</span>
        <span style="color:#334155;">Online Retail BI System &nbsp;·&nbsp; Built with PHP 8 + MySQL</span>
        <span style="color:#1e293b;">·</span>
        <a href="https://github.com/pebriana060205-stack/BI-Nana" target="_blank" style="color:#334155;">GitHub Repository</a>
    </div>
    <div style="color:#1e293b; font-size:.72rem;">
        Dataset: Online Retail II (UCI Machine Learning Repository) &nbsp;·&nbsp; 20.000+ Rows Transaction Data
    </div>
</div>

<!-- Counter animation on scroll -->
<script>
// Animate stats on load
document.querySelectorAll('.lp-stat-val').forEach((el, i) => {
    const target = el.textContent.replace(/,/g,'');
    const isNum  = !isNaN(target) && target !== '—';
    if (!isNum) return;
    const num = parseInt(target);
    let cur = 0;
    const step = Math.ceil(num / 60);
    const timer = setInterval(() => {
        cur = Math.min(cur + step, num);
        el.textContent = cur.toLocaleString('id-ID');
        if (cur >= num) clearInterval(timer);
    }, 20 + i * 5);
});

// Intersection Observer for fade-in sections
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.feat-card, .algo-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity .5s ease, transform .5s ease';
    observer.observe(el);
});
</script>

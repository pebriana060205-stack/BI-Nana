<?php
// ============================================================
//  Landing Page View — Public Overview & Presentation Page
// ============================================================
?>

<div class="landing-hero" style="padding: 48px 0; text-align: center;">
    <span class="panel-badge" style="font-size: 0.85rem; padding: 6px 16px; border-radius: 20px; background: rgba(20,184,166,0.15); color: var(--accent-teal); border: 1px solid rgba(20,184,166,0.3); display: inline-block; margin-bottom: 20px;">
        🚀 Platform Business Intelligence E-Commerce
    </span>
    
    <h1 style="font-size: 2.8rem; font-weight: 800; line-height: 1.2; margin-bottom: 20px; background: linear-gradient(135deg, #f1f5f9 0%, #94a3b8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Online Retail Analytics & Data Mining System
    </h1>
    
    <p style="font-size: 1.1rem; color: var(--text-secondary); max-width: 720px; margin: 0 auto 36px; line-height: 1.7;">
        Platform analisis keputusan bisnis yang mengolah <strong>20.000+ baris data transaksi e-commerce internasional</strong> menggunakan 5 fitur utama Business Intelligence: 
        <em>Analysis Services, Integration Services, Data Mining, Reporting Services, dan Clustering Support</em>.
    </p>

    <div style="display: flex; gap: 16px; justify-content: center; align-items: center; flex-wrap: wrap; margin-bottom: 48px;">
        <?php if (isset($_SESSION['user'])): ?>
            <a href="?page=dashboard" class="btn btn-primary btn-lg" style="padding: 14px 32px; font-size: 1rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(20,184,166,0.4);">
                📊 Buka Dashboard Utama →
            </a>
        <?php else: ?>
            <a href="?page=login" class="btn btn-primary btn-lg" style="padding: 14px 32px; font-size: 1rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(20,184,166,0.4);">
                🔑 Login System →
            </a>
            <a href="?page=register" class="btn btn-secondary btn-lg" style="padding: 14px 32px; font-size: 1rem; border-radius: 10px;">
                ✍️ Registrasi Akun
            </a>
        <?php endif; ?>
    </div>

    <!-- Live Statistics Badges -->
    <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr); max-width: 960px; margin: 0 auto 48px;">
        <div class="kpi-card teal">
            <div class="kpi-icon">📊</div>
            <div class="kpi-label">Dataset Source</div>
            <div class="kpi-value" style="font-size: 1.4rem;">20.000+ Baris</div>
            <div class="kpi-change">Online Retail II CSV</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-icon">📦</div>
            <div class="kpi-label">Master Produk</div>
            <div class="kpi-value" style="font-size: 1.4rem;">2.500+ SKU</div>
            <div class="kpi-change">Klasifikasi Pareto ABC</div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-icon">👥</div>
            <div class="kpi-label">Pelanggan Unik</div>
            <div class="kpi-value" style="font-size: 1.4rem;">600+ Client</div>
            <div class="kpi-change">Skor RFM & Segmentasi</div>
        </div>
        <div class="kpi-card emerald">
            <div class="kpi-icon">🔮</div>
            <div class="kpi-label">K-Means Cluster</div>
            <div class="kpi-value" style="font-size: 1.4rem;">4 Klaster</div>
            <div class="kpi-change">Unsupervised Machine Learning</div>
        </div>
    </div>
</div>

<!-- TOP 5 BI FEATURES CARDS -->
<div class="panel" style="margin-bottom: 32px; border-color: rgba(99,102,241,0.3);">
    <div class="panel-header" style="text-align: center; display: block; margin-bottom: 28px;">
        <h2 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">
            🎯 Top 5 Business Intelligence Features
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Fitur lengkap yang diimplementasikan untuk mendukung analisis strategis perusahaan
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px;">
            <div style="font-size: 2rem; margin-bottom: 12px;">📊</div>
            <h3 style="font-size: 1.1rem; color: var(--accent-teal); margin-bottom: 8px;">1. Analysis Services</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                Analisis & Scoring <strong>RFM (Recency, Frequency, Monetary)</strong> untuk evaluasi perilaku dan retensi pelanggan (*Champions, Loyal, At Risk, Lost*).
            </p>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px;">
            <div style="font-size: 2rem; margin-bottom: 12px;">🔄</div>
            <h3 style="font-size: 1.1rem; color: var(--accent-indigo); margin-bottom: 8px;">2. Integration Services</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                Pipeline <strong>ETL (Extract, Transform, Load)</strong> otomatis untuk import CSV, pembersihan data mentah (`etl_staging`), serta pencatatan log (`etl_log`).
            </p>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px;">
            <div style="font-size: 2rem; margin-bottom: 12px;">⛏️</div>
            <h3 style="font-size: 1.1rem; color: var(--accent-amber); margin-bottom: 8px;">3. Data Mining</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                Klasifikasi <strong>Pareto ABC (80/20 Rule)</strong> untuk omset produk dan <strong>Market Basket Analysis (Association Rules)</strong> untuk bundling item.
            </p>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px;">
            <div style="font-size: 2rem; margin-bottom: 12px;">📈</div>
            <h3 style="font-size: 1.1rem; color: var(--accent-emerald); margin-bottom: 8px;">4. Reporting Services</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                Laporan tren omset bulanan, distribusi penjualan per negara, <strong>MySQL Views analitik</strong>, dan fitur <strong>Export Data ke file CSV</strong>.
            </p>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; grid-column: span 2;">
            <div style="font-size: 2rem; margin-bottom: 12px;">🔮</div>
            <h3 style="font-size: 1.1rem; color: var(--accent-violet); margin-bottom: 8px;">5. Clustering Support</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                Algoritma Unsupervised Machine Learning <strong>K-Means Clustering ($k=4$)</strong> dengan Min-Max Scaling & Euclidean Distance untuk mengelompokkan pelanggan menjadi klaster *VIP, Regular, Dormant, dan One-Time*.
            </p>
        </div>
    </div>
</div>

<!-- Technology Stack Overview -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🛠️ Technology Stack Architecture</div>
    </div>
    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
        <span class="badge badge-regular" style="padding: 8px 16px; font-size: 0.85rem;">Backend: PHP 8.x Native (MVC Manual)</span>
        <span class="badge badge-vip" style="padding: 8px 16px; font-size: 0.85rem;">Database: MySQL 8.0+ (OLTP 3NF & OLAP Star Schema)</span>
        <span class="badge badge-a" style="padding: 8px 16px; font-size: 0.85rem;">Frontend: HTML5 + Vanilla CSS3 (Dark Glassmorphism)</span>
        <span class="badge badge-b" style="padding: 8px 16px; font-size: 0.85rem;">Charts: Chart.js 4.x</span>
        <span class="badge badge-new" style="padding: 8px 16px; font-size: 0.85rem;">Tables: DataTables.js</span>
    </div>
</div>

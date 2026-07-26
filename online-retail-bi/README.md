# 🚀 Online Retail BI Dashboard

Aplikasi Business Intelligence berbasis **PHP + MySQL** untuk menganalisis dataset Online Retail II (20.000 transaksi e-commerce internasional).

## 📋 Fitur yang Tersedia

| Fitur BI | Implementasi | Halaman |
|---|---|---|
| **Analysis Services** | Kalkulasi RFM (Recency, Frequency, Monetary) | `/index.php?page=customers` |
| **Integration Services** | Upload CSV + ETL Log | `/index.php?page=import` |
| **Data Mining** | ABC Analysis (Pareto) + Market Basket | `/index.php?page=products` & `mining` |
| **Reporting Services** | Laporan Bulanan & Negara + Export CSV | `/index.php?page=reports` |
| **Clustering Support** | K-Means (k=4): VIP, Regular, Dormant, One-Time | `/index.php?page=clustering` |

---

## 🛠️ Cara Setup (Step by Step)

### Prasyarat
- **PHP 8.1+** (dengan ekstensi: pdo_mysql, fileinfo)
- **MySQL 8.0+** (atau MariaDB 10.6+)
- **Web Server**: XAMPP / Laragon / WAMP

---

### Langkah 1 — Setup Database

1. Buka **phpMyAdmin** atau **MySQL Workbench**
2. Jalankan file SQL berikut:

```
online-retail-bi/config/setup_database.sql
```

Ini akan membuat database `online_retail_bi` beserta semua tabel OLTP, OLAP, dan BI.

---

### Langkah 2 — Konfigurasi Database

Edit file [`config/database.php`](config/database.php):

```php
define('DB_HOST', 'localhost');   // Host MySQL Anda
define('DB_NAME', 'online_retail_bi');
define('DB_USER', 'root');        // Username MySQL Anda
define('DB_PASS', '');            // Password MySQL Anda
```

---

### Langkah 3 — Konfigurasi Web Server

**Opsi A — XAMPP:**
1. Copy folder `online-retail-bi/` ke `C:/xampp/htdocs/`
2. Buka browser: `http://localhost/online-retail-bi/public/`

**Opsi B — Laragon:**
1. Copy folder ke `C:/laragon/www/`
2. Buka: `http://online-retail-bi.test/public/`

**Opsi C — PHP Built-in Server (untuk testing):**
```bash
cd online-retail-bi/public
php -S localhost:8080
```
Buka: `http://localhost:8080`

---

### Langkah 4 — Import Data

Setelah aplikasi berjalan:

1. Buka halaman **Import Data** (`?page=import`)
2. Klik **"Import Sekarang (20.000 Baris)"** — file CSV sudah terdeteksi otomatis
3. Tunggu proses selesai (~1-3 menit)
4. Cek hasil di halaman **Dashboard**

---

### Langkah 5 — Jalankan Analisis BI

Setelah data diimport, jalankan fitur-fitur berikut secara berurutan:

```
1. Pelanggan & RFM  → Klik "Hitung / Update RFM"
2. Produk & ABC     → Klik "Hitung ABC Analysis"
3. Clustering       → Klik "Jalankan K-Means Clustering"
4. Laporan          → Export CSV sesuai kebutuhan
```

---

## 📁 Struktur Folder

```
online-retail-bi/
├── config/
│   ├── database.php          ← Konfigurasi koneksi MySQL
│   └── setup_database.sql    ← Script SQL setup database
├── app/
│   ├── controllers/
│   │   └── ApiController.php ← Handler export CSV
│   └── views/
│       ├── layout/
│       │   ├── header.php    ← Sidebar + Top Bar
│       │   └── footer.php    ← Penutup HTML
│       ├── dashboard/        ← Halaman Dashboard (KPI + Charts)
│       ├── import/           ← Upload CSV + ETL Log
│       ├── customers/        ← RFM Segmentation
│       ├── products/         ← ABC Analysis
│       ├── clustering/       ← K-Means Results
│       ├── mining/           ← Market Basket Rules
│       └── reports/          ← Laporan + Export
├── helpers/
│   ├── ETL.php               ← Integration Services (Import CSV)
│   ├── RFM.php               ← Analysis Services (Scoring)
│   ├── ABC.php               ← Data Mining (Pareto)
│   └── Clustering.php        ← Clustering Support (K-Means)
├── public/
│   ├── index.php             ← Router utama (Entry Point)
│   ├── css/style.css         ← Stylesheet (Dark Mode Premium)
│   └── js/app.js             ← Script global
└── storage/uploads/          ← Folder upload CSV sementara
```

---

## ⚡ Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.x (native, MVC manual) |
| Database | MySQL 8.x (OLTP + OLAP Star Schema) |
| Frontend | HTML5 + CSS3 + Vanilla JS |
| Charts | Chart.js v4 |
| Tables | DataTables.js |
| Icons | Unicode Emoji |
| Font | Inter (Google Fonts) |

---

## 🔮 Pengembangan Lanjutan (Opsional)

- [ ] Tambah halaman Login & Autentikasi
- [ ] Implementasi Apriori PHP untuk Market Basket otomatis
- [ ] Export PDF menggunakan DomPDF
- [ ] Star Schema ETL (populate tabel DIM + FACT)
- [ ] Notifikasi email untuk pelanggan "At Risk"

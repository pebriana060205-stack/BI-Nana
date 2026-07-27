# Business Intelligence Project - Online Retail E-Commerce Analytics

## Latar Belakang

Project Business Intelligence ini dibangun untuk menganalisis data transaksi e-commerce internasional dari platform **Online Retail**. Dataset terdiri dari 20.000 baris transaksi e-commerce internasional (UK & Global). Website ini menyajikan dashboard interaktif untuk visualisasi data, analisis penjualan, segmentasi pelanggan menggunakan **RFM Analysis**, **ABC Analysis (Pareto 80/20)**, serta **K-Means Clustering**.

## Tujuan Project

1. Membangun website BI interaktif menggunakan **PHP Native (MVC Manual) + MySQL 8.x**
2. Merancang dan mengimplementasikan database OLTP yang ter-normalisasi (3NF) di MySQL
3. Merancang Data Warehouse dengan **Star Schema (1 Fact Table + 4 Dimension Tables)**
4. Mengimplementasikan **Integration Services (ETL Pipeline)** dengan log history & staging area
5. Membangun dashboard interaktif dengan visualisasi data menggunakan **Chart.js v4** dan **DataTables.js**
6. Menyajikan **Analysis Services (RFM Analysis)** untuk segmentasi nilai & retensi pelanggan
7. Mengaplikasikan **Data Mining (Pareto ABC Analysis & Association Rules Market Basket)**
8. Menyajikan **Clustering Support (K-Means Clustering $k=4$)** untuk pengelompokan pelanggan (VIP, Regular, Dormant, One-Time)
9. Menyajikan **Reporting Services** dengan laporan tren bulanan, geografi negara, dan fitur export CSV

## Technology Stack

| Komponen | Teknologi | Keterangan |
|----------|-----------|------------|
| Runtime / Server | PHP 8.1+ | Native PHP Engine (MVC Manual Architecture) |
| Web Server | Apache / Nginx | Supported via XAMPP, Laragon, or Built-in Server |
| Database | MySQL 8.0+ | Relational OLTP Database + OLAP Star Schema |
| Database Driver | PDO (PHP Data Objects) | Secure Prepared Statements (Anti SQL-Injection) |
| Styling (CSS) | Modern Vanilla CSS3 | Dark Mode Premium Aesthetic with Glassmorphism |
| Typography | Google Fonts (Inter) | Modern Typography System |
| Chart Library | Chart.js 4.x | Interactive Data Visualization (Bar, Line, Doughnut, Radar) |
| Table Library | DataTables.js | Dynamic Tabular Interaction with Search & Pagination |
| Export Engine | PHP fputcsv | Direct Server-Side UTF-8 CSV Exporter |
| Security | PDO Prepared Statements | Input Sanitization & Protection |

## Fitur Website

| No | Halaman | Deskripsi Fitur | Fitur BI |
|----|---------|-----------------|----------|
| 1 | Dashboard Utama | Overview bisnis: KPI Cards, Revenue Trend Line Chart, Top Products, Country Bar Chart, & RFM Segment Doughnut | Dashboard Overview |
| 2 | Import Data | Form upload CSV + ETL Engine execution + History etl_log tabel | **Integration Services** |
| 3 | Pelanggan & RFM | Kalkulasi skor Recency, Frequency, Monetary + Tabel segmentasi pelanggan | **Analysis Services** |
| 4 | Produk & ABC | Klasifikasi Pareto ABC (A = 80% revenue, B = 80-95%, C = sisanya) + Top revenue generators | **Data Mining** |
| 5 | Market Basket Analysis | Tabel Association Rules (Support, Confidence, Lift) untuk produk yang dibeli bersama | **Data Mining** |
| 6 | Clustering Pelanggan | Visualisasi K-Means Clustering ($k=4$) dengan Donut Chart & Radar Profile Chart | **Clustering Support** |
| 7 | Laporan & Export | Tabel laporan penjualan bulanan & per negara + Tombol Export CSV | **Reporting Services** |

## Deskripsi Dataset

| Properti | Nilai |
|----------|-------|
| File | `online_retail_II_20000.csv` |
| Delimiter | Comma (`,`) |
| Total Baris | 20.000 |
| Total Kolom | 8 |
| Periode Transaksi | 2009 - 2010 |
| Mata Uang | Pound Sterling (£ / GBP) |
| Wilayah | United Kingdom & International (38+ Negara) |

### Kolom Dataset

| No | Nama Kolom | Tipe Data | Keterangan |
|----|-----------|-----------|------------|
| 1 | Invoice | VARCHAR(20) | Nomor invoice unik (Diawali 'C' jika Cancelled/Retur) |
| 2 | StockCode | VARCHAR(20) | Kode unik barang/produk |
| 3 | Description | VARCHAR(255) | Nama atau deskripsi produk |
| 4 | Quantity | INT | Jumlah barang yang dibeli (Nilai negatif = retur) |
| 5 | InvoiceDate | DATETIME | Tanggal dan waktu lengkap transaksi |
| 6 | Price | DECIMAL(10,2) | Harga satuan produk dalam Pound Sterling (£) |
| 7 | Customer ID | INT | ID unik pelanggan (NULL / 0 = Guest Customer) |
| 8 | Country | VARCHAR(100) | Negara lokasi pelanggan bertransaksi |

### Insight Utama

- **Wilayah Terbesar**: United Kingdom mendominasi > 85% total transaksi dan pendapatan
- **Puncak Penjualan**: Lonjakan transaksi drastis terjadi di bulan November - Desember (akhir tahun/holiday season)
- **Produk Kelas A (Pareto)**: ~15-20% total SKU produk menyumbang > 80% dari total pendapatan bisnis
- **Segmen Pelanggan**: Klaster VIP & Champions menyumbang kontribusi nilai belanja terbanyak

## Arsitektur Website BI

```
┌─────────────────────────────────────────────────────────────────┐
│                      User Browser (Frontend)                    │
│           HTML5 + Vanilla CSS (Dark Mode) + Chart.js            │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       PHP Server (Backend)                      │
│        index.php (Router) ──> Helpers (ETL, RFM, ABC, K-Means)  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      MySQL Database (8.0+)                      │
│     ShopeeOLTP (3NF Tables)  ──>  ShopeeDW (Star Schema)        │
└─────────────────────────────────────────────────────────────────┘
        │                       │                       │
        ▼                       ▼                       ▼
┌───────────────┐       ┌───────────────┐       ┌───────────────┐
│  Integration  │       │   Analysis    │       │  Clustering   │
│   Services    │       │   Services    │       │   & Mining    │
│  ETL Pipeline │       │ RFM Scoring   │       │ ABC + K-Means │
└───────────────┘       └───────────────┘       └───────────────┘
```

### Alur Data (Data Pipeline)

```
[CSV Dataset Source] ──> [PHP ETL Helper] ──> [Staging Area] ──> [OLTP Database]
   (20.000 rows)         Validation & Clean    (etl_staging)      (3NF Normalization)
                                                                       │
                                                                       ▼
[Dashboard UI] <── [MySQL Views & API] <── [BI Analytics] <── [Star Schema OLAP]
 (Chart.js/Tables)   (vw_monthly_sales)    (RFM, ABC, K-Means)   (FACT_SALES + DIM)
```

## Database Design

### OLTP Tables (MySQL 3NF Schema & Status CRUD)

| Tabel | Keterangan | Primary Key / Foreign Key | Status Operasi CRUD |
|-------|-----------|---------------------------|---------------------|
| `users` | Akun pengguna & administrator BI | `id` (PK) | **Create, Read, Update, Delete** (Login, Registrasi, User Session) |
| `products` | Master data produk e-commerce | `stock_code` (PK) | **Create, Read, Update, Delete** (Form Modal CRUD Master Produk) |
| `customers` | Master data pelanggan & RFM | `customer_id` (PK), `country_id` (FK) | **Create, Read, Update** (Kalkulasi RFM & Segmen Update) |
| `transactions` | Header transaksi / invoice | `transaction_id` (PK), `customer_id` (FK), `country_id` (FK) | **Create, Read, Delete** (ETL Pipeline & Log Delete) |
| `transaction_items` | Detail item barang per invoice | `item_id` (PK), `transaction_id` (FK), `stock_code` (FK) | **Create, Read** (ETL Bulk Insert Item) |
| `countries` | Master data negara & wilayah | `country_id` (PK) | **Create, Read** (Lookup Master Country) |

### Tabel Pendukung Analytics & BI

| Tabel | Fitur BI | Deskripsi |
|-------|----------|-----------|
| `analytics_rfm` | **Analysis Services** | Hasil kalkulasi Recency, Frequency, Monetary, & Skor Segmen |
| `mining_product_abc` | **Data Mining** | Hasil klasifikasi Pareto ABC produk berdasarkan pendapatan |
| `mining_association_rules` | **Data Mining** | Hasil Market Basket Analysis (Support, Confidence, Lift) |
| `clustering_customer_groups` | **Clustering Support** | Penugasan pelanggan ke klaster K-Means ($k=4$) |
| `etl_log` | **Integration Services** | History eksekusi import CSV (total baris, sukses, gagal) |
| `etl_staging` | **Integration Services** | Area validasi mentah sebelum dimuat ke tabel utama |

### Entity Relationship Diagram (ERD)

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│  COUNTRIES  │         │  CUSTOMERS   │         │  PRODUCTS   │
│─────────────│         │──────────────│         │─────────────│
│ country_id  │◄──┐     │ customer_id  │         │ stock_code  │
│ country_name│   │     │ country_id   │──┐      │ description │
│ region      │   │     │ rfm_score    │  │      │ unit_price  │
└─────────────┘   │     │ cluster_id   │  │      │ price_tier  │
                  │     └──────────────┘  │      └──────┬──────┘
                  │                       │             │
                  │     ┌─────────────────┘             │
                  │     │                               │
                  ▼     ▼                               │
        ┌───────────────────┐                           │
        │   TRANSACTIONS    │                           │
        │───────────────────│                           │
        │ transaction_id    │                           │
        │ invoice_no        │                           │
        │ customer_id (FK)  │                           │
        │ country_id (FK)   │                           │
        │ invoice_date      │                           │
        │ total_amount      │                           │
        │ is_cancelled      │                           │
        └─────────┬─────────┘                           │
                  │                                     │
                  └───────────────────┬─────────────────┘
                                      ▼
                           ┌─────────────────────┐
                           │  TRANSACTION_ITEMS  │
                           │─────────────────────│
                           │ item_id (PK)        │
                           │ transaction_id (FK) │
                           │ stock_code (FK)     │
                           │ quantity            │
                           │ unit_price          │
                           │ subtotal            │
                           └─────────────────────┘
```

---

## 🛠️ Cara Setup & Instalasi (Local Development)

### Prasyarat System
- **PHP 8.1+** (Ekstensi aktif: `pdo_mysql`, `fileinfo`)
- **MySQL 8.0+** / MariaDB 10.6+
- **Web Server**: XAMPP / Laragon / WAMP / PHP Built-in Server

---

### Langkah 1 — Setup Database MySQL

1. Buka **phpMyAdmin** atau **MySQL Workbench**
2. Buat database baru bernama `online_retail_bi` atau jalankan file SQL:
   ```
   online-retail-bi/config/setup_database.sql
   ```
   *Script ini secara otomatis membuat seluruh tabel OLTP, OLAP Star Schema, Tabel Analytics, dan MySQL Views.*

---

### Langkah 2 — Konfigurasi Koneksi Database

Edit file [`online-retail-bi/config/database.php`](online-retail-bi/config/database.php):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'online_retail_bi');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

### Langkah 3 — Jalankan Server

**Menggunakan PHP Built-in Web Server:**
```bash
cd online-retail-bi/public
php -S localhost:8080
```
Buka browser: `http://localhost:8080`

**Menggunakan XAMPP:**
Copy folder `online-retail-bi` ke `C:/xampp/htdocs/` lalu buka `http://localhost/online-retail-bi/public/`

---

### Langkah 4 — Import Dataset & Jalankan Fitur Analisis

1. Masuk ke halaman **Import Data** (`/index.php?page=import`)
2. Klik **"Import Sekarang"** untuk memproses `online_retail_II_20000.csv`
3. Masuk ke **Pelanggan & RFM** → Klik **"Hitung / Update RFM"**
4. Masuk ke **Produk & ABC** → Klik **"Hitung ABC Analysis"**
5. Masuk ke **Clustering** → Klik **"Jalankan K-Means Clustering"**
6. Masuk ke **Laporan** → Download hasil analisis dalam format CSV

---

## 📄 License & Repository

Project ini didokumentasikan dan dipublikasikan di repository GitHub resmi:
- **Repository URL**: [https://github.com/pebriana060205-stack/BI-Nana](https://github.com/pebriana060205-stack/BI-Nana)
- **Implementation Plan**: [implementation_plan.md](https://github.com/pebriana060205-stack/BI-Nana/blob/main/implementation_plan.md)

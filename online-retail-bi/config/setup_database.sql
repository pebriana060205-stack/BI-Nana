-- ============================================================
--  SETUP DATABASE: online_retail_bi
--  Jalankan file SQL ini di MySQL Workbench / phpMyAdmin / CLI
--  Urutan: DROP -> CREATE DB -> OLTP Tables -> OLAP Tables
--          -> Analytic Tables -> Views
-- ============================================================

DROP DATABASE IF EXISTS online_retail_bi;
CREATE DATABASE online_retail_bi
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE online_retail_bi;

-- ============================================================
--  BAGIAN 1: TABEL OLTP (Operasional)
-- ============================================================

CREATE TABLE countries (
    country_id   INT AUTO_INCREMENT PRIMARY KEY,
    country_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nama negara (misal: United Kingdom)',
    region       VARCHAR(50)  DEFAULT 'Unknown' COMMENT 'Wilayah (Europe, Asia, dll)',
    currency     VARCHAR(10)  DEFAULT 'GBP'     COMMENT 'Kode mata uang'
) ENGINE=InnoDB COMMENT='Master data negara';

CREATE TABLE customers (
    customer_id       INT PRIMARY KEY COMMENT 'ID dari dataset (bukan auto increment)',
    country_id        INT            COMMENT 'FK ke countries',
    segment           VARCHAR(30)    DEFAULT 'New' COMMENT 'Segmen RFM: Champions, Loyal, At Risk, Lost, New',
    first_purchase_date DATE          NULL,
    last_purchase_date  DATE          NULL,
    total_orders      INT            DEFAULT 0,
    total_spent       DECIMAL(12,2)  DEFAULT 0.00,
    rfm_score         DECIMAL(5,2)   NULL COMMENT 'Skor RFM 0-100 dari Data Mining',
    cluster_id        INT            NULL COMMENT 'Hasil K-Means Clustering',
    created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cust_country FOREIGN KEY (country_id) REFERENCES countries(country_id)
) ENGINE=InnoDB COMMENT='Data pelanggan';

CREATE TABLE products (
    stock_code  VARCHAR(20)  PRIMARY KEY COMMENT 'Kode produk dari dataset',
    description VARCHAR(255) NOT NULL,
    category    VARCHAR(50)  DEFAULT 'General',
    unit_price  DECIMAL(10,2) NOT NULL COMMENT 'Harga satuan terkini (£)',
    price_tier  VARCHAR(10)  NOT NULL COMMENT 'Low (<£2) / Mid (£2-£10) / Premium (>£10)',
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Master data produk';

CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no     VARCHAR(20)   NOT NULL,
    customer_id    INT           NULL COMMENT 'NULL atau 0 untuk tamu (guest)',
    country_id     INT           NOT NULL,
    invoice_date   DATETIME      NOT NULL,
    total_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_items    INT           NOT NULL DEFAULT 0,
    is_cancelled   TINYINT(1)    DEFAULT 0 COMMENT '1 jika invoice diawali C',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice  (invoice_no),
    INDEX idx_customer (customer_id),
    INDEX idx_date     (invoice_date),
    CONSTRAINT fk_trans_country  FOREIGN KEY (country_id)  REFERENCES countries(country_id)
) ENGINE=InnoDB COMMENT='Header transaksi per invoice';

CREATE TABLE transaction_items (
    item_id        INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT           NOT NULL,
    stock_code     VARCHAR(20)   NOT NULL,
    quantity       INT           NOT NULL COMMENT 'Negatif untuk retur',
    unit_price     DECIMAL(10,2) NOT NULL,
    subtotal       DECIMAL(12,2) NOT NULL COMMENT 'quantity x unit_price',
    CONSTRAINT fk_item_trans   FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
    CONSTRAINT fk_item_product FOREIGN KEY (stock_code)     REFERENCES products(stock_code)
) ENGINE=InnoDB COMMENT='Detail item per transaksi';

-- ============================================================
--  BAGIAN 2: TABEL OLAP — STAR SCHEMA (Data Warehouse)
-- ============================================================

CREATE TABLE dim_time (
    time_key      INT PRIMARY KEY,
    full_date     DATE    NOT NULL,
    day_of_month  TINYINT NOT NULL,
    day_name      VARCHAR(10),
    week_of_year  TINYINT,
    month_number  TINYINT NOT NULL,
    month_name    VARCHAR(15),
    quarter       TINYINT,
    year          SMALLINT NOT NULL,
    is_weekend    TINYINT(1) DEFAULT 0,
    season        VARCHAR(10) COMMENT 'Spring/Summer/Autumn/Winter'
) ENGINE=InnoDB COMMENT='Dimensi Waktu';

CREATE TABLE dim_customer (
    customer_key  INT AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT,
    segment       VARCHAR(30),
    recency_days  INT,
    frequency     INT,
    monetary      DECIMAL(12,2),
    r_score       TINYINT,
    f_score       TINYINT,
    m_score       TINYINT,
    cluster_id    INT,
    cluster_label VARCHAR(30)
) ENGINE=InnoDB COMMENT='Dimensi Pelanggan (OLAP)';

CREATE TABLE dim_product (
    product_key INT AUTO_INCREMENT PRIMARY KEY,
    stock_code  VARCHAR(20),
    description VARCHAR(255),
    category    VARCHAR(50),
    price_tier  VARCHAR(10),
    abc_class   CHAR(1) COMMENT 'A=Top80%, B=80-95%, C=sisanya'
) ENGINE=InnoDB COMMENT='Dimensi Produk (OLAP)';

CREATE TABLE dim_geography (
    geography_key INT AUTO_INCREMENT PRIMARY KEY,
    country_name  VARCHAR(100),
    region        VARCHAR(50),
    sub_region    VARCHAR(50),
    is_domestic   TINYINT(1) DEFAULT 0 COMMENT '1 jika UK'
) ENGINE=InnoDB COMMENT='Dimensi Geografi (OLAP)';

CREATE TABLE fact_sales (
    sales_key      INT AUTO_INCREMENT PRIMARY KEY,
    time_key       INT,
    customer_key   INT,
    product_key    INT,
    geography_key  INT,
    invoice_no     VARCHAR(20),
    quantity       INT,
    unit_price     DECIMAL(10,2),
    revenue        DECIMAL(12,2),
    is_cancelled   TINYINT(1) DEFAULT 0,
    INDEX idx_fact_time   (time_key),
    INDEX idx_fact_cust   (customer_key),
    INDEX idx_fact_prod   (product_key),
    INDEX idx_fact_geo    (geography_key)
) ENGINE=InnoDB COMMENT='Tabel Fakta Penjualan (OLAP)';

-- ============================================================
--  BAGIAN 3: TABEL PENDUKUNG FITUR BI
-- ============================================================

-- Integration Services: Log ETL
CREATE TABLE etl_log (
    log_id        INT AUTO_INCREMENT PRIMARY KEY,
    source_file   VARCHAR(255),
    total_rows    INT DEFAULT 0,
    success_rows  INT DEFAULT 0,
    failed_rows   INT DEFAULT 0,
    error_details TEXT          COMMENT 'JSON array berisi error per baris',
    started_at    DATETIME,
    completed_at  DATETIME,
    status        ENUM('running','success','failed','partial') DEFAULT 'running'
) ENGINE=InnoDB COMMENT='Log proses ETL import CSV';

CREATE TABLE etl_staging (
    staging_id     INT AUTO_INCREMENT PRIMARY KEY,
    raw_invoice    VARCHAR(50),
    raw_stockcode  VARCHAR(50),
    raw_description TEXT,
    raw_quantity   VARCHAR(20),
    raw_date       VARCHAR(50),
    raw_price      VARCHAR(20),
    raw_customerid VARCHAR(20),
    raw_country    VARCHAR(100),
    is_valid       TINYINT DEFAULT 0,
    error_reason   VARCHAR(255),
    loaded_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Area staging untuk validasi sebelum load';

-- Analysis Services: RFM
CREATE TABLE analytics_rfm (
    customer_id   INT PRIMARY KEY,
    recency_days  INT           COMMENT 'Hari sejak pembelian terakhir',
    frequency     INT           COMMENT 'Jumlah invoice unik',
    monetary      DECIMAL(12,2) COMMENT 'Total belanja (£)',
    r_score       TINYINT       COMMENT 'Skor Recency 1-5',
    f_score       TINYINT       COMMENT 'Skor Frequency 1-5',
    m_score       TINYINT       COMMENT 'Skor Monetary 1-5',
    rfm_segment   VARCHAR(30)   COMMENT 'Champions, Loyal, At Risk, dll',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Hasil kalkulasi RFM per pelanggan';

-- Data Mining: Association Rules
CREATE TABLE mining_association_rules (
    rule_id    INT AUTO_INCREMENT PRIMARY KEY,
    antecedent VARCHAR(100) COMMENT 'Produk pemicu',
    consequent VARCHAR(100) COMMENT 'Produk yang ikut dibeli',
    support    DECIMAL(6,4) COMMENT 'Frekuensi kemunculan bersama',
    confidence DECIMAL(6,4) COMMENT 'Probabilitas B|A',
    lift       DECIMAL(8,4) COMMENT 'Kekuatan asosiasi (>1 = signifikan)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Hasil Market Basket Analysis';

-- Data Mining: ABC Analysis
CREATE TABLE mining_product_abc (
    stock_code     VARCHAR(20) PRIMARY KEY,
    description    VARCHAR(255),
    total_revenue  DECIMAL(12,2),
    revenue_pct    DECIMAL(5,2) COMMENT '% dari total revenue',
    cumulative_pct DECIMAL(5,2) COMMENT '% kumulatif',
    abc_class      CHAR(1)      COMMENT 'A=Top80%, B=80-95%, C=sisanya'
) ENGINE=InnoDB COMMENT='Klasifikasi ABC produk berdasarkan revenue';

-- Clustering Support: Segmentasi Pelanggan
CREATE TABLE clustering_customer_groups (
    cluster_id    INT,
    customer_id   INT,
    cluster_label VARCHAR(30)   COMMENT 'VIP, Regular, Dormant, One-Time',
    centroid_r    DECIMAL(8,4)  COMMENT 'Pusat klaster: Recency',
    centroid_f    DECIMAL(8,4)  COMMENT 'Pusat klaster: Frequency',
    centroid_m    DECIMAL(8,4)  COMMENT 'Pusat klaster: Monetary',
    assigned_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cluster_id, customer_id)
) ENGINE=InnoDB COMMENT='Hasil K-Means Clustering pelanggan';

-- ============================================================
--  BAGIAN 4: VIEWS UNTUK REPORTING SERVICES
-- ============================================================

CREATE OR REPLACE VIEW vw_monthly_sales AS
SELECT
    YEAR(invoice_date)       AS tahun,
    MONTH(invoice_date)      AS bulan,
    MONTHNAME(invoice_date)  AS nama_bulan,
    COUNT(DISTINCT invoice_no)  AS total_order,
    COUNT(DISTINCT customer_id) AS pelanggan_unik,
    ROUND(SUM(total_amount), 2) AS total_revenue,
    ROUND(AVG(total_amount), 2) AS rata_rata_order
FROM transactions
WHERE is_cancelled = 0
GROUP BY YEAR(invoice_date), MONTH(invoice_date)
ORDER BY tahun, bulan;

CREATE OR REPLACE VIEW vw_top_products AS
SELECT
    p.stock_code,
    p.description,
    p.price_tier,
    p.category,
    SUM(ti.quantity)                  AS total_qty_terjual,
    ROUND(SUM(ti.subtotal), 2)        AS total_revenue,
    COUNT(DISTINCT ti.transaction_id) AS jumlah_order
FROM transaction_items ti
JOIN products     p ON ti.stock_code     = p.stock_code
JOIN transactions t ON ti.transaction_id = t.transaction_id
WHERE t.is_cancelled = 0 AND ti.quantity > 0
GROUP BY p.stock_code, p.description, p.price_tier, p.category
ORDER BY total_revenue DESC;

CREATE OR REPLACE VIEW vw_sales_by_country AS
SELECT
    c.country_name,
    c.region,
    COUNT(DISTINCT t.invoice_no)  AS total_order,
    COUNT(DISTINCT t.customer_id) AS pelanggan_unik,
    ROUND(SUM(t.total_amount), 2) AS total_revenue
FROM transactions t
JOIN countries c ON t.country_id = c.country_id
WHERE t.is_cancelled = 0
GROUP BY c.country_name, c.region
ORDER BY total_revenue DESC;

CREATE OR REPLACE VIEW vw_daily_sales AS
SELECT
    DATE(invoice_date)              AS tanggal,
    COUNT(DISTINCT invoice_no)      AS total_order,
    ROUND(SUM(total_amount), 2)     AS total_revenue,
    COUNT(DISTINCT customer_id)     AS pelanggan_unik
FROM transactions
WHERE is_cancelled = 0
GROUP BY DATE(invoice_date)
ORDER BY tanggal;

-- ============================================================
--  SELESAI — Jalankan script ETL PHP setelah ini
-- ============================================================
SELECT 'Database setup selesai!' AS status;

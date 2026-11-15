-- Cấu trúc database supplier_management (dựng lại từ các cột mà code của project sử dụng).
-- Chỉ cần chạy file này khi tạo database mới; nếu bạn đã có database từ trước thì KHÔNG cần chạy (project không đổi cấu trúc bảng).
-- Cách chạy: HeidiSQL → File → Run SQL file → chọn file này, mục Encoding chọn UTF-8.
-- Sau đó chạy database/seed.sql để có dữ liệu mẫu. Tạo tài khoản admin: xem database/make_hash.php.

CREATE DATABASE IF NOT EXISTS supplier_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE supplier_management;
SET NAMES utf8mb4;

-- Tài khoản. Chỉ role 'admin' đang hoạt động (is_active = 1) đăng nhập được; role 'staff' chỉ để lưu, không có giao diện đăng nhập.
CREATE TABLE IF NOT EXISTS users (
    user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS supplier_categories (
    category_id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description   VARCHAR(255) NULL
) ENGINE = InnoDB;

-- Nhà cung cấp. Xóa danh mục đang có nhà cung cấp bị chặn (RESTRICT). avg_rating là cột lưu tạm, do phần đánh giá cập nhật.
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(20)  NOT NULL UNIQUE,
    category_id   INT UNSIGNED NOT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    tax_code      VARCHAR(14)  NULL UNIQUE,
    email         VARCHAR(100) NULL,
    phone         VARCHAR(20)  NULL,
    address       VARCHAR(255) NULL,
    logo_url      VARCHAR(255) NULL,
    credit_limit  DECIMAL(15, 2) NOT NULL DEFAULT 0,
    avg_rating    DECIMAL(3, 2)  NOT NULL DEFAULT 0,
    status        ENUM('active', 'inactive', 'blacklisted') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_suppliers_category FOREIGN KEY (category_id) REFERENCES supplier_categories (category_id) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Người liên hệ: xóa nhà cung cấp thì xóa theo (CASCADE)
CREATE TABLE IF NOT EXISTS supplier_contacts (
    contact_id  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    position    VARCHAR(100) NULL,
    email       VARCHAR(100) NULL,
    phone       VARCHAR(20)  NULL,
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    CONSTRAINT fk_contacts_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (supplier_id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Đánh giá: overall_score do MySQL tự tính (trung bình 3 điểm, làm tròn 2 chữ số)
CREATE TABLE IF NOT EXISTS supplier_evaluations (
    evaluation_id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supplier_id     INT UNSIGNED NOT NULL,
    evaluation_date DATE NOT NULL,
    quality_score   TINYINT UNSIGNED NOT NULL,
    delivery_score  TINYINT UNSIGNED NOT NULL,
    service_score   TINYINT UNSIGNED NOT NULL,
    overall_score   DECIMAL(3, 2) GENERATED ALWAYS AS (ROUND((quality_score + delivery_score + service_score) / 3, 2)) STORED,
    comment         TEXT NULL,
    evaluated_by    INT UNSIGNED NOT NULL,
    CONSTRAINT fk_eval_supplier FOREIGN KEY (supplier_id)  REFERENCES suppliers (supplier_id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_user     FOREIGN KEY (evaluated_by) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Giao dịch (hóa đơn mua hàng): remaining_amount và payment_status do MySQL tự tính từ total_amount và paid_amount.
-- Nhà cung cấp đã có giao dịch thì không xóa được (RESTRICT).
CREATE TABLE IF NOT EXISTS supply_transactions (
    transaction_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supplier_id       INT UNSIGNED NOT NULL,
    invoice_number    VARCHAR(50)  NOT NULL,
    transaction_date  DATE NOT NULL,
    due_date          DATE NULL,
    total_amount      DECIMAL(15, 2) NOT NULL,
    paid_amount       DECIMAL(15, 2) NOT NULL DEFAULT 0,
    remaining_amount  DECIMAL(15, 2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    payment_status    VARCHAR(10) GENERATED ALWAYS AS (
        CASE WHEN paid_amount <= 0 THEN 'unpaid' WHEN paid_amount >= total_amount THEN 'paid' ELSE 'partial' END
    ) STORED,
    invoice_file_path VARCHAR(255) NULL,
    created_by        INT UNSIGNED NOT NULL,
    CONSTRAINT fk_trans_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (supplier_id) ON DELETE RESTRICT,
    CONSTRAINT fk_trans_user     FOREIGN KEY (created_by)  REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE = InnoDB;

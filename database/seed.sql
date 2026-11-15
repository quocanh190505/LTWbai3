-- Dữ liệu mẫu cho database supplier_management (chạy lại được nhiều lần).
-- Cách chạy: HeidiSQL → File → Run SQL file → chọn file này, mục Encoding chọn UTF-8.
-- Chỉ xóa và nạp lại 10 nhà cung cấp mẫu NCC001–NCC010 và 2 tài khoản thử;
-- dữ liệu khác của bạn (danh mục, tài khoản admin, nhà cung cấp bạn tự thêm) được giữ nguyên.
-- Yêu cầu: bảng users đã có ít nhất 1 tài khoản admin đang hoạt động (dùng làm người tạo giao dịch, người đánh giá).

USE supplier_management;
SET NAMES utf8mb4;

-- ---------- 1) Xóa dữ liệu mẫu cũ ----------
-- Giao dịch xóa trước vì khóa ngoại RESTRICT; liên hệ và đánh giá tự xóa theo nhà cung cấp (CASCADE).
DELETE FROM supply_transactions WHERE supplier_id IN (
    SELECT supplier_id FROM suppliers
    WHERE supplier_code IN ('NCC001','NCC002','NCC003','NCC004','NCC005','NCC006','NCC007','NCC008','NCC009','NCC010'));
DELETE FROM suppliers
    WHERE supplier_code IN ('NCC001','NCC002','NCC003','NCC004','NCC005','NCC006','NCC007','NCC008','NCC009','NCC010');
DELETE FROM users WHERE username IN ('demo_staff', 'demo_off');

-- ---------- 2) Danh mục (bỏ qua nếu tên đã có) ----------
INSERT IGNORE INTO supplier_categories (category_name, description) VALUES
('Nguyên vật liệu', 'Thép, nhựa, giấy và các nguyên liệu sản xuất'),
('Thiết bị điện tử', 'Linh kiện, thiết bị điện và điện tử'),
('Văn phòng phẩm', 'Giấy, bút và dụng cụ văn phòng'),
('Vận chuyển', 'Vận tải và chuyển phát hàng hóa'),
('Thực phẩm', 'Thực phẩm và nông sản');

-- ---------- 3) Nhà cung cấp ----------
-- 2 nhà cung cấp để trống mã số thuế (NULL); 3 trạng thái; 1 tên có "&"; 1 tên có chữ "Điện".
INSERT INTO suppliers (supplier_code, category_id, supplier_name, tax_code, email, phone, address, logo_url, credit_limit, status) VALUES
('NCC001', (SELECT category_id FROM supplier_categories WHERE category_name = 'Nguyên vật liệu'), 'Công ty TNHH Thép Việt Phát', '0101234567', 'kinhdoanh@thepvietphat.example.com', '0243123456', 'KCN Quang Minh, Mê Linh, Hà Nội', 'uploads/logos/ncc001.png', 500000000, 'active'),
('NCC002', (SELECT category_id FROM supplier_categories WHERE category_name = 'Thiết bị điện tử'), 'Công ty CP Điện tử Hoàng Long', '0312345678', 'sales@hoanglong.example.com', '0283456789', '25 Nguyễn Thị Minh Khai, Quận 1, TP. Hồ Chí Minh', 'uploads/logos/ncc002.png', 300000000, 'active'),
('NCC003', (SELECT category_id FROM supplier_categories WHERE category_name = 'Văn phòng phẩm'), 'Văn phòng phẩm Bình Minh & Đối tác', '0309876543', 'binhminh@vpp.example.com', '0905123456', '12 Lê Lợi, Hải Châu, Đà Nẵng', 'uploads/logos/ncc003.png', 80000000, 'active'),
('NCC004', (SELECT category_id FROM supplier_categories WHERE category_name = 'Vận chuyển'), 'Vận tải Nam Á', NULL, 'dieuphoi@namavantai.example.com', '0912345678', '88 Quốc lộ 1A, Thủ Đức, TP. Hồ Chí Minh', 'uploads/logos/ncc004.png', 100000000, 'active'),
('NCC005', (SELECT category_id FROM supplier_categories WHERE category_name = 'Thực phẩm'), 'Công ty CP Thực phẩm Sạch Xanh', '0401122334', 'info@sachxanh.example.com', '0236345678', '5 Trần Phú, Hải Châu, Đà Nẵng', 'uploads/logos/ncc005.png', 200000000, 'active'),
('NCC006', (SELECT category_id FROM supplier_categories WHERE category_name = 'Nguyên vật liệu'), 'Nhựa Đông Á', '0108899776', 'nhuadonga@example.com', '0225123789', 'KCN Đình Vũ, Hải Phòng', 'uploads/logos/ncc006.png', 150000000, 'inactive'),
('NCC007', (SELECT category_id FROM supplier_categories WHERE category_name = 'Thiết bị điện tử'), 'Linh kiện Phương Nam', '0314455667-001', 'lienhe@phuongnam.example.com', '0987654321', '101 Nguyễn Văn Linh, Quận 7, TP. Hồ Chí Minh', 'uploads/logos/ncc007.png', 50000000, 'blacklisted'),
('NCC008', (SELECT category_id FROM supplier_categories WHERE category_name = 'Vận chuyển'), 'Chuyển phát nhanh Tia Chớp', '0102233445', 'cskh@tiachop.example.com', '0933456789', '3 Phạm Hùng, Nam Từ Liêm, Hà Nội', 'uploads/logos/ncc008.png', 40000000, 'active'),
('NCC009', (SELECT category_id FROM supplier_categories WHERE category_name = 'Văn phòng phẩm'), 'Giấy Hải Phòng', '0200112233', 'ban@giayhaiphong.example.com', '0225345678', '27 Lạch Tray, Ngô Quyền, Hải Phòng', 'uploads/logos/ncc009.png', 80000000, 'active'),
('NCC010', (SELECT category_id FROM supplier_categories WHERE category_name = 'Thực phẩm'), 'Nông sản Tây Nguyên', NULL, 'nongsan@taynguyen.example.com', '0262123456', '9 Phan Chu Trinh, Buôn Ma Thuột, Đắk Lắk', 'uploads/logos/ncc010.png', 120000000, 'active');

-- ---------- 4) Người liên hệ (mỗi nhà cung cấp đúng 1 người chính) ----------
INSERT INTO supplier_contacts (supplier_id, full_name, position, email, phone, is_primary) VALUES
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), 'Nguyễn Văn An', 'Giám đốc kinh doanh', 'an.nguyen@thepvietphat.example.com', '0912000001', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), 'Trần Thị Bình', 'Kế toán trưởng', 'binh.tran@thepvietphat.example.com', '0912000002', 0),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC002'), 'Lê Hoàng Long', 'Trưởng phòng bán hàng', 'long.le@hoanglong.example.com', '0912000003', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC002'), 'Phạm Quốc Huy', 'Kỹ sư hỗ trợ', 'huy.pham@hoanglong.example.com', '0912000004', 0),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC003'), 'Võ Thị Cúc', 'Quản lý đơn hàng', 'cuc.vo@vpp.example.com', '0912000005', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC003'), 'Đỗ Minh Đức', 'Nhân viên giao hàng', NULL, '0912000006', 0),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC004'), 'Hoàng Văn Em', 'Điều phối viên', 'em.hoang@namavantai.example.com', '0912000007', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC005'), 'Ngô Thị Phương', 'Giám đốc thương mại', 'phuong.ngo@sachxanh.example.com', '0912000008', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC006'), 'Bùi Văn Giang', 'Trưởng phòng kinh doanh', 'giang.bui@example.com', '0912000009', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC007'), 'Đặng Thị Hoa', 'Chủ cửa hàng', NULL, '0912000010', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC008'), 'Lý Văn Khoa', 'Quản lý khách hàng', 'khoa.ly@tiachop.example.com', '0912000011', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC009'), 'Phan Thị Lan', 'Nhân viên kinh doanh', 'lan.phan@giayhaiphong.example.com', '0912000012', 1),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC009'), 'Trịnh Văn Mạnh', 'Kế toán', NULL, '0912000013', 0),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC010'), 'Mai Thị Nga', 'Chủ trang trại', 'nga.mai@taynguyen.example.com', '0912000014', 1);

-- ---------- 5) Giao dịch ----------
-- Người tạo giao dịch và người đánh giá là tài khoản admin đang hoạt động đầu tiên.
SET @admin_id = (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id LIMIT 1);

-- Ngày tính theo hôm nay để hóa đơn "quá hạn" luôn đúng dù bạn chạy lúc nào.
-- NCC008 và NCC010 cố ý không có giao dịch; HD-2026-007 không có hạn thanh toán; HD-2026-008 hạn đúng hôm nay.
INSERT INTO supply_transactions (supplier_id, invoice_number, transaction_date, due_date, total_amount, paid_amount, created_by) VALUES
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), 'HD-2026-001', CURDATE() - INTERVAL 90 DAY, CURDATE() - INTERVAL 60 DAY, 120000000, 120000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), 'HD-2026-002', CURDATE() - INTERVAL 45 DAY, CURDATE() - INTERVAL 15 DAY, 80000000, 30000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), 'HD-2026-003', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 60000000, 0, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC002'), 'HD-2026-004', CURDATE() - INTERVAL 70 DAY, CURDATE() - INTERVAL 40 DAY, 45000000, 45000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC002'), 'HD-2026-005', CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 10 DAY, 90000000, 40000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC003'), 'HD-2026-006', CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 5 DAY, 12500000, 0, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC003'), 'HD-2026-007', CURDATE() - INTERVAL 10 DAY, NULL, 7300000, 0, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC004'), 'HD-2026-008', CURDATE() - INTERVAL 30 DAY, CURDATE(), 25000000, 10000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC005'), 'HD-2026-009', CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 30 DAY, 33000000, 33000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC006'), 'HD-2026-010', CURDATE() - INTERVAL 120 DAY, CURDATE() - INTERVAL 90 DAY, 18000000, 18000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC007'), 'HD-2026-011', CURDATE() - INTERVAL 80 DAY, CURDATE() - INTERVAL 50 DAY, 22000000, 5000000, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC009'), 'HD-2026-012', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 15 DAY, 9800000, 0, @admin_id);

-- ---------- 6) Đánh giá ----------
INSERT INTO supplier_evaluations (supplier_id, evaluation_date, quality_score, delivery_score, service_score, comment, evaluated_by) VALUES
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), CURDATE() - INTERVAL 80 DAY, 5, 4, 5, 'Giao đúng hẹn, chất lượng thép ổn định.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC001'), CURDATE() - INTERVAL 20 DAY, 4, 4, 4, 'Ổn định, hỗ trợ kịp thời.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC002'), CURDATE() - INTERVAL 60 DAY, 4, 3, 4, 'Linh kiện đạt chuẩn nhưng giao hơi chậm.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC002'), CURDATE() - INTERVAL 10 DAY, 5, 4, 5, NULL, @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC003'), CURDATE() - INTERVAL 30 DAY, 3, 3, 4, 'Giá tốt, đôi lúc thiếu hàng.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC004'), CURDATE() - INTERVAL 40 DAY, 4, 5, 4, 'Vận chuyển nhanh.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC005'), CURDATE() - INTERVAL 50 DAY, 5, 5, 5, 'Thực phẩm tươi, đóng gói tốt.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC006'), CURDATE() - INTERVAL 100 DAY, 3, 2, 3, 'Giao trễ nhiều lần.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC007'), CURDATE() - INTERVAL 70 DAY, 1, 2, 1, 'Giao thiếu hàng nhiều lần, đã đưa vào danh sách đen.', @admin_id),
((SELECT supplier_id FROM suppliers WHERE supplier_code = 'NCC009'), CURDATE() - INTERVAL 12 DAY, 4, 4, 3, NULL, @admin_id);

-- avg_rating là cột lưu tạm: đặt bằng trung bình overall_score (0 nếu chưa có đánh giá)
UPDATE suppliers s SET s.avg_rating = COALESCE(
    (SELECT ROUND(AVG(e.overall_score), 2) FROM supplier_evaluations e WHERE e.supplier_id = s.supplier_id), 0);

-- ---------- 7) Hai tài khoản thử cho bài kiểm thử đăng nhập (mật khẩu: Demo@123) ----------
-- demo_staff có role staff, demo_off là admin nhưng đã bị khóa: cả hai KHÔNG được đăng nhập vào khu quản trị.
INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES
('demo_staff', '$2y$10$5vtZJxG2hb2O6Wnf7BbQxuxdsjGV6QM5CSf45fDghs2izBF42ZKAC', 'Nhân viên thử (demo)', 'staff', 1),
('demo_off', '$2y$10$5vtZJxG2hb2O6Wnf7BbQxuxdsjGV6QM5CSf45fDghs2izBF42ZKAC', 'Admin đã khóa (demo)', 'admin', 0);

-- Xem nhanh kết quả
SELECT 'suppliers' AS bang, COUNT(*) AS so_dong FROM suppliers
UNION ALL SELECT 'supplier_contacts', COUNT(*) FROM supplier_contacts
UNION ALL SELECT 'supply_transactions', COUNT(*) FROM supply_transactions
UNION ALL SELECT 'supplier_evaluations', COUNT(*) FROM supplier_evaluations
UNION ALL SELECT 'users', COUNT(*) FROM users;

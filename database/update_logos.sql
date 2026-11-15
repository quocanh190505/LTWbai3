-- Gán logo cho 10 nhà cung cấp mẫu.
-- Cách chạy: mở HeidiSQL, chọn database supplier_management, mở tab Truy vấn, dán file này rồi bấm Chạy (F9).
-- Các file ảnh đã nằm sẵn trong thư mục uploads/logos/ của project (ncc001.png ... ncc010.png).
-- Chỉ gán cho nhà cung cấp chưa có logo, nên không ghi đè logo bạn đã tự tải lên.
UPDATE suppliers
SET logo_url = CONCAT('uploads/logos/', LOWER(supplier_code), '.png')
WHERE supplier_code IN ('NCC001', 'NCC002', 'NCC003', 'NCC004', 'NCC005',
                        'NCC006', 'NCC007', 'NCC008', 'NCC009', 'NCC010')
  AND logo_url IS NULL;

-- Kiểm tra: phải thấy 10 dòng, cột logo_url có đường dẫn.
SELECT supplier_code, supplier_name, logo_url FROM suppliers ORDER BY supplier_code;

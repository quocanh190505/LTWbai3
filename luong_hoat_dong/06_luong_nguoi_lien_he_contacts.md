# 06. LUỒNG NGƯỜI LIÊN HỆ CỦA NHÀ CUNG CẤP (CONTACTS)

**File thực thi**: `modules/contacts/add.php`, `edit.php`, `delete.php`, `primary.php`  
**Giao diện bấm nút**: trang chi tiết nhà cung cấp (`templates/assets/layouts/supplier-detail.php`)  
**Quy tắc nghiệp vụ**: mỗi nhà cung cấp có **tối đa một** người liên hệ chính; người đầu tiên được thêm tự là người chính.  
**Có redirect?**: **Có (PRG)**, luôn quay về `?module=suppliers&action=detail&id=<mã nhà cung cấp>`.

---

## 1. CÁC NÚT TRÊN TRANG CHI TIẾT

| Nút | Dòng (`supplier-detail.php`) | Bản chất | Action |
| :-- | :--: | :-- | :-- |
| **Thêm liên hệ** | 58 | Liên kết GET kèm `supplier_id` | `contacts/add` |
| **Đặt làm chính** | 77-79 | Form POST (ô ẩn `contact_id`) | `contacts/primary` |
| **Sửa** | 82 | Liên kết GET kèm `id` | `contacts/edit` |
| **Xóa** | 83-86 | Form POST + `confirm()` | `contacts/delete` |

---

## 2. SƠ ĐỒ: THÊM NGƯỜI LIÊN HỆ

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant Add as contacts/add.php
    participant DB as MySQL
    participant Sess as $_SESSION

    Admin->>Add: Bấm "Thêm liên hệ" → GET ?module=contacts&action=add&supplier_id=5
    Note over Add: Dòng 11-16: getInt('supplier_id') rồi SELECT nhà cung cấp; không có thì về danh sách
    Add-->>Admin: Hiện form (họ tên, chức vụ, email, điện thoại, tick "người chính")
    Admin->>Add: Bấm "Lưu" → POST tới ...&supplier_id=5
    Add->>Add: Dòng 21: validateContactForm($filter)
    alt Có lỗi
        Add->>Sess: Dòng 36: setFormFlash(...)
        Add-->>Admin: Dòng 37: redirect về form
    else Hợp lệ
        Add->>DB: Dòng 25-32: transaction(...)
        Note over DB: 1) đếm số liên hệ đang có<br/>2) $isPrimary = tick HOẶC chưa có ai<br/>3) nếu $isPrimary: UPDATE ... is_primary = 0 (bỏ cờ người cũ)<br/>4) INSERT dòng mới
        Add->>Sess: setMsg('Đã thêm người liên hệ')
        Add-->>Admin: Dòng 34: redirect về chi tiết nhà cung cấp
    end
```

---

## 3. DIỄN GIẢI

### 3.1. Vì sao `supplier_id` đọc bằng `getInt()` chứ không phải `postInt()`?
Form có `action="...?module=contacts&action=add&supplier_id=5"`: mã nhà cung cấp nằm **trên địa chỉ** nên với cả GET (mở form) lẫn POST (lưu) đều phải đọc từ `$_GET`. Nếu dùng `postInt()` thì bước POST luôn nhận 0 và bị đuổi về danh sách (lỗi này đã gặp và sửa khi chạy thử). Ngược lại, `primary.php` và `delete.php` có ô ẩn `contact_id` **trong thân form** nên dùng `postInt('contact_id')`.

### 3.2. Vì sao dùng `transaction()` (add.php dòng 25-32)?
Hai việc "bỏ cờ chính của người cũ" và "ghi người mới là chính" phải xảy ra **cùng nhau**. Nếu chỉ thực hiện được nửa đầu (mất điện, lỗi) thì nhà cung cấp mất người chính. `transaction()` gọi `beginTransaction` → chạy → `commit`; có ngoại lệ thì `rollBack` hoàn tác tất cả. Nhờ đó thao tác không bao giờ bị dở dang (không có chuyện đã bỏ cờ người cũ mà chưa ghi người mới).

### 3.3. Đặt làm chính (`primary.php`)
```php
$row = getOne('SELECT supplier_id FROM supplier_contacts WHERE contact_id = :id', ...);
transaction(function () use ($id, $supplierId) {
    execute('UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = :sid', ['sid' => $supplierId]);
    execute('UPDATE supplier_contacts SET is_primary = 1 WHERE contact_id = :id', ['id' => $id]);
});
```
Mã nhà cung cấp lấy **từ database** theo `contact_id` (không tin dữ liệu form gửi lên), nên không thể đổi cờ của nhà cung cấp khác.

### 3.4. Xóa (`delete.php`)
Xóa dòng rồi `redirect` về chi tiết. Xóa đúng người chính thì tạm thời chưa có người chính; bấm "Đặt làm chính" ở người khác để có lại. Khách (trang `home/detail`) chỉ thấy người liên hệ chính (`loadSupplierDetail(..., false)` thêm `AND is_primary = 1`).

---

## 4. CÂU HỎI VẤN ĐÁP

> **Hỏi 1**: *"Làm sao đảm bảo mỗi nhà cung cấp chỉ có một người chính?"*  
> **Đáp**: *"Mọi thay đổi cờ `is_primary` đều nằm trong `transaction()`: trước khi đặt một người là chính thì bỏ cờ của tất cả người khác cùng nhà cung cấp, rồi mới đặt cờ cho người này, nên thao tác bình thường luôn để lại đúng một người chính. Đây là quy tắc do PHP giữ, database không có ràng buộc riêng cho việc này; nếu muốn chặt hơn nữa thì có thể thêm khóa dòng `FOR UPDATE` như ở phần thanh toán."*

> **Hỏi 2**: *"Nếu admin sửa `contact_id` trong ô ẩn thành số của nhà cung cấp khác?"*  
> **Đáp**: *"Không gây hại ngoài việc tác động đúng người liên hệ đó, vì admin vốn có quyền với mọi nhà cung cấp. Quan trọng là server luôn lấy `supplier_id` từ database theo `contact_id` thay vì tin giá trị gửi lên."*

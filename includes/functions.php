<?php
// functions.php – các hàm dùng chung cho mọi trang (được nạp qua config.php).
// Chia thành các mục:  1. Dữ liệu form  2. Database  3. Đăng nhập  4. Thông báo  5. Kiểm tra định dạng
//                      6. Hiển thị (tiền, ngày, nhãn, phân trang)  7. Tải file  8. Truy vấn nhà cung cấp  9. Hạng đối tác và quy tắc hóa đơn
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}

// =====================================================================
// 1. DỮ LIỆU FORM
// =====================================================================

// Làm sạch một giá trị người dùng gửi lên (giống test_input() của W3Schools).
// Ở đây chỉ cắt khoảng trắng hai đầu; việc chống chèn mã HTML làm lúc IN RA bằng h() (xem bên dưới),
// nhờ vậy dữ liệu lưu vào database luôn là chữ gốc người dùng gõ.
function test_input($data)
{
    if (!is_string($data)) {
        return "";   // ô gửi lên dạng mảng (kiểu tấn công) thì coi như rỗng
    }
    return trim($data);
}

// Thoát ký tự đặc biệt của HTML khi in dữ liệu ra trang (chống chèn mã). Dùng:  echo h($name);
function h($value)
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Số nguyên dương từ một chuỗi (id trên địa chỉ hoặc trong ô ẩn); không hợp lệ thì trả 0
function toInt($value)
{
    return is_string($value) && ctype_digit($value) ? (int) $value : 0;
}

// Một giá trị chuỗi trên địa chỉ ($_GET), đã cắt khoảng trắng; không có thì ""
function getParam($key)
{
    return test_input($_GET[$key] ?? "");
}

// Số nguyên dương trên địa chỉ ($_GET), ví dụ id, page; không hợp lệ thì 0
function getInt($key)
{
    return toInt($_GET[$key] ?? "");
}

// Chuỗi rỗng -> null, để ô bỏ trống được lưu là NULL trong database (không lưu chuỗi rỗng)
function nullIfEmpty($value)
{
    return $value === "" ? null : $value;
}

// " is-invalid" (viền đỏ của Bootstrap) nếu ô đó có lỗi
function invalid($error)
{
    return $error !== "" ? " is-invalid" : "";
}

// " selected" nếu hai giá trị bằng nhau (so như chuỗi), dùng trong <option>
function selected($current, $value)
{
    return (string) $current === (string) $value ? " selected" : "";
}

// Dựng phần "?a=1&b=2" từ mảng tham số, bỏ tham số rỗng. Dùng cho liên kết giữ nguyên bộ lọc.
function qs($params)
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== "" && $v !== false && $v !== 0);
    return $params ? "?" . http_build_query($params) : "";
}

// =====================================================================
// 2. DATABASE (bọc PDO, luôn dùng prepared statement)
// Giá trị truyền qua mảng $params, không bao giờ nối thẳng dữ liệu người dùng vào câu SQL:
//   getOne("SELECT * FROM users WHERE user_id = :id", ["id" => 5]);
// Không dùng cùng một tên tham số hai lần trong một câu SQL.
// =====================================================================

// Chạy một câu SQL, trả về statement đã thực thi
function runQuery($sql, $params = [])
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Nhiều dòng: mảng các dòng (mảng rỗng nếu không có)
function getAll($sql, $params = [])
{
    return runQuery($sql, $params)->fetchAll();
}

// Một dòng, hoặc null nếu không có
function getOne($sql, $params = [])
{
    $row = runQuery($sql, $params)->fetch();
    return $row === false ? null : $row;
}

// Một giá trị (cột đầu của dòng đầu), hoặc null nếu không có dòng. Hay dùng cho COUNT(*), SUM(...)
function getValue($sql, $params = [])
{
    $value = runQuery($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

// INSERT, UPDATE, DELETE: trả về số dòng bị ảnh hưởng
function execute($sql, $params = [])
{
    return runQuery($sql, $params)->rowCount();
}

// Mã tự tăng của dòng vừa INSERT
function lastInsertId()
{
    global $conn;
    return (int) $conn->lastInsertId();
}

// Chạy $fn trong một giao dịch: thành công thì commit và trả về kết quả của $fn,
// có lỗi thì rollback (hoàn tác) rồi ném lỗi đó ra tiếp
function transaction($fn)
{
    global $conn;
    $conn->beginTransaction();
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

// Lỗi trùng giá trị ở cột UNIQUE (MySQL báo mã 1062)
function isDuplicateError($e)
{
    return $e instanceof PDOException && ($e->errorInfo[1] ?? null) == 1062;
}

// Lỗi xóa dòng còn được bảng khác tham chiếu qua khóa ngoại (MySQL báo mã 1451)
function isForeignKeyError($e)
{
    return $e instanceof PDOException && ($e->errorInfo[1] ?? null) == 1451;
}

// Danh sách rút gọn mọi nhà cung cấp để đổ vào ô chọn (select) trong các form và bộ lọc
function supplierOptions()
{
    return getAll("SELECT supplier_id, supplier_code, supplier_name FROM suppliers ORDER BY supplier_code");
}

// Danh sách danh mục để đổ vào ô chọn
function categoryOptions()
{
    return getAll("SELECT category_id, category_name FROM supplier_categories ORDER BY category_name");
}

// Đặt avg_rating của nhà cung cấp = trung bình overall_score của mọi đánh giá (làm tròn 2 chữ số; chưa có đánh giá thì 0).
// Cột avg_rating là cột "lưu tạm" để danh sách khỏi tính lại; gọi hàm này mỗi lần thêm, sửa hoặc xóa một đánh giá.
function recalcAvgRating($supplierId)
{
    execute(
        "UPDATE suppliers
         SET avg_rating = COALESCE((SELECT ROUND(AVG(overall_score), 2) FROM supplier_evaluations WHERE supplier_id = :sid), 0)
         WHERE supplier_id = :id",
        ["sid" => $supplierId, "id" => $supplierId]
    );
}

// =====================================================================
// 3. ĐĂNG NHẬP (chỉ admin)
// =====================================================================

// Người đang đăng nhập (user_id, username, full_name, role) hoặc null. Đọc lại từ database mỗi yêu cầu,
// nên tài khoản vừa bị khóa hoặc hạ quyền mất quyền ngay.
function currentUser()
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $userId = $_SESSION["user_id"] ?? null;
    if (is_int($userId)) {
        $row = getOne("SELECT user_id, username, full_name, role, is_active FROM users WHERE user_id = :id", ["id" => $userId]);
        if ($row !== null && $row["role"] === "admin" && (int) $row["is_active"] === 1) {
            $row["user_id"] = (int) $row["user_id"];
            $user = $row;
        } else {
            unset($_SESSION["user_id"]);
        }
    }
    return $user;
}

function isLogin()
{
    return currentUser() !== null;
}

// Đặt ở đầu mọi trang dành cho admin: chưa đăng nhập thì chuyển về trang đăng nhập rồi dừng.
// Trả về thông tin người đang đăng nhập.
function requireLogin()
{
    $user = currentUser();
    if ($user === null) {
        header("Location: login.php?msg=need_login");
        exit;
    }
    return $user;
}

// Kiểm tra tên đăng nhập + mật khẩu. Chỉ tài khoản role "admin" đang hoạt động mới vào được.
// Mọi lý do thất bại đều trả false (để thông báo không tiết lộ tài khoản có tồn tại hay không).
function loginAdmin($username, $password)
{
    $row = getOne("SELECT user_id, password_hash, role, is_active FROM users WHERE username = :username", ["username" => $username]);
    // Không có tài khoản vẫn kiểm tra với một mã băm giả để thời gian trả lời không lộ tên đăng nhập có thật hay không
    $hash = $row["password_hash"] ?? '$2y$10$5vtZJxG2hb2O6Wnf7BbQxuxdsjGV6QM5CSf45fDghs2izBF42ZKAC';
    $match = password_verify($password, $hash);
    if ($row === null || !$match || $row["role"] !== "admin" || (int) $row["is_active"] !== 1) {
        return false;
    }
    session_regenerate_id(true);   // đổi mã phiên sau khi đăng nhập để chống chiếm phiên
    $_SESSION["user_id"] = (int) $row["user_id"];
    return true;
}

// =====================================================================
// 4. THÔNG BÁO SAU KHI LƯU
// Trang xử lý form xong thì chuyển về danh sách kèm ?msg=<mã>, ví dụ  header("Location: suppliers.php?msg=supplier_added");
// Trang danh sách gọi showMessage() để hiện khung thông báo. Mã chưa có trong bảng thì không hiện gì (không thể chèn chữ lạ).
// %1$d và %2$d là số lượng truyền thêm qua ?n= và ?m= (ví dụ số giao dịch khiến không xóa được).
// =====================================================================
function showMessage()
{
    $messages = [
        "need_login"       => ["warning", "Vui lòng đăng nhập để tiếp tục"],
        "login_ok"         => ["success", "Đăng nhập thành công"],
        "logged_out"       => ["success", "Đã đăng xuất"],
        "password_changed" => ["success", "Đã đổi mật khẩu"],
        "not_found"        => ["danger",  "Không tìm thấy dữ liệu yêu cầu"],
        "in_use"           => ["danger",  "Không xóa được vì dữ liệu này vừa được dùng ở nơi khác"],

        "supplier_added"   => ["success", "Đã thêm nhà cung cấp"],
        "supplier_updated" => ["success", "Đã cập nhật nhà cung cấp"],
        "supplier_deleted" => ["success", "Đã xóa nhà cung cấp"],
        "supplier_has_transactions" => ["danger", 'Nhà cung cấp đã có %1$d giao dịch nên không thể xóa. Hãy chuyển sang "ngừng hoạt động".'],

        "contact_added"    => ["success", "Đã thêm người liên hệ"],
        "contact_updated"  => ["success", "Đã cập nhật người liên hệ"],
        "contact_deleted"  => ["success", "Đã xóa người liên hệ"],
        "contact_primary"  => ["success", "Đã đặt làm liên hệ chính"],

        "transaction_added"   => ["success", "Đã thêm giao dịch"],
        "transaction_updated" => ["success", "Đã cập nhật giao dịch"],
        "transaction_deleted" => ["success", "Đã xóa giao dịch"],
        "transaction_paid"    => ["success", "Đã ghi nhận thanh toán"],
        "invoice_none"        => ["danger",  "Giao dịch này chưa có hóa đơn đính kèm"],
        "invoice_missing"     => ["danger",  "Không tìm thấy tệp hóa đơn"],

        "evaluation_added"   => ["success", "Đã thêm đánh giá"],
        "evaluation_updated" => ["success", "Đã cập nhật đánh giá"],
        "evaluation_deleted" => ["success", "Đã xóa đánh giá"],

        "category_added"   => ["success", "Đã thêm danh mục"],
        "category_updated" => ["success", "Đã cập nhật danh mục"],
        "category_deleted" => ["success", "Đã xóa danh mục"],
        "category_has_suppliers" => ["danger", "Danh mục đang có %1\$d nhà cung cấp nên không thể xóa. Hãy chuyển các nhà cung cấp đó sang danh mục khác trước."],

        "user_added"       => ["success", "Đã thêm tài khoản"],
        "user_updated"     => ["success", "Đã cập nhật tài khoản"],
        "user_deleted"     => ["success", "Đã xóa tài khoản"],
        "user_delete_self" => ["danger",  "Bạn không thể tự xóa tài khoản của chính mình"],
        "user_in_use"      => ["danger",  'Tài khoản đã tạo %1$d giao dịch và %2$d đánh giá nên không thể xóa (để giữ sổ sách). Hãy khóa tài khoản thay vì xóa.'],
    ];
    $code = getParam("msg");
    if (!isset($messages[$code])) {
        return "";
    }
    [$type, $text] = $messages[$code];
    $text = sprintf($text, getInt("n"), getInt("m"));
    return '<div class="alert alert-' . $type . '" role="alert">' . h($text) . "</div>";
}

// =====================================================================
// 5. KIỂM TRA ĐỊNH DẠNG DỮ LIỆU NHẬP
// Dùng \z (hết chuỗi thật sự) thay vì $ vì $ trong regex còn chấp nhận một ký tự xuống dòng ở cuối.
// =====================================================================

// Mã nhà cung cấp: 3–20 ký tự gồm chữ HOA, số, dấu - hoặc _
function valid_supplier_code($value)
{
    return preg_match('/^[A-Z0-9_-]{3,20}\z/', $value) === 1;
}

// Mã số thuế: 10 chữ số, hoặc 10 chữ số kèm dấu - và 3 chữ số (chi nhánh)
function valid_tax_code($value)
{
    return preg_match('/^\d{10}(-\d{3})?\z/', $value) === 1;
}

// Số điện thoại Việt Nam: bắt đầu bằng 0 hoặc +84, sau đó 9–10 chữ số
function valid_phone($value)
{
    return preg_match('/^(\+84|0)\d{9,10}\z/', $value) === 1;
}

// Ngày dạng YYYY-MM-DD và là ngày có thật (không nhận 2026-02-30)
function valid_date($value)
{
    $date = DateTime::createFromFormat("!Y-m-d", $value);
    return $date !== false && $date->format("Y-m-d") === $value;
}

// Số tiền không âm: tối đa 13 chữ số nguyên và 2 chữ số thập phân (vừa cột DECIMAL(15,2))
function valid_money($value)
{
    return preg_match('/^\d{1,13}(\.\d{1,2})?\z/', $value) === 1;
}

// Tên đăng nhập: 3–50 ký tự gồm chữ, số, dấu chấm, gạch ngang hoặc gạch dưới
function valid_username($value)
{
    return preg_match('/^[A-Za-z0-9_.-]{3,50}\z/', $value) === 1;
}

// Đổi số tiền dạng chuỗi sang số nguyên "đồng lẻ" (xu) để so sánh và cộng mà không bị sai số của số thực.
// Chỉ gọi sau khi valid_money() đúng. Ví dụ "12.5" -> 1250.
function money_cents($value)
{
    [$whole, $fraction] = array_pad(explode(".", $value, 2), 2, "");
    return (int) $whole * 100 + (int) str_pad($fraction, 2, "0");
}

// Số tiền dạng chuỗi 2 chữ số thập phân để ghi vào cột DECIMAL: 1234.5 -> "1234.50"
function money($value)
{
    return number_format((float) $value, 2, ".", "");
}

// =====================================================================
// 6. HIỂN THỊ: tiền, ngày, nhãn, logo, phân trang
// =====================================================================

// Số tiền kiểu Việt Nam: 1234567.5 -> "1.234.567,5 ₫"
function formatMoney($amount)
{
    $number = is_numeric($amount) ? (float) $amount : 0.0;
    $text = number_format($number, 2, ",", ".");
    $text = rtrim(rtrim($text, "0"), ",");
    return $text . " ₫";
}

// Ngày 2026-10-15 -> "15/10/2026"; rỗng -> chuỗi rỗng
function formatDate($iso)
{
    if ($iso !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
        return $m[3] . "/" . $m[2] . "/" . $m[1];
    }
    return "";
}

// Chữ (đã thoát HTML) hoặc dấu "—" mờ khi rỗng
function textOrDash($value)
{
    return $value === null || $value === "" ? '<span class="text-muted">—</span>' : h($value);
}

// 5 ngôi sao tô theo điểm kèm điểm số; chưa có đánh giá thì dấu "—"
function ratingHtml($value, $digits = 2)
{
    $score = (float) $value;
    if (!($score > 0)) {
        return '<span class="text-muted">—</span>';
    }
    $pct = min(100, max(0, $score / 5 * 100));
    return '<span class="stars" style="--pct:' . number_format($pct, 0, ".", "") . '%" aria-hidden="true">★★★★★</span>'
        . '<span class="rating-num">' . number_format($score, $digits, ".", "") . '</span><span class="visually-hidden"> trên 5 sao</span>';
}

// Logo nhà cung cấp: có ảnh thì hiện ảnh; chưa có thì hiện chữ cái đầu trên nền màu nhạt (6 màu xoay vòng theo mã)
function logoHtml($supplier, $big = false)
{
    $cls = $big ? " logo-big" : "";
    if (!empty($supplier["logo_url"])) {
        return '<img class="logo-thumb' . $cls . '" src="' . h(ROOT . $supplier["logo_url"]) . '" alt="">';
    }
    $name = trim((string) ($supplier["supplier_name"] ?? "?"));
    $initial = mb_strtoupper(mb_substr($name === "" ? "?" : $name, 0, 1));
    $tone = " tone-" . ((int) ($supplier["supplier_id"] ?? 0) % 6);
    return '<span class="logo-placeholder' . $cls . $tone . '" aria-hidden="true">' . h($initial) . "</span>";
}

// Chữ viết tắt trong ô tròn của người dùng: chữ đầu của hai từ cuối trong họ tên ("Đoàn Quốc Anh" -> "QA")
function nameInitials($fullName)
{
    $words = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = "";
    foreach (array_slice($words, -2) as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return $initials !== "" ? $initials : "?";
}

// Tiêu đề trang: h1 + dòng mô tả + (tùy chọn) một nút "Thêm ..." bên phải
function pageHeader($title, $sub = "", $buttonText = "", $buttonHref = "")
{
    echo '<div class="page-header"><div><h1>' . h($title) . "</h1>";
    if ($sub !== "") {
        echo '<p class="page-sub">' . h($sub) . "</p>";
    }
    echo "</div>";
    if ($buttonText !== "" && $buttonHref !== "") {
        echo '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="' . h($buttonHref) . '">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> '
            . h($buttonText) . "</a>";
    }
    echo "</div>";
}

// Tên và màu nhãn của từng trạng thái nhà cung cấp
const STATUS_LABELS = ["active" => "Hoạt động", "inactive" => "Ngừng hoạt động", "blacklisted" => "Danh sách đen"];
const STATUS_CSS = ["active" => "badge-soft-success", "inactive" => "badge-soft-secondary", "blacklisted" => "badge-soft-danger"];

function statusBadge($status)
{
    if (!isset(STATUS_LABELS[$status])) {
        return '<span class="badge badge-soft badge-soft-secondary">' . h($status) . "</span>";
    }
    return '<span class="badge badge-soft ' . STATUS_CSS[$status] . '">' . STATUS_LABELS[$status] . "</span>";
}

// Nhãn tình trạng thanh toán của hóa đơn
function paymentBadge($status)
{
    $map = [
        "unpaid"  => ["Chưa trả", "badge-soft-danger"],
        "partial" => ["Trả một phần", "badge-soft-warning"],
        "paid"    => ["Đã trả đủ", "badge-soft-success"],
    ];
    [$label, $css] = $map[$status] ?? [$status, "badge-soft-secondary"];
    return '<span class="badge badge-soft ' . $css . '">' . h($label) . "</span>";
}

// Ô "Còn nợ": số tiền, và thanh so với hạn mức tín dụng khi nhà cung cấp còn nợ
function debtCell($s)
{
    $remaining = (float) $s["remaining_amount"];
    $limit = (float) $s["credit_limit"];
    $owes = $remaining > 0;
    $html = '<div class="debt-cell"><span class="money' . ($owes ? " debt" : " text-muted") . '">' . h(formatMoney($remaining)) . "</span>";
    if ($owes && $limit > 0) {
        $pct = (int) round($remaining / $limit * 100);
        $level = $pct >= 100 ? " is-over" : ($pct >= 70 ? " is-high" : "");
        $html .= '<span class="meter' . $level . '" aria-hidden="true"><span style="width:' . min(100, $pct) . '%"></span></span>'
            . '<span class="meter-note">' . $pct . "% hạn mức</span>";
    } elseif ($owes) {
        $html .= '<span class="meter-note">Chưa đặt hạn mức</span>';
    }
    return $html . "</div>";
}

// Ô nhà cung cấp trong bảng: logo + tên + mã (tên là liên kết nếu có $link)
function supplierCell($s, $link = null)
{
    $name = $link === null
        ? '<div class="supplier-name">' . h($s["supplier_name"]) . "</div>"
        : '<a class="supplier-name supplier-link" href="' . h($link) . '">' . h($s["supplier_name"]) . "</a>";
    return '<div class="supplier-cell">' . logoHtml($s) . "<div>" . $name
        . '<div class="supplier-sub">' . h($s["supplier_code"]) . "</div></div></div>";
}

// Thẻ nhà cung cấp trên trang khách: logo lớn, danh mục, sao, điện thoại, email, nút "Xem chi tiết"
function supplierCard($s)
{
    $phone = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
    $mail = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
    $rating = (float) $s["avg_rating"] > 0 ? ratingHtml($s["avg_rating"], 1) : '<span class="text-muted">Chưa có đánh giá</span>';
    return '<article class="supplier-card">'
        . '<div class="sc-head">' . logoHtml($s, true)
        . '<div class="sc-title"><div class="supplier-name">' . h($s["supplier_name"]) . "</div>"
        . '<div class="supplier-sub">' . h($s["supplier_code"]) . "</div></div></div>"
        . '<div class="sc-meta"><span class="chip">' . h($s["category_name"]) . '</span><span class="sc-rating">' . $rating . "</span></div>"
        . '<ul class="sc-contact"><li>' . $phone . '<span class="sc-phone">' . textOrDash($s["phone"]) . "</span></li>"
        . "<li>" . $mail . '<span class="text-truncate">' . textOrDash($s["email"]) . "</span></li></ul>"
        . '<a class="btn btn-sm btn-outline-primary sc-action" href="detail.php?id=' . (int) $s["supplier_id"] . '">Xem chi tiết</a>'
        . "</article>";
}

// Tiêu đề cột bấm để sắp xếp. $current = [khóa đang sắp xếp, chiều]; $params = các tham số lọc cần giữ.
// Bấm cột đang sắp xếp thì đổi chiều; bấm cột khác thì dùng chiều mặc định của cột đó. Luôn quay về trang 1.
function sortHeader($label, $key, $current, $params)
{
    $defaultDir = ["name" => "asc", "rating" => "desc", "debt" => "desc"];
    $isCurrent = $current[0] === $key;
    $dir = $isCurrent ? ($current[1] === "asc" ? "desc" : "asc") : ($defaultDir[$key] ?? "asc");
    return '<a class="sort-btn" href="' . h(qs($params + ["sort" => $key, "dir" => $dir])) . '">' . h($label) . "</a>";
}

// Giá trị aria-sort của <th> cho cột $key
function ariaSort($key, $current)
{
    return $current[0] === $key ? ($current[1] === "asc" ? "ascending" : "descending") : "none";
}

// Số trang tối đa từ tổng số dòng
function lastPageOf($total, $perPage = PER_PAGE)
{
    return max(1, (int) ceil($total / $perPage));
}

// Trang hiện tại (?page=), tối thiểu 1
function currentPage()
{
    return max(1, getInt("page"));
}

// Dòng "Hiển thị 1–10 trong 25 nhà cung cấp"
function resultInfo($page, $perPage, $total, $shown, $noun)
{
    if ($total === 0) {
        return "Không có " . $noun . " nào";
    }
    $from = ($page - 1) * $perPage + 1;
    return "Hiển thị " . $from . "–" . ($from + $shown - 1) . " trong " . $total . " " . $noun;
}

// Các nút phân trang Bootstrap ‹ 1 2 … n ›. $params là các tham số địa chỉ cần giữ (bộ lọc...), không gồm page.
// Luôn hiện trang đầu, trang cuối và 2 trang quanh trang hiện tại; chỗ bỏ qua thay bằng "…".
// Liên kết chỉ có phần "?..." nên luôn trỏ về chính trang đang xem.
function paginationHtml($page, $lastPage, $params = [])
{
    if ($lastPage <= 1) {
        return "";
    }
    $item = function ($label, $target, $disabled, $active, $aria) use ($params) {
        $cls = "page-item" . ($disabled ? " disabled" : "") . ($active ? " active" : "");
        $attr = ($aria ? ' aria-label="' . h($aria) . '"' : "") . ($active ? ' aria-current="page"' : "");
        $inner = $disabled
            ? '<span class="page-link"' . $attr . ">" . $label . "</span>"
            : '<a class="page-link" href="' . h(qs($params + ["page" => $target])) . '"' . $attr . ">" . $label . "</a>";
        return '<li class="' . $cls . '">' . $inner . "</li>";
    };

    $html = $item("‹", $page - 1, $page === 1, false, "Trang trước");
    $previous = 0;
    for ($p = 1; $p <= $lastPage; $p++) {
        if ($p === 1 || $p === $lastPage || abs($p - $page) <= 2) {
            if ($previous && $p - $previous > 1) {
                $html .= $item("…", 0, true, false, null);
            }
            $html .= $item((string) $p, $p, false, $p === $page, "Trang " . $p);
            $previous = $p;
        }
    }
    $html .= $item("›", $page + 1, $page === $lastPage, false, "Trang sau");
    return '<ul class="pagination pagination-sm mb-0">' . $html . "</ul>";
}

// =====================================================================
// 7. TẢI FILE LÊN (chỉ làm việc trong thư mục uploads/)
// $allowedMime là mảng "loại file => đuôi file", ví dụ ["image/png" => "png", "image/jpeg" => "jpg"].
// =====================================================================

const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_TYPES = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
const INVOICE_MAX_BYTES = 5 * 1024 * 1024;
const INVOICE_TYPES = ["application/pdf" => "pdf", "image/jpeg" => "jpg", "image/png" => "png"];

// Kiểm tra một mục của $_FILES mà KHÔNG lưu gì. Trả về thông báo lỗi, hoặc null nếu hợp lệ.
function check_upload($file, $maxBytes, $allowedMime)
{
    $tooBig = "Tệp quá lớn (tối đa " . round($maxBytes / 1048576, 1) . " MB)";
    $error = $file["error"] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return $tooBig;
    }
    if ($error !== UPLOAD_ERR_OK) {
        return "Không tải được tệp lên, vui lòng thử lại";
    }
    if (!is_uploaded_file($file["tmp_name"])) {
        return "Tệp tải lên không hợp lệ";
    }
    if ($file["size"] > $maxBytes) {
        return $tooBig;
    }
    // Loại file lấy từ NỘI DUNG thật, không tin đuôi tên file hay loại do trình duyệt khai báo
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
    if (!isset($allowedMime[$mime])) {
        return "Chỉ nhận tệp " . strtoupper(implode(", ", array_values($allowedMime)));
    }
    if (str_starts_with($mime, "image/") && @getimagesize($file["tmp_name"]) === false) {
        return "Tệp không phải ảnh hợp lệ";
    }
    return null;
}

// Đọc ô tải file tùy chọn $_FILES[$field] và kiểm tra (chưa lưu).
// Trả về [mục $_FILES hợp lệ hoặc null nếu không chọn file, thông báo lỗi hoặc ""].
function read_optional_upload($field, $maxBytes, $allowedMime)
{
    $file = $_FILES[$field] ?? null;
    if ($file === null) {
        return [null, ""];
    }
    if (!is_array($file) || !is_int($file["error"] ?? null)) {
        return [null, "Tệp tải lên không hợp lệ"];
    }
    if ($file["error"] === UPLOAD_ERR_NO_FILE) {
        return [null, ""];
    }
    $problem = check_upload($file, $maxBytes, $allowedMime);
    return $problem === null ? [$file, ""] : [null, $problem];
}

// Lưu file đã kiểm tra vào uploads/<$subdir>/ với tên ngẫu nhiên. Trả về đường dẫn tương đối để lưu vào database
// (ví dụ "uploads/logos/ab12.png"), hoặc null nếu không lưu được.
function save_upload($file, $subdir, $allowedMime)
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
    $dir = __DIR__ . "/uploads/" . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    $name = bin2hex(random_bytes(16)) . "." . $allowedMime[$mime];   // đuôi theo loại thật của file
    if (!move_uploaded_file($file["tmp_name"], $dir . "/" . $name)) {
        return null;
    }
    return "uploads/" . $subdir . "/" . $name;
}

// Xóa file đã lưu. Chỉ xóa file nằm trong uploads/ (chặn đường dẫn kiểu ../)
function delete_upload($relativePath)
{
    if ($relativePath === null || $relativePath === "") {
        return;
    }
    $base = realpath(__DIR__ . "/uploads");
    $full = realpath(__DIR__ . "/" . $relativePath);
    if ($base !== false && $full !== false && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full)) {
        @unlink($full);
    }
}

// =====================================================================
// 8. TRUY VẤN NHÀ CUNG CẤP (dùng chung cho: suppliers.php, supplier_export.php, index.php, supplier_detail.php, detail.php)
// =====================================================================

const SUPPLIER_STATUSES = ["active", "inactive", "blacklisted"];

// Cột được phép sắp xếp: khóa gửi lên => [biểu thức SQL, chiều mặc định, chỉ admin]
const SUPPLIER_SORTS = [
    "code"   => ["s.supplier_code", "asc", false],
    "name"   => ["s.supplier_name", "asc", false],
    "rating" => ["s.avg_rating", "desc", false],
    "debt"   => ["COALESCE(d.remaining, 0)", "desc", true],   // d là bảng tổng nợ ghép vào ở supplierSelectSql (chỉ có với admin)
];

// Phần SELECT dùng chung cho danh sách và chi tiết; admin được lấy thêm cột nội bộ và tổng còn nợ
function supplierSelectSql($isAdmin)
{
    $sql = "SELECT s.supplier_id, s.supplier_code, s.supplier_name, s.category_id, c.category_name,
                   s.phone, s.email, s.address, s.logo_url, s.avg_rating, s.status";
    if ($isAdmin) {
        $sql .= ", s.tax_code, s.credit_limit, COALESCE(d.remaining, 0) AS remaining_amount";
    }
    $sql .= " FROM suppliers s JOIN supplier_categories c ON c.category_id = s.category_id";
    if ($isAdmin) {
        $sql .= " LEFT JOIN (SELECT supplier_id, SUM(remaining_amount) AS remaining
                             FROM supply_transactions GROUP BY supplier_id) d ON d.supplier_id = s.supplier_id";
    }
    return $sql;
}

// Điều kiện lọc từ địa chỉ (?q=&category_id=&status=&rating_min=&has_debt=): trả về [WHERE, tham số]
// Khách chỉ thấy nhà cung cấp "active"; status và has_debt chỉ admin dùng được.
function supplierListFilters($isAdmin)
{
    $where = [];
    $params = [];

    if (!$isAdmin) {
        $where[] = "s.status = 'active'";
    } elseif (in_array(getParam("status"), SUPPLIER_STATUSES, true)) {
        $where[] = "s.status = :status";
        $params["status"] = getParam("status");
    }

    $q = getParam("q");
    if ($q !== "") {
        // Thoát !, % và _ để chúng được tìm như ký tự thường (nếu không, "%" khớp mọi dòng)
        $like = "%" . str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $q) . "%";
        $where[] = "(s.supplier_code LIKE :q1 ESCAPE '!' OR s.supplier_name LIKE :q2 ESCAPE '!')";
        $params["q1"] = $like;
        $params["q2"] = $like;
    }

    $categoryId = getInt("category_id");
    if ($categoryId > 0) {
        $where[] = "s.category_id = :category_id";
        $params["category_id"] = $categoryId;
    }

    // Điểm trung bình từ mức này trở lên; không phải số nguyên 1 đến 5 thì bỏ qua
    $ratingMin = getInt("rating_min");
    if ($ratingMin >= 1 && $ratingMin <= 5) {
        $where[] = "s.avg_rating >= :rating_min";
        $params["rating_min"] = $ratingMin;
    }

    // Chỉ nhà cung cấp còn nợ (khách không có dữ liệu công nợ nên bỏ qua)
    if ($isAdmin && getParam("has_debt") === "1") {
        $where[] = "EXISTS (SELECT 1 FROM supply_transactions x WHERE x.supplier_id = s.supplier_id AND x.remaining_amount > 0)";
    }

    return [$where ? " WHERE " . implode(" AND ", $where) : "", $params];
}

// Cách sắp xếp đang dùng sau khi bỏ giá trị không hợp lệ: [khóa, chiều] (mặc định theo mã)
function supplierSort($isAdmin)
{
    $key = getParam("sort");
    $rule = SUPPLIER_SORTS[$key] ?? null;
    if ($rule === null || ($rule[2] && !$isAdmin)) {
        return ["code", "asc"];
    }
    $dir = strtolower(getParam("dir"));
    return [$key, $dir === "asc" || $dir === "desc" ? $dir : $rule[1]];
}

// ORDER BY của danh sách: chỉ lấy biểu thức trong SUPPLIER_SORTS (không bao giờ lấy chữ người dùng gửi lên),
// luôn thêm mã nhà cung cấp làm khóa phụ để thứ tự ổn định giữa các trang
function supplierOrderSql($isAdmin)
{
    [$key, $dir] = supplierSort($isAdmin);
    return " ORDER BY " . SUPPLIER_SORTS[$key][0] . " " . strtoupper($dir) . ($key === "code" ? "" : ", s.supplier_code");
}

// Các tham số lọc đang có trên địa chỉ, để gắn vào liên kết (phân trang, sắp xếp) mà không mất bộ lọc
function supplierFilterParams($isAdmin)
{
    $params = ["q" => getParam("q"), "category_id" => getInt("category_id") ?: null, "rating_min" => getInt("rating_min") ?: null];
    if ($isAdmin) {
        $params["status"] = in_array(getParam("status"), SUPPLIER_STATUSES, true) ? getParam("status") : null;
        $params["has_debt"] = getParam("has_debt") === "1" ? 1 : null;
    }
    return $params;
}

// Đọc đầy đủ một nhà cung cấp để xem chi tiết: trả về null nếu không có.
// Khách chỉ xem được nhà cung cấp đang hoạt động, chỉ nhận người liên hệ chính và không nhận dữ liệu nội bộ
// (mã số thuế, hạn mức, công nợ). Admin nhận tất cả.
function loadSupplierDetail($id, $isAdmin)
{
    $supplier = getOne(
        supplierSelectSql($isAdmin) . " WHERE s.supplier_id = :id" . ($isAdmin ? "" : " AND s.status = 'active'"),
        ["id" => $id]
    );
    if ($supplier === null) {
        return null;
    }

    $contacts = getAll(
        "SELECT contact_id, supplier_id, full_name, position, email, phone, is_primary
         FROM supplier_contacts WHERE supplier_id = :id" . ($isAdmin ? "" : " AND is_primary = 1") . "
         ORDER BY is_primary DESC, full_name",
        ["id" => $id]
    );

    $evaluations = getAll(
        "SELECT evaluation_date, quality_score, delivery_score, service_score, overall_score, comment
         FROM supplier_evaluations WHERE supplier_id = :id ORDER BY evaluation_date DESC, evaluation_id DESC",
        ["id" => $id]
    );

    $summary = null;
    if ($isAdmin) {
        $summary = getOne(
            "SELECT COALESCE(SUM(t.total_amount), 0) AS total_amount,
                    COALESCE(SUM(t.remaining_amount), 0) AS remaining_amount,
                    COALESCE(SUM(CASE WHEN " . overdueSql() . " THEN 1 ELSE 0 END), 0) AS overdue_count
             FROM supply_transactions t WHERE t.supplier_id = :id",
            ["id" => $id]
        );
    }
    return ["supplier" => $supplier, "contacts" => $contacts, "evaluations" => $evaluations, "summary" => $summary];
}

// In phần nội dung chi tiết một nhà cung cấp, dùng cho cả admin (supplier_detail.php) và khách (detail.php).
// $detail là kết quả của loadSupplierDetail(). Admin thấy thêm: tổng giá trị giao dịch, còn nợ, hóa đơn quá hạn,
// mã số thuế, hạn mức, mọi người liên hệ (kèm nút thêm/sửa/xóa/đặt chính).
function supplierDetailHtml($detail, $isAdmin)
{
    $s = $detail["supplier"];
    $contacts = $detail["contacts"];
    $evaluations = $detail["evaluations"];
    $summary = $detail["summary"];
    $sid = (int) $s["supplier_id"];

    $row = fn($label, $valueHtml) => '<dt class="col-sm-4">' . $label . '</dt><dd class="col-sm-8">' . $valueHtml . "</dd>";
    $summaryCard = fn($label, $value, $cls) =>
        '<div class="col-md-4"><div class="summary-card"><div class="text-muted">' . $label . '</div><div class="summary-value' . ($cls ? " " . $cls : "") . '">' . h($value) . "</div></div></div>";
    ?>
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="detail-head">
            <?php echo logoHtml($s, true); ?>
            <div class="detail-meta">
                <span class="supplier-sub"><?php echo h($s["supplier_code"]); ?></span>
                <span class="chip"><?php echo h($s["category_name"]); ?></span>
                <?php echo statusBadge($s["status"]); ?>
            </div>
        </div>

        <?php if ($isAdmin && $summary) { ?>
            <?php $owes = (float) $summary["remaining_amount"] > 0; $late = (int) $summary["overdue_count"]; ?>
            <div class="row g-2 mb-3">
                <?php echo $summaryCard("Tổng giá trị giao dịch", formatMoney($summary["total_amount"]), ""); ?>
                <?php echo $summaryCard("Còn nợ", formatMoney($summary["remaining_amount"]), $owes ? "debt" : ""); ?>
                <?php echo $summaryCard("Hóa đơn quá hạn", (string) $late, $late > 0 ? "debt" : ""); ?>
            </div>
            <div class="mb-3"><a class="btn btn-sm btn-outline-primary" href="transactions.php?supplier_id=<?php echo $sid; ?>">Xem giao dịch</a></div>
        <?php } ?>

        <dl class="row mb-0 detail-list">
            <?php echo $row("Điện thoại", textOrDash($s["phone"])); ?>
            <?php echo $row("Email", textOrDash($s["email"])); ?>
            <?php echo $row("Địa chỉ", textOrDash($s["address"])); ?>
            <?php echo $row("Đánh giá trung bình", (float) $s["avg_rating"] > 0 ? ratingHtml($s["avg_rating"], 2) : "Chưa có đánh giá"); ?>
            <?php if ($isAdmin) { ?>
                <?php echo $row("Mã số thuế", textOrDash($s["tax_code"])); ?>
                <?php echo $row("Hạn mức tín dụng", h(formatMoney($s["credit_limit"]))); ?>
            <?php } ?>
        </dl>

        <!-- Người liên hệ -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h3 class="h6 mb-0"><?php echo $isAdmin ? "Người liên hệ" : "Người liên hệ chính"; ?></h3>
            <?php if ($isAdmin) { ?>
                <a class="btn btn-sm btn-outline-primary" href="contact_form.php?supplier_id=<?php echo $sid; ?>">Thêm liên hệ</a>
            <?php } ?>
        </div>
        <?php if (!$contacts) { ?>
            <p class="text-muted mb-0">Chưa có người liên hệ.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Họ tên</th><th>Chức vụ</th><th>Email</th><th>Điện thoại</th><?php echo $isAdmin ? "<th></th>" : ""; ?></tr></thead>
                    <tbody>
                    <?php foreach ($contacts as $c) { ?>
                        <tr>
                            <td><?php echo h($c["full_name"]); ?><?php echo (int) $c["is_primary"] === 1 ? ' <span class="badge badge-soft badge-soft-success">Chính</span>' : ""; ?></td>
                            <td><?php echo textOrDash($c["position"]); ?></td>
                            <td><?php echo textOrDash($c["email"]); ?></td>
                            <td><?php echo textOrDash($c["phone"]); ?></td>
                            <?php if ($isAdmin) { ?>
                            <td class="text-end text-nowrap actions">
                                <?php if ((int) $c["is_primary"] !== 1) { ?>
                                <form class="d-inline" method="post" action="contact_primary.php">
                                    <input type="hidden" name="contact_id" value="<?php echo (int) $c["contact_id"]; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Đặt làm chính</button>
                                </form>
                                <?php } ?>
                                <a class="btn btn-sm btn-outline-primary" href="contact_form.php?id=<?php echo (int) $c["contact_id"]; ?>">Sửa</a>
                                <form class="d-inline" method="post" action="contact_delete.php"
                                      onsubmit="return confirm('Xóa người liên hệ «<?php echo h(addslashes($c["full_name"])); ?>»?');">
                                    <input type="hidden" name="contact_id" value="<?php echo (int) $c["contact_id"]; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <!-- Đánh giá -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h3 class="h6 mb-0">Đánh giá</h3>
            <?php if ($isAdmin) { ?>
                <a class="btn btn-sm btn-outline-primary" href="evaluations.php?supplier_id=<?php echo $sid; ?>">Quản lý đánh giá</a>
            <?php } ?>
        </div>
        <?php if (!$evaluations) { ?>
            <p class="text-muted mb-0">Chưa có đánh giá.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Ngày</th><th>Chất lượng</th><th>Giao hàng</th><th>Dịch vụ</th><th>Tổng</th><th>Nhận xét</th></tr></thead>
                    <tbody>
                    <?php foreach ($evaluations as $e) { ?>
                        <tr>
                            <td><?php echo h(formatDate($e["evaluation_date"])); ?></td>
                            <td><?php echo (int) $e["quality_score"]; ?></td>
                            <td><?php echo (int) $e["delivery_score"]; ?></td>
                            <td><?php echo (int) $e["service_score"]; ?></td>
                            <td class="fw-medium"><span class="star-gold">★</span> <?php echo number_format((float) $e["overall_score"], 2, ".", ""); ?></td>
                            <td><?php echo textOrDash($e["comment"]); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
    <?php
}

// =====================================================================
// 9. QUY TẮC HÓA ĐƠN VÀ HẠNG ĐỐI TÁC
// =====================================================================

// Quy tắc "quá hạn" và "sắp đến hạn" của hóa đơn, dùng chung cho danh sách giao dịch, chi tiết nhà cung cấp và tổng quan
// để các nơi luôn đếm giống nhau. Điều kiện viết cho bảng supply_transactions có bí danh "t".
// Ngày hôm nay do PHP tính rồi ghép vào câu SQL (không phải dữ liệu người dùng nên an toàn).
const DUE_SOON_DAYS = 7;   // số ngày tính từ hôm nay được coi là "sắp đến hạn"

// Quá hạn: có hạn thanh toán, hạn đã qua (đúng ngày hạn thì chưa quá hạn) và vẫn còn nợ
function overdueSql()
{
    return "(t.due_date IS NOT NULL AND t.due_date < '" . date("Y-m-d") . "' AND t.remaining_amount > 0)";
}

// Sắp đến hạn: có hạn thanh toán từ hôm nay đến DUE_SOON_DAYS ngày tới và vẫn còn nợ (không giao với quá hạn)
function dueSoonSql()
{
    $today = date("Y-m-d");
    $limit = date("Y-m-d", strtotime("+" . DUE_SOON_DAYS . " days"));
    return "(t.due_date IS NOT NULL AND t.due_date >= '$today' AND t.due_date <= '$limit' AND t.remaining_amount > 0)";
}

const PAYMENT_STATUSES = ["unpaid", "partial", "paid"];
const DUE_FILTERS = ["overdue" => "Quá hạn", "soon" => "Sắp đến hạn (7 ngày tới)"];

// Hạng đối tác theo tổng giá trị mua (cộng total_amount của mọi hóa đơn). Xếp từ cao xuống thấp;
// "min" là số đồng tối thiểu để đạt hạng đó; hạng cuối có min = 0 nên ai cũng rơi vào một hạng.
const PARTNER_TIERS = [
    ["key" => "vip1",    "label" => "VIP 1",  "min" => 200000000],
    ["key" => "vip2",    "label" => "VIP 2",  "min" => 100000000],
    ["key" => "vip3",    "label" => "VIP 3",  "min" => 30000000],
    ["key" => "regular", "label" => "Thường", "min" => 0],
];

// Khóa hạng ("vip1", "vip2", "vip3", "regular") ứng với tổng giá trị mua; đúng bằng ngưỡng thì đạt hạng đó
function partner_tier($totalAmount)
{
    $total = (float) $totalAmount;
    foreach (PARTNER_TIERS as $tier) {
        if ($total >= $tier["min"]) {
            return $tier["key"];
        }
    }
    return "regular";
}

// Tên hạng kèm ô vuông màu của hạng (màu chỉ là phụ, luôn có chữ)
function tierTag($key, $label)
{
    return '<span class="tier-tag tier-' . h($key) . '">' . h($label) . "</span>";
}

// Số tiền gọn cho ngưỡng hạng: 200000000 -> "200 triệu", 1500000000 -> "1,5 tỷ"
function compactMoney($amount)
{
    $viNumber = function ($value) {
        $text = number_format(round($value, 2), 2, ",", ".");
        return rtrim(rtrim($text, "0"), ",");
    };
    if ($amount >= 1e9) {
        return $viNumber($amount / 1e9) . " tỷ";
    }
    if ($amount >= 1e6) {
        return $viNumber($amount / 1e6) . " triệu";
    }
    return formatMoney($amount);
}

// Thanh chia theo tỷ lệ: mỗi đoạn rộng theo số lượng (flex-grow = số lượng); đoạn bằng 0 bị bỏ.
// $segments là mảng [class CSS, số lượng]
function ratioBar($segments, $ariaLabel)
{
    $html = '<div class="status-bar" role="img" aria-label="' . h($ariaLabel) . '">';
    foreach ($segments as [$class, $count]) {
        if ($count > 0) {
            $html .= '<span class="seg ' . h($class) . '" style="flex-grow:' . (int) $count . '"></span>';
        }
    }
    return $html . "</div>";
}

// =====================================================================
// 10. KHUNG GIAO DIỆN DÙNG CHUNG
// =====================================================================

// Địa chỉ file tĩnh (CSS, JS, ảnh) trong assets/, kèm ?v=<thời điểm sửa file> để trình duyệt tải bản mới khi file đổi
function asset($path)
{
    $file = APP_DIR . "/assets/" . $path;
    return ROOT . "assets/" . $path . "?v=" . (is_file($file) ? filemtime($file) : "0");
}

// Logo + tên website (dùng ở thanh menu, đầu trang khách, màn hình đăng nhập). $href là nơi chuyển tới khi bấm.
function brandHtml($href = "index.php", $class = "")
{
    return '<a class="app-brand ' . h($class) . '" href="' . h($href) . '">'
        . '<span class="app-mark" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 32 32" fill="none">'
        . '<polygon points="16,6 24.5,11 16,16 7.5,11" fill="#3CC4AE"/>'
        . '<path d="M16 6 L24.5 11 V21 L16 26 L7.5 21 V11 Z M7.5 11 L16 16 L24.5 11 M16 16 V26" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/>'
        . '</svg></span><span class="app-name">Quản lý nhà cung cấp</span></a>';
}

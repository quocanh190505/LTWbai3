<?php
// layouts/footer_public.php – cuối trang công khai: đóng <main>, chân trang, nạp JavaScript.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
?>
</main>

<footer class="pub-footer">
    <div class="container">
        <div class="pub-footer-grid">
            <div>
                <div class="pub-footer-brand">Quản lý nhà cung cấp</div>
                <p>Danh bạ các nhà cung cấp đang hợp tác: thông tin liên hệ, người liên hệ chính và điểm đánh giá.</p>
            </div>
            <nav aria-label="Liên kết chân trang">
                <a href="<?php echo ROOT; ?>index.php#danhmuc">Danh mục</a>
                <a href="<?php echo ROOT; ?>index.php#nhacungcap">Nhà cung cấp</a>
                <a href="<?php echo ROOT; ?>auth/login.php">Đăng nhập quản trị</a>
            </nav>
        </div>
        <div class="pub-footer-note">© <?php echo date("Y"); ?> Quản lý nhà cung cấp – Bài thực hành 3</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

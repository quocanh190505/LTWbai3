// Thanh menu bên trái của trang quản trị: nút 3 gạch thu gọn / mở menu.
//  - Máy tính (từ 992px): menu mở sẵn; bấm 3 gạch thì thu lại hoặc mở ra, lựa chọn được nhớ trong localStorage
//    (header.php đọc lại ngay từ đầu trang để không bị giật). Trạng thái nằm ở lớp 'sidebar-collapsed' trên <html>.
//  - Điện thoại (dưới 992px): menu ẩn sẵn; bấm 3 gạch thì trượt ra kèm nền mờ, đóng bằng nền mờ hoặc phím Esc.
//    Trạng thái nằm ở lớp 'sidebar-open' trên <body> và không được nhớ.
(function () {
    var root = document.documentElement;
    var sidebar = document.getElementById('appSidebar');
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !toggle || !backdrop) { return; }

    var desktop = window.matchMedia('(min-width: 992px)');

    function isOpen() {
        return desktop.matches
            ? !root.classList.contains('sidebar-collapsed')
            : document.body.classList.contains('sidebar-open');
    }

    function render() {
        toggle.setAttribute('aria-expanded', isOpen() ? 'true' : 'false');
        backdrop.hidden = desktop.matches || !document.body.classList.contains('sidebar-open');
    }

    function setOpen(open) {
        root.classList.add('sidebar-anim');   // chỉ bật hiệu ứng trượt sau lần bấm đầu tiên, tránh trượt lúc vừa tải trang
        if (desktop.matches) {
            root.classList.toggle('sidebar-collapsed', !open);
            try { localStorage.setItem('sidebar', open ? 'open' : 'collapsed'); } catch (e) { /* trình duyệt chặn lưu trữ: vẫn dùng được, chỉ không nhớ */ }
        } else {
            document.body.classList.toggle('sidebar-open', open);
            if (open) {
                var first = sidebar.querySelector('a, button');
                if (first) { first.focus(); }
            } else {
                toggle.focus();
            }
        }
        render();
    }

    toggle.addEventListener('click', function () { setOpen(!isOpen()); });
    backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !desktop.matches && isOpen()) { setOpen(false); }
    });

    // Đổi cỡ cửa sổ (xoay điện thoại, kéo rộng trình duyệt): bỏ trạng thái mở của điện thoại để không kẹt nền mờ
    function onBreakpoint() {
        document.body.classList.remove('sidebar-open');
        render();
    }
    if (desktop.addEventListener) { desktop.addEventListener('change', onBreakpoint); } else { desktop.addListener(onBreakpoint); }

    render();
})();

<?php
// Tạo mã băm mật khẩu để dán vào cột users.password_hash.
// Dùng: php database\make_hash.php <mật khẩu>
if ($argc < 2 || $argv[1] === '') {
    fwrite(STDERR, "Cách dùng: php database\\make_hash.php <mật khẩu>\n");
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT), "\n";

<?php
require_once __DIR__ . '/../src/Session.php';
logoutUser();
setFlash('info', 'Bạn đã đăng xuất khỏi Cổng Quản Trị thành công.');
header('Location: /admin/login.php');
exit;


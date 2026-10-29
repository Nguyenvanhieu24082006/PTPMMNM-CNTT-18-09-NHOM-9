<?php
require_once __DIR__ . '/../src/Session.php';
logoutUser();
setFlash('info', 'Bạn đã đăng xuất thành công.');
header('Location: /login.php');
exit;


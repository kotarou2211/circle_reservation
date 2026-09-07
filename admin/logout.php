<?php
require_once __DIR__ . '/../common/auth.php';
logoutAdmin();
header('Location: login.php?logout=1');
exit;

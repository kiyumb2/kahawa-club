<?php
setcookie('admin_session', '', time() - 3600, '/');
header("Location: /admin/index.html");
exit;

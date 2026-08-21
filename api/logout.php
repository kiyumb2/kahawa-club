<?php
setcookie('user_session', '', time() - 3600, '/');
header("Location: /index.html");
exit;
?>

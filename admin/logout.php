<?php
// admin/logout.php
session_start();
session_unset();
session_destroy();

$redirect = "index.php";
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $redirect .= "?error=timeout";
}

header("Location: $redirect");
exit;
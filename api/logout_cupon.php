<?php
session_start();
unset($_SESSION['cupon_activo']);
header("Location: ../index.php");
?>
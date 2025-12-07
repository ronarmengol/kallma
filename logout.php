<?php
session_start();
session_destroy();

if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    header("Location: login.php?timeout=1");
} else {
    header("Location: index.php");
}
exit();
?>

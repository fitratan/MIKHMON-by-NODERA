<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified for NODERA Ecosystem.
 */
include_once(__DIR__ . '/../include/init_session.php');
// hide all error
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

if (!empty($removepactive)) {
    $API->comm("/ppp/active/remove", array(
        ".id" => "$removepactive",
    ));
}

echo "<script>window.location='./?ppp=active&session=" . $session . "'</script>";

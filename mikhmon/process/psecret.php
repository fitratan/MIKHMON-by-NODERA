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

if (!empty($removesecr)) {
    $API->comm("/ppp/secret/remove", array(
        ".id" => "$removesecr",
    ));
} elseif (!empty($enablesecr)) {
    $API->comm("/ppp/secret/set", array(
        ".id" => "$enablesecr",
        "disabled" => "no",
    ));
} elseif (!empty($disablesecr)) {
    $API->comm("/ppp/secret/set", array(
        ".id" => "$disablesecr",
        "disabled" => "yes",
    ));
}

echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";

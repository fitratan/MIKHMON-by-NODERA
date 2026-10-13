<?php
/**
 * MIKHMON by NODERA — Version & Engine Manifest
 */
$m_version = '3.20.4-v8';
$m_build = '2026-10-11';
$m_commit = '4c0ba02';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    // Keep auth check for direct script access
} else {
    $_SESSION["v"] = $m_version . " (" . $m_commit . ")";
}

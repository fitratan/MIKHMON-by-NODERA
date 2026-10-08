<?php
// Root router for Mikhmon Desktop standalone
if (file_exists(__DIR__ . '/mikhmon/index.php')) {
    chdir(__DIR__ . '/mikhmon');
    require __DIR__ . '/mikhmon/index.php';
} else {
    header('Location: ./admin.php?id=sessions');
    exit;
}
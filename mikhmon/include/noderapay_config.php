<?php
if(substr($_SERVER["REQUEST_URI"], -20) == "noderapay_config.php"){header("Location:./");};
$npApiUrl = (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.' . (function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : 'airnetsolution.com')) . '/api/v1/noderapay';
$noderapay_data = array (
  'default' => 
  array (
    'enabled' => 'yes',
    'payment_mode' => 'noderapay',
    'merchant_code' => '',
    'api_key' => 'np_live_aESxxRAB9OZfnq4mK6BZhtpA3B6V9OKF1rNR',
    'secret_key' => 'sec_s1yPoQva5D6YEN9BKACWpDuLn5fhR7iA',
    'api_url' => $npApiUrl,
    'merchant_name' => 'TEMPLATE-V7 HOTSPOT',
    'store_title' => 'Voucher WiFi Online',
    'store_subtitle' => 'Internet Cepat, Murah & Aktif Otomatis',
    'portal_theme' => 'standard',
    'stock_mode' => 'unused_pool',
    'qris_timeout_minutes' => '15',
    'voucher_prefix' => 'VC-',
    'char_length' => '6',
    'profile_mode' => 'all',
    'allowed_profiles' => 
    array (
    ),
  ),
);

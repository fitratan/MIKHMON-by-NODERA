<?php
/**
 * MIKHMON by NODERA Engine — Manajemen Fitur Warung (Reseller Kulakan)
 * Platform Billing ISP & Manajemen Reseller Hotspot Terintegrasi
 * 1:1 Mengikuti Standar Visual MIKHMON Native
 * dgtlnetsolution.com
 */

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @session_start();
}

if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    header("Location:./admin.php?id=login");
    exit;
}

include_once __DIR__ . '/license.php';
include_once __DIR__ . '/warung_helper.php';
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/readcfg.php';
// Load language
$langid = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
if (empty($langid)) {
    if (file_exists(__DIR__ . '/../include/lang.php')) {
        include __DIR__ . '/../include/lang.php';
    } elseif (file_exists(__DIR__ . '/lang.php')) {
        include __DIR__ . '/lang.php';
    }
}
if (empty($langid)) {
    $langid = 'id';
}
if (file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    include __DIR__ . '/../lang/' . $langid . '.php';
} elseif (file_exists(__DIR__ . '/lang/' . $langid . '.php')) {
    include __DIR__ . '/lang/' . $langid . '.php';
}


$subdomain = defined('MIKHMON_SUBDOMAIN') ? MIKHMON_SUBDOMAIN : basename(dirname(__DIR__));
if (empty($subdomain) || in_array($subdomain, ['include', 'mikhmon-template', 'mikhmon-template-v7', 'public', 'html'])) {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $hostParts = explode('.', $_SERVER['HTTP_HOST']);
        $subdomain = $hostParts[0] ?? '';
    }
}

$activeTab = $_GET['tab'] ?? 'list';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$session = isset($_GET['session']) && !empty($_GET['session'])
    ? $_GET['session']
    : (isset($_SESSION['session']) && !empty($_SESSION['session']) ? $_SESSION['session'] : '');

if (empty($session) && isset($data) && is_array($data)) {
    foreach ($data as $k => $v) {
        if ($k !== 'mikhmon' && is_array($v) && !empty($v[1])) {
            $session = $k;
            break;
        }
    }
}

// Ensure active currency is loaded from Mikhmon session config
if ((empty($currency) || !is_string($currency)) && !empty($session) && !empty($data[$session][6])) {
    $currency = explode('&', $data[$session][6])[1] ?? '';
}
if (empty($currency)) {
    $currency = ($langid === 'fr') ? 'FCFA' : 'Rp';
}
$currency = trim((string)$currency);

$isDirectHotspot = isset($_GET['hotspot']) && $_GET['hotspot'] === 'warung';
$adminWarungBaseUrl = $isDirectHotspot ? './?hotspot=warung' : './admin.php?id=warung';

// Check Addon Status (force refresh if requested)
$forceRefresh = isset($_GET['refresh']) || isset($_GET['refresh_status']);
$addonStatus = warung_check_addon_status($subdomain, $forceRefresh);
$isAddonActive = $addonStatus['addon']['is_subscribed'] ?? false;
$addonPrice = $addonStatus['addon']['price'] ?? 15000;
$addonPriceFmt = $addonStatus['addon']['formatted_price'] ?? 'Rp 15.000';
$tenantBalance = $addonStatus['tenant']['balance'] ?? 0;
$tenantBalanceFmt = $addonStatus['tenant']['formatted_balance'] ?? 'Rp 0';
$hasEnoughBalance = $addonStatus['tenant']['has_enough_balance'] ?? false;
$tenantName = $addonStatus['tenant']['name'] ?? '';

// Handle AJAX Activation
if ($action === 'ajax_activate_addon') {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $actRes = warung_activate_addon($subdomain);
    echo json_encode($actRes);
    exit;
}

// Handle Form Submissions when active
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$alertMsg = '';
$alertType = 'success';

if (!empty($_SESSION['warung_flash_msg'])) {
    $alertMsg = $_SESSION['warung_flash_msg'];
    $alertType = $_SESSION['warung_flash_type'] ?? 'success';
    unset($_SESSION['warung_flash_msg'], $_SESSION['warung_flash_type']);
}

if ($isAddonActive && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_warung') {
        $wId = trim($_POST['warung_id'] ?? '');
        $wName = trim($_POST['name'] ?? '');
        $wOwner = trim($_POST['owner'] ?? '');
        $wPhone = trim($_POST['phone'] ?? '');
        $wUsername = trim($_POST['username'] ?? '');
        $wPin = trim($_POST['pin'] ?? '');
        $wStatus = trim($_POST['status'] ?? 'active');
        $wRouterSession = trim($_POST['router_session'] ?? '');
        $enableCustomPrice = ($_POST['enable_custom_price'] ?? 'disable') === 'enable';
        $wCustomPrices = $enableCustomPrice ? ($_POST['custom_prices'] ?? []) : [];

        if (empty($wName) || empty($wUsername)) {
            $alertMsg = 'Nama Warung dan Username wajib diisi.';
            $alertType = 'danger';
        } else {
            $saveRes = warung_save_account($wId, $wName, $wOwner, $wPhone, $wUsername, $wPin, $wStatus, $wRouterSession, $wCustomPrices);
            if ($saveRes['success']) {
                $_SESSION['warung_flash_msg'] = 'Akun warung berhasil disimpan.';
                $_SESSION['warung_flash_type'] = 'success';
                $redirectUrl = $adminWarungBaseUrl . '&tab=list' . (!empty($session) ? '&session=' . urlencode($session) : '');
                header("Location: " . $redirectUrl);
                echo "<script>window.location='" . $redirectUrl . "';</script>";
                exit;
            } else {
                $alertMsg = $saveRes['error'] ?? 'Gagal menyimpan akun warung.';
                $alertType = 'danger';
            }
        }
    } elseif ($action === 'topup_manual') {
        $wId = trim($_POST['warung_id'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Top-up manual oleh Admin');

        $tpRes = warung_admin_topup_balance($wId, $amount, $notes);
        if ($tpRes['success']) {
            $_SESSION['warung_flash_msg'] = 'Saldo warung berhasil ditambahkan sebesar ' . mikhmon_format_money($amount, $currency, $langid) . '.';
            $_SESSION['warung_flash_type'] = 'success';
        } else {
            $_SESSION['warung_flash_msg'] = $tpRes['error'] ?? 'Gagal menambah saldo warung.';
            $_SESSION['warung_flash_type'] = 'danger';
        }
        $redirectUrl = $adminWarungBaseUrl . '&tab=topup' . (!empty($session) ? '&session=' . urlencode($session) : '');
        header("Location: " . $redirectUrl);
        echo "<script>window.location='" . $redirectUrl . "';</script>";
        exit;
    } elseif ($action === 'delete_topup') {
        $txId = trim($_POST['tx_id'] ?? '');
        $delRes = warung_admin_delete_topup($txId);
        if ($delRes['success']) {
            $_SESSION['warung_flash_msg'] = 'Top-up ' . htmlspecialchars($txId) . ' berhasil dibatalkan. Saldo warung ' . htmlspecialchars($delRes['warung_name']) . ' ditarik kembali sebesar ' . mikhmon_format_money($delRes['amount'], $currency, $langid) . ' (Sisa saldo: ' . mikhmon_format_money($delRes['new_balance'], $currency, $langid) . ').';
            $_SESSION['warung_flash_type'] = 'success';
        } else {
            $_SESSION['warung_flash_msg'] = $delRes['error'] ?? 'Gagal membatalkan top-up.';
            $_SESSION['warung_flash_type'] = 'danger';
        }
        $redirectUrl = $adminWarungBaseUrl . '&tab=topup' . (!empty($session) ? '&session=' . urlencode($session) : '');
        header("Location: " . $redirectUrl);
        echo "<script>window.location='" . $redirectUrl . "';</script>";
        exit;
    } elseif ($action === 'reset_pin') {
        $wId = trim($_POST['warung_id'] ?? '');
        $newPin = trim($_POST['new_pin'] ?? '');
        $resetRes = warung_reset_pin($wId, $newPin);
        if ($resetRes['success']) {
            $_SESSION['warung_flash_msg'] = 'PIN warung <b>' . htmlspecialchars($resetRes['warung']['name']) . '</b> berhasil direset menjadi: <b style="font-size: 16px; font-family: monospace; letter-spacing: 2px;">' . htmlspecialchars($resetRes['new_pin']) . '</b>. Mohon berikan PIN baru ini kepada pemilik warung.';
            $_SESSION['warung_flash_type'] = 'success';
        } else {
            $_SESSION['warung_flash_msg'] = $resetRes['error'] ?? 'Gagal mereset PIN warung.';
            $_SESSION['warung_flash_type'] = 'danger';
        }
        $redirectUrl = $adminWarungBaseUrl . '&tab=list' . (!empty($session) ? '&session=' . urlencode($session) : '');
        header("Location: " . $redirectUrl);
        echo "<script>window.location='" . $redirectUrl . "';</script>";
        exit;
    } elseif ($action === 'delete_warung') {
        $wId = trim($_POST['warung_id'] ?? '');
        if (warung_delete_account($wId)) {
            $_SESSION['warung_flash_msg'] = 'Akun warung berhasil dihapus.';
            $_SESSION['warung_flash_type'] = 'success';
        } else {
            $_SESSION['warung_flash_msg'] = 'Gagal menghapus akun warung.';
            $_SESSION['warung_flash_type'] = 'danger';
        }
        $redirectUrl = $adminWarungBaseUrl . '&tab=list' . (!empty($session) ? '&session=' . urlencode($session) : '');
        header("Location: " . $redirectUrl);
        echo "<script>window.location='" . $redirectUrl . "';</script>";
        exit;
    } elseif ($action === 'save_prices') {
        $profilePrices = $_POST['prices'] ?? [];
        $targetSession = trim($_POST['target_session'] ?? $session);
        $cleanPrices = [];
        foreach ($profilePrices as $pName => $pData) {
            $status = ($pData['status'] ?? 'enable') === 'disable' ? 'disable' : 'enable';
            $cleanPrices[$pName] = [
                'cost_price' => (float) ($pData['cost_price'] ?? 0),
                'sell_price' => (float) ($pData['sell_price'] ?? 0),
                'status'     => $status
            ];
        }
        warung_save_profile_prices($cleanPrices, $targetSession);
        $alertMsg = 'Pengaturan harga modal profil voucher untuk sesi router ' . htmlspecialchars($targetSession) . ' berhasil disimpan.';
        $alertType = 'success';
        $activeTab = 'prices';
    } elseif ($action === 'save_settings') {
        $newSettings = [
            'stock_mode'      => $_POST['stock_mode'] ?? 'auto',
            'voucher_prefix'  => preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['voucher_prefix'] ?? 'VC-'),
            'char_length'     => max(4, min(12, intval($_POST['char_length'] ?? 6))),
            'char_format'     => $_POST['char_format'] ?? 'mix_lower',
            'auto_purge_days' => intval($_POST['auto_purge_days'] ?? 30),
        ];
        warung_save_settings($newSettings);
        $alertMsg = 'Pengaturan operasional warung berhasil disimpan.';
        $alertType = 'success';
        $activeTab = 'settings';
    } elseif ($action === 'manual_purge') {
        $wSettings = warung_get_settings();
        $days = intval($wSettings['auto_purge_days'] ?? 30);
        if ($days <= 0) $days = 30;
        $purgedCount = warung_purge_old_data($days);
        $alertMsg = 'Pembersihan riwayat selesai. ' . $purgedCount . ' transaksi lama (> ' . $days . ' hari) berhasil dihapus dari database.';
        $alertType = 'success';
        $activeTab = 'settings';
    }
}

// Load Warung Data
$warungData = warung_get_data();
$warungList = array_values($warungData['warungs'] ?? []);
$priceMap = (!empty($session) && isset($warungData['session_profile_prices'][$session]) && is_array($warungData['session_profile_prices'][$session]))
    ? $warungData['session_profile_prices'][$session]
    : ($warungData['profile_prices'] ?? []);
$txList = array_reverse($warungData['transactions'] ?? []);
$warungSettings = warung_get_settings();

// Fetch MikroTik profiles strictly for active router session
$hotspotProfiles = [];
$creds = warung_get_router_credentials($session);
if ($creds) {
    include_once __DIR__ . '/../lib/routeros_api.class.php';
    include_once __DIR__ . '/order_helper.php';

    $API = mikhmon_get_router_api($creds['ip'], $creds['user'], $creds['pass']);
    if ($API) {
        $rawProfs = $API->comm('/ip/hotspot/user/profile/print');
        $API->disconnect();
        if (is_array($rawProfs)) {
            $hotspotProfiles = $rawProfs;
        }
    }
}

// If router is offline, fallback ONLY to profiles previously saved for THIS router session
if (empty($hotspotProfiles) && !empty($priceMap)) {
    foreach ($priceMap as $pName => $pData) {
        if ($pName !== 'default' && !empty($pName)) {
            $hotspotProfiles[] = [
                'name'     => $pName,
                'validity' => $pData['validity'] ?? 'Aktif'
            ];
        }
    }
}

// Selected Warung for Edit or Topup
$selectedWarungId = $_GET['warung_id'] ?? '';
$selectedWarung = null;
if (!empty($warungList)) {
    if (!empty($selectedWarungId)) {
        foreach ($warungList as $w) {
            if ($w['id'] === $selectedWarungId) {
                $selectedWarung = $w;
                break;
            }
        }
    }
    if (!$selectedWarung && ($activeTab === 'topup' || $activeTab === 'edit')) {
        $selectedWarung = $warungList[0];
    }
}

// Portal Warung URL
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$baseUrl = ($isHttps ? 'https://' : 'http://') . $currentHost;
$warungPortalUrl = $baseUrl . '/warung';
?>

<?php if (!empty($alertMsg)): ?>
  <div class="alert bg-<?= $alertType; ?>" style="padding: 10px 14px; margin-bottom: 15px; border-radius: 4px;">
    <i class="fa <?= $alertType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?= htmlspecialchars($alertMsg); ?>
  </div>
<?php endif; ?>

<?php if (!$isAddonActive): ?>
  <!-- =========================================================================
       ADDON TERKUNCI — NATIVE MIKHMON 2-COLUMN LAYOUT
       ========================================================================= -->
  <div class="row">
    <!-- Kolom Kiri: Deskripsi Fitur & Detail Add-on -->
    <div class="col-8 col-box-12">
      <div class="card">
        <div class="card-header">
          <h3><i class="fa fa-shopping-basket"></i> <?= $_warung_reseller_title ?? 'Fitur Warung & Reseller Voucher Hotspot'; ?></h3>
        </div>
        <div class="card-body">
          <table class="table table-sm">
            <tr>
              <td class="align-middle" style="width: 32%;"><b><?= $_system_module ?? 'Modul Sistem'; ?></b></td>
              <td><?= $_warung_module_name ?? 'Fitur Warung / Reseller Kulakan Voucher (Portal Kasir /warung)'; ?></td>
            </tr>
            <tr>
              <td class="align-middle"><b><?= $_main_functions ?? 'Fungsi Utama'; ?></b></td>
              <td>
                <ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
                  <li><?= $_warung_feat_1 ?? 'Warung kulakan voucher mandiri via portal kasir terisolasi (potong saldo deposit warung).'; ?></li>
                  <li><?= $_warung_feat_2 ?? 'Cetak struk kasir printer thermal Bluetooth (58mm / 80mm).'; ?></li>
                  <li><?= $_warung_feat_3 ?? 'Kirim rincian voucher via WhatsApp resmi otomatis ke nomor pembeli.'; ?></li>
                  <li><?= $_warung_feat_4 ?? 'Top-up saldo warung mandiri via QRIS Payment Gateway admin Mikhmon.'; ?></li>
                </ul>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><b><?= $_subscription_cost ?? 'Biaya Berlangganan'; ?></b></td>
              <td><b class="text-primary" style="font-size: 15px;"><?= htmlspecialchars($addonPriceFmt); ?></b> <small class="text-secondary"><?= $_per_month ?? '/ bulan'; ?></small></td>
            </tr>
            <tr>
              <td class="align-middle"><b><?= $_feature_status ?? 'Status Fitur'; ?></b></td>
              <td><span class="badge bg-danger" style="padding: 4px 8px; border-radius: 4px; font-size: 11px;"><?= $_inactive ?? 'BELUM AKTIF'; ?></span></td>
            </tr>
          </table>

          <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(128,128,128,0.2);">
            <?php if ($hasEnoughBalance): ?>
              <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn bg-primary" id="btnActivateAddon" onclick="activateWarungAddon()" style="font-size: 14px; font-weight: bold;">
                  <i class="fa fa-bolt"></i> <?= $_activate_warung_now ?? 'Aktifkan Fitur Warung Sekarang'; ?>
                </button>
                <span class="text-secondary" style="font-size: 12px;"><?= $_activation_fee_note ?? 'Biaya akan dipotong langsung dari Saldo Panel Anda.'; ?></span>
              </div>
            <?php else: ?>
              <div style="background: rgba(231, 76, 60, 0.12); border: 1px solid rgba(231, 76, 60, 0.4); border-radius: 4px; padding: 12px 14px; margin-bottom: 14px; font-size: 13px; line-height: 1.5;">
                <b class="text-danger"><i class="fa fa-exclamation-circle"></i> <?= $_insufficient_panel_balance ?? 'Saldo Panel Tidak Mencukupi'; ?></b><br>
                <?= $_insufficient_panel_balance_desc ?? 'Saldo panel Anda saat ini tidak mencukupi untuk biaya aktivasi.'; ?>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="https://panel.dgtlnetsolution.com/admin/topup" target="_blank" class="btn bg-success" style="font-size: 14px; font-weight: bold;">
                  <i class="fa fa-plus-circle"></i> <?= $_topup_panel_balance ?? 'Top Up Saldo Panel'; ?>
                </a>
                <a href="<?= $adminWarungBaseUrl ?>&refresh_status=1<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-secondary" style="font-size: 13px;">
                  <i class="fa fa-refresh"></i> <?= $_refresh_balance_status ?? 'Refresh Status Saldo'; ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Status Saldo Panel -->
    <div class="col-4 col-box-12">
      <div class="card">
        <div class="card-header">
          <h3><i class="fa fa-server"></i> <?= $_panel_balance ?? 'Saldo Panel'; ?></h3>
        </div>
        <div class="card-body text-center">
          <div style="font-size: 12px; opacity: 0.7; margin-bottom: 5px;"><?= $_your_panel_balance ?? 'Saldo Panel Manajemen Anda'; ?></div>
          <div style="font-size: 24px; font-weight: bold; margin-bottom: 10px;" class="<?= $hasEnoughBalance ? 'text-green' : 'text-danger'; ?>">
            <?= htmlspecialchars($tenantBalanceFmt); ?>
          </div>
          <?php if (!empty($tenantName)): ?>
            <small class="text-secondary"><?= $_account ?? 'Akun'; ?>: <b><?= htmlspecialchars($tenantName); ?></b></small>
          <?php endif; ?>

          <hr style="margin: 15px 0; border: 0; border-top: 1px solid rgba(128,128,128,0.2);">

          <a href="https://panel.dgtlnetsolution.com/admin/topup" target="_blank" class="btn bg-success btn-block" style="margin-bottom: 8px; font-size: 13px;">
            <i class="fa fa-plus-circle"></i> <?= $_topup_panel_balance ?? 'Top Up Saldo Panel'; ?>
          </a>

          <a href="<?= $adminWarungBaseUrl ?>&refresh_status=1<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-secondary btn-block" style="font-size: 12px;">
            <i class="fa fa-refresh"></i> <?= $_refresh_balance_status ?? 'Refresh Status Saldo'; ?>
          </a>
        </div>
      </div>

      <div class="card" style="margin-top: 15px;">
        <div class="card-header">
          <h3><i class="fa fa-info-circle"></i> <?= $_information ?? 'Informasi'; ?></h3>
        </div>
        <div class="card-body" style="font-size: 12px; line-height: 1.6;">
          <?= $_panel_auto_debit_info ?? 'Saldo panel dipotong secara otomatis setiap siklus perpanjangan bulanan add-on. Anda dapat menonaktifkan fitur kapan saja melalui panel.dgtlnetsolution.com.'; ?>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- SUB-VIEW: TAMBAH ATAU EDIT WARUNG (NATIVE FORM) -->
  <?php if ($activeTab === 'add' || $activeTab === 'edit'): ?>
    <?php 
      $isEdit = ($activeTab === 'edit' && $selectedWarung);
      $formSession = $isEdit ? ($selectedWarung['router_session'] ?? $session) : $session;
      $formCreds = warung_get_router_credentials($formSession);
      $formHotspotProfiles = [];
      if ($formCreds) {
          try {
              $formAPI = @mikhmon_get_router_api($formCreds['ip'], $formCreds['user'], $formCreds['pass']);
              if ($formAPI) {
                  $rawFormProfs = @$formAPI->comm('/ip/hotspot/user/profile/print');
                  @$formAPI->disconnect();
                  if (is_array($rawFormProfs)) {
                      $formHotspotProfiles = $rawFormProfs;
                  }
              }
          } catch (\Throwable $e) {
              $formHotspotProfiles = [];
          }
      }
      $formPriceMap = (!empty($formSession) && isset($warungData['session_profile_prices'][$formSession]))
          ? $warungData['session_profile_prices'][$formSession]
          : ($warungData['profile_prices'] ?? []);
      if (empty($formHotspotProfiles) && !empty($formPriceMap)) {
          foreach ($formPriceMap as $pName => $pData) {
              if ($pName !== 'default' && !empty($pName)) {
                  $formHotspotProfiles[] = ['name' => $pName, 'validity' => $pData['validity'] ?? 'Aktif'];
              }
          }
      }
    ?>
    <div class="row">
      <div class="col-8 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3>
              <i class="fa <?= $isEdit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> <?= $isEdit ? (($_edit_warung_partner ?? 'Edit Mitra Warung') . ': ' . htmlspecialchars($selectedWarung['name'])) : ($_add_new_warung ?? 'Tambah Mitra Warung Baru'); ?>
            </h3>
          </div>
          <div class="card-body">
            <form autocomplete="off" method="POST" action="<?= $adminWarungBaseUrl ?>&tab=list<?= !empty($session) ? '&session='.$session : ''; ?>">
              <input type="hidden" name="action" value="save_warung">
              <input type="hidden" name="warung_id" value="<?= htmlspecialchars($selectedWarung['id'] ?? ''); ?>">

              <table class="table table-sm">
                <tr>
                  <td class="align-middle" style="width: 35%;"><?= $_warung_name_store ?? 'Nama Warung / Toko'; ?> *</td>
                  <td>
                    <input class="form-control" type="text" name="name" value="<?= htmlspecialchars($selectedWarung['name'] ?? ''); ?>" placeholder="<?= $_warung_name_placeholder ?? 'Contoh: Warung Berkah'; ?>" required autofocus>
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_router_session ?? 'Sesi Router MikroTik'; ?> *</td>
                  <td>
                    <select class="form-control" name="router_session" required>
                      <option value="">-- <?= $_select_router_session ?? 'Pilih Sesi Router'; ?> --</option>
                      <?php if (isset($data) && is_array($data)): ?>
                        <?php foreach ($data as $rKey => $rVal): ?>
                          <?php if ($rKey !== 'mikhmon' && is_array($rVal) && !empty($rVal[1])): ?>
                            <?php 
                              $rHs = $rVal[4] ?? $rKey;
                              $rCleanHs = strpos($rHs, '%') !== false ? explode('%', $rHs)[0] : $rHs;
                              $isSelected = (($selectedWarung['router_session'] ?? '') === $rKey) || (empty($selectedWarung['router_session']) && $rKey === $session);
                            ?>
                            <option value="<?= htmlspecialchars($rKey); ?>" <?= $isSelected ? 'selected' : ''; ?>>
                              <?= htmlspecialchars($rCleanHs); ?> (<?= htmlspecialchars($rKey); ?> - <?= htmlspecialchars($rVal[1]); ?>)
                            </option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                    <small class="text-secondary"><?= $_select_router_session_hint ?? 'Pilih sesi router MikroTik tempat warung ini beroperasi.'; ?></small>
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_owner_name ?? 'Nama Pemilik / Pengelola'; ?></td>
                  <td>
                    <input class="form-control" type="text" name="owner" value="<?= htmlspecialchars($selectedWarung['owner'] ?? ''); ?>" placeholder="<?= $_owner_name_placeholder ?? 'Nama Pemilik'; ?>">
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_whatsapp_number ?? 'No. WhatsApp Pemilik'; ?></td>
                  <td>
                    <input class="form-control" type="text" name="phone" value="<?= htmlspecialchars($selectedWarung['phone'] ?? ''); ?>" placeholder="081234567890">
                    <small class="text-secondary"><?= $_whatsapp_notif_hint ?? 'Untuk konfirmasi transaksi & notifikasi WhatsApp.'; ?></small>
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_warung_login_username ?? 'Username Login Warung'; ?> *</td>
                  <td>
                    <input class="form-control" type="text" name="username" value="<?= htmlspecialchars($selectedWarung['username'] ?? ''); ?>" placeholder="warung01" required>
                    <small class="text-secondary"><?= $_login_portal_hint ?? 'Digunakan untuk login ke portal /warung.'; ?></small>
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_warung_pin ?? 'PIN Transaksi (4 Digit Angka)'; ?> <?= $isEdit ? '' : '*'; ?></td>
                  <td>
                    <div style="display: flex; gap: 6px; align-items: center; max-width: 320px;">
                      <input class="form-control" type="text" inputmode="numeric" pattern="[0-9]{4}" name="pin" id="_inputPin" placeholder="<?= $isEdit ? ($_leave_blank_pin ?? 'Kosongkan jika tidak ubah PIN') : '1234'; ?>" maxlength="4" style="font-family: monospace; font-size: 15px; letter-spacing: 2px;" <?= $isEdit ? '' : 'required'; ?>>
                      <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('_inputPin').value = String(Math.floor(1000 + Math.random() * 9000));" title="<?= $_random_pin ?? 'Acak PIN Baru'; ?>">
                        <i class="fa fa-random"></i> <?= $_random ?? 'Acak'; ?>
                      </button>
                    </div>
                    <small class="text-secondary"><?= $_warung_pin_hint ?? 'PIN angka 4 digit untuk login kasir warung & otorisasi transaksi.'; ?></small>
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_account_status ?? 'Status Akun'; ?></td>
                  <td>
                    <select class="form-control" name="status">
                      <option value="active" <?= ($selectedWarung['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>><?= $_active_can_transact ?? 'Aktif (Dapat Bertransaksi)'; ?></option>
                      <option value="inactive" <?= ($selectedWarung['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>><?= $_inactive_locked ?? 'Nonaktif / Terkunci'; ?></option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td class="align-middle"><?= $_custom_price ?? 'Harga Khusus'; ?></td>
                  <td>
                    <?php
                      $hasCustomPrices = !empty($selectedWarung['custom_prices']) && count($selectedWarung['custom_prices']) > 0;
                    ?>
                    <select class="form-control" name="enable_custom_price" onchange="document.getElementById('customPriceSection').style.display = (this.value === 'enable' ? '' : 'none')">
                      <option value="disable" <?= !$hasCustomPrices ? 'selected' : ''; ?>><?= $_disable ?? 'Disable'; ?></option>
                      <option value="enable" <?= $hasCustomPrices ? 'selected' : ''; ?>><?= $_enable ?? 'Enable'; ?></option>
                    </select>
                  </td>
                </tr>
                <tr id="customPriceSection" style="<?= !$hasCustomPrices ? 'display: none;' : ''; ?>">
                  <td colspan="2" style="padding-top: 10px;">
                    <table class="table table-bordered table-sm" style="margin-bottom: 0;">
                      <thead>
                        <tr>
                          <th><?= $_user_profile ?? 'Profil Voucher'; ?></th>
                          <th><?= $_warung_cost_price ?? 'Harga Modal Warung'; ?> (<?= htmlspecialchars($currency ?: 'Rp'); ?>)</th>
                          <th><?= $_consumer_sell_price ?? 'Harga Jual Konsumen'; ?> (<?= htmlspecialchars($currency ?: 'Rp'); ?>)</th>
                          <th style="width: 145px;"><?= $_profit_margin ?? 'Margin & Rasio'; ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($formHotspotProfiles)): ?>
                          <tr>
                            <td colspan="4" class="text-center text-secondary" style="font-size: 12px; padding: 10px;">
                              <?= $_no_profiles_detected_router ?? 'Belum ada profil hotspot yang terdeteksi untuk sesi router ini.'; ?>
                            </td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($formHotspotProfiles as $hp): ?>
                            <?php 
                              $pName = $hp['name'] ?? '';
                              if (empty($pName) || $pName === 'default') continue;
                              $wCustom = $selectedWarung['custom_prices'][$pName] ?? [];
                              $wSell = $wCustom['sell_price'] ?? '';
                              $wCost = $wCustom['cost_price'] ?? '';
                              $defSell = (float)($formPriceMap[$pName]['sell_price'] ?? 0);
                              $defCost = (float)($formPriceMap[$pName]['cost_price'] ?? 0);
                              $calcCost = ($wCost !== '' && (float)$wCost > 0) ? (float)$wCost : $defCost;
                              $calcSell = ($wSell !== '' && (float)$wSell > 0) ? (float)$wSell : $defSell;
                              $cpMr = mikhmon_calc_margin_ratio($calcCost, $calcSell);
                              $pHash = md5($pName);
                            ?>
                            <tr>
                              <td class="align-middle"><b><?= htmlspecialchars($pName); ?></b></td>
                              <td>
                                <input type="number" min="0" step="any" name="custom_prices[<?= htmlspecialchars($pName); ?>][cost_price]" value="<?= htmlspecialchars($wCost); ?>" class="form-control input-sm" placeholder="Sesi: <?= $defCost > 0 ? htmlspecialchars(mikhmon_format_money($defCost, $currency, $langid)) : '-'; ?>" id="cp_cost_<?= $pHash; ?>" oninput="calcCustomMargin('<?= $pHash; ?>', <?= $defCost; ?>, <?= $defSell; ?>)">
                              </td>
                              <td>
                                <input type="number" min="0" step="any" name="custom_prices[<?= htmlspecialchars($pName); ?>][sell_price]" value="<?= htmlspecialchars($wSell); ?>" class="form-control input-sm" placeholder="Sesi: <?= $defSell > 0 ? htmlspecialchars(mikhmon_format_money($defSell, $currency, $langid)) : '-'; ?>" id="cp_sell_<?= $pHash; ?>" oninput="calcCustomMargin('<?= $pHash; ?>', <?= $defCost; ?>, <?= $defSell; ?>)">
                              </td>
                              <td class="align-middle" id="cp_margin_<?= $pHash; ?>" style="white-space: nowrap;">
                                <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                  <b class="<?= $cpMr['is_profit'] ? 'text-primary' : 'text-danger'; ?>" style="font-size: 11px;">
                                    <?= mikhmon_format_margin($cpMr['margin'], $currency, $langid); ?>
                                  </b>
                                  <span class="badge <?= $cpMr['is_profit'] ? 'bg-success' : 'bg-danger'; ?>" style="font-size: 10px; padding: 2px 5px;">
                                    <?= ($cpMr['ratio'] >= 0 ? '+' : '') . number_format($cpMr['ratio'], 1); ?>%
                                  </span>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td style="padding-top: 15px;">
                    <button type="submit" class="btn bg-primary">
                      <i class="fa fa-save"></i> <?= $isEdit ? ($_save_changes ?? 'Simpan Perubahan') : ($_save_warung ?? 'Simpan Warung Baru'); ?>
                    </button>
                  </td>
                </tr>
              </table>
            </form>
          </div>
        </div>
      </div>

      <div class="col-4 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-info-circle"></i> <?= $_warung_instructions_links ?? 'Petunjuk & Tautan Warung'; ?></h3>
          </div>
          <div class="card-body text-secondary" style="font-size: 12px; line-height: 1.6;">
            <p style="margin-top: 0;">
              <?= $_warung_login_info_desc ?? 'Pemilik warung dapat langsung login di /warung dengan memasukkan Username dan PIN. Saldo warung dapat diisi manual atau mandiri via scan QRIS.'; ?>
            </p>
            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed rgba(128,128,128,0.2);">
              <a href="./warung.php?session=<?= urlencode(!empty($selectedWarung['router_session']) ? $selectedWarung['router_session'] : $session); ?>" target="_blank" class="btn bg-primary" style="display: block; text-align: center; text-decoration: none; font-weight: bold; width: 100%; box-sizing: border-box;">
                <i class="fa fa-external-link"></i> <?= $_open_warung_portal ?? 'Buka Portal Warung'; ?> (<code>/warung</code>)
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

  <!-- SUB-VIEW: ISI SALDO MANUAL / TOP UP (NATIVE FORM) -->
  <?php elseif ($activeTab === 'topup'): ?>
    <?php
      if (!$selectedWarung && !empty($warungList)) {
        $selectedWarung = $warungList[0];
      }
    ?>
    <div class="row">
      <div class="col-8 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-plus-circle"></i> <?= $_warung_topup_title ?? 'Top Up Saldo Deposit Warung'; ?></h3>
          </div>
          <div class="card-body">
            <?php if (empty($warungList)): ?>
              <div style="padding: 30px; text-align: center;">
                <i class="fa fa-info-circle" style="font-size: 32px; opacity: 0.5; margin-bottom: 10px; display: block;"></i>
                <?= $_warung_no_partners_registered ?? 'Belum ada mitra warung yang terdaftar. Silakan tambah warung terlebih dahulu.'; ?>
                <div style="margin-top: 12px;">
                  <a href="<?= $adminWarungBaseUrl ?>&tab=add<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-primary btn-sm">
                    <i class="fa fa-plus"></i> <?= $_warung_add_new_partner ?? 'Tambah Warung Baru'; ?>
                  </a>
                </div>
              </div>
            <?php else: ?>
              <form method="POST" action="<?= $adminWarungBaseUrl ?>&tab=topup<?= !empty($session) ? '&session='.$session : ''; ?>" onsubmit="var b=this.querySelector('button[type=submit]'); if(b){ b.disabled=true; b.innerHTML='<i class=\'fa fa-circle-o-notch fa-spin\'></i> Menyimpan...'; } return true;">
                <input type="hidden" name="action" value="topup_manual">

                <table class="table table-sm">
                  <tr>
                    <td class="align-middle" style="width: 35%;"><?= $_warung_target_partner ?? 'Warung Tujuan *'; ?></td>
                    <td>
                      <select class="form-control" name="warung_id" required onchange="location.href='<?= $adminWarungBaseUrl ?>&tab=topup&warung_id=' + this.value + '<?= !empty($session) ? '&session='.$session : '' ?>'">
                        <?php foreach ($warungList as $w): ?>
                          <option value="<?= htmlspecialchars($w['id']); ?>" <?= ($selectedWarung && $selectedWarung['id'] === $w['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($w['name']); ?> — Saldo: <?= mikhmon_format_money((float)($w['balance'] ?? 0), $currency, $langid); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                  <?php if ($selectedWarung): ?>
                    <tr>
                      <td class="align-middle"><?= $_warung_current_balance ?? 'Sisa Saldo Saat Ini'; ?></td>
                      <td><b class="text-green" style="font-size: 15px;"><?= mikhmon_format_money((float)($selectedWarung['balance'] ?? 0), $currency, $langid); ?></b></td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_warung_add_amount ?? 'Nominal Tambah'; ?> (<?= htmlspecialchars($currency ?: 'Rp'); ?>) *</td>
                      <td>
                        <input class="form-control" type="number" name="amount" id="topupAmtInput" placeholder="Contoh: 100000" min="1" step="any" required autofocus style="font-size: 15px; font-weight: bold;">
                        
                        <div style="display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap;">
                          <?php if (strtoupper($currency) === 'FCFA' || $langid === 'fr'): ?>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=1000">+1.000</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=5000">+5.000</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=10000">+10.000</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=25000">+25.000</button>
                          <?php elseif (strtoupper($currency) === '$' || strtoupper($currency) === 'USD'): ?>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=5">+$5</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=10">+$10</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=20">+$20</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=50">+$50</button>
                          <?php else: ?>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=50000">+50 Ribu</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=100000">+100 Ribu</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=200000">+200 Ribu</button>
                            <button type="button" class="btn bg-secondary btn-sm" onclick="document.getElementById('topupAmtInput').value=500000">+500 Ribu</button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_warung_th_notes ?? 'Keterangan / Catatan'; ?></td>
                      <td>
                        <input class="form-control" type="text" name="notes" value="<?= $_warung_default_topup_note ?? 'Setor tunai ke Admin'; ?>">
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                        <button type="submit" class="btn bg-success">
                          <i class="fa fa-check"></i> <?= $_warung_add_balance_btn ?? 'Tambah Saldo Warung'; ?>
                        </button>
                      </td>
                    </tr>
                  <?php endif; ?>
                </table>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-4 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-history"></i> Saldo Deposit Warung</h3>
          </div>
          <div class="card-body text-secondary" style="font-size: 12px; line-height: 1.6;">
            Gunakan fitur ini apabila pemilik warung menyetorkan uang tunai kepada Anda secara langsung untuk pengisian saldo kulakan voucher hotspot. Saldo akan bertambah seketika dan langsung dapat digunakan oleh kasir warung.
          </div>
        </div>
      </div>
    </div>

    <!-- RIWAYAT TOP-UP MANUAL SALDO WARUNG -->
    <?php
      $manualTopupList = [];
      if (!empty($warungData['transactions'])) {
        foreach (array_reverse($warungData['transactions']) as $tx) {
          if (($tx['type'] ?? '') === 'manual_topup') {
            $manualTopupList[] = $tx;
          }
        }
      }
    ?>
    <div class="row" style="margin-top: 20px;">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-history"></i> Riwayat Top-up Manual Saldo Warung</h3>
          </div>
          <div class="card-body">
            <?php if (empty($manualTopupList)): ?>
              <div style="padding: 30px; text-align: center; opacity: 0.7;">
                <i class="fa fa-history" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                Belum ada riwayat transaksi top-up manual saldo warung.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                  <thead>
                    <tr>
                      <th style="width: 14%;">Waktu</th>
                      <th style="width: 13%;">ID Transaksi</th>
                      <th style="width: 18%;">Mitra Warung</th>
                      <th style="width: 15%;">Nominal Top-up</th>
                      <th style="width: 15%;">Saldo Akhir</th>
                      <th>Keterangan</th>
                      <th style="width: 12%; text-align: center;">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($manualTopupList as $tx): ?>
                      <tr>
                        <td style="font-size: 11px;" class="text-secondary align-middle"><?= htmlspecialchars($tx['created_at'] ?? '-'); ?></td>
                        <td class="align-middle"><code><?= htmlspecialchars($tx['id'] ?? '-'); ?></code></td>
                        <td class="align-middle"><b><?= htmlspecialchars($tx['warung_name'] ?? '-'); ?></b></td>
                        <td class="align-middle text-green" style="font-weight: bold;">
                          +<?= mikhmon_format_money((float)($tx['amount'] ?? 0), $currency, $langid); ?>
                        </td>
                        <td class="align-middle" style="font-weight: bold;">
                          <?= mikhmon_format_money((float)($tx['balance'] ?? 0), $currency, $langid); ?>
                        </td>
                        <td class="align-middle text-secondary" style="font-size: 12px;">
                          <?= htmlspecialchars($tx['notes'] ?? '-'); ?>
                        </td>
                        <td class="align-middle text-center">
                          <form method="POST" action="<?= $adminWarungBaseUrl ?>&tab=topup<?= !empty($session) ? '&session='.$session : ''; ?>" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan/menghapus top-up ini?\n\nSaldo warung &quot;<?= htmlspecialchars(addslashes($tx['warung_name'] ?? '')); ?>&quot; akan ditarik/dikurangi kembali sebesar <?= htmlspecialchars(addslashes(mikhmon_format_money((float)($tx['amount'] ?? 0), $currency, $langid))); ?>.');">
                            <input type="hidden" name="action" value="delete_topup">
                            <input type="hidden" name="tx_id" value="<?= htmlspecialchars($tx['id'] ?? ''); ?>">
                            <button type="submit" class="btn bg-danger btn-sm" style="padding: 3px 8px; font-size: 11px;" title="Hapus Topup &amp; Tarik Saldo Warung">
                              <i class="fa fa-trash"></i> Hapus
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <!-- TAB 1: DAFTAR WARUNG (TABLE VIEW) -->
  <?php elseif ($activeTab === 'list'): ?>
    <?php
      $totalBalance = 0;
      foreach ($warungList as $w) $totalBalance += (float) ($w['balance'] ?? 0);
    ?>
    <div class="row">
      <div class="col-4 col-box-12">
        <div class="box bg-teal bmh-75">
          <a href="<?= $adminWarungBaseUrl ?>&tab=list<?= !empty($session) ? '&session='.$session : ''; ?>">
            <h1><?= count($warungList); ?> <span style="font-size: 14px;"><?= $_warung ?? 'warung'; ?></span></h1>
            <div><i class="fa fa-shopping-basket"></i> <?= $_total_warung_partners ?? 'Total Mitra Warung'; ?></div>
          </a>
        </div>
      </div>
      <div class="col-4 col-box-12">
        <div class="box bg-green bmh-75">
          <a href="<?= $adminWarungBaseUrl ?>&tab=list<?= !empty($session) ? '&session='.$session : ''; ?>">
            <h1><?= mikhmon_format_money($totalBalance, $currency, $langid); ?></h1>
            <div><i class="fa fa-money"></i> <?= $_circulating_balance ?? 'Saldo Warung Beredar'; ?></div>
          </a>
        </div>
      </div>
      <div class="col-4 col-box-12">
        <div class="box bg-blue bmh-75">
          <a href="<?= $adminWarungBaseUrl ?>&tab=reports<?= !empty($session) ? '&session='.$session : ''; ?>">
            <h1><?= count($txList); ?> <span style="font-size: 14px;"><?= $_transactions ?? 'transaksi'; ?></span></h1>
            <div><i class="fa fa-ticket"></i> <?= $_total_sales ?? 'Total Penjualan'; ?></div>
          </a>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <h3><i class="fa fa-shopping-basket"></i> <?= $_managed_warung_partners ?? 'Mitra Warung Binaan'; ?></h3>
            <a href="<?= $adminWarungBaseUrl ?>&tab=add<?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-primary btn-sm">
              <i class="fa fa-plus"></i> <?= $_add_new_warung ?? 'Tambah Warung Baru'; ?>
            </a>
          </div>
          <div class="card-body">
            <?php if (empty($warungList)): ?>
              <div style="padding: 30px; text-align: center; opacity: 0.7;">
                <i class="fa fa-shopping-basket" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                <?= $_no_registered_warung ?? 'Belum ada mitra warung yang terdaftar. Klik "Tambah Warung Baru" untuk mendaftarkan warung pertama Anda.'; ?>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                  <thead>
                    <tr>
                      <th style="width: 85px;"><?= $_warung_id ?? 'ID Warung'; ?></th>
                      <th><?= $_warung_name_store ?? 'Nama Warung'; ?></th>
                      <th><?= $_owner_name ?? 'Pemilik'; ?></th>
                      <th><?= $_username ?? 'Username'; ?></th>
                      <th><?= $_whatsapp_number ?? 'No. WhatsApp'; ?></th>
                      <th><?= $_router_session ?? 'Sesi Router'; ?></th>
                      <th><?= $_deposit_balance ?? 'Saldo Deposit'; ?></th>
                      <th class="text-center" style="width: 80px;"><?= $_status ?? 'Status'; ?></th>
                      <th class="text-center" style="width: 140px; white-space: nowrap;"><?= $_action ?? 'Aksi'; ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($warungList as $w): ?>
                      <?php
                        $wRouter = $w['router_session'] ?? '';
                        $hasCustomPrices = !empty($w['custom_prices']) && count($w['custom_prices']) > 0;
                      ?>
                      <tr>
                        <td class="align-middle">
                          <code><?= htmlspecialchars($w['id']); ?></code>
                        </td>
                        <td class="align-middle">
                          <b><?= htmlspecialchars($w['name']); ?></b>
                        </td>
                        <td class="align-middle">
                          <?= htmlspecialchars($w['owner'] ?: '-'); ?>
                        </td>
                        <td class="align-middle">
                          <code><?= htmlspecialchars($w['username']); ?></code>
                        </td>
                        <td class="align-middle">
                          <?php if (!empty($w['phone'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $w['phone']); ?>" target="_blank" class="text-green" style="text-decoration: none; font-weight: 500;">
                              <i class="fa fa-whatsapp"></i> <?= htmlspecialchars($w['phone']); ?>
                            </a>
                          <?php else: ?>
                            <span class="text-secondary">-</span>
                          <?php endif; ?>
                        </td>
                        <td class="align-middle">
                          <span class="badge bg-secondary" style="padding: 3px 6px; border-radius: 4px; font-size: 11px;">
                            <i class="fa fa-wifi"></i> <?= htmlspecialchars(!empty($wRouter) ? $wRouter : ($session ?: 'Auto')); ?>
                          </span>
                          <?php if ($hasCustomPrices): ?>
                            <br><span class="badge bg-primary" style="padding: 2px 5px; border-radius: 3px; font-size: 10px; margin-top: 3px; display: inline-block;">
                              <i class="fa fa-tags"></i> Harga Khusus (<?= count($w['custom_prices']); ?>)
                            </span>
                          <?php endif; ?>
                        </td>
                        <td class="align-middle">
                          <b class="text-green" style="font-size: 14px;"><?= mikhmon_format_money((float)($w['balance'] ?? 0), $currency, $langid); ?></b>
                        </td>
                        <td class="align-middle text-center">
                          <span class="badge bg-<?= ($w['status'] ?? 'active') === 'active' ? 'success' : 'danger'; ?>" style="padding: 3px 6px; border-radius: 4px; font-size: 11px;">
                            <?= strtoupper($w['status'] ?? 'active'); ?>
                          </span>
                        </td>
                        <td class="align-middle text-center" style="white-space: nowrap;">
                          <div style="display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                            <a href="./warung.php?session=<?= urlencode(!empty($wRouter) ? $wRouter : $session); ?>" target="_blank" class="btn bg-primary btn-sm" title="Buka Portal Warung" style="padding: 4px 8px;">
                              <i class="fa fa-external-link"></i>
                            </a>
                            <a href="<?= $adminWarungBaseUrl ?>&tab=topup&warung_id=<?= urlencode($w['id']); ?><?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-success btn-sm" title="Top Up Saldo" style="padding: 4px 8px;">
                              <i class="fa fa-money"></i>
                            </a>
                            <button type="button" class="btn bg-info btn-sm" title="Reset PIN Warung (4 Digit)" style="padding: 4px 8px; color: #fff;" onclick="promptResetPin('<?= htmlspecialchars($w['id']); ?>', '<?= htmlspecialchars(addslashes($w['name'])); ?>')">
                              <i class="fa fa-key"></i>
                            </button>
                            <a href="<?= $adminWarungBaseUrl ?>&tab=edit&warung_id=<?= urlencode($w['id']); ?><?= !empty($session) ? '&session='.$session : ''; ?>" class="btn bg-warning btn-sm" title="Edit Data Warung" style="padding: 4px 8px;">
                              <i class="fa fa-pencil"></i>
                            </a>
                            <form method="POST" style="display: inline-block; margin: 0; padding: 0;" onsubmit="return confirm('Hapus akun warung <?= htmlspecialchars(addslashes($w['name'])); ?>?');">
                              <input type="hidden" name="action" value="delete_warung">
                              <input type="hidden" name="warung_id" value="<?= htmlspecialchars($w['id']); ?>">
                              <button type="submit" class="btn bg-danger btn-sm" title="Hapus Warung" style="padding: 4px 8px;"><i class="fa fa-trash"></i></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <!-- TAB 2: HARGA KULAKAN PROFIL (GLOBAL) -->
  <?php elseif ($activeTab === 'prices'): ?>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <h3><i class="fa fa-tags"></i> Pengaturan Harga Modal Profil Hotspot (Per Sesi Router)</h3>
            <div style="display: flex; align-items: center; gap: 6px;">
              <?php if (isset($data) && is_array($data)): ?>
                <label style="margin: 0; font-size: 11px; font-weight: normal; color: #fff;">Pilih Sesi Router:</label>
                <select class="form-control input-sm" style="width: auto; display: inline-block; height: 28px; padding: 2px 8px; font-size: 12px;" onchange="window.location.href='<?= $adminWarungBaseUrl ?>&tab=prices&session=' + encodeURIComponent(this.value);">
                  <?php foreach ($data as $rKey => $rVal): ?>
                    <?php if ($rKey !== 'mikhmon' && is_array($rVal) && !empty($rVal[1])): ?>
                      <?php 
                        $rHs = $rVal[4] ?? $rKey;
                        $rCleanHs = strpos($rHs, '%') !== false ? explode('%', $rHs)[0] : $rHs;
                      ?>
                      <option value="<?= htmlspecialchars($rKey); ?>" <?= $rKey === $session ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($rCleanHs); ?> (<?= htmlspecialchars($rKey); ?>)
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              <?php elseif (!empty($session)): ?>
                <span class="badge bg-primary" style="font-size: 11px;">
                  <i class="fa fa-server"></i> Sesi: <?= htmlspecialchars($session); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <p class="text-secondary" style="font-size: 13px; margin-top: 0; margin-bottom: 15px;">
              Daftar profil di bawah ini diambil otomatis dari <b>User Profiles Hotspot MikroTik</b> untuk sesi <b><?= htmlspecialchars($session ?: 'Default'); ?></b>. Pengaturan harga ini tersimpan khusus untuk sesi router ini dan berlaku bagi mitra warung yang terhubung ke sesi ini.
            </p>

            <form method="POST">
              <input type="hidden" name="action" value="save_prices">
              <input type="hidden" name="target_session" value="<?= htmlspecialchars($session); ?>">
              <table class="table table-bordered table-hover table-sm">
                <thead>
                  <tr>
                    <th><?= $_user_profile ?? 'Nama Profil Hotspot'; ?></th>
                    <th style="width: 200px;"><?= $_warung_cost_price ?? 'Harga Modal Warung'; ?> (<?= htmlspecialchars($currency ?: 'Rp'); ?>)</th>
                    <th style="width: 200px;"><?= $_consumer_sell_price ?? 'Harga Jual Konsumen'; ?> (<?= htmlspecialchars($currency ?: 'Rp'); ?>)</th>
                    <th style="width: 175px;"><?= $_profit_margin ?? 'Margin & Rasio'; ?></th>
                    <th style="width: 130px;"><?= $_status ?? 'Status'; ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($hotspotProfiles)): ?>
                    <tr>
                      <td colspan="5" style="text-align: center; padding: 30px;" class="text-secondary">
                        <i class="fa fa-info-circle" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
                        Tidak ada profil hotspot yang terdeteksi di MikroTik router.<br>
                        Pastikan sesi router MikroTik terhubung dan profil voucher telah dibuat di menu <b>Hotspot &rarr; User Profiles</b>.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($hotspotProfiles as $hp): ?>
                      <?php
                        $pName = $hp['name'] ?? '';
                        if ($pName === 'default' || empty($pName)) continue;
                        $curSell = (float) ($priceMap[$pName]['sell_price'] ?? 0);
                        $curCost = (float) ($priceMap[$pName]['cost_price'] ?? 0);
                        $curStatus = ($priceMap[$pName]['status'] ?? 'enable') === 'disable' ? 'disable' : 'enable';
                        $mr = mikhmon_calc_margin_ratio($curCost, $curSell);
                        $pHash = md5($pName);
                      ?>
                      <tr>
                        <td class="align-middle">
                          <b><?= htmlspecialchars($pName); ?></b>
                          <?php if (!empty($hp['validity'])): ?>
                            <br><small class="text-secondary"><i class="fa fa-clock-o"></i> <?= htmlspecialchars($hp['validity']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <input type="number" name="prices[<?= htmlspecialchars($pName); ?>][cost_price]" value="<?= $curCost; ?>" class="form-control" placeholder="Contoh: 4000" min="0" step="any" required id="cost_<?= $pHash; ?>" oninput="calcMargin('<?= $pHash; ?>')">
                        </td>
                        <td>
                          <input type="number" name="prices[<?= htmlspecialchars($pName); ?>][sell_price]" value="<?= $curSell; ?>" class="form-control" placeholder="Contoh: 5000" min="0" step="any" required id="sell_<?= $pHash; ?>" oninput="calcMargin('<?= $pHash; ?>')">
                        </td>
                        <td class="align-middle">
                          <div id="margin_<?= $pHash; ?>" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <b class="<?= $mr['is_profit'] ? 'text-primary' : 'text-danger'; ?>" style="font-size: 13px;">
                              <?= mikhmon_format_margin($mr['margin'], $currency, $langid); ?>
                            </b>
                            <span class="badge <?= $mr['is_profit'] ? 'bg-success' : 'bg-danger'; ?>" style="font-size: 11px; padding: 2px 6px;">
                              <?= ($mr['ratio'] >= 0 ? '+' : '') . number_format($mr['ratio'], 1); ?>%
                            </span>
                          </div>
                        </td>
                        <td class="align-middle">
                          <select name="prices[<?= htmlspecialchars($pName); ?>][status]" class="form-control">
                            <option value="enable" <?= $curStatus === 'enable' ? 'selected' : ''; ?>>Enable</option>
                            <option value="disable" <?= $curStatus === 'disable' ? 'selected' : ''; ?>>Disable</option>
                          </select>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>

              <?php if (!empty($hotspotProfiles)): ?>
                <div style="margin-top: 15px; text-align: right;">
                  <button type="submit" class="btn bg-primary">
                    <i class="fa fa-save"></i> Simpan Pengaturan Harga
                  </button>
                </div>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    </div>

  <!-- TAB: PENGATURAN OPERASIONAL WARUNG -->
  <?php elseif ($activeTab === 'settings'): ?>
    <?php $wSettings = warung_get_settings(); ?>
    <div class="row">
      <div class="col-8 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-cog"></i> Pengaturan Operasional Warung</h3>
          </div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="action" value="save_settings">
              <table class="table table-sm">
                <tbody>
                  <tr>
                    <td style="width: 32%; font-weight: bold; vertical-align: middle;">Metode Stok Voucher</td>
                    <td>
                      <select name="stock_mode" class="form-control">
                        <option value="auto" <?= ($wSettings['stock_mode'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Otomatis (Prioritas Pool, Fallback On-Demand)</option>
                        <option value="pool" <?= ($wSettings['stock_mode'] ?? '') === 'pool' ? 'selected' : ''; ?>>Pool Voucher (Ambil dari Stok MikroTik)</option>
                        <option value="ondemand" <?= ($wSettings['stock_mode'] ?? '') === 'ondemand' ? 'selected' : ''; ?>>On-Demand (Generate Baru Setiap Pembelian)</option>
                      </select>
                      <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 4px;">
                        Mode <b>Pool Voucher</b> dan <b>Otomatis</b> akan menampilkan jumlah sisa stok voucher di dashboard kasir warung.
                      </small>
                    </td>
                  </tr>
                  <tr>
                    <td style="font-weight: bold; vertical-align: middle;">Prefix Kode Voucher</td>
                    <td>
                      <input type="text" name="voucher_prefix" class="form-control" value="<?= htmlspecialchars($wSettings['voucher_prefix'] ?? 'VC-'); ?>" placeholder="Contoh: VC-" maxlength="8">
                      <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 4px;">
                        Awalan kode voucher saat digenerate on-demand (contoh: <code>VC-</code>, <code>WRG-</code>, atau kosong).
                      </small>
                    </td>
                  </tr>
                  <tr>
                    <td style="font-weight: bold; vertical-align: middle;">Panjang Karakter Kode</td>
                    <td>
                      <select name="char_length" class="form-control">
                        <option value="3" <?= intval($wSettings['char_length'] ?? 6) === 3 ? 'selected' : ''; ?>>3 Karakter</option>
                        <option value="4" <?= intval($wSettings['char_length'] ?? 6) === 4 ? 'selected' : ''; ?>>4 Karakter</option>
                        <option value="5" <?= intval($wSettings['char_length'] ?? 6) === 5 ? 'selected' : ''; ?>>5 Karakter</option>
                        <option value="6" <?= intval($wSettings['char_length'] ?? 6) === 6 ? 'selected' : ''; ?>>6 Karakter (Standar)</option>
                        <option value="7" <?= intval($wSettings['char_length'] ?? 6) === 7 ? 'selected' : ''; ?>>7 Karakter</option>
                        <option value="8" <?= intval($wSettings['char_length'] ?? 6) === 8 ? 'selected' : ''; ?>>8 Karakter</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td style="font-weight: bold; vertical-align: middle;">Format Karakter Kode</td>
                    <td>
                      <select name="char_format" class="form-control">
                        <option value="mix1" <?= in_array($wSettings['char_format'] ?? 'mix1', ['mix1', 'mix_upper']) ? 'selected' : ''; ?>>Random 5AB2C34D (Huruf Besar &amp; Angka - Standar Mikhmon)</option>
                        <option value="mix" <?= in_array($wSettings['char_format'] ?? '', ['mix', 'mix_lower']) ? 'selected' : ''; ?>>Random 5ab2c34d (Huruf Kecil &amp; Angka)</option>
                        <option value="mix2" <?= ($wSettings['char_format'] ?? '') === 'mix2' ? 'selected' : ''; ?>>Random 5aB2c34D (Campur Huruf Besar, Kecil &amp; Angka)</option>
                        <option value="upper" <?= ($wSettings['char_format'] ?? '') === 'upper' ? 'selected' : ''; ?>>Random ABCD2345 (Huruf Besar)</option>
                        <option value="lower" <?= ($wSettings['char_format'] ?? '') === 'lower' ? 'selected' : ''; ?>>Random abcd2345 (Huruf Kecil)</option>
                        <option value="upplow" <?= ($wSettings['char_format'] ?? '') === 'upplow' ? 'selected' : ''; ?>>Random aBcD2345 (Huruf Besar &amp; Kecil)</option>
                        <option value="num" <?= in_array($wSettings['char_format'] ?? '', ['num', 'numeric']) ? 'selected' : ''; ?>>Random 1234 (Hanya Angka)</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td style="font-weight: bold; vertical-align: middle;">Pembersihan Otomatis Riwayat</td>
                    <td>
                      <select name="auto_purge_days" class="form-control">
                        <option value="30" <?= intval($wSettings['auto_purge_days'] ?? 30) === 30 ? 'selected' : ''; ?>>1 Bulan (30 Hari) — Sangat Disarankan</option>
                        <option value="60" <?= intval($wSettings['auto_purge_days'] ?? 30) === 60 ? 'selected' : ''; ?>>2 Bulan (60 Hari)</option>
                        <option value="90" <?= intval($wSettings['auto_purge_days'] ?? 30) === 90 ? 'selected' : ''; ?>>3 Bulan (90 Hari)</option>
                        <option value="0" <?= intval($wSettings['auto_purge_days'] ?? 30) === 0 ? 'selected' : ''; ?>>Nonaktifkan Pembersihan Otomatis</option>
                      </select>
                      <small class="text-secondary" style="font-size: 11px; display: block; margin-top: 4px;">
                        Menghapus log transaksi lama secara otomatis agar file database tidak menumpuk dan server VPS tetap ringan.
                      </small>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td style="padding-top: 15px;">
                      <button type="submit" class="btn bg-primary">
                        <i class="fa fa-save"></i> Simpan Pengaturan
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </div>

      <!-- Kolom Kanan: Manual Maintenance & Penjelasan Metode Stok -->
      <div class="col-4 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-database"></i> Pembersihan Data Manual</h3>
          </div>
          <div class="card-body" style="font-size: 12px; line-height: 1.6;">
            <p class="text-secondary" style="margin-top: 0;">
              Total riwayat transaksi saat ini: <b><?= count($txList); ?> transaksi</b>.
            </p>
            <p class="text-secondary">
              Anda dapat membersihkan data transaksi yang lebih tua dari batas periode secara instan untuk menghemat ruang penyimpanan server.
            </p>
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membersihkan riwayat transaksi kadaluarsa sekarang?');">
              <input type="hidden" name="action" value="manual_purge">
              <button type="submit" class="btn bg-warning btn-block" style="width: 100%; box-sizing: border-box;">
                <i class="fa fa-trash"></i> Bersihkan Riwayat Kadaluarsa
              </button>
            </form>
          </div>
        </div>

        <div class="card" style="margin-top: 15px;">
          <div class="card-header">
            <h3><i class="fa fa-info-circle"></i> Penjelasan Metode Stok</h3>
          </div>
          <div class="card-body" style="font-size: 12px; line-height: 1.6;">
            <div style="margin-bottom: 12px;">
              <b class="text-green"><i class="fa fa-bolt"></i> Otomatis (Rekomendasi)</b>
              <p class="text-secondary" style="margin: 2px 0 0 0;">
                Sistem memprioritaskan stok voucher yang ada di pool MikroTik. Jika stok habis, sistem secara otomatis men-generate voucher baru on-demand sehingga warung tidak terhambat.
              </p>
            </div>
            <div style="margin-bottom: 12px;">
              <b class="text-primary"><i class="fa fa-cubes"></i> Pool Voucher</b>
              <p class="text-secondary" style="margin: 2px 0 0 0;">
                Mengambil voucher yang sudah di-generate sebelumnya di MikroTik dan belum digunakan. Sisa stok ditampilkan real-time di kasir warung.
              </p>
            </div>
            <div>
              <b class="text-warning"><i class="fa fa-plus-circle"></i> On-Demand</b>
              <p class="text-secondary" style="margin: 2px 0 0 0;">
                Voucher baru selalu dibuatkan langsung ke MikroTik setiap kali kasir menekan tombol Beli, sesuai prefix dan format karakter yang ditentukan.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

  <!-- TAB 3: LAPORAN PENJUALAN -->
  <?php elseif ($activeTab === 'reports'): ?>
    <?php
      $voucherSalesList = [];
      if (!empty($warungData['transactions'])) {
        foreach (array_reverse($warungData['transactions']) as $tx) {
          if (($tx['type'] ?? '') === 'voucher') {
            $voucherSalesList[] = $tx;
          }
        }
      }
    ?>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-shopping-cart"></i> Riwayat Penjualan Voucher Warung</h3>
          </div>
          <div class="card-body">
            <?php if (empty($voucherSalesList)): ?>
              <div style="padding: 30px; text-align: center; opacity: 0.7;">
                <i class="fa fa-shopping-cart" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                Belum ada riwayat transaksi penjualan voucher dari kasir warung.
              </div>
            <?php else: ?>
              <table class="table table-bordered table-hover table-sm">
                <thead>
                  <tr>
                    <th>Waktu</th>
                    <th>Warung</th>
                    <th>Profil Voucher</th>
                    <th>Kode Voucher</th>
                    <th>Harga Jual / Modal</th>
                    <th>Margin Warung</th>
                    <th>No. WA Pembeli</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($voucherSalesList as $tx): ?>
                    <tr>
                      <td style="font-size: 11px;" class="text-secondary align-middle"><?= htmlspecialchars($tx['created_at']); ?></td>
                      <td class="align-middle"><b><?= htmlspecialchars($tx['warung_name'] ?? '-'); ?></b></td>
                      <td class="align-middle">
                        <span class="badge bg-primary" style="padding: 3px 6px; border-radius: 4px; font-size: 11px;">VOUCHER</span>
                        <?= htmlspecialchars($tx['profile'] ?? '-'); ?>
                      </td>
                      <td class="align-middle">
                        <?php if (!empty($tx['username'])): ?>
                          <code><?= htmlspecialchars($tx['username']); ?></code>
                        <?php else: ?>
                          -
                        <?php endif; ?>
                      </td>
                      <td class="align-middle">
                        <span class="text-green"><?= mikhmon_format_money((float)($tx['sell_price'] ?? 0), $currency, $langid); ?></span> /
                        <span class="text-secondary"><?= mikhmon_format_money((float)($tx['cost_price'] ?? 0), $currency, $langid); ?></span>
                      </td>
                      <td class="align-middle">
                        <b class="text-primary"><?= mikhmon_format_margin((float)($tx['margin'] ?? 0), $currency, $langid); ?></b>
                      </td>
                      <td style="font-size: 12px;" class="align-middle">
                        <?= htmlspecialchars($tx['buyer_phone'] ?: '-'); ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <!-- TAB 4: AKSES PORTAL WARUNG -->
  <?php elseif ($activeTab === 'portal'): ?>
    <div class="row">
      <div class="col-8 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-qrcode"></i> Link Akses Portal Warung</h3>
          </div>
          <div class="card-body">
            <p class="text-secondary" style="font-size: 13px; margin-top: 0; line-height: 1.6;">
              Bagikan tautan portal di bawah ini kepada pemilik warung mitra Anda. Pemilik warung dapat membukanya di browser HP dan mengklik <b>"Install Aplikasi (PWA)"</b> untuk mengakses warung secara cepat dari layar utama HP.
            </p>

            <table class="table table-sm">
              <tr>
                <td class="align-middle" style="width: 25%;"><b>URL Warung:</b></td>
                <td>
                  <div class="input-group">
                    <input type="text" class="form-control" readonly value="<?= htmlspecialchars($warungPortalUrl); ?>" id="inputWarungUrl">
                  </div>
                </td>
              </tr>
            </table>

            <div style="margin-top: 15px; display: flex; gap: 8px;">
              <button type="button" class="btn bg-primary" onclick="copyWarungUrl()">
                <i class="fa fa-copy"></i> Salin Link Warung
              </button>
              <a href="<?= htmlspecialchars($warungPortalUrl); ?>" target="_blank" class="btn bg-success">
                <i class="fa fa-external-link"></i> Buka Portal Warung
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-4 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-qrcode"></i> QR Code Warung</h3>
          </div>
          <div class="card-body text-center">
            <p class="text-secondary" style="font-size: 11px; margin-top: 0; margin-bottom: 12px;">Scan dengan kamera HP untuk membuka langsung portal warung.</p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($warungPortalUrl); ?>" alt="QR Warung" style="border-radius: 6px; padding: 6px; background: #fff; border: 1px solid rgba(128,128,128,0.2);">
          </div>
        </div>
      </div>
    </div>

  <!-- TAB 5: STATUS LISENSI -->
  <?php elseif ($activeTab === 'license'): ?>
    <div class="row">
      <div class="col-8 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-shield"></i> Status Lisensi Fitur Warung</h3>
          </div>
          <div class="card-body">
            <div style="padding: 15px; background: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 15px;">
              <h4 style="margin: 0 0 5px 0; color: #28a745;"><i class="fa fa-check-circle"></i> Lisensi Add-on Aktif</h4>
              <p style="margin: 0; font-size: 13px;">Fitur Warung &amp; Reseller Voucher Hotspot telah aktif pada domain ini dan siap digunakan tanpa batas waktu.</p>
            </div>

            <table class="table table-sm">
              <tr>
                <td style="width: 35%;"><b>Nama Add-on:</b></td>
                <td>Warung &amp; Reseller Voucher Hotspot</td>
              </tr>
              <tr>
                <td><b>Status Lisensi:</b></td>
                <td><span class="badge bg-success" style="padding: 4px 8px; border-radius: 4px;">AKTIF SEUMUR HIDUP</span></td>
              </tr>
              <tr>
                <td><b>Domain Mikhmon:</b></td>
                <td><code><?= htmlspecialchars($hostHeader ?? $subdomain); ?></code></td>
              </tr>
              <tr>
                <td><b>Versi Template:</b></td>
                <td>Mikhmon Native v7 (NODERA Edition)</td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <div class="col-4 col-box-12">
        <div class="card">
          <div class="card-header">
            <h3><i class="fa fa-money"></i> Saldo Panel Master</h3>
          </div>
          <div class="card-body text-center">
            <div style="font-size: 12px; opacity: 0.7; margin-bottom: 5px;">Saldo Billing VPN / Panel</div>
            <div style="font-size: 24px; font-weight: bold; color: #28a745; margin-bottom: 10px;">
              <?= htmlspecialchars($userBalanceFmt ?? $tenantBalanceFmt); ?>
            </div>
            <p class="text-secondary" style="font-size: 11px; margin: 0; line-height: 1.5;">
              Saldo panel digunakan untuk tagihan perpanjangan hosting Mikhmon &amp; langganan VPN Remote.
            </p>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<script>
function activateWarungAddon() {
  var btn = document.getElementById('btnActivateAddon');
  if (!confirm('Aktifkan Fitur Warung sekarang? Saldo panel Anda akan dipotong <?= htmlspecialchars($addonPriceFmt); ?>.')) {
    return;
  }
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengaktifkan...';

  $.ajax({
    url: '<?= $adminWarungBaseUrl ?>&action=ajax_activate_addon',
    type: 'POST',
    dataType: 'json',
    success: function(res) {
      if (res && res.success) {
        alert('Selamat! ' + (res.message || 'Fitur Warung berhasil diaktifkan!'));
        window.location.reload();
      } else {
        alert('Gagal: ' + (res.error || res.message || 'Terjadi kesalahan saat aktivasi.'));
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-bolt"></i> ' + (window.warungI18n ? window.warungI18n.activateBtn : 'Aktifkan Fitur Warung Sekarang');
      }
    },
    error: function(xhr, status, error) {
      var errMsg = 'Gagal menghubungi server.';
      try {
        var json = JSON.parse(xhr.responseText);
        if (json && (json.error || json.message)) {
          errMsg = json.error || json.message;
        }
      } catch(e) {}
      alert('Gagal: ' + errMsg);
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-bolt"></i> ' + (window.warungI18n ? window.warungI18n.activateBtn : 'Aktifkan Fitur Warung Sekarang');
    }
  });
}

var mikhmonCurrency = <?= json_encode($currency ?: 'Rp'); ?>;
var mikhmonLang = <?= json_encode($langid ?? 'id'); ?>;

function formatMoneyJs(num, curr, lang) {
  num = parseFloat(num) || 0;
  var currUpper = (curr || 'RP').toUpperCase().trim();
  var decimals = (Math.floor(num) !== num) ? 2 : 0;
  var formatted = num.toLocaleString(lang === 'fr' ? 'fr-FR' : 'id-ID', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: 2
  });
  if (currUpper === 'FCFA' || lang === 'fr') {
    return formatted + ' FCFA';
  } else if (currUpper === '$' || currUpper === 'USD') {
    return '$ ' + formatted;
  } else if (currUpper === 'EUR' || currUpper === '€') {
    return '€ ' + formatted;
  } else {
    return curr + ' ' + formatted;
  }
}

function calcMargin(hash) {
  var sellEl = document.getElementById('sell_' + hash);
  var costEl = document.getElementById('cost_' + hash);
  var sell = sellEl ? parseFloat(sellEl.value) : 0;
  var cost = costEl ? parseFloat(costEl.value) : 0;
  if (isNaN(sell)) sell = 0;
  if (isNaN(cost)) cost = 0;
  var margin = sell - cost;
  var ratio = 0;
  if (cost > 0) {
    ratio = (margin / cost) * 100;
  } else if (sell > 0) {
    ratio = 100;
  }
  var el = document.getElementById('margin_' + hash);
  if (el) {
    var sign = margin >= 0 ? '+' : '-';
    var ratioSign = ratio >= 0 ? '+' : '';
    var badgeClass = margin >= 0 ? 'bg-success' : 'bg-danger';
    var textClass = margin >= 0 ? 'text-primary' : 'text-danger';
    var formatted = formatMoneyJs(Math.abs(margin), mikhmonCurrency, mikhmonLang);
    el.innerHTML = '<div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">' +
                   '<b class="' + textClass + '" style="font-size: 13px;">' + sign + formatted + '</b> ' +
                   '<span class="badge ' + badgeClass + '" style="font-size: 11px; padding: 2px 6px;">' +
                   ratioSign + ratio.toFixed(1) + '%' +
                   '</span>' +
                   '</div>';
  }
}

function calcCustomMargin(hash, defCost, defSell) {
  var costIn = document.getElementById('cp_cost_' + hash);
  var sellIn = document.getElementById('cp_sell_' + hash);
  var costVal = costIn ? costIn.value.trim() : '';
  var sellVal = sellIn ? sellIn.value.trim() : '';

  var cost = (costVal !== '' && !isNaN(parseFloat(costVal))) ? parseFloat(costVal) : parseFloat(defCost || 0);
  var sell = (sellVal !== '' && !isNaN(parseFloat(sellVal))) ? parseFloat(sellVal) : parseFloat(defSell || 0);

  var margin = sell - cost;
  var ratio = 0;
  if (cost > 0) {
    ratio = (margin / cost) * 100;
  } else if (sell > 0) {
    ratio = 100;
  }
  var el = document.getElementById('cp_margin_' + hash);
  if (el) {
    var sign = margin >= 0 ? '+' : '-';
    var ratioSign = ratio >= 0 ? '+' : '';
    var badgeClass = margin >= 0 ? 'bg-success' : 'bg-danger';
    var textClass = margin >= 0 ? 'text-primary' : 'text-danger';
    var formatted = formatMoneyJs(Math.abs(margin), mikhmonCurrency, mikhmonLang);
    el.innerHTML = '<div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">' +
                   '<b class="' + textClass + '" style="font-size: 11px;">' + sign + formatted + '</b> ' +
                   '<span class="badge ' + badgeClass + '" style="font-size: 10px; padding: 2px 5px;">' +
                   ratioSign + ratio.toFixed(1) + '%' +
                   '</span>' +
                   '</div>';
  }
}

function promptResetPin(warungId, warungName) {
  var randomPin = String(Math.floor(1000 + Math.random() * 9000));
  var inputPin = prompt('Masukkan PIN Baru (4 Digit Angka) untuk warung "' + warungName + '":\n(Atau tekan OK langsung untuk menggunakan PIN acak di bawah)', randomPin);
  if (inputPin === null) return;
  inputPin = inputPin.replace(/[^0-9]/g, '');
  if (inputPin.length !== 4) {
    alert('PIN harus tepat 4 digit angka (contoh: 1234).');
    return;
  }
  
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = '<?= $adminWarungBaseUrl ?><?= !empty($session) ? '&session='.$session : ''; ?>';
  
  var act = document.createElement('input');
  act.type = 'hidden'; act.name = 'action'; act.value = 'reset_pin';
  form.appendChild(act);

  var idIn = document.createElement('input');
  idIn.type = 'hidden'; idIn.name = 'warung_id'; idIn.value = warungId;
  form.appendChild(idIn);

  var pinIn = document.createElement('input');
  pinIn.type = 'hidden'; pinIn.name = 'new_pin'; pinIn.value = inputPin;
  form.appendChild(pinIn);

  document.body.appendChild(form);
  form.submit();
}

function copyWarungUrl() {
  var input = document.getElementById('inputWarungUrl');
  input.select();
  input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value);
  alert('Link Kasir Warung berhasil disalin ke clipboard: ' + input.value);
}
</script>

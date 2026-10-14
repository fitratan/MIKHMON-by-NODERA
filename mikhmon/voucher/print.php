<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();

error_reporting(0);

ob_start("ob_gzhandler");

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  
  date_default_timezone_set($_SESSION['timezone']);
  
// load session MikroTik
  $session = $_GET['session'];

// load config
  include_once('../include/config.php');
  include_once('../include/readcfg.php');

  include_once('../lib/formatbytesbites.php');

  $id = $_GET['id'];
  $qr = $_GET['qr'];
  $small = $_GET['small'];
  $userp = $_GET['user'];

  require_once('../lib/routeros_api.class.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, mikhmon_decrypt($passwdhost));

  

  if ($userp != "") {
    $usermode = explode('-', $userp)[0];
    $pulluser = explode('-', $userp);
    $iuser = count($pulluser);
    $prefix = explode('-', $userp)[$iuser - 2];
    $user = explode('-', $userp)[$iuser - 1];
    if ($iuser == 3) {
      $user = $prefix . "-" . $user;
    } else {
      $user = $user;
    }
    $voucher_props = ".id,name,password,profile,limit-uptime,limit-bytes-total,comment";
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?name" => "$user",
      ".proplist" => "$voucher_props"
    ));
    $getuser = is_array($getuser) ? $getuser : array();
    $TotalReg = count($getuser);
  } elseif ($id != "") {
    $usermode = explode('-', $id)[0];
    $voucher_props = ".id,name,password,profile,limit-uptime,limit-bytes-total,comment";
    $getuser = $API->comm('/ip/hotspot/user/print', array(
      "?comment" => "$id",
      ".proplist" => "$voucher_props"
    ));
    $getuser = is_array($getuser) ? $getuser : array();
    $TotalReg = count($getuser);
  }
  $getuprofile = isset($getuser[0]['profile']) ? $getuser[0]['profile'] : '';


  $getprofile = $API->comm("/ip/hotspot/user/profile/print", array(
    "?name" => "$getuprofile",
    ".proplist" => "shared-users,on-login"
  ));
  $getsharedu = $getprofile[0]['shared-users'];
  $ponlogin = $getprofile[0]['on-login'];
  $validity = explode(",", $ponlogin)[3];
  $getprice = explode(",", $ponlogin)[2];
  $getsprice = explode(",", $ponlogin)[4];

 
  
    if($getsprice == "0" && $getprice != "0"){
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float)$getprice, 2);
      }
    }else if($getsprice != "0"){
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float)$getsprice, 2);
      }
    }else if ($getsprice == "0") {
      $price = "";
    }

    
  

  $imgDir = "../img/";
  $cleanSession = trim((string)$session);
  $candidates = [
    $imgDir . "logo-{$cleanSession}.png",
    $imgDir . "logo-{$cleanSession}.jpg",
    $imgDir . "logo-{$cleanSession}.jpeg",
    $imgDir . "logo-{$cleanSession}.webp",
    $imgDir . "logo-" . strtolower($cleanSession) . ".png",
    $imgDir . "logo-" . strtoupper($cleanSession) . ".png",
  ];

  $foundLogo = "";
  foreach ($candidates as $cand) {
    if (file_exists($cand)) {
      $foundLogo = $cand;
      break;
    }
  }

  if (empty($foundLogo) && is_dir($imgDir)) {
    $targetLower = strtolower("logo-{$cleanSession}");
    if ($dh = @opendir($imgDir)) {
      while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..') continue;
        $info = pathinfo($f);
        if (strtolower($info['filename'] ?? '') === $targetLower && in_array(strtolower($info['extension'] ?? ''), ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
          $foundLogo = $imgDir . $f;
          break;
        }
      }
      closedir($dh);
    }
  }

  // Check logo configuration preference
  $logoCfgFile = '../include/logo_config.php';
  $logoCfgData = [];
  if (file_exists($logoCfgFile)) {
    @include($logoCfgFile);
  }
  $useLogoInVoucher = !isset($logoCfgData[$cleanSession]['use_in_voucher']) || $logoCfgData[$cleanSession]['use_in_voucher'] !== 'no';

  if ($useLogoInVoucher) {
    if (!empty($foundLogo)) {
      $logo = $foundLogo . "?t=" . filemtime($foundLogo);
    } elseif (file_exists($imgDir . "logo.png")) {
      $logo = $imgDir . "logo.png?t=" . filemtime($imgDir . "logo.png");
    } else {
      $logo = $imgDir . "logo.png";
    }
  } else {
    $logo = "";
  }


  $thermal = $_GET['thermal'] ?? ($_GET['t'] ?? ($_GET['template'] ?? ''));
  $isThermal = ($userp != "" || $thermal === "yes" || $thermal === "thermal");
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Voucher-<?= $hotspotname . "-" . $getuprofile . "-" . $id; ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta http-equiv="pragma" content="no-cache" />
		<link rel="icon" href="../img/favicon.png" />
		<link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
		<script src="../js/qrious.min.js"></script>
		<style>
body {
  color: #000000;
  background-color: #FFFFFF;
  font-size: 13px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  margin: 0px;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.thermal-print-bar {
  position: sticky;
  top: 0;
  left: 0;
  right: 0;
  background: #1e293b;
  color: #fff;
  padding: 8px 12px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  z-index: 9999;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 13px;
}
.thermal-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 4px;
  border: none;
  font-size: 12px;
  font-weight: bold;
  cursor: pointer;
  text-decoration: none;
}
.thermal-btn-primary { background: #2563eb; color: #fff; }
.thermal-btn-primary:hover { background: #1d4ed8; }
.thermal-btn-secondary { background: #475569; color: #fff; }
.thermal-btn-secondary:hover { background: #334155; }
@media print {
  .thermal-print-bar { display: none !important; }
}
.voucher, table.voucher {
  display: inline-block;
  vertical-align: top;
  margin: 3px;
  page-break-inside: avoid;
}
@page {
  size: auto;
  margin-left: 5mm;
  margin-right: 5mm;
  margin-top: 5mm;
  margin-bottom: 5mm;
}
@media print {
  body { background: #fff !important; }
  table, tr, td, .voucher { page-break-inside: avoid !important; }
  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }
}
#num {
  float: right;
  display: inline-block;
}
		</style>
	</head>
	<body <?= !$isThermal ? 'onload="window.print()"' : ''; ?>>

  <?php if ($isThermal): ?>
    <div class="thermal-print-bar">
      <span><i class="fa fa-print text-primary"></i> <strong>Cetak Thermal:</strong></span>
      <button type="button" class="thermal-btn thermal-btn-primary" onclick="printAllThermalBt()" id="btnBtPrintAll">
        <i class="fa fa-bluetooth"></i> <span id="btnBtPrintAllText">Cetak via Bluetooth</span>
      </button>
      <button type="button" class="thermal-btn thermal-btn-secondary" onclick="window.print()">
        <i class="fa fa-print"></i> Cetak Browser
      </button>
      <span id="btPrintStatus" style="font-size: 11px; opacity: 0.85;"></span>
    </div>
  <?php endif; ?>

<?php for ($i = 0; $i < $TotalReg; $i++) {;
  $regtable = $getuser[$i];
  $uid = str_replace("=","",base64_encode($regtable['.id']));
  $idqr = str_replace("=","",base64_encode(($regtable['.id']."qr")));
  $username = $regtable['name'];
  $password = $regtable['password'];
  $profile = $regtable['profile'];
  $timelimit = $regtable['limit-uptime'];
  $getdatalimit = $regtable['limit-bytes-total'];
  $comment = $regtable['comment'];
  if ($getdatalimit == 0) {
    $datalimit = "";
  } else {
    $datalimit = formatBytes($getdatalimit, 2);
  }
  
  $urilogin = "http://$dnsname/login?username=$username&password=$password";
  $qrcode = "
	<canvas class='qrcode' id='".$uid."'></canvas>
    <script>
      (function() {
        var ".$uid." = new QRious({
          element: document.getElementById('".$uid."'),
          value: '".$urilogin."',
          size:'256'
        });

      })();
    </script>
	";
 
  $num = $i + 1;
  ?>
<?php
$thermal = $_GET['thermal'] ?? ($_GET['t'] ?? ($_GET['template'] ?? ''));
if ($userp != "" || $thermal === "yes" || $thermal === "thermal") {
  include('./template-thermal.php');
} else {
  if ($small == "yes") {
    include('./template-small.php');
  } else {
    include('./template.php');
  }
}
?>
<?php 
} ?>

<?php if ($isThermal): ?>
<script src="../js/mikhmon-thermal.js?v=<?= time(); ?>"></script>
<script>
function updatePrintBarBt(status, msg, thermalInstance) {
  var btnText = document.getElementById('btnBtPrintAllText');
  var statusEl = document.getElementById('btPrintStatus');
  if (status === 'connected') {
    if (btnText) btnText.innerHTML = 'Cetak ke ' + (thermalInstance.deviceName || 'Bluetooth');
    if (statusEl) statusEl.innerHTML = '<span style="color:#4ade80;"><i class="fa fa-circle"></i> Terhubung</span>';
  } else if (status === 'connecting') {
    if (statusEl) statusEl.innerHTML = '<span style="color:#facc15;"><i class="fa fa-circle-o-notch fa-spin"></i> Menghubungkan...</span>';
  } else {
    if (btnText) btnText.innerHTML = 'Cetak via Bluetooth';
    if (statusEl) statusEl.innerHTML = '<span style="color:#94a3b8;"><i class="fa fa-circle-o"></i> Belum Terhubung</span>';
  }
}

if (window.MikhmonThermal) {
  window.MikhmonThermal.addListener(updatePrintBarBt);
}

function printAllThermalBt() {
  if (!window.MikhmonThermal) return;
  var btnText = document.getElementById('btnBtPrintAllText');
  var origHtml = btnText ? btnText.innerHTML : 'Cetak via Bluetooth';

  var vouchers = [];
  var vEls = document.querySelectorAll('.voucher, .v-thermal');
  if (vEls.length > 0) {
    vEls.forEach(function(el) {
      var codeEl = el.querySelector('.v-th-code, .v-code, .qrcode');
      var code = codeEl ? codeEl.textContent.trim() : '';
      if (!code && codeEl && codeEl.id) code = codeEl.id;

      var profEl = el.querySelector('.v-th-meta, .v-profile');
      var prof = profEl ? profEl.textContent.trim() : '';

      var priceEl = el.querySelector('.v-th-price, .v-price');
      var price = priceEl ? priceEl.textContent.trim() : '';

      vouchers.push({
        hotspotName: <?= json_encode($hotspotname); ?>,
        dnsName: <?= json_encode($dnsname); ?>,
        code: code,
        profile: prof,
        price: price
      });
    });
  }

  function doPrint() {
    if (vouchers.length === 0) {
      alert('Tidak ada voucher yang dapat dicetak.');
      return;
    }
    if (btnText) btnText.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Mencetak (' + vouchers.length + ')...';
    var p = Promise.resolve();
    vouchers.forEach(function(v) {
      p = p.then(function() {
        return window.MikhmonThermal.printVoucher(v);
      }).then(function() {
        return new Promise(function(r) { setTimeout(r, 400); });
      });
    });

    p.then(function() {
      if (btnText) btnText.innerHTML = '<i class="fa fa-check"></i> Selesai Dicetak!';
      setTimeout(function() { if (btnText) btnText.innerHTML = origHtml; }, 2500);
    }).catch(function(err) {
      if (btnText) btnText.innerHTML = origHtml;
      alert('Gagal cetak Bluetooth: ' + (err.message || err));
    });
  }

  if (!window.MikhmonThermal.isConnected) {
    window.MikhmonThermal.connect().then(function() {
      doPrint();
    }).catch(function(err) {
      alert('Koneksi printer Bluetooth: ' + (err.message || err));
    });
  } else {
    doPrint();
  }
}
</script>
<?php endif; ?>
	
</body>
</html>

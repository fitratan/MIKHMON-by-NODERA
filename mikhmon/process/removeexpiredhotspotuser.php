<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified by NODERA (nodera.id)
 */
include_once(__DIR__ . '/../include/init_session.php');
error_reporting(0);
@set_time_limit(0);
@ini_set('max_execution_time', '0');

include_once __DIR__ . '/../include/telegram_helper.php';
?>
<script>
    (function() {
        var mc = document.querySelector('.main-container');
        if (mc) mc.style.display = 'block';
        var ld = document.getElementById('loading');
        if (ld) ld.style.display = 'none';
        if (typeof $ !== 'undefined') {
            $('.main-container').show();
            $('#loading').hide();
        }
    })();
</script>
<?php
try {
  $getuser = $API->comm("/ip/hotspot/user/print", array(
    "?limit-uptime" => "1s",
    ".proplist" => ".id,name,profile,comment,uptime,mac-address,caller-id"
  ));
  $getuser = is_array($getuser) ? $getuser : array();
  $TotalReg = count($getuser);

  $_SESSION['ubp'] = isset($getuser[0]['profile']) ? $getuser[0]['profile'] : '';
  $_SESSION['ubc'] = "";

  if ($TotalReg > 0) {
    // Pre-fetch schedulers and scripts ONCE to avoid hundreds of sequential roundtrips
    $allScheds = $API->comm("/system/scheduler/print", array(".proplist" => ".id,name"));
    $schedMap = array();
    if (is_array($allScheds)) {
      foreach ($allScheds as $sch) {
        if (!empty($sch['name']) && !empty($sch['.id'])) {
          $schedMap[$sch['name']] = $sch['.id'];
        }
      }
    }

    $allScripts = $API->comm("/system/script/print", array(".proplist" => ".id,name"));
    $scriptMap = array();
    if (is_array($allScripts)) {
      foreach ($allScripts as $scr) {
        if (!empty($scr['name']) && !empty($scr['.id'])) {
          $scriptMap[$scr['name']] = $scr['.id'];
        }
      }
    }

    $userIds = array();
    $schedIds = array();
    $scriptIds = array();

    for ($i = 0; $i < $TotalReg; $i++) {
      $u = $getuser[$i];
      $uname = $u['name'] ?? '';

      if (!empty($uname) && function_exists('mikhmon_send_user_voucher_expired_telegram')) {
        mikhmon_send_user_voucher_expired_telegram($session, [
          'username'      => $uname,
          'profile'       => $u['profile'] ?? '',
          'uptime'        => $u['uptime'] ?? '',
          'mac'           => $u['mac-address'] ?? ($u['caller-id'] ?? ''),
          'location_name' => $identity ?: ($session ?: 'Hotspot'),
          'expired_at'    => date('Y-m-d H:i:s'),
        ], 'Rp', null, true);
      }

      if (!empty($u['.id'])) {
        $userIds[] = $u['.id'];
      }

      if (!empty($uname)) {
        if (isset($schedMap[$uname])) {
          $schedIds[] = $schedMap[$uname];
        }
        if (isset($scriptMap[$uname])) {
          $scriptIds[] = $scriptMap[$uname];
        }
      }
    }

    // Batch remove hotspot users in chunks of 50
    $userChunks = array_chunk($userIds, 50);
    foreach ($userChunks as $chunk) {
      $API->comm("/ip/hotspot/user/remove", array(
        ".id" => implode(",", $chunk),
      ));
    }

    // Batch remove orphaned schedulers and scripts
    if (!empty($schedIds)) {
      $schedChunks = array_chunk($schedIds, 50);
      foreach ($schedChunks as $chunk) {
        @$API->comm("/system/scheduler/remove", array(
          ".id" => implode(",", $chunk),
        ));
      }
    }
    if (!empty($scriptIds)) {
      $scriptChunks = array_chunk($scriptIds, 50);
      foreach ($scriptChunks as $chunk) {
        @$API->comm("/system/script/remove", array(
          ".id" => implode(",", $chunk),
        ));
      }
    }
  }
} catch (\Throwable $e) {}

if (!empty($_SESSION['ubp'])) {
  echo "<script>window.location='./?hotspot=users&profile=" . urlencode($_SESSION['ubp']) . "&session=" . urlencode($session) . "'</script>";
} else {
  echo "<script>window.location='./?hotspot=users&profile=all&session=" . urlencode($session) . "'</script>";
}
?>

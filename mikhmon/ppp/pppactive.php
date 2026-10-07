<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified for NODERA Ecosystem.
 */
include_once(__DIR__ . '/../include/init_session.php');
// hide all error
error_reporting(0);
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
if (!isset($_SESSION["mikhmon"])) {
  echo '<script>window.location="./admin.php?id=login";</script>';
  return;
}

$getactive = array();
$iface_stats = array();

if (!empty($API) && !empty($API->connected)) {
    try {
        $getactive = $API->comm("/ppp/active/print");
        $getactive = is_array($getactive) ? $getactive : array();
    } catch (\Throwable $e) {
        $getactive = array();
    }

    try {
        $ifaces = $API->comm("/interface/print", array(
            ".proplist" => ".id,name,rx-byte,tx-byte"
        ));
        if (is_array($ifaces)) {
            foreach ($ifaces as $iface) {
                $iface_stats[$iface['name']] = array(
                    'rx' => (float)($iface['rx-byte'] ?? 0),
                    'tx' => (float)($iface['tx-byte'] ?? 0),
                );
            }
        }
    } catch (\Throwable $e) {}
}

$countactive = count($getactive);
$total_rx_all = 0;
$total_tx_all = 0;
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-exchange"></i> <?= $_ppp_active ?>
            <small class="text-muted" style="font-size: 13px; font-weight: normal; opacity: 0.85;">
              (<?= ($countactive < 2) ? "$countactive item" : "$countactive items"; ?>)
            </small>
          </h3>
          <div class="card-header-actions">
            <span onclick="location.reload();" class="header-action-btn pointer" title="<?= $_reload_data ?? "Reload data"; ?>"><i class="fa fa-refresh"></i></span>
          </div>
        </div>
      </div>
      <div class="card-body overflow">
        <div style="padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
          <input id="filterTable" type="text" class="form-control" style="max-width: 220px; padding: 5px 10px; margin: 0;" placeholder="<?= $_search ?>">
        </div>

        <table id="dataTable" class="table table-bordered table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width: 50px; text-align: center;"><?= $_action ?></th>
              <th><?= $_user_name ?></th>
              <th><?= $_service ?></th>
              <th><?= $_caller_id ?></th>
              <th><?= $_address ?? "IP Address" ?></th>
              <th class="text-right"><?= $_uptime ?></th>
              <th class="text-right"><?= $_bytes_in ?> (Upload)</th>
              <th class="text-right"><?= $_bytes_out ?> (Download)</th>
              <th class="text-right" style="font-weight: bold;"><?= $_total_quota ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($countactive === 0): ?>
              <tr>
                <td colspan="9" style="text-align: center; padding: 20px; color: #888;">
                  <i class="fa fa-info-circle"></i> <?= $_no_active_ppp ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($getactive as $act): 
                $actId = $act['.id'];
                $uname = $act['name'] ?? '';
                $service = $act['service'] ?? 'ppp';
                $caller = $act['caller-id'] ?? '-';
                $addr = $act['address'] ?? '-';
                $uptimeRaw = $act['uptime'] ?? '0s';
                $uptime = function_exists('formatDTM') ? formatDTM($uptimeRaw) : $uptimeRaw;

                // Match dynamic interface for real-time bytes
                $rx = 0;
                $tx = 0;
                $candidates = array(
                    '<' . $service . '-' . $uname . '>',
                    '<' . $uname . '>',
                    $service . '-' . $uname,
                    $uname,
                    '<' . strtolower($service) . '-' . $uname . '>',
                );
                foreach ($candidates as $cand) {
                    if (isset($iface_stats[$cand])) {
                        $rx = $iface_stats[$cand]['rx'];
                        $tx = $iface_stats[$cand]['tx'];
                        break;
                    }
                }
                $total_bytes = $rx + $tx;
                $total_rx_all += $rx;
                $total_tx_all += $tx;
              ?>
                <tr>
                  <td style="text-align: center;">
                    <i class="fa fa-times-circle text-danger pointer" 
                       onclick="if(confirm('<?= addslashes($_confirm_disconnect); ?> (<?= htmlspecialchars(addslashes($uname), ENT_QUOTES); ?>)')){loadpage('./?remove-pactive=<?= $actId; ?>&session=<?= $session; ?>')}else{}" 
                       title="<?= $_disconnect ?> <?= htmlspecialchars($uname); ?>"></i>
                  </td>
                  <td>
                    <a title="<?= $_edit_ppp_secret ?>: <?= htmlspecialchars($uname); ?>" 
                       href="./?ppp=edit-secret&secret=<?= urlencode($uname); ?>&session=<?= $session; ?>"
                       style="font-weight: bold;">
                      <i class="fa fa-user"></i> <?= htmlspecialchars($uname); ?>
                    </a>
                  </td>
                  <td>
                    <span class="badge" style="background-color: #20a8d8; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                      <?= htmlspecialchars($service); ?>
                    </span>
                  </td>
                  <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($caller); ?></td>
                  <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($addr); ?></td>
                  <td class="text-right" style="font-size: 12px;"><?= htmlspecialchars($uptime); ?></td>
                  <td class="text-right" style="font-size: 12px; color: #4dbd74; font-weight: 500;">
                    <i class="fa fa-arrow-up"></i> <?= formatBytes($rx, 2); ?>
                  </td>
                  <td class="text-right" style="font-size: 12px; color: #20a8d8; font-weight: 500;">
                    <i class="fa fa-arrow-down"></i> <?= formatBytes($tx, 2); ?>
                  </td>
                  <td class="text-right" style="font-size: 12px; font-weight: bold; color: #f86c6b;">
                    <?= formatBytes($total_bytes, 2); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if ($countactive > 0): ?>
            <tfoot>
              <tr style="font-weight: bold; background: rgba(0,0,0,0.03);">
                <td colspan="6" class="text-right">TOTAL:</td>
                <td class="text-right" style="color: #4dbd74;"><i class="fa fa-arrow-up"></i> <?= formatBytes($total_rx_all, 2); ?></td>
                <td class="text-right" style="color: #20a8d8;"><i class="fa fa-arrow-down"></i> <?= formatBytes($total_tx_all, 2); ?></td>
                <td class="text-right" style="color: #f86c6b;"><?= formatBytes($total_rx_all + $total_tx_all, 2); ?></td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>

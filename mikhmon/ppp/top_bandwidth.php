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

echo "<script>window.location='./?ppp=active&session=" . $session . "';</script>";
return;

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

$leaderboard = array();
$total_rx_all = 0;
$total_tx_all = 0;

foreach ($getactive as $act) {
    $uname = $act['name'] ?? '';
    $service = $act['service'] ?? 'ppp';
    $caller = $act['caller-id'] ?? '-';
    $addr = $act['address'] ?? '-';
    $uptimeRaw = $act['uptime'] ?? '0s';
    $uptime = function_exists('formatDTM') ? formatDTM($uptimeRaw) : $uptimeRaw;

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

    $total = $rx + $tx;
    $total_rx_all += $rx;
    $total_tx_all += $tx;

    $leaderboard[] = array(
        'id' => $act['.id'] ?? '',
        'name' => $uname,
        'service' => $service,
        'caller' => $caller,
        'address' => $addr,
        'uptime' => $uptime,
        'rx' => $rx,
        'tx' => $tx,
        'total' => $total,
    );
}

// Sort descending by total bytes
usort($leaderboard, function($a, $b) {
    if ($a['total'] === $b['total']) {
        return 0;
    }
    return ($a['total'] > $b['total']) ? -1 : 1;
});

$total_traffic_all = $total_rx_all + $total_tx_all;
$countleaderboard = count($leaderboard);
?>

<div class="row">
  <!-- Top Stat Widgets -->
  <div class="col-3 col-m-6">
    <div class="card" style="border-left: 4px solid #20a8d8; padding: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: bold;"><?= $_active_sessions ?></span>
          <h2 style="margin: 4px 0 0 0; font-size: 22px; font-weight: bold;"><?= $countleaderboard; ?></h2>
        </div>
        <div>
          <i class="fa fa-users text-muted" style="font-size: 28px; opacity: 0.3;"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-3 col-m-6">
    <div class="card" style="border-left: 4px solid #4dbd74; padding: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: bold;"><?= $_bytes_in ?> (Upload)</span>
          <h2 style="margin: 4px 0 0 0; font-size: 20px; font-weight: bold; color: #4dbd74;"><?= formatBytes($total_rx_all, 2); ?></h2>
        </div>
        <div>
          <i class="fa fa-arrow-up text-muted" style="font-size: 28px; opacity: 0.3;"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-3 col-m-6">
    <div class="card" style="border-left: 4px solid #63c2de; padding: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: bold;"><?= $_bytes_out ?> (Download)</span>
          <h2 style="margin: 4px 0 0 0; font-size: 20px; font-weight: bold; color: #20a8d8;"><?= formatBytes($total_tx_all, 2); ?></h2>
        </div>
        <div>
          <i class="fa fa-arrow-down text-muted" style="font-size: 28px; opacity: 0.3;"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-3 col-m-6">
    <div class="card" style="border-left: 4px solid #f86c6b; padding: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: bold;"><?= $_total_quota ?></span>
          <h2 style="margin: 4px 0 0 0; font-size: 20px; font-weight: bold; color: #f86c6b;"><?= formatBytes($total_traffic_all, 2); ?></h2>
        </div>
        <div>
          <i class="fa fa-tachometer text-muted" style="font-size: 28px; opacity: 0.3;"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Leaderboard Card -->
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-bar-chart"></i> <?= $_ppp_top_bandwidth ?>
            <small class="text-muted" style="font-size: 12px; font-weight: normal; opacity: 0.85;">
              (<?= $_top_bandwidth_desc ?>)
            </small>
          </h3>
          <div class="card-header-actions">
            <a href="./?ppp=active&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-exchange"></i> <?= $_ppp_active ?></a>
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
              <th style="width: 60px; text-align: center;"><?= $_rank ?></th>
              <th style="width: 50px; text-align: center;"><?= $_action ?></th>
              <th><?= $_user_name ?></th>
              <th><?= $_service ?></th>
              <th><?= $_address ?? "IP Address" ?></th>
              <th class="text-right"><?= $_uptime ?></th>
              <th class="text-right"><?= $_bytes_in ?> (Upload)</th>
              <th class="text-right"><?= $_bytes_out ?> (Download)</th>
              <th class="text-right" style="min-width: 180px;"><?= $_total_quota ?> & % Rasio</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($countleaderboard === 0): ?>
              <tr>
                <td colspan="9" style="text-align: center; padding: 20px; color: #888;">
                  <i class="fa fa-info-circle"></i> <?= $_no_active_ppp ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($leaderboard as $rankIdx => $row): 
                $rank = $rankIdx + 1;
                $pct = ($total_traffic_all > 0) ? round(($row['total'] / $total_traffic_all) * 100, 1) : 0;
                
                // Medal badges
                if ($rank === 1) {
                    $rankBadge = '<span style="font-size: 14px; font-weight: bold; color: #ffc107;" title="Rank 1">🥇 #1</span>';
                } elseif ($rank === 2) {
                    $rankBadge = '<span style="font-size: 14px; font-weight: bold; color: #c0c0c0;" title="Rank 2">🥈 #2</span>';
                } elseif ($rank === 3) {
                    $rankBadge = '<span style="font-size: 14px; font-weight: bold; color: #cd7f32;" title="Rank 3">🥉 #3</span>';
                } else {
                    $rankBadge = '<span class="text-muted" style="font-size: 12px; font-weight: bold;">#' . $rank . '</span>';
                }
              ?>
                <tr>
                  <td style="text-align: center; vertical-align: middle;"><?= $rankBadge ?></td>
                  <td style="text-align: center; vertical-align: middle;">
                    <i class="fa fa-times-circle text-danger pointer" 
                       onclick="if(confirm('<?= addslashes($_confirm_disconnect); ?> (<?= htmlspecialchars(addslashes($row['name']), ENT_QUOTES); ?>)')){loadpage('./?remove-pactive=<?= $row['id']; ?>&from=top&session=<?= $session; ?>')}else{}" 
                       title="<?= $_disconnect ?> <?= htmlspecialchars($row['name']); ?>"></i>
                  </td>
                  <td style="vertical-align: middle;">
                    <a title="<?= $_edit_ppp_secret ?>: <?= htmlspecialchars($row['name']); ?>" 
                       href="./?ppp=edit-secret&secret=<?= urlencode($row['name']); ?>&session=<?= $session; ?>"
                       style="font-weight: bold;">
                      <i class="fa fa-user"></i> <?= htmlspecialchars($row['name']); ?>
                    </a>
                  </td>
                  <td style="vertical-align: middle;">
                    <span class="badge" style="background-color: #20a8d8; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                      <?= htmlspecialchars($row['service']); ?>
                    </span>
                  </td>
                  <td style="vertical-align: middle; font-family: monospace; font-size: 12px;"><?= htmlspecialchars($row['address']); ?></td>
                  <td class="text-right" style="vertical-align: middle; font-size: 12px;"><?= htmlspecialchars($row['uptime']); ?></td>
                  <td class="text-right" style="vertical-align: middle; font-size: 12px; color: #4dbd74; font-weight: 500;">
                    <i class="fa fa-arrow-up"></i> <?= formatBytes($row['rx'], 2); ?>
                  </td>
                  <td class="text-right" style="vertical-align: middle; font-size: 12px; color: #20a8d8; font-weight: 500;">
                    <i class="fa fa-arrow-down"></i> <?= formatBytes($row['tx'], 2); ?>
                  </td>
                  <td class="text-right" style="vertical-align: middle;">
                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 3px;">
                      <span style="font-size: 12px; font-weight: bold; color: #f86c6b;">
                        <?= formatBytes($row['total'], 2); ?>
                      </span>
                      <div style="width: 100%; max-width: 150px; background: rgba(0,0,0,0.08); border-radius: 4px; height: 12px; overflow: hidden; position: relative;">
                        <div style="background: linear-gradient(90deg, #36a2eb, #4bc0c0); width: <?= min(100, max(2, $pct)); ?>%; height: 100%;"></div>
                      </div>
                      <span style="font-size: 10px; color: #888;"><?= $pct ?>% of total</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if ($countleaderboard > 0): ?>
            <tfoot>
              <tr style="font-weight: bold; background: rgba(0,0,0,0.03);">
                <td colspan="6" class="text-right">TOTAL:</td>
                <td class="text-right" style="color: #4dbd74;"><i class="fa fa-arrow-up"></i> <?= formatBytes($total_rx_all, 2); ?></td>
                <td class="text-right" style="color: #20a8d8;"><i class="fa fa-arrow-down"></i> <?= formatBytes($total_tx_all, 2); ?></td>
                <td class="text-right" style="color: #f86c6b;"><?= formatBytes($total_traffic_all, 2); ?></td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>

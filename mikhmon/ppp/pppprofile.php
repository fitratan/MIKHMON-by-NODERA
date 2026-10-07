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

$getprofiles = array();
if (!empty($API) && !empty($API->connected)) {
    try {
        $getprofiles = $API->comm("/ppp/profile/print");
        $getprofiles = is_array($getprofiles) ? $getprofiles : array();
    } catch (\Throwable $e) {
        $getprofiles = array();
    }
}
$countprofiles = count($getprofiles);
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?>
            <small class="text-muted" style="font-size: 13px; font-weight: normal; opacity: 0.85;">
              (<?= ($countprofiles < 2) ? "$countprofiles item" : "$countprofiles items"; ?>)
            </small>
          </h3>
          <div class="card-header-actions">
            <a href="./?ppp=add-profile&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-plus-square"></i> <?= $_add_ppp_profile ?></a>
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
              <th style="width: 70px; text-align: center;"><?= $_action ?></th>
              <th><?= $_name ?></th>
              <th><?= $_local_address ?></th>
              <th><?= $_remote_address ?></th>
              <th><?= $_rate_limit ?></th>
              <th><?= $_only_one ?></th>
              <th><?= $_dns_server ?></th>
              <th><?= $_comment ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($countprofiles === 0): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 20px; color: #888;">
                  <i class="fa fa-info-circle"></i> <?= $_no_ppp_profiles ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($getprofiles as $prof): 
                $pid = $prof['.id'];
                $pname = $prof['name'] ?? '';
                $plocal = $prof['local-address'] ?? '-';
                $premote = $prof['remote-address'] ?? '-';
                $pratelimit = $prof['rate-limit'] ?? '-';
                $ponlyone = $prof['only-one'] ?? 'default';
                $pdns = $prof['dns-server'] ?? '-';
                $pcomment = $prof['comment'] ?? '';
                $isDefault = in_array($pname, array('default', 'default-encryption'));
              ?>
                <tr>
                  <td style="text-align: center;">
                    <?php if (!$isDefault): ?>
                      <i class="fa fa-minus-square text-danger pointer" 
                         onclick="if(confirm('<?= addslashes($_confirm_delete_ppp_profile); ?> (<?= htmlspecialchars(addslashes($pname), ENT_QUOTES); ?>)')){loadpage('./?remove-pprofile=<?= $pid; ?>&session=<?= $session; ?>')}else{}" 
                         title="<?= $_delete ?> <?= htmlspecialchars($pname); ?>"></i>
                    <?php else: ?>
                      <span class="text-muted" title="Default profile cannot be deleted"><i class="fa fa-minus-square" style="opacity: 0.3;"></i></span>
                    <?php endif; ?>
                    &nbsp;&nbsp;
                    <a href="./?ppp=secrets&profile=<?= urlencode($pname); ?>&session=<?= $session; ?>" title="<?= $_ppp_secrets ?> (<?= htmlspecialchars($pname); ?>)">
                      <i class="fa fa-users"></i>
                    </a>
                  </td>
                  <td>
                    <a title="<?= $_edit_ppp_profile ?>: <?= htmlspecialchars($pname); ?>" 
                       href="./?ppp=edit-profile&profile=<?= urlencode($pid); ?>&session=<?= $session; ?>"
                       style="font-weight: bold;">
                      <i class="fa fa-edit"></i> <?= htmlspecialchars($pname); ?>
                    </a>
                  </td>
                  <td><?= htmlspecialchars($plocal); ?></td>
                  <td><?= htmlspecialchars($premote); ?></td>
                  <td>
                    <?php if ($pratelimit !== '-'): ?>
                      <span class="badge" style="background-color: #4dbd74; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                        <?= htmlspecialchars($pratelimit); ?>
                      </span>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($ponlyone); ?></td>
                  <td><?= htmlspecialchars($pdns); ?></td>
                  <td style="font-size: 12px;"><?= htmlspecialchars($pcomment); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

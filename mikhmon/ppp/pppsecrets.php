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

$getsecrets = array();
$getprofiles = array();

if (!empty($API) && !empty($API->connected)) {
    try {
        $prof_filter = $_GET['profile'] ?? '';
        if (!empty($prof_filter) && $prof_filter !== 'all') {
            $getsecrets = $API->comm("/ppp/secret/print", array(
                "?profile" => "$prof_filter"
            ));
        } else {
            $getsecrets = $API->comm("/ppp/secret/print");
        }
        $getsecrets = is_array($getsecrets) ? $getsecrets : array();
    } catch (\Throwable $e) {
        $getsecrets = array();
    }

    try {
        $getprofiles = $API->comm("/ppp/profile/print");
        $getprofiles = is_array($getprofiles) ? $getprofiles : array();
    } catch (\Throwable $e) {
        $getprofiles = array();
    }
}
$countsecrets = count($getsecrets);
?>
<script>
    function togglePass(idx) {
        var el = document.getElementById('pass_' + idx);
        var ico = document.getElementById('eye_' + idx);
        if (el) {
            if (el.getAttribute('data-hidden') === 'true') {
                el.innerText = el.getAttribute('data-raw');
                el.setAttribute('data-hidden', 'false');
                if (ico) { ico.className = 'fa fa-eye-slash'; }
            } else {
                el.innerText = '••••••••';
                el.setAttribute('data-hidden', 'true');
                if (ico) { ico.className = 'fa fa-eye'; }
            }
        }
    }
</script>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-users"></i> <?= $_ppp_secrets ?>
            <?php if (!empty($prof_filter) && $prof_filter !== 'all'): ?>
              <span style="font-size: 13px; font-weight: normal;">[<?= htmlspecialchars($prof_filter); ?>]</span>
            <?php endif; ?>
            <small class="text-muted" style="font-size: 13px; font-weight: normal; opacity: 0.85;">
              (<?= ($countsecrets < 2) ? "$countsecrets item" : "$countsecrets items"; ?>)
            </small>
          </h3>
          <div class="card-header-actions">
            <?php if (!empty($prof_filter) && $prof_filter !== 'all'): ?>
              <a href="./?ppp=secrets&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-search"></i> <?= $_show_all ?? "Show all"; ?></a>
            <?php endif; ?>
            <a href="./?ppp=add-secret&session=<?= $session; ?>" class="header-action-btn"><i class="fa fa-user-plus"></i> <?= $_add_ppp_secret ?></a>
            <span onclick="location.reload();" class="header-action-btn pointer" title="<?= $_reload_data ?? "Reload data"; ?>"><i class="fa fa-refresh"></i></span>
          </div>
        </div>
      </div>
      <div class="card-body overflow">
        <div style="padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
          <input id="filterTable" type="text" class="form-control" style="max-width: 220px; padding: 5px 10px; margin: 0;" placeholder="<?= $_search ?>">
          
          <?php if (!empty($getprofiles)): ?>
            <div style="display: flex; align-items: center; gap: 6px;">
              <span style="font-size: 12px; font-weight: bold;"><?= $_profile ?>:</span>
              <select class="form-control" style="max-width: 180px; padding: 4px 8px; font-size: 12px;" onchange="location.href='./?ppp=secrets&profile=' + encodeURIComponent(this.value) + '&session=<?= $session; ?>';">
                <option value="all"><?= $_all ?? "all"; ?></option>
                <?php foreach ($getprofiles as $profItem): 
                  $pItemName = $profItem['name'] ?? '';
                  $selected = (($prof_filter ?? '') === $pItemName) ? 'selected' : '';
                ?>
                  <option value="<?= htmlspecialchars($pItemName); ?>" <?= $selected ?>><?= htmlspecialchars($pItemName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>

        <table id="dataTable" class="table table-bordered table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width: 80px; text-align: center;"><?= $_action ?></th>
              <th><?= $_user_name ?></th>
              <th><?= $_password ?></th>
              <th><?= $_service ?></th>
              <th><?= $_profile ?></th>
              <th><?= $_local_address ?></th>
              <th><?= $_remote_address ?></th>
              <th><?= $_last_logged_out ?></th>
              <th><?= $_comment ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($countsecrets === 0): ?>
              <tr>
                <td colspan="9" style="text-align: center; padding: 20px; color: #888;">
                  <i class="fa fa-info-circle"></i> <?= $_no_ppp_secrets ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($getsecrets as $i => $secret): 
                $sid = $secret['.id'];
                $sname = $secret['name'] ?? '';
                $spass = $secret['password'] ?? '';
                $sservice = !empty($secret['service']) ? $secret['service'] : 'any';
                $sprofile = $secret['profile'] ?? '';
                $slocal = $secret['local-address'] ?? '-';
                $sremote = $secret['remote-address'] ?? '-';
                $slastlogout = $secret['last-logged-out'] ?? '-';
                $scomment = $secret['comment'] ?? '';
                $sdisabled = ($secret['disabled'] ?? 'false') === 'true';
              ?>
                <tr <?= $sdisabled ? "style='opacity: 0.6;'" : ""; ?>>
                  <td style="text-align: center;">
                    <i class="fa fa-minus-square text-danger pointer" 
                       onclick="if(confirm('<?= addslashes($_confirm_delete_ppp_secret); ?> (<?= htmlspecialchars(addslashes($sname), ENT_QUOTES); ?>)')){loadpage('./?remove-pppsecret=<?= $sid; ?>&session=<?= $session; ?>')}else{}" 
                       title="<?= $_delete ?> <?= htmlspecialchars($sname); ?>"></i>
                    &nbsp;&nbsp;
                    <?php if ($sdisabled): ?>
                      <span class="text-warning pointer" 
                            title="<?= $_enable ?> <?= htmlspecialchars($sname); ?>" 
                            onclick="loadpage('./?enable-pppsecret=<?= $sid; ?>&session=<?= $session; ?>')">
                        <i class="fa fa-lock"></i>
                      </span>
                    <?php else: ?>
                      <span class="pointer" 
                            title="<?= $_disable ?> <?= htmlspecialchars($sname); ?>" 
                            onclick="loadpage('./?disable-pppsecret=<?= $sid; ?>&session=<?= $session; ?>')">
                        <i class="fa fa-unlock"></i>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a title="<?= $_edit_ppp_secret ?>: <?= htmlspecialchars($sname); ?>" 
                       href="./?ppp=edit-secret&secret=<?= urlencode($sid); ?>&session=<?= $session; ?>"
                       style="font-weight: bold;">
                      <i class="fa fa-edit"></i> <?= htmlspecialchars($sname); ?>
                    </a>
                  </td>
                  <td>
                    <span id="pass_<?= $i ?>" data-raw="<?= htmlspecialchars($spass); ?>" data-hidden="true">••••••••</span>
                    <i id="eye_<?= $i ?>" class="fa fa-eye pointer" style="margin-left: 6px; opacity: 0.6;" onclick="togglePass(<?= $i ?>)" title="<?= $_view_password ?? 'View Password'; ?>"></i>
                  </td>
                  <td>
                    <span class="badge" style="background-color: #20a8d8; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                      <?= htmlspecialchars($sservice); ?>
                    </span>
                  </td>
                  <td>
                    <a href="./?ppp=secrets&profile=<?= urlencode($sprofile); ?>&session=<?= $session; ?>" title="<?= $_profile ?>: <?= htmlspecialchars($sprofile); ?>">
                      <?= htmlspecialchars($sprofile); ?>
                    </a>
                  </td>
                  <td><?= htmlspecialchars($slocal); ?></td>
                  <td><?= htmlspecialchars($sremote); ?></td>
                  <td style="font-size: 12px;"><?= htmlspecialchars($slastlogout); ?></td>
                  <td style="font-size: 12px;"><?= htmlspecialchars($scomment); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

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

$getpools = array();
if (!empty($API) && !empty($API->connected)) {
    try {
        $getpools = $API->comm("/ip/pool/print");
        $getpools = is_array($getpools) ? $getpools : array();
    } catch (\Throwable $e) {}
}

$error_msg = '';

if (isset($_POST['name']) && !empty($API) && !empty($API->connected)) {
    $name = trim($_POST['name']);
    $local = trim($_POST['local_address'] ?? '');
    $remote = trim($_POST['remote_address'] ?? '');
    $rate = trim($_POST['rate_limit'] ?? '');
    $onlyone = $_POST['only_one'] ?? 'default';
    $dns = trim($_POST['dns_server'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    $data = array(
        "name" => $name,
        "only-one" => $onlyone,
    );
    if ($local !== '') { $data["local-address"] = $local; }
    if ($remote !== '') { $data["remote-address"] = $remote; }
    if ($rate !== '') { $data["rate-limit"] = $rate; }
    if ($dns !== '') { $data["dns-server"] = $dns; }
    if ($comment !== '') { $data["comment"] = $comment; }

    try {
        $res = $API->comm("/ppp/profile/add", $data);
        if (isset($res['!trap'])) {
            $error_msg = $res['!trap'][0]['message'] ?? ($_failed_save ?? 'MikroTik API Error');
        } else {
            echo "<script>window.location='./?ppp=profiles&session=" . $session . "'</script>";
            exit;
        }
    } catch (\Throwable $e) {
        $error_msg = $e->getMessage();
    }
}
?>

<div class="row">
  <div class="col-8">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-plus-square"></i> <?= $_add_ppp_profile ?></h3>
      </div>
      <div class="card-body">
        <?php if (!empty($error_msg)): ?>
          <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_msg); ?>
          </div>
        <?php endif; ?>

        <form autocomplete="off" method="post" action="">
          <div style="margin-bottom: 15px;">
            <a class="btn bg-warning" href="./?ppp=profiles&session=<?= $session; ?>">
              <i class="fa fa-close"></i> <?= $_cancel ?>
            </a>
            <button type="submit" class="btn bg-primary" name="save">
              <i class="fa fa-save"></i> <?= $_save ?>
            </button>
          </div>

          <table class="table">
            <tr>
              <td class="align-middle" style="width: 25%; font-weight: bold;"><?= $_name ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="name" required="1" autofocus placeholder="e.g. 10Mbps-PPPoE">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_local_address ?></td>
              <td>
                <input class="form-control" list="local_pools" type="text" autocomplete="off" name="local_address" placeholder="IP Address or Pool (e.g. 10.0.0.1)">
                <datalist id="local_pools">
                  <?php foreach ($getpools as $pool): ?>
                    <option value="<?= htmlspecialchars($pool['name'] ?? ''); ?>"><?= htmlspecialchars($pool['name'] ?? ''); ?></option>
                  <?php endforeach; ?>
                </datalist>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_remote_address ?></td>
              <td>
                <input class="form-control" list="remote_pools" type="text" autocomplete="off" name="remote_address" placeholder="IP Address or Pool (e.g. pool-pppoe)">
                <datalist id="remote_pools">
                  <?php foreach ($getpools as $pool): ?>
                    <option value="<?= htmlspecialchars($pool['name'] ?? ''); ?>"><?= htmlspecialchars($pool['name'] ?? ''); ?></option>
                  <?php endforeach; ?>
                </datalist>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_rate_limit ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="rate_limit" placeholder="rx/tx (e.g. 5M/10M)">
                <small class="text-muted" style="font-size: 11px;">Format: [rx/tx] upload/download (contoh: 2M/5M, 10M/10M)</small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_only_one ?></td>
              <td>
                <select class="form-control" name="only_one">
                  <option value="default" selected>default</option>
                  <option value="yes">yes</option>
                  <option value="no">no</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_dns_server ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="dns_server" placeholder="e.g. 8.8.8.8,1.1.1.1 (optional)">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_comment ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="comment" placeholder="Keterangan profil">
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

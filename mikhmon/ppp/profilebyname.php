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

$profParam = $_GET['profile'] ?? '';
$profileData = array();

if (!empty($API) && !empty($API->connected)) {
    try {
        if (substr($profParam, 0, 1) === "*") {
            $getprof = $API->comm("/ppp/profile/print", array(
                "?.id" => "$profParam"
            ));
        } else {
            $getprof = $API->comm("/ppp/profile/print", array(
                "?name" => "$profParam"
            ));
        }
        $getprof = is_array($getprof) ? $getprof : array();
        $profileData = $getprof[0] ?? array();
    } catch (\Throwable $e) {}
}

if (empty($profileData)) {
    echo "<div class='card'><div class='card-body text-center p-4'><b>Profile not found!</b><br><br><a class='btn bg-warning' href='./?ppp=profiles&session=" . $session . "'><i class='fa fa-arrow-left'></i> Back</a></div></div>";
    return;
}

$profId = $profileData['.id'];
$profName = $profileData['name'] ?? '';
$profLocal = $profileData['local-address'] ?? '';
$profRemote = $profileData['remote-address'] ?? '';
$profRate = $profileData['rate-limit'] ?? '';
$profOnlyOne = $profileData['only-one'] ?? 'default';
$profDns = $profileData['dns-server'] ?? '';
$profComment = $profileData['comment'] ?? '';

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
        ".id" => $profId,
        "name" => $name,
        "only-one" => $onlyone,
        "local-address" => $local,
        "remote-address" => $remote,
        "rate-limit" => $rate,
        "dns-server" => $dns,
        "comment" => $comment,
    );

    try {
        $res = $API->comm("/ppp/profile/set", $data);
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
        <h3><i class="fa fa-edit"></i> <?= $_edit_ppp_profile ?>: <?= htmlspecialchars($profName); ?></h3>
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
                <input class="form-control" type="text" autocomplete="off" name="name" value="<?= htmlspecialchars($profName); ?>" required="1">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_local_address ?></td>
              <td>
                <input class="form-control" list="local_pools" type="text" autocomplete="off" name="local_address" value="<?= htmlspecialchars($profLocal); ?>" placeholder="IP Address or Pool">
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
                <input class="form-control" list="remote_pools" type="text" autocomplete="off" name="remote_address" value="<?= htmlspecialchars($profRemote); ?>" placeholder="IP Address or Pool">
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
                <input class="form-control" type="text" autocomplete="off" name="rate_limit" value="<?= htmlspecialchars($profRate); ?>" placeholder="rx/tx (e.g. 5M/10M)">
                <small class="text-muted" style="font-size: 11px;">Format: [rx/tx] upload/download (contoh: 2M/5M, 10M/10M)</small>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_only_one ?></td>
              <td>
                <select class="form-control" name="only_one">
                  <option value="default" <?= ($profOnlyOne === 'default') ? 'selected' : ''; ?>>default</option>
                  <option value="yes" <?= ($profOnlyOne === 'yes') ? 'selected' : ''; ?>>yes</option>
                  <option value="no" <?= ($profOnlyOne === 'no') ? 'selected' : ''; ?>>no</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_dns_server ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="dns_server" value="<?= htmlspecialchars($profDns); ?>" placeholder="e.g. 8.8.8.8,1.1.1.1">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_comment ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="comment" value="<?= htmlspecialchars($profComment); ?>" placeholder="Keterangan profil">
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

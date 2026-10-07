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

$secParam = $_GET['secret'] ?? $secretbyname ?? '';
$secretData = array();

if (!empty($API) && !empty($API->connected)) {
    try {
        if (substr($secParam, 0, 1) === "*") {
            $getsec = $API->comm("/ppp/secret/print", array(
                "?.id" => "$secParam"
            ));
        } else {
            $getsec = $API->comm("/ppp/secret/print", array(
                "?name" => "$secParam"
            ));
        }
        $getsec = is_array($getsec) ? $getsec : array();
        $secretData = $getsec[0] ?? array();
    } catch (\Throwable $e) {}
}

if (empty($secretData)) {
    echo "<div class='card'><div class='card-body text-center p-4'><b>Secret not found!</b><br><br><a class='btn bg-warning' href='./?ppp=secrets&session=" . $session . "'><i class='fa fa-arrow-left'></i> Back</a></div></div>";
    return;
}

$secId = $secretData['.id'];
$secName = $secretData['name'] ?? '';
$secPass = $secretData['password'] ?? '';
$secService = $secretData['service'] ?? 'any';
$secProfile = $secretData['profile'] ?? 'default';
$secLocal = $secretData['local-address'] ?? '';
$secRemote = $secretData['remote-address'] ?? '';
$secComment = $secretData['comment'] ?? '';
$secDisabled = ($secretData['disabled'] ?? 'false') === 'true';

$getprofiles = array();
if (!empty($API) && !empty($API->connected)) {
    try {
        $getprofiles = $API->comm("/ppp/profile/print");
        $getprofiles = is_array($getprofiles) ? $getprofiles : array();
    } catch (\Throwable $e) {}
}

$error_msg = '';

if (isset($_POST['name']) && !empty($API) && !empty($API->connected)) {
    $name = trim($_POST['name']);
    $pass = $_POST['pass'] ?? '';
    $service = $_POST['service'] ?? 'any';
    $profile = $_POST['profile'] ?? 'default';
    $local = trim($_POST['local_address'] ?? '');
    $remote = trim($_POST['remote_address'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $disabled = (($_POST['disabled'] ?? 'no') === 'yes') ? 'yes' : 'no';

    $data = array(
        ".id" => $secId,
        "name" => $name,
        "password" => $pass,
        "service" => $service,
        "profile" => $profile,
        "local-address" => $local,
        "remote-address" => $remote,
        "comment" => $comment,
        "disabled" => $disabled,
    );

    try {
        $res = $API->comm("/ppp/secret/set", $data);
        if (isset($res['!trap'])) {
            $error_msg = $res['!trap'][0]['message'] ?? ($_failed_save ?? 'MikroTik API Error');
        } else {
            echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
            exit;
        }
    } catch (\Throwable $e) {
        $error_msg = $e->getMessage();
    }
}
?>
<script>
    function PassUser() {
        var x = document.getElementById('passUser');
        if (x.type === 'password') {
            x.type = 'text';
        } else {
            x.type = 'password';
        }
    }
</script>

<div class="row">
  <div class="col-8">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-edit"></i> <?= $_edit_ppp_secret ?>: <?= htmlspecialchars($secName); ?></h3>
      </div>
      <div class="card-body">
        <?php if (!empty($error_msg)): ?>
          <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_msg); ?>
          </div>
        <?php endif; ?>

        <form autocomplete="off" method="post" action="">
          <div style="margin-bottom: 15px;">
            <a class="btn bg-warning" href="./?ppp=secrets&session=<?= $session; ?>">
              <i class="fa fa-close"></i> <?= $_cancel ?>
            </a>
            <button type="submit" class="btn bg-primary" name="save">
              <i class="fa fa-save"></i> <?= $_save ?>
            </button>
          </div>

          <table class="table">
            <tr>
              <td class="align-middle" style="width: 25%; font-weight: bold;"><?= $_user_name ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="name" value="<?= htmlspecialchars($secName); ?>" required="1">
              </td>
            </tr>
            <tr>
              <td class="align-middle" style="font-weight: bold;"><?= $_password ?></td>
              <td>
                <div class="input-group">
                  <div class="input-group-11 col-box-10">
                    <input class="group-item group-item-l" id="passUser" type="password" name="pass" autocomplete="new-password" value="<?= htmlspecialchars($secPass); ?>" required="1">
                  </div>
                  <div class="input-group-1 col-box-2">
                    <div class="group-item group-item-r pd-2p5 text-center">
                      <input title="<?= $_show_hide_password ?? 'Show/Hide Password'; ?>" type="checkbox" onclick="PassUser()">
                    </div>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <td class="align-middle" style="font-weight: bold;"><?= $_service ?></td>
              <td>
                <select class="form-control" name="service">
                  <option value="any" <?= ($secService === 'any') ? 'selected' : ''; ?>><?= $_all_services ?? 'any'; ?></option>
                  <option value="pppoe" <?= ($secService === 'pppoe') ? 'selected' : ''; ?>>pppoe</option>
                  <option value="pptp" <?= ($secService === 'pptp') ? 'selected' : ''; ?>>pptp</option>
                  <option value="l2tp" <?= ($secService === 'l2tp') ? 'selected' : ''; ?>>l2tp</option>
                  <option value="ovpn" <?= ($secService === 'ovpn') ? 'selected' : ''; ?>>ovpn</option>
                  <option value="sstp" <?= ($secService === 'sstp') ? 'selected' : ''; ?>>sstp</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle" style="font-weight: bold;"><?= $_profile ?></td>
              <td>
                <select class="form-control" name="profile" required="1">
                  <?php foreach ($getprofiles as $prof): 
                    $pName = $prof['name'] ?? '';
                    $pSel = ($pName === $secProfile) ? 'selected' : '';
                  ?>
                    <option value="<?= htmlspecialchars($pName); ?>" <?= $pSel ?>><?= htmlspecialchars($pName); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_local_address ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="local_address" value="<?= htmlspecialchars($secLocal); ?>" placeholder="e.g. 192.168.10.1 (optional)">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_remote_address ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="remote_address" value="<?= htmlspecialchars($secRemote); ?>" placeholder="e.g. 192.168.10.2 (optional)">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_comment ?></td>
              <td>
                <input class="form-control" type="text" autocomplete="off" name="comment" value="<?= htmlspecialchars($secComment); ?>" placeholder="Keterangan / Pelanggan">
              </td>
            </tr>
            <tr>
              <td class="align-middle"><?= $_status ?></td>
              <td>
                <select class="form-control" name="disabled">
                  <option value="no" <?= !$secDisabled ? 'selected' : ''; ?>><?= $_enable ?></option>
                  <option value="yes" <?= $secDisabled ? 'selected' : ''; ?>><?= $_disable ?></option>
                </select>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

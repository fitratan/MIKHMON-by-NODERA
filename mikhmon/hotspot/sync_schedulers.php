<?php
/**
 * Auto-Sync All Profile Schedulers & Clean Expired Vouchers
 * Nodera Mikhmon Engine - Multi-Version ROS6 & ROS7 Enhanced
 */
@ini_set('max_execution_time', '300');
@set_time_limit(300);
@ini_set('memory_limit', '512M');
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    echo "<script>window.location='./admin.php?id=login';</script>";
    exit;
}

include_once(__DIR__ . '/../include/auto_sync.php');

$is_connected = !empty($API) && !empty($API->connected);
$synced_profiles = 0;
$removed_users = 0;
$error_msg = "";

if ($is_connected && function_exists('mikhmon_auto_sync_router')) {
    $fallbackTz = (!empty($_SESSION['timezone'])) ? $_SESSION['timezone'] : 'Asia/Jakarta';
    $res = mikhmon_auto_sync_router($API, $session ?? '', true, $fallbackTz);
    $synced_profiles = $res['synced_profiles'] ?? 0;
    $removed_users = $res['removed_users'] ?? 0;
    $error_msg = $res['error'] ?? '';
}
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
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header align-middle">
                <h3><i class="fa fa-magic"></i> <?= $_sync_scheduler ?? 'Sinkronisasi Scheduler' ?></h3>
            </div>
            <div class="card-body">
                <div class="box-bordered pd-20 text-center mr-t-10">
                    <?php if ($is_connected): ?>
                        <div class="mr-b-15">
                            <i class="fa fa-check-circle" style="font-size: 52px; color: #10b981;"></i>
                        </div>
                        <h2 class="mr-b-10"><?= $_sync_schedulers_success ?? 'Sinkronisasi MikroTik Berhasil!'; ?></h2>
                        <p class="mr-b-20" style="font-size: 14px; opacity: 0.85; max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.6;">
                            <?= ($langid ?? '') === 'fr'
                                ? "Le système a réparé et mis à jour <b>$synced_profiles profils d'utilisateurs & planificateurs</b>, activé le <b>Nettoyeur Automatique 24h</b>, synchronisé l'<b>Horloge NTP</b>, et supprimé immédiatement <b>$removed_users coupons expirés</b> de votre routeur MikroTik."
                                : "Sistem telah memperbarui <b>$synced_profiles profil & scheduler monitor</b>, mengaktifkan <b>Master Auto-Cleaner 24 Jam</b>, menyelaraskan <b>NTP Clock</b>, dan langsung menghapus <b>$removed_users voucher expired</b> dari router MikroTik Anda."
                            ?>
                        </p>
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-warning text-left mr-t-10 mr-b-10" style="max-width: 600px; margin-left: auto; margin-right: auto; font-size: 13px;">
                                <i class="fa fa-exclamation-triangle"></i> <?= ($langid ?? '') === 'fr' ? 'Note' : 'Catatan' ?>: <?= htmlspecialchars($error_msg) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mr-b-15">
                            <i class="fa fa-exclamation-circle" style="font-size: 52px; color: #ef4444;"></i>
                        </div>
                        <h2 class="mr-b-10" style="color: #ef4444;"><?= $_sync_schedulers_failed ?? (($langid ?? '') === 'fr' ? 'Échec de Connexion au Routeur MikroTik' : 'Gagal Terhubung ke Router MikroTik'); ?></h2>
                        <p class="mr-b-20" style="font-size: 14px; opacity: 0.85; max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.6;">
                            <?= ($langid ?? '') === 'fr'
                                ? "Impossible d'effectuer la réparation et synchronisation car la session MikroTik est déconnectée ou le routeur est hors ligne. Vérifiez l'adresse IP, le port API, le nom d'utilisateur et le mot de passe."
                                : "Tidak dapat melakukan perbaikan & sinkronisasi karena sesi MikroTik sedang terputus atau router sedang offline. Pastikan IP Host, Port API, Username, dan Password pada sesi router sudah benar."
                            ?>
                        </p>
                    <?php endif; ?>
                    <div class="mr-t-20">
                        <a href="./?hotspot=user-profiles&session=<?= urlencode($session ?? '') ?>" class="btn bg-primary btn-mrg">
                            <i class="fa fa-arrow-left"></i> <?= $_back_to_user_profiles ?? (($langid ?? '') === 'fr' ? 'Retour aux Profils' : 'Kembali ke User Profiles'); ?>
                        </a>
                        <a href="./?hotspot=users&session=<?= urlencode($session ?? '') ?>" class="btn bg-warning btn-mrg">
                            <i class="fa fa-users"></i> <?= $_view_voucher_list ?? (($langid ?? '') === 'fr' ? 'Liste des Coupons' : 'Lihat Daftar Voucher'); ?>
                        </a>
                        <a href="./admin.php?id=sessions" class="btn bg-secondary btn-mrg">
                            <i class="fa fa-server"></i> <?= $_manage_sessions ?? (($langid ?? '') === 'fr' ? 'Gérer les Sessions' : 'Kelola Sesi Router'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

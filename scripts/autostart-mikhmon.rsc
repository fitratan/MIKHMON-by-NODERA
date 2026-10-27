# ==============================================================================
# Script Auto-Start & Watchdog Mikhmon Container by NODERA
# Kompatibel: MikroTik RouterOS v7.4+ (x86, CHR, ARM, ARM64)
# Fungsi   : Otomatis menjalankan Mikhmon saat MikroTik reboot / mati lampu,
#            serta menjaga container tetap running 24/7 (Watchdog).
# ==============================================================================

:log info "Mengonfigurasi Auto-Start & Watchdog untuk Mikhmon Container...";

# 1. Aktifkan start-on-boot pada container
:do {
    /container set [find interface=veth-mikhmon] start-on-boot=yes;
    :log info "[Mikhmon AutoStart] Property start-on-boot=yes berhasil diaktifkan.";
} on-error={
    :log warning "[Mikhmon AutoStart] Gagal set start-on-boot (abaikan jika container belum terpasang).";
};

# 2. Bersihkan scheduler lama jika ada untuk mencegah duplikasi
/system scheduler remove [find name="AutoStart-Mikhmon"];
/system scheduler remove [find name="Watchdog-Mikhmon"];

# 3. Tambahkan Scheduler Auto-Start saat startup (delay 15s agar storage USB/SSD siap sepenuhnya)
/system scheduler add name="AutoStart-Mikhmon" start-time=startup interval=0s \
    on-event=":delay 15s; :log info \"[Mikhmon AutoStart] Memulai container Mikhmon pasca boot...\"; /container start [find interface=veth-mikhmon];" \
    comment="Auto-start Container Mikhmon saat MikroTik Booting (NODERA)";

# 4. Tambahkan Scheduler Watchdog 24/7 (memeriksa setiap 1 menit jika container mati/crash)
/system scheduler add name="Watchdog-Mikhmon" interval=1m \
    on-event=":if ([:len [/container find interface=veth-mikhmon status=running]] = 0) do={ :log warning \"[Mikhmon Watchdog] Container offline! Mencoba menyalakan kembali...\"; /container start [find interface=veth-mikhmon]; }" \
    comment="Watchdog jaga Mikhmon Container tetap Running 24/7 (NODERA)";

# 5. Jalankan container sekarang jika saat ini dalam kondisi STOPPED
:if ([:len [/container find interface=veth-mikhmon status=running]] = 0) do={
    :log info "[Mikhmon AutoStart] Menyalakan container sekarang...";
    /container start [find interface=veth-mikhmon];
};

:log info "Konfigurasi Auto-Start & Watchdog Mikhmon Selesai!";

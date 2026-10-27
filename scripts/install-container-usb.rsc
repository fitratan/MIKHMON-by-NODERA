# ==============================================================================
# Script Instalasi Lengkap Mikhmon Container (RouterBOARD Fisik / USB) by NODERA
# Kompatibel: MikroTik RouterOS v7.4+ pada ARM / ARM64 / CCR / hAP (Storage usb1)
# ==============================================================================

:log info "Memulai instalasi Mikhmon Container untuk RouterBOARD Fisik (USB usb1)...";

# 1. Pastikan fitur container aktif
:do {
    /system device-mode update container=yes;
} on-error={};

# 2. Bridge & Virtual Ethernet
:if ([:len [/interface bridge find name=dockers]] = 0) do={
    /interface bridge add name=dockers comment="Bridge Container Mikhmon";
};
:if ([:len [/ip address find interface=dockers address="172.17.0.1/24"]] = 0) do={
    /ip address add address=172.17.0.1/24 interface=dockers;
};
:if ([:len [/interface veth find name=veth-mikhmon]] = 0) do={
    /interface veth add name=veth-mikhmon address=172.17.0.2/24 gateway=172.17.0.1;
    /interface bridge port add bridge=dockers interface=veth-mikhmon;
};

# 3. NAT Masquerade
:if ([:len [/ip firewall nat find comment="Masquerade Container Mikhmon"]] = 0) do={
    /ip firewall nat add chain=srcnat action=masquerade src-address=172.17.0.0/24 comment="Masquerade Container Mikhmon";
};

# 4. Port Forwarding Akses Browser port 8082
:if ([:len [/ip firewall nat find comment="Akses Web Mikhmon NODERA"]] = 0) do={
    /ip firewall nat add chain=dstnat dst-port=8082 protocol=tcp action=dst-nat to-addresses=172.17.0.2 to-ports=8080 comment="Akses Web Mikhmon NODERA";
};

# 5. Mount Folder Config ke USB Flashdisk
:do {
    /container mounts add list=mikhmon_config src=usb1/mikhmon/config dst=/app/mikhmon/config;
} on-error={
    :do { /container mounts add name=mikhmon_config src=usb1/mikhmon/config dst=/app/mikhmon/config; } on-error={};
};

# 6. Unduh Image Container dari GitHub ke usb1
:log info "Mengunduh image mikhmon-x86.tar.gz ke usb1...";
/tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/container-release/mikhmon-x86.tar.gz" dst-path="usb1/mikhmon.tar.gz" mode=https;

:delay 10s;

# 7. Tambah Container dengan root-dir di USB usb1
:log info "Memasang container...";
:do {
    /container add file=usb1/mikhmon.tar.gz interface=veth-mikhmon root-dir=usb1/mikhmon mountlists=mikhmon_config logging=yes start-on-boot=yes;
} on-error={
    :log warning "Gagal add container, periksa apakah file usb1/mikhmon.tar.gz sudah selesai diunduh.";
};

# 8. Pasang Auto-Start & Watchdog Scheduler (PENTING: delay 15 detik agar mount disk USB selesai)
/system scheduler remove [find name="AutoStart-Mikhmon"];
/system scheduler remove [find name="Watchdog-Mikhmon"];

/system scheduler add name="AutoStart-Mikhmon" start-time=startup interval=0s \
    on-event=":delay 15s; :log info \"[Mikhmon AutoStart] Memulai container USB pasca boot...\"; /container start [find interface=veth-mikhmon];" \
    comment="Auto-start Container Mikhmon saat MikroTik Booting (NODERA)";

/system scheduler add name="Watchdog-Mikhmon" interval=1m \
    on-event=":if ([:len [/container find interface=veth-mikhmon status=running]] = 0) do={ :log warning \"[Mikhmon Watchdog] Container offline! Menyalakan...\"; /container start [find interface=veth-mikhmon]; }" \
    comment="Watchdog jaga Mikhmon Container tetap Running 24/7 (NODERA)";

:log info "Instalasi selesai! Akses browser: http://[IP-ROUTER]:8082";

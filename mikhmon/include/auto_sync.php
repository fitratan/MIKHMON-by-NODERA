<?php
/**
 * Universal Auto-Sync Engine for MikroTik Hotspot
 * Synchronizes Profile On-Login Scripts, Background Schedulers & Cleans Expired Vouchers
 * Nodera Mikhmon Engine - ROS6 & ROS7 Safe
 */

if (!function_exists('mikhmon_auto_sync_router')) {
    function mikhmon_auto_sync_router($API, $session = '', $force = false, $fallbackTimezone = 'Asia/Jakarta')
    {
        if (empty($API) || empty($API->connected)) {
            return [
                'success' => false,
                'error' => 'API is not connected',
                'synced_profiles' => 0,
                'removed_users' => 0
            ];
        }

        $sessionKey = !empty($session) ? (string)$session : 'default';
        $throttleKey = $sessionKey . '_auto_synced_v3';

        // Throttle check: run at most once every 30 minutes (1800s) per router session unless forced
        if (!$force && !empty($_SESSION[$throttleKey]) && (time() - (int)$_SESSION[$throttleKey] < 1800)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Already auto-synced within last 30 minutes',
                'synced_profiles' => 0,
                'removed_users' => 0
            ];
        }

        $_SESSION[$throttleKey] = time();

        @ini_set('max_execution_time', '300');
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $origTimeout = $API->timeout ?? 2;
        $API->timeout = 10;

        $synced_profiles = 0;
        $removed_users = 0;
        $error_msg = '';

        try {
            // 1. Auto-Ensure Clock & NTP Configuration
            $clock = @$API->comm("/system/clock/print");
            $clockDate = $clock[0]['date'] ?? date('M/d/Y');
            $clockTime = $clock[0]['time'] ?? date('H:i:s');
            $timeZone = $clock[0]['time-zone-name'] ?? '';

            $targetTz = !empty($_SESSION['timezone']) ? $_SESSION['timezone'] : '';
            if (empty($targetTz)) {
                $targetTz = (!empty($timeZone) && $timeZone !== 'manual' && strpos($timeZone, 'GMT') === false) ? $timeZone : $fallbackTimezone;
            }
            if (!empty($targetTz) && ($timeZone !== $targetTz || empty($timeZone) || $timeZone === 'manual' || strpos($timeZone, 'GMT') !== false)) {
                @$API->comm("/system/clock/set", ["time-zone-name" => $targetTz]);
            }

            // NTP activation for ROS6 & ROS7
            try {
                @$API->comm("/system/ntp/client/set", [
                    "enabled" => "yes",
                    "primary-ntp" => "162.159.200.1",
                    "secondary-ntp" => "1.1.1.1"
                ]);
                @$API->comm("/system/ntp/client/set", ["enabled" => "yes"]);

                $servers = @$API->comm("/system/ntp/client/servers/print");
                $hasServer = false;
                if (is_array($servers)) {
                    foreach ($servers as $srv) {
                        $addr = $srv['address'] ?? '';
                        if ($addr === 'id.pool.ntp.org' || $addr === 'pool.ntp.org' || $addr === 'time.cloudflare.com' || $addr === 'time.google.com') {
                            $hasServer = true;
                            break;
                        }
                    }
                }
                if (!$hasServer) {
                    $primaryPool = ($fallbackTimezone === 'Asia/Jakarta') ? "id.pool.ntp.org" : "pool.ntp.org";
                    @$API->comm("/system/ntp/client/servers/add", ["address" => $primaryPool]);
                    @$API->comm("/system/ntp/client/servers/add", ["address" => "pool.ntp.org"]);
                }
            } catch (\Throwable $e) {}

            $monthArray = [
                "jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6,
                "jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12
            ];

            $parseDateInt = function($dateStr) use ($monthArray) {
                $dateStr = strtolower(trim((string)$dateStr));
                if (empty($dateStr)) return 0;
                
                if (strpos($dateStr, '/') !== false) {
                    $parts = explode('/', $dateStr);
                    if (count($parts) >= 3) {
                        $p0 = substr($parts[0], 0, 3);
                        if (isset($monthArray[$p0])) {
                            $m = $monthArray[$p0];
                            $d = (int)$parts[1];
                            $y = (int)$parts[2];
                            return (int)sprintf("%04d%02d%02d", $y, $m, $d);
                        } elseif (is_numeric($parts[0])) {
                            $m = (int)$parts[0];
                            $d = (int)$parts[1];
                            $y = (int)$parts[2];
                            return (int)sprintf("%04d%02d%02d", $y, $m, $d);
                        }
                    }
                } elseif (strpos($dateStr, '-') !== false) {
                    $parts = explode('-', $dateStr);
                    if (count($parts) >= 3) {
                        $y = (int)$parts[0];
                        $m = (int)$parts[1];
                        $d = (int)$parts[2];
                        return (int)sprintf("%04d%02d%02d", $y, $m, $d);
                    }
                }
                return 0;
            };

            $parseTimeInt = function($timeStr) {
                $parts = explode(':', trim((string)$timeStr));
                $h = (int)($parts[0] ?? 0);
                $m = (int)($parts[1] ?? 0);
                return ($h * 60) + $m;
            };

            $parseDurationSec = function($str) {
                $sec = 0;
                if (empty($str) || $str === '0s') return 0;
                if (preg_match('/(\d+)w/', $str, $m)) $sec += (int)$m[1] * 604800;
                if (preg_match('/(\d+)d/', $str, $m)) $sec += (int)$m[1] * 86400;
                if (preg_match('/(\d+)h/', $str, $m)) $sec += (int)$m[1] * 3600;
                if (preg_match('/(\d+)m/', $str, $m)) $sec += (int)$m[1] * 60;
                if (preg_match('/(\d+)s/', $str, $m)) $sec += (int)$m[1];
                return $sec;
            };

            $todayInt = $parseDateInt($clockDate);
            $curtimeInt = $parseTimeInt($clockTime);

            // 2. Fetch User Profiles & Existing Schedulers
            $profiles = @$API->comm("/ip/hotspot/user/profile/print");
            $schedulers = @$API->comm("/system/scheduler/print");
            $schedByName = [];
            if (is_array($schedulers)) {
                foreach ($schedulers as $s) {
                    if (isset($s['name'])) {
                        $schedByName[$s['name']] = $s;
                    }
                }
            }

            $profileValidity = [];
            $profileNames = [];

            if (is_array($profiles)) {
                foreach ($profiles as $prof) {
                    $pname = $prof['name'] ?? '';
                    $pid = $prof['.id'] ?? '';
                    $onLogin = $prof['on-login'] ?? '';
                    if (empty($pname) || $pname === 'default' || empty($pid)) continue;

                    $profileNames[$pname] = true;

                    $expmode = '';
                    $price = '';
                    $validity = '';
                    $sprice = '';
                    $getlock = 'Disable';
                    $getsinglesession = 'Disable';

                    $rawMeta = '';
                    if (preg_match('/:\s*put\s*\(\s*["\']([^"\']+)["\']/i', $onLogin, $pm)) {
                        $rawMeta = trim($pm[1]);
                    } elseif (preg_match('/,\s*(rem|remc|ntf|ntfc)\s*,.*/i', $onLogin, $pm)) {
                        $rawMeta = trim($pm[0]);
                    }

                    if (!empty($rawMeta)) {
                        $parts = array_map('trim', explode(',', $rawMeta));
                        if (isset($parts[0]) && $parts[0] === '') {
                            array_shift($parts);
                        }
                        if (isset($parts[0]) && in_array(strtolower($parts[0]), ['rem', 'remc', 'ntf', 'ntfc'])) {
                            $expmode = strtolower($parts[0]);
                            $price = $parts[1] ?? '';
                            $validity = $parts[2] ?? '';
                            $sprice = $parts[3] ?? '';
                            $getlock = (!empty($parts[5]) && in_array($parts[5], ['Enable', 'Disable'])) ? $parts[5] : 'Disable';
                            $getsinglesession = (!empty($parts[6]) && in_array($parts[6], ['Enable', 'Disable', 'Username', 'MAC'])) ? $parts[6] : 'Disable';
                        }
                    }

                    if (!empty($validity)) {
                        $profileValidity[$pname] = $validity;
                    }

                    if (in_array($expmode, ['rem', 'remc', 'ntf', 'ntfc'])) {
                        $singlescript = '';
                        if ($getsinglesession === "Enable" || $getsinglesession === "Username" || $getsinglesession === "MAC") {
                            $singlescript = ' { :local antiMacAcakLatestWins "yes"; :local curuser $user; :local curmac $"mac-address"; :local curaddr $address; :if (($curuser = "") or ($curmac = "")) do={} else={ :delay 1s; :local curid ""; :foreach a in=[/ip hotspot active find where user=$curuser] do={ :local amac [/ip hotspot active get $a mac-address]; :local aaddr [/ip hotspot active get $a address]; :if (($amac = $curmac) and (($curaddr = "") or ($aaddr = $curaddr))) do={ :set curid $a; }; }; :if ($curid != "") do={ :foreach a in=[/ip hotspot active find where user=$curuser] do={ :if ($a = $curid) do={} else={ /ip hotspot active remove $a; }; }; } else={ :foreach a in=[/ip hotspot active find where user=$curuser] do={ :local amac [/ip hotspot active get $a mac-address]; :if ($amac = $curmac) do={} else={ /ip hotspot active remove $a; }; }; }; :foreach c in=[/ip hotspot cookie find where user=$curuser] do={ :local cmac [/ip hotspot cookie get $c mac-address]; :if (($cmac = "") or ($cmac = $curmac)) do={} else={ /ip hotspot cookie remove $c; }; }; } };';
                        }
                        $lock = ($getlock === "Enable") ? '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]' : '';
                        $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; /system script add name="$date-|-$time-|-$user-|-' . $price . '-|-$address-|-$mac-|-' . $validity . '-|-' . $pname . '-|-$comment" owner="$month$year" source="$date" comment="mikhmon"';
                        $onLoginExpAction = ($expmode === 'ntf' || $expmode === 'ntfc') ? '/ip hotspot user set [find where name="$user"] limit-uptime=1s;' : '/ip hotspot user remove [find where name="$user"];';
                        $expHead = '{ :local cN do={ :local s "$v"; :while ([:len $s]>1 and [:pick $s 0 1]="0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n]="num") do={ :return $n; } else={ :return 0; }; }; :local d2i do={ :local cN do={ :local s "$v"; :while ([:len $s]>1 and [:pick $s 0 1]="0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n]="num") do={ :return $n; } else={ :return 0; }; }; :local ma ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"); :local y 0; :local m 0; :local day 0; :local l [:len $d]; :if ($l>=10 and [:pick $d 4]="-" and [:pick $d 7]="-") do={ :set y [$cN v=[:pick $d 0 4]]; :set m [$cN v=[:pick $d 5 7]]; :set day [$cN v=[:pick $d 8 10]]; } else={ :local s1 [:find $d "/"]; :if ([:typeof $s1]="num") do={ :local s2 [:find $d "/" ($s1+1)]; :if ([:typeof $s2]="num") do={ :local p1 [:pick $d 0 $s1]; :local p2 [:pick $d ($s1+1) $s2]; :local p3 [:pick $d ($s2+1) $l]; :local mp [:find $ma $p1]; :if ([:typeof $mp]="num") do={ :set m (($mp%12)+1); :set day [$cN v=$p2]; :set y [$cN v=$p3]; } else={ :set m [$cN v=$p1]; :set day [$cN v=$p2]; :set y [$cN v=$p3]; }; }; }; }; :if ($y>2000 and $m>0 and $m<=12 and $day>0 and $day<=31) do={ :local ms "$m"; :if ($m<10) do={ :set ms ("0"."$m"); }; :local ds "$day"; :if ($day<10) do={ :set ds ("0"."$day"); }; :return [:tonum ("$y"."$ms"."$ds")]; } else={ :return 0; }; }; :local t2i do={ :local cN do={ :local s "$v"; :while ([:len $s]>1 and [:pick $s 0 1]="0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n]="num") do={ :return $n; } else={ :return 0; }; }; :local c1 [:find $t ":"]; :if ([:typeof $c1]="num") do={ :local c2 [:find $t ":" ($c1+1)]; :local h [$cN v=[:pick $t 0 $c1]]; :local mn 0; :if ([:typeof $c2]="num") do={ :set mn [$cN v=[:pick $t ($c1+1) $c2]]; } else={ :set mn [$cN v=[:pick $t ($c1+1) [:len $t]]]; }; :return (($h*60)+$mn); } else={ :return 0; }; }; :local uLu [/ip hotspot user get [/ip hotspot user find where name="$user"] limit-uptime]; :if ($uLu = 1s) do={ /ip hotspot active remove [find where user="$user"]; /ip hotspot cookie remove [find where user="$user"]; ' . $onLoginExpAction . ' } else={ :local comment [/ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local isStamped "no"; :local sp [:find $comment " "]; :if ([:typeof $sp] = "num" and [:find $comment ":"] != -1 and ([:find $comment "/"] != -1 or [:find $comment "-"] != -1)) do={ :set isStamped "yes"; }; :if ($isStamped = "yes") do={ :local curdate [$d2i d=[/system clock get date]]; :local curtime [$t2i t=[/system clock get time]]; :if ($curdate > 20000000) do={ :local expd [$d2i d=[:pick $comment 0 $sp]]; :local expt [$t2i t=[:pick $comment ($sp + 1) [:len $comment]]]; :if ($expd > 0 and ($expd < $curdate or ($expd = $curdate and $expt <= $curtime))) do={ :do { /system script add name=("EXP-|-" . [/system clock get date] . "-|-" . [/system clock get time] . "-|-" . "$user" . "-|-'.$pname.'-|-expired") owner="mikhmon" source="expired" comment="mikhmon_expired"; } on-error={}; /ip hotspot active remove [find where user="$user"]; /ip hotspot cookie remove [find where user="$user"]; ' . $onLoginExpAction . ' }; }; } else={ :local date [/system clock get date]; :local time [/system clock get time]; :local dlen [:len $date]; :local year; :local month; :if ([:pick $date 3] = "/") do={ :set month [:pick $date 0 3]; :set year [:pick $date ($dlen - 4) $dlen]; } else={ :set year [:pick $date 0 4]; :set month [:pick $date 5 7]; }; :do { /sys sch remove [find where name="$user"]; } on-error={}; /sys sch add name="$user" disable=no start-date=$date start-time=$time interval="' . $validity . '" policy=read,write,policy,test; :delay 2s; :local exp [/sys sch get [/sys sch find where name="$user"] next-run]; :if ([:len $exp] = 0) do={ :delay 1s; :set exp [/sys sch get [/sys sch find where name="$user"] next-run]; }; :local getxp [:len $exp]; :if ($getxp = 8) do={ /ip hotspot user set [find where name="$user"] comment="$date $exp"; } else={ :local sp [:find $exp " "]; :if ([:typeof $sp] = "num") do={ :local d [:pick $exp 0 $sp]; :local t [:pick $exp ($sp + 1) [:len $exp]]; :if ([:find $d "/"] != -1 and [:len $d] <= 6) do={ /ip hotspot user set [find where name="$user"] comment="$d/$year $t"; } else={ :if ([:find $d "-"] != -1 and [:len $d] <= 6) do={ /ip hotspot user set [find where name="$user"] comment="$year-$d $t"; } else={ /ip hotspot user set [find where name="$user"] comment="$exp"; }; }; } else={ :if ($getxp > 0) do={ /ip hotspot user set [find where name="$user"] comment="$exp"; }; }; }; :delay 1s; :do { /sys sch remove [find where name="$user"]; } on-error={};';

                        $newOnLogin = '';
                        if ($expmode === "rem" || $expmode === "ntf") {
                            $newOnLogin = ':put (",' . $expmode . ',' . $price . ',' . $validity . ',' . $sprice . ',,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . ' ' . $expHead . $lock . " }}}";
                        } elseif ($expmode === "remc" || $expmode === "ntfc") {
                            $newOnLogin = ':put (",' . $expmode . ',' . $price . ',' . $validity . ',' . $sprice . ',,' . $getlock . ',' . $getsinglesession . ',");' . $singlescript . ' ' . $expHead . $record . $lock . " }}}";
                        }

                        if (!empty($newOnLogin) && $onLogin !== $newOnLogin) {
                            @$API->comm("/ip/hotspot/user/profile/set", [
                                ".id"      => $pid,
                                "on-login" => $newOnLogin
                            ]);
                        }

                        // Profile Background Service Scheduler
                        $expAction = ($expmode === 'ntf' || $expmode === 'ntfc') ? '/ip hotspot user set $u limit-uptime=1s;' : '/ip hotspot user remove $u;';
                        $profBgService = ':local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local dateint do={ :local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"); :local days 0; :local month 0; :local year 0; :local dlen [:len $d]; :if ($dlen >= 10 and [:pick $d 4] = "-" and [:pick $d 7] = "-") do={ :set year [$cleanNum val=[:pick $d 0 4]]; :set month [$cleanNum val=[:pick $d 5 7]]; :set days [$cleanNum val=[:pick $d 8 10]]; } else={ :local s1 [:find $d "/"]; :if ([:typeof $s1] = "num") do={ :local s2 [:find $d "/" ($s1 + 1)]; :if ([:typeof $s2] = "num") do={ :local p1 [:pick $d 0 $s1]; :local p2 [:pick $d ($s1 + 1) $s2]; :local p3 [:pick $d ($s2 + 1) $dlen]; :local mpos [:find $montharray $p1]; :if ([:typeof $mpos] = "num") do={ :set month (($mpos % 12) + 1); :set days [$cleanNum val=$p2]; :set year [$cleanNum val=$p3]; } else={ :set month [$cleanNum val=$p1]; :set days [$cleanNum val=$p2]; :set year [$cleanNum val=$p3]; }; }; }; }; :if ($year > 2000 and $month > 0 and $month <= 12 and $days > 0 and $days <= 31) do={ :local mstr "$month"; :if ($month < 10) do={ :set mstr ("0" . "$month"); }; :local dstr "$days"; :if ($days < 10) do={ :set dstr ("0" . "$days"); }; :return [:tonum ("$year" . "$mstr" . "$dstr")]; } else={ :return 0; }; }; :local timeint do={ :local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local c1 [:find $t ":"]; :if ([:typeof $c1] = "num") do={ :local c2 [:find $t ":" ($c1 + 1)]; :local h [$cleanNum val=[:pick $t 0 $c1]]; :local m 0; :if ([:typeof $c2] = "num") do={ :set m [$cleanNum val=[:pick $t ($c1 + 1) $c2]]; } else={ :set m [$cleanNum val=[:pick $t ($c1 + 1) [:len $t]]]; }; :return (($h * 60) + $m); } else={ :return 0; }; }; :local curdate [$dateint d=[/system clock get date]]; :local curtime [$timeint t=[/system clock get time]]; :if ($curdate > 20000000) do={ :foreach u in=[/ip hotspot user find where profile="' . $pname . '"] do={ :local comment [/ip hotspot user get $u comment]; :local ucode [:pick $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $ucode = "NP" or $ucode = "np" or $comment = "") do={} else={ :local sp [:find $comment " "]; :if ([:typeof $sp] = "num") do={ :local expd [$dateint d=[:pick $comment 0 $sp]]; :local expt [$timeint t=[:pick $comment ($sp + 1) [:len $comment]]]; :if ($expd > 0 and ($expd < $curdate or ($expd = $curdate and $expt <= $curtime))) do={ :local uname [/ip hotspot user get $u name]; :do { /system script add name=("EXP-|-" . [/system clock get date] . "-|-" . [/system clock get time] . "-|-" . $uname . "-|-' . $pname . '-|-expired") owner="mikhmon" source="expired" comment="mikhmon_expired"; } on-error={}; /ip hotspot active remove [find where user=$uname]; /ip hotspot cookie remove [find where user=$uname]; ' . $expAction . ' }; }; }; }; };';

                        $randStartTime = "0" . rand(1, 5) . ":" . str_pad(rand(10, 59), 2, "0", STR_PAD_LEFT) . ":" . str_pad(rand(10, 59), 2, "0", STR_PAD_LEFT);
                        $randInterval = "00:02:" . str_pad(rand(10, 59), 2, "0", STR_PAD_LEFT);

                        if (isset($schedByName[$pname]) && !empty($schedByName[$pname]['.id'])) {
                            $schId = $schedByName[$pname]['.id'];
                            $existingEvt = $schedByName[$pname]['on-event'] ?? '';
                            $isDisabled = ($schedByName[$pname]['disabled'] ?? '') === 'true' || ($schedByName[$pname]['disabled'] ?? '') === 'yes';
                            if ($existingEvt !== $profBgService || $isDisabled) {
                                @$API->comm("/system/scheduler/set", [
                                    ".id"      => $schId,
                                    "on-event" => $profBgService,
                                    "disabled" => "no",
                                    "interval" => $randInterval,
                                    "policy"   => "read,write,policy,test"
                                ]);
                            }
                        } else {
                            @$API->comm("/system/scheduler/add", [
                                "name"       => $pname,
                                "start-time" => $randStartTime,
                                "interval"   => $randInterval,
                                "on-event"   => $profBgService,
                                "disabled"   => "no",
                                "policy"     => "read,write,policy,test",
                                "comment"    => "Monitor Profile " . $pname
                            ]);
                        }
                        $synced_profiles++;
                    }
                }
            }

            // 3. Install Master 24/7 Universal Auto-Cleaner on MikroTik
            $masterCleaner = ':foreach a in=[/ip hotspot active find] do={ :local aname [/ip hotspot active get $a user]; :if ([:len [/ip hotspot user find where name=$aname]] = 0) do={ /ip hotspot active remove $a; /ip hotspot cookie remove [find where user=$aname]; }; }; :foreach u in=[/ip hotspot user find where disabled=no] do={ :local lu [/ip hotspot user get $u limit-uptime]; :local ut [/ip hotspot user get $u uptime]; :if (($lu = 1s) or ($lu > 0s and $ut >= $lu)) do={ :local uname [/ip hotspot user get $u name]; :local uprof [/ip hotspot user get $u profile]; :do { /system script add name=("EXP-|-" . [/system clock get date] . "-|-" . [/system clock get time] . "-|-" . $uname . "-|-" . $uprof . "-|-expired") owner="mikhmon" source="expired" comment="mikhmon_expired"; } on-error={}; /ip hotspot active remove [find where user=$uname]; /ip hotspot cookie remove [find where user=$uname]; /ip hotspot user remove $u; }; };';
            $randCleanerTime = "00:" . str_pad(rand(1, 5), 2, "0", STR_PAD_LEFT) . ":" . str_pad(rand(10, 59), 2, "0", STR_PAD_LEFT);
            if (isset($schedByName['AutoCleanExpiredVouchers']) && !empty($schedByName['AutoCleanExpiredVouchers']['.id'])) {
                $existingEvt = $schedByName['AutoCleanExpiredVouchers']['on-event'] ?? '';
                $isDisabled = ($schedByName['AutoCleanExpiredVouchers']['disabled'] ?? '') === 'true' || ($schedByName['AutoCleanExpiredVouchers']['disabled'] ?? '') === 'yes';
                if ($existingEvt !== $masterCleaner || $isDisabled) {
                    @$API->comm("/system/scheduler/set", [
                        ".id"      => $schedByName['AutoCleanExpiredVouchers']['.id'],
                        "on-event" => $masterCleaner,
                        "disabled" => "no",
                        "interval" => "00:02:00",
                        "policy"   => "read,write,policy,test"
                    ]);
                }
            } else {
                @$API->comm("/system/scheduler/add", [
                    "name"       => "AutoCleanExpiredVouchers",
                    "start-time" => $randCleanerTime,
                    "interval"   => "00:02:00",
                    "on-event"   => $masterCleaner,
                    "disabled"   => "no",
                    "policy"     => "read,write,policy,test",
                    "comment"    => "Master Auto Clean Expired Vouchers by Nodera"
                ]);
            }

            // Clean orphan temporary login schedulers
            if (is_array($schedulers)) {
                foreach ($schedulers as $s) {
                    $sName = $s['name'] ?? '';
                    $sId = $s['.id'] ?? '';
                    if (!empty($sName) && !empty($sId) && $sName !== 'AutoCleanExpiredVouchers' && !isset($profileNames[$sName])) {
                        if (isset($s['interval']) && preg_match('/^(\d+[smhdw]|00:)/i', $s['interval']) && empty($s['comment'])) {
                            @$API->comm("/system/scheduler/remove", [".id" => $sId]);
                        }
                    }
                }
            }

            // 4. Purge ALL Expired Users Immediately (Optimized Batch Mode)
            $activePrint = @$API->comm("/ip/hotspot/active/print");
            $activeByUser = [];
            if (is_array($activePrint)) {
                foreach ($activePrint as $act) {
                    if (!empty($act['user']) && !empty($act['.id'])) {
                        $activeByUser[$act['user']][] = $act['.id'];
                    }
                }
            }

            $users = @$API->comm("/ip/hotspot/user/print");
            $expiredUserIds = [];
            $activeIdsToRemove = [];

            if (is_array($users)) {
                foreach ($users as $u) {
                    $uname = $u['name'] ?? '';
                    $ucomment = $u['comment'] ?? '';
                    $uid = $u['.id'] ?? '';
                    $ulimit = $u['limit-uptime'] ?? '0s';
                    $uuptime = $u['uptime'] ?? '0s';
                    $uprof = $u['profile'] ?? '';

                    if (empty($uid) || $uname === 'default-trial') continue;

                    $isExpired = false;
                    $uptimeSec = $parseDurationSec($uuptime);

                    // Check 1: Limit uptime reached or Notice 1s
                    if ($ulimit === '1s') {
                        $isExpired = true;
                    } elseif (!empty($ulimit) && $ulimit !== '0s' && $uptimeSec > 0) {
                        if ($uptimeSec >= $parseDurationSec($ulimit)) {
                            $isExpired = true;
                        }
                    }

                    // Check 2: Date in comment has expired
                    if (!$isExpired && !empty($ucomment)) {
                        if (preg_match('/([A-Za-z]{3}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4})\s+(\d{1,2}:\d{2}(?::\d{2})?)/', $ucomment, $m)) {
                            $uExpDate = $parseDateInt($m[1]);
                            $uExpTime = $parseTimeInt($m[2]);

                            if ($uExpDate > 0 && ($uExpDate < $todayInt || ($uExpDate === $todayInt && $uExpTime <= $curtimeInt))) {
                                $isExpired = true;
                            }
                        } elseif (preg_match('/^(?:exp|ex|rem|ntf)-(\d+)/i', $ucomment, $m)) {
                            $ts = (int)$m[1];
                            if ($ts > 0 && $ts <= time()) {
                                $isExpired = true;
                            }
                        }
                    }

                    // Check 3: Profile validity exceeded even if comment was never stamped
                    if (!$isExpired && !empty($uprof) && isset($profileValidity[$uprof])) {
                        $valSec = $parseDurationSec($profileValidity[$uprof]);
                        if ($valSec > 0 && $uptimeSec >= $valSec) {
                            $isExpired = true;
                        }
                    }

                    if ($isExpired) {
                        $expiredUserIds[] = $uid;
                        if (!empty($activeByUser[$uname])) {
                            foreach ($activeByUser[$uname] as $actId) {
                                $activeIdsToRemove[] = $actId;
                            }
                        }
                    }
                }

                // Batch delete active connections in chunks of 50
                if (!empty($activeIdsToRemove)) {
                    $uniqueActive = array_unique($activeIdsToRemove);
                    foreach (array_chunk($uniqueActive, 50) as $chunk) {
                        @$API->comm("/ip/hotspot/active/remove", [".id" => implode(',', $chunk)]);
                    }
                }

                // Batch delete expired users in chunks of 50
                if (!empty($expiredUserIds)) {
                    $uniqueUsers = array_unique($expiredUserIds);
                    foreach (array_chunk($uniqueUsers, 50) as $chunk) {
                        @$API->comm("/ip/hotspot/user/remove", [".id" => implode(',', $chunk)]);
                    }
                    $removed_users = count($uniqueUsers);
                }
            }
        } catch (\Throwable $e) {
            $error_msg = $e->getMessage();
        } finally {
            $API->timeout = $origTimeout;
        }

        return [
            'success' => empty($error_msg),
            'synced_profiles' => $synced_profiles,
            'removed_users' => $removed_users,
            'error' => $error_msg,
            'message' => empty($error_msg)
                ? "Auto-synced {$synced_profiles} profile(s) and cleaned {$removed_users} expired user(s)."
                : "Auto-sync encountered an issue: " . $error_msg
        ];
    }
}

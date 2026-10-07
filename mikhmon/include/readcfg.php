<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
include_once __DIR__ . '/init_session.php';
// hide all error
error_reporting(0);
if (substr($_SERVER["REQUEST_URI"], -11) == "readcfg.php") {
    header("Location:./");
};
// read config

$iphost = !empty($session) && !empty($data[$session][1]) ? (explode('!', $data[$session][1])[1] ?? '') : '';
$userhost = !empty($session) && !empty($data[$session][2]) ? (explode('@|@', $data[$session][2])[1] ?? '') : '';
$passwdhost = !empty($session) && !empty($data[$session][3]) ? (explode('#|#', $data[$session][3])[1] ?? '') : '';
$hotspotname = !empty($session) && !empty($data[$session][4]) ? (explode('%', $data[$session][4])[1] ?? '') : '';
$dnsname = !empty($session) && !empty($data[$session][5]) ? (explode('^', $data[$session][5])[1] ?? '') : '';
$currency = !empty($session) && !empty($data[$session][6]) ? (explode('&', $data[$session][6])[1] ?? 'Rp') : 'Rp';
$areload = !empty($session) && !empty($data[$session][7]) ? (explode('*', $data[$session][7])[1] ?? '10') : '10';
if (empty($areload) || intval($areload) < 5) {
    $areload = '10';
}
$iface = !empty($session) && !empty($data[$session][8]) ? (explode('(', $data[$session][8])[1] ?? '') : '';
$infolp = !empty($session) && !empty($data[$session][9]) ? (explode(')', $data[$session][9])[1] ?? '') : '';
$idleto = !empty($session) && !empty($data[$session][10]) ? (explode('=', $data[$session][10])[1] ?? '') : '';
$sesname = !empty($session) && !empty($data[$session][10]) ? (explode('+', $data[$session][10])[1] ?? '') : '';
$useradm = !empty($data['mikhmon'][1]) ? (explode('<|<', $data['mikhmon'][1])[1] ?? 'admin') : 'admin';
$passadm = !empty($data['mikhmon'][2]) ? (explode('>|>', $data['mikhmon'][2])[1] ?? '') : '';
$livereport = 'enable';

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);

if (!function_exists('mikhmon_decrypt')) {
    function mikhmon_decrypt($string, $key=128) {
        $result = '';
        $string = base64_decode($string);
        for($i=0, $k=strlen($string); $i< $k ; $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key))-1, 1);
            $char = chr(ord($char)-ord($keychar));
            $result .= $char;
        }
        return $result;
    }
}

if (!function_exists('mikhmon_encrypt')) {
    function mikhmon_encrypt($string, $key=128) {
        $result = '';
        for($i=0, $k= strlen($string); $i<$k; $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key))-1, 1);
            $char = chr(ord($char)+ord($keychar));
            $result .= $char;
        }
        return base64_encode($result);
    }
}



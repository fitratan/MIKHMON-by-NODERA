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

if (!function_exists('mikhmon_parse_onlogin_meta')) {
    function mikhmon_parse_onlogin_meta($onlogin) {
        $meta = [
            'mode' => '0',
            'modet' => 'None',
            'price' => '',
            'validity' => '',
            'sprice' => '',
            'grace' => '',
            'lock' => 'Disable',
            'singlesession' => 'Disable',
        ];
        $onlogin = (string)$onlogin;
        $raw = '';
        if (preg_match('/:\s*put\s*\(\s*["\']([^"\']+)["\']/i', $onlogin, $pm)) {
            $raw = trim($pm[1]);
        } elseif (preg_match('/,\s*(rem|remc|ntf|ntfc)\s*,.*/i', $onlogin, $pm)) {
            $raw = trim($pm[0]);
        }

        if (!empty($raw)) {
            $parts = array_map('trim', explode(',', $raw));
            if (isset($parts[0]) && $parts[0] === '') {
                array_shift($parts);
            }
            if (isset($parts[0]) && in_array(strtolower($parts[0]), ['rem', 'remc', 'ntf', 'ntfc'])) {
                $meta['mode'] = strtolower($parts[0]);
                $meta['price'] = ($parts[1] ?? '0') === '0' ? '' : ($parts[1] ?? '');
                $meta['validity'] = $parts[2] ?? '';
                $meta['sprice'] = ($parts[3] ?? '0') === '0' ? '' : ($parts[3] ?? '');
                $meta['grace'] = $parts[4] ?? '';
                $meta['lock'] = (!empty($parts[5]) && in_array($parts[5], ['Enable', 'Disable'])) ? $parts[5] : 'Disable';
                $meta['singlesession'] = (!empty($parts[6]) && in_array($parts[6], ['Enable', 'Disable', 'Username', 'MAC'])) ? $parts[6] : 'Disable';
            } elseif (isset($parts[4]) && $parts[4] === 'noexp') {
                $meta['mode'] = '0';
                $meta['price'] = ($parts[1] ?? '0') === '0' ? '' : ($parts[1] ?? '');
                $meta['lock'] = (!empty($parts[5]) && in_array($parts[5], ['Enable', 'Disable'])) ? $parts[5] : 'Disable';
                $meta['singlesession'] = (!empty($parts[6]) && in_array($parts[6], ['Enable', 'Disable', 'Username', 'MAC'])) ? $parts[6] : 'Disable';
            }
        } else {
            $legacy = explode(',', $onlogin);
            if (isset($legacy[1]) && in_array($legacy[1], ['rem', 'ntf', 'remc', 'ntfc'])) {
                $meta['mode'] = $legacy[1];
                $meta['price'] = ($legacy[2] ?? '0') === '0' ? '' : ($legacy[2] ?? '');
                $meta['validity'] = $legacy[3] ?? '';
                $meta['sprice'] = ($legacy[4] ?? '0') === '0' ? '' : ($legacy[4] ?? '');
                $meta['lock'] = (!empty($legacy[6]) && in_array($legacy[6], ['Enable', 'Disable'])) ? $legacy[6] : 'Disable';
                $meta['singlesession'] = $legacy[7] ?? 'Disable';
            }
        }

        if ($meta['mode'] === 'rem') {
            $meta['modet'] = 'Remove';
        } elseif ($meta['mode'] === 'ntf') {
            $meta['modet'] = 'Notice';
        } elseif ($meta['mode'] === 'remc') {
            $meta['modet'] = 'Remove & Record';
        } elseif ($meta['mode'] === 'ntfc') {
            $meta['modet'] = 'Notice & Record';
        } else {
            $meta['mode'] = '0';
            $meta['modet'] = 'None';
        }

        return $meta;
    }
}



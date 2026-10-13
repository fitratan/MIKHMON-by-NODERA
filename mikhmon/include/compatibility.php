<?php
/**
 * Universal PHP Compatibility Layer (PHP 7.0 - PHP 8.4)
 * by NODERA (nodera.id)
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        if ($needle === '' || $needle === null) return true;
        return substr((string)$haystack, 0, strlen((string)$needle)) === (string)$needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '' || $needle === null) return true;
        $len = strlen((string)$needle);
        if ($len === 0) return true;
        return substr((string)$haystack, -$len) === (string)$needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        if ($needle === '' || $needle === null) return true;
        return strpos((string)$haystack, (string)$needle) !== false;
    }
}

if (!function_exists('mikhmon_curl_set_ssl')) {
    function mikhmon_curl_set_ssl($ch) {
        if (!is_resource($ch) && !($ch instanceof \CurlHandle)) {
            return;
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $caCandidates = [
            __DIR__ . '/../cacert.pem',
            __DIR__ . '/cacert.pem',
            dirname(__DIR__, 2) . '/bin/php-win/cacert.pem',
            dirname(__DIR__) . '/bin/php-win/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
        ];
        foreach ($caCandidates as $ca) {
            if (file_exists($ca) && is_readable($ca)) {
                curl_setopt($ch, CURLOPT_CAINFO, realpath($ca));
                break;
            }
        }
    }
}

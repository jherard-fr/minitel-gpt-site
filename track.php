<?php
// Collecteur de visites — enregistre une ligne JSON par hit dans data/hits.jsonl
$dir = __DIR__ . '/data';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

function client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '';
}

$rec = [
    't'    => date('c'),
    'ip'   => client_ip(),
    'ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 400),
    'ref'  => substr($_GET['r'] ?? '', 0, 400),
    'page' => substr($_GET['p'] ?? '/', 0, 200),
    'lang' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 40),
];
@file_put_contents($dir . '/hits.jsonl', json_encode($rec, JSON_UNESCAPED_SLASHES) . "\n",
                   FILE_APPEND | LOCK_EX);

// Répond un GIF transparent 1×1
header('Content-Type: image/gif');
header('Cache-Control: no-store');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

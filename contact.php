<?php
// Formulaire de contact → envoi d'un email à Jérôme via l'API Resend.
// La clé API n'est PAS dans le dépôt (public) : elle est injectée au
// déploiement dans resend-key.php (gitignoré) depuis un secret GitHub.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

// Anti-spam : le champ « website » (honeypot) doit rester vide pour un humain.
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]); // on fait semblant d'accepter, on jette
    exit;
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}
if (mb_strlen($name) > 80 || mb_strlen($email) > 120 || mb_strlen($message) > 3000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'too_long']);
    exit;
}

// ── Garde-fou anti-flood ────────────────────────────────────────────────
$rlDir = __DIR__ . '/data';
if (!is_dir($rlDir)) @mkdir($rlDir, 0775, true);
$now = time();
$ip  = $_SERVER['REMOTE_ADDR'] ?? 'x';

// 1 envoi / 60 s par adresse IP
$ipFile = $rlDir . '/rl_' . md5($ip) . '.txt';
$last   = is_file($ipFile) ? (int) @file_get_contents($ipFile) : 0;
if ($now - $last < 60) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate']);
    exit;
}

// Plafond global : 20 envois / heure
$gFile = $rlDir . '/rl_global.txt';
$hits  = is_file($gFile) ? array_map('intval', file($gFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
$hits  = array_values(array_filter($hits, function ($t) use ($now) { return $now - $t < 3600; }));
if (count($hits) >= 20) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate']);
    exit;
}

// On réserve le créneau dès maintenant (empêche les rafales même si l'envoi traîne).
@file_put_contents($ipFile, (string) $now, LOCK_EX);
$hits[] = $now;
@file_put_contents($gFile, implode("\n", $hits) . "\n", LOCK_EX);

// Clé API Resend : fichier injecté au déploiement, sinon variable d'environnement.
$keyFile = __DIR__ . '/resend-key.php';
$apiKey  = is_file($keyFile) ? (include $keyFile) : (getenv('RESEND_API_KEY') ?: '');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'config']); // pas d'envoi silencieux
    exit;
}

$safeName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeMsg   = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$html = "<p><strong>Nouveau message depuis minitel-gpt.herard.com</strong></p>"
      . "<p><strong>Nom :</strong> {$safeName}<br>"
      . "<strong>Email :</strong> {$safeEmail}</p>"
      . "<hr><p>{$safeMsg}</p>";
$text = "Nouveau message depuis minitel-gpt.herard.com\n\n"
      . "Nom : {$name}\nEmail : {$email}\n\n{$message}\n";

$payload = [
    'from'     => 'MINITEL GPT <onboarding@resend.dev>',
    'to'       => ['jerome@herard.com'],
    'reply_to' => $email,
    'subject'  => 'Contact MINITEL GPT - ' . mb_substr($name, 0, 60),
    'html'     => $html,
    'text'     => $text,
];

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json; charset=utf-8',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 20,
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    echo json_encode(['ok' => true]);
} else {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/contact-errors.log',
        date('c') . " HTTP {$code} {$err} " . substr((string) $resp, 0, 300) . "\n",
        FILE_APPEND | LOCK_EX);
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
}

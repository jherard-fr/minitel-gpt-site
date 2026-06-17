<?php
// Tableau de bord des statistiques — accès protégé par token.
// URL : https://minitel-gpt.herard.com/stats.php?token=VOTRE_TOKEN
$TOKEN_HASH = '83e78a52158daafa02ae6413b410f4d754e8ae3f4c9ddc7466e356824494c92a';

$token = $_GET['token'] ?? '';
if (hash('sha256', $token) !== $TOKEN_HASH) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset=utf-8><body style="background:#1b1b1f;color:#e6e6e6;font-family:monospace;text-align:center;padding:60px">';
    echo '<h2 style="color:#ff5b5b">Accès refusé</h2><p>Token invalide.</p></body>';
    exit;
}

$dir  = __DIR__ . '/data';
$file = $dir . '/hits.jsonl';
$hits = [];
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $r = json_decode($l, true);
        if ($r) $hits[] = $r;
    }
}

// ── Parsing navigateur / OS ──────────────────────────────────────────────
function parse_browser($ua) {
    $ua = $ua ?: '';
    if (preg_match('/Edg\//i', $ua))      return 'Edge';
    if (preg_match('/OPR\/|Opera/i', $ua)) return 'Opera';
    if (preg_match('/Firefox/i', $ua))    return 'Firefox';
    if (preg_match('/Chrome/i', $ua))     return 'Chrome';
    if (preg_match('/Safari/i', $ua))     return 'Safari';
    if (preg_match('/bot|crawl|spider/i', $ua)) return 'Bot';
    return 'Autre';
}
function parse_os($ua) {
    $ua = $ua ?: '';
    if (preg_match('/Windows/i', $ua))            return 'Windows';
    if (preg_match('/iPhone|iPad|iOS/i', $ua))    return 'iOS';
    if (preg_match('/Android/i', $ua))            return 'Android';
    if (preg_match('/Mac OS X|Macintosh/i', $ua)) return 'macOS';
    if (preg_match('/Linux/i', $ua))              return 'Linux';
    return 'Autre';
}
function ref_domain($ref) {
    if (!$ref) return '(direct)';
    $h = parse_url($ref, PHP_URL_HOST);
    return $h ?: '(direct)';
}

// ── Géolocalisation des IP (cache + ip-api.com, max 40 nouvelles/charge) ──
$geo_file = $dir . '/geo.json';
$geo = is_file($geo_file) ? (json_decode(file_get_contents($geo_file), true) ?: []) : [];
$ips = array_values(array_unique(array_filter(array_map(fn($h) => $h['ip'] ?? '', $hits))));
$new = 0;
foreach ($ips as $ip) {
    if (isset($geo[$ip]) || $new >= 40) continue;
    $j = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city");
    if ($j) {
        $d = json_decode($j, true);
        $geo[$ip] = trim(($d['city'] ?? '') . ', ' . ($d['country'] ?? ''), ', ') ?: '?';
    }
    $new++;
}
if ($new) @file_put_contents($geo_file, json_encode($geo));

// ── Agrégations ──────────────────────────────────────────────────────────
$total = count($hits);
$by_day = $by_browser = $by_os = $by_ref = $by_geo = [];
$uniq_ip = [];
foreach ($hits as $h) {
    $day = substr($h['t'] ?? '', 0, 10);
    $by_day[$day] = ($by_day[$day] ?? 0) + 1;
    $by_browser[parse_browser($h['ua'] ?? '')] = ($by_browser[parse_browser($h['ua'] ?? '')] ?? 0) + 1;
    $by_os[parse_os($h['ua'] ?? '')] = ($by_os[parse_os($h['ua'] ?? '')] ?? 0) + 1;
    $by_ref[ref_domain($h['ref'] ?? '')] = ($by_ref[ref_domain($h['ref'] ?? '')] ?? 0) + 1;
    $loc = $geo[$h['ip'] ?? ''] ?? '?';
    $by_geo[$loc] = ($by_geo[$loc] ?? 0) + 1;
    if (!empty($h['ip'])) $uniq_ip[$h['ip']] = 1;
}
krsort($by_day);
arsort($by_browser); arsort($by_os); arsort($by_ref); arsort($by_geo);
$days30 = array_slice($by_day, 0, 30, true);

function table($title, $data, $limit = 12) {
    echo "<div class=card><h2>" . htmlspecialchars($title) . "</h2><table>";
    $i = 0;
    foreach ($data as $k => $v) {
        if ($i++ >= $limit) break;
        $k = htmlspecialchars($k === '' ? '(vide)' : $k);
        echo "<tr><td>$k</td><td class=n>$v</td></tr>";
    }
    if (!$data) echo "<tr><td>(aucune donnée)</td><td></td></tr>";
    echo "</table></div>";
}
?><!DOCTYPE html><html lang=fr><head><meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1">
<title>Stats — MINITEL GPT</title>
<style>
:root{--accent:#4ecdc4;--bg:#1b1b1f;--card:#26262b;--border:#3a3a42;--muted:#9a9aa4}
*{box-sizing:border-box}body{background:var(--bg);color:#e6e6e6;font-family:'Courier New',monospace;margin:0;padding:20px}
h1{color:var(--accent)}h2{color:var(--accent);font-size:1em;margin:0 0 10px}
.kpis{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px 22px;text-align:center}
.kpi b{display:block;font-size:1.8em;color:var(--accent)}.kpi span{color:var(--muted);font-size:.85em}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px}
table{width:100%;border-collapse:collapse;font-size:.9em}
td{padding:5px 6px;border-bottom:1px solid var(--border)}
td.n{text-align:right;color:var(--accent);width:60px}
.bar{height:8px;background:var(--accent);border-radius:4px;margin-top:6px}
</style></head><body>
<h1>📊 Statistiques — MINITEL GPT</h1>
<div class=kpis>
  <div class=kpi><b><?= $total ?></b><span>visites totales</span></div>
  <div class=kpi><b><?= count($uniq_ip) ?></b><span>visiteurs uniques (IP)</span></div>
  <div class=kpi><b><?= $days30 ? reset($days30) : 0 ?></b><span>aujourd'hui</span></div>
</div>
<div class=grid>
<?php
table("Trafic par jour (30 j)", $days30, 30);
table("Provenance (ville, pays)", $by_geo);
table("Sites référents", $by_ref);
table("Navigateurs", $by_browser);
table("Systèmes d'exploitation", $by_os);
?>
</div>
<p style="color:var(--muted);font-size:.8em;margin-top:20px">Géoloc via ip-api.com (cache). Mise à jour à chaque chargement.</p>
</body></html>

<?php
// Tableau de bord des statistiques - accès protégé par token.
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
$PAGE_LABELS = [
    '/' => 'Accueil', '/index.html' => 'Accueil',
    '/notice-cablage.html' => 'Câblage pas à pas',
    '/interface-admin.html' => 'Interface admin',
    '/github.html' => 'Code GitHub', '/contact.html' => 'Contact',
];
$total = count($hits);
$by_day = $by_browser = $by_os = $by_ref = $by_geo = [];
$day_ips = $by_page = $uniq_ip = [];
foreach ($hits as $h) {
    $day = substr($h['t'] ?? '', 0, 10);
    if ($day) {
        $by_day[$day] = ($by_day[$day] ?? 0) + 1;
        if (!empty($h['ip'])) $day_ips[$day][$h['ip']] = 1;
    }
    $by_browser[parse_browser($h['ua'] ?? '')] = ($by_browser[parse_browser($h['ua'] ?? '')] ?? 0) + 1;
    $by_os[parse_os($h['ua'] ?? '')] = ($by_os[parse_os($h['ua'] ?? '')] ?? 0) + 1;
    $by_ref[ref_domain($h['ref'] ?? '')] = ($by_ref[ref_domain($h['ref'] ?? '')] ?? 0) + 1;
    $loc = $geo[$h['ip'] ?? ''] ?? '?';
    $by_geo[$loc] = ($by_geo[$loc] ?? 0) + 1;
    $p = $h['page'] ?? '/'; if ($p === '') $p = '/';
    $pname = $PAGE_LABELS[$p] ?? $p;
    $by_page[$pname] = ($by_page[$pname] ?? 0) + 1;
    if (!empty($h['ip'])) $uniq_ip[$h['ip']] = 1;
}
krsort($by_day);
arsort($by_browser); arsort($by_os); arsort($by_ref); arsort($by_geo); arsort($by_page);

// Séries pour la courbe quotidienne (30 derniers jours, ordre chronologique)
$chart_days = $chart_views = $chart_visitors = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_days[] = $d;
    $chart_views[] = $by_day[$d] ?? 0;
    $chart_visitors[] = isset($day_ips[$d]) ? count($day_ips[$d]) : 0;
}
$today_views = $by_day[date('Y-m-d')] ?? 0;

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

function daily_chart($days, $views, $visitors) {
    $n = count($days);
    if ($n < 2) return '<p style="color:#9a9aa4">Pas encore assez de données.</p>';
    $W = 720; $H = 240; $L = 34; $R = 12; $T = 14; $B = 26;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $max = max(1, max($views), max($visitors));
    $px = fn($i) => $L + ($pw * $i / ($n - 1));
    $py = fn($v) => $T + $ph - ($ph * $v / $max);
    $line = function($arr) use ($px, $py, $n) {
        $p = [];
        for ($i = 0; $i < $n; $i++) $p[] = round($px($i), 1) . ',' . round($py($arr[$i]), 1);
        return implode(' ', $p);
    };
    $svg  = "<svg viewBox='0 0 $W $H' style='width:100%;height:auto' xmlns='http://www.w3.org/2000/svg'>";
    foreach ([0, 0.5, 1] as $f) {              // grille + axe Y
        $val = (int) round($max * $f); $yy = round($py($val), 1);
        $svg .= "<line x1='$L' y1='$yy' x2='" . ($W - $R) . "' y2='$yy' stroke='#3a3a42' stroke-width='1'/>";
        $svg .= "<text x='" . ($L - 6) . "' y='" . ($yy + 3) . "' text-anchor='end' font-size='9' fill='#9a9aa4'>$val</text>";
    }
    $area = "$L," . ($T + $ph) . " " . $line($views) . " " . round($px($n - 1), 1) . "," . ($T + $ph);
    $svg .= "<polygon points='$area' fill='#4ecdc4' opacity='0.12'/>";
    $svg .= "<polyline points='" . $line($views) . "' fill='none' stroke='#4ecdc4' stroke-width='2'/>";
    $svg .= "<polyline points='" . $line($visitors) . "' fill='none' stroke='#ffd166' stroke-width='2'/>";
    $step = max(1, (int) floor($n / 6));        // labels X (~6 dates)
    for ($i = 0; $i < $n; $i += $step) {
        $svg .= "<text x='" . round($px($i), 1) . "' y='" . ($H - 8) . "' text-anchor='middle' font-size='9' fill='#9a9aa4'>"
              . date('d/m', strtotime($days[$i])) . "</text>";
    }
    return $svg . "</svg>";
}
?><!DOCTYPE html><html lang=fr><head><meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1">
<title>Stats - MINITEL GPT</title>
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
.legend{display:flex;gap:18px;flex-wrap:wrap;margin-top:10px;font-size:.82em;color:var(--muted)}
.legend i{display:inline-block;width:11px;height:11px;border-radius:3px;margin-right:5px;vertical-align:-1px}
</style></head><body>
<h1>📊 Statistiques - MINITEL GPT</h1>
<div class=kpis>
  <div class=kpi><b><?= $total ?></b><span>pages vues</span></div>
  <div class=kpi><b><?= count($uniq_ip) ?></b><span>visiteurs uniques (IP)</span></div>
  <div class=kpi><b><?= $today_views ?></b><span>pages vues aujourd'hui</span></div>
</div>
<div class=card style="margin-bottom:16px">
  <h2>Visites &amp; pages vues par jour (30 j)</h2>
  <?= daily_chart($chart_days, $chart_views, $chart_visitors) ?>
  <div class=legend>
    <span><i style="background:#ffd166"></i>Visites (visiteurs uniques / jour)</span>
    <span><i style="background:#4ecdc4"></i>Pages vues</span>
  </div>
</div>
<div class=grid>
<?php
table("Audience par page", $by_page);
table("Provenance (ville, pays)", $by_geo);
table("Sites référents", $by_ref);
table("Navigateurs", $by_browser);
table("Systèmes d'exploitation", $by_os);
?>
</div>
<p style="color:var(--muted);font-size:.8em;margin-top:20px">Géoloc via ip-api.com (cache). Mise à jour à chaque chargement.</p>
</body></html>

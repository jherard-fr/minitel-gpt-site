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

// ── Exclusion de mes visites (case cochée par défaut) ────────────────────
$MY_IPS = ['88.160.78.52'];   // IP(s) explicitement exclues
$base = 'stats.php?token=' . urlencode($token);
$exclude_on = ($_GET['myip'] ?? '1') !== '0';

// IP du visiteur courant (toi, quand tu ouvres les stats) — détection track.php
$my_ip = '';
foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) {
        $c = trim(explode(',', $_SERVER[$k])[0]);
        if (filter_var($c, FILTER_VALIDATE_IP)) { $my_ip = $c; break; }
    }
}

// Un hit est "à moi" s'il est dans $MY_IPS, ou dans le même réseau que mon IP
// courante : /64 en IPv6 (gère la rotation d'adresses), exact en IPv4.
function ip_is_mine($ip, $my_ip, $list) {
    if ($ip === '') return false;
    if (in_array($ip, $list, true)) return true;
    if (!$my_ip) return false;
    $a = @inet_pton($ip); $b = @inet_pton($my_ip);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    return strlen($a) === 16 ? substr($a, 0, 8) === substr($b, 0, 8) : $a === $b;
}

$all_hits = $hits;
if ($exclude_on) {
    $hits = array_values(array_filter($all_hits, fn($h) => !ip_is_mine($h['ip'] ?? '', $my_ip, $MY_IPS)));
}
$hidden = count($all_hits) - count($hits);

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
$day_ips = $uniq_ip = [];
$by_page = ['Accueil' => 0, 'Câblage pas à pas' => 0, 'Interface admin' => 0, 'Code GitHub' => 0, 'Contact' => 0];
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

// Série quotidienne (90 derniers jours) pour la courbe interactive (rendue en JS)
$daily = [];
for ($i = 89; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily[] = ['d' => $d, 'v' => $by_day[$d] ?? 0, 'u' => isset($day_ips[$d]) ? count($day_ips[$d]) : 0];
}
$daily_json = json_encode($daily);
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
select{background:#1b1b1f;color:#e6e6e6;border:1px solid var(--border);border-radius:6px;padding:4px 9px;font:inherit;margin-left:auto;cursor:pointer}
.excl{display:inline-flex;align-items:center;gap:8px;color:var(--muted);font-size:.85em;margin:-6px 0 16px;cursor:pointer}
.excl input{accent-color:var(--accent);cursor:pointer}
.iptag{display:inline-block;background:#1b1b1f;border:1px solid var(--border);border-radius:12px;padding:2px 9px;margin:2px 4px 2px 0;font-size:.85em}
.iptag a{color:#ff6b6b;text-decoration:none;font-weight:700;margin-left:4px}
a{color:var(--accent)}
#tip{position:absolute;display:none;background:#0d0d1a;border:1px solid var(--border);border-radius:6px;padding:6px 9px;font-size:.8em;pointer-events:none;white-space:nowrap;transform:translate(-50%,-115%);z-index:5}
</style></head><body>
<h1>📊 Statistiques - MINITEL GPT</h1>
<label class=excl>
  <input type=checkbox <?= $exclude_on ? 'checked' : '' ?>
    onchange="location.href='<?= $base ?>&myip='+(this.checked?'1':'0')">
  Exclure mes visites<?= $my_ip ? ' (réseau de ' . htmlspecialchars($my_ip) . ')' : '' ?><?= ' · ' . $hidden . ' vue' . ($hidden > 1 ? 's' : '') . ' masquée' . ($hidden > 1 ? 's' : '') ?>
</label>
<div class=kpis>
  <div class=kpi><b><?= $total ?></b><span>pages vues</span></div>
  <div class=kpi><b><?= count($uniq_ip) ?></b><span>visiteurs uniques (IP)</span></div>
  <div class=kpi><b><?= $today_views ?></b><span>pages vues aujourd'hui</span></div>
</div>
<div class=card style="margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px">
    <h2 style="margin:0">Visites &amp; pages vues par jour</h2>
    <select id=range>
      <option value=7 selected>7 jours</option>
      <option value=30>30 jours</option>
      <option value=90>90 jours</option>
    </select>
  </div>
  <div style="position:relative;height:280px"><canvas id=chart></canvas></div>
</div>
<?php table("Audience par page", $by_page); ?>
<div class=grid style="margin-top:16px">
<?php
table("Provenance (ville, pays)", $by_geo);
table("Sites référents", $by_ref);
table("Navigateurs", $by_browser);
table("Systèmes d'exploitation", $by_os);
?>
</div>
<p style="color:var(--muted);font-size:.8em;margin-top:20px">Géoloc via ip-api.com (cache). Mise à jour à chaque chargement.</p>

<script src="assets/chart.min.js"></script>
<script>
var DAILY = <?= $daily_json ?>;
(function(){
  var el = document.getElementById('chart'), sel = document.getElementById('range'), chart;
  function build(n){
    var data = DAILY.slice(-n);
    var cfg = {
      type: 'line',
      data: {
        labels: data.map(function(p){ return p.d.slice(8) + '/' + p.d.slice(5,7); }),
        datasets: [
          { label: 'Pages vues', data: data.map(function(p){ return p.v; }),
            borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,.15)',
            fill: true, tension: .25, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5 },
          { label: 'Visites', data: data.map(function(p){ return p.u; }),
            borderColor: '#ffd166', backgroundColor: 'transparent',
            fill: false, tension: .25, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { color: '#cfcfd6', boxWidth: 14, usePointStyle: true } } },
        scales: {
          x: { ticks: { color: '#9a9aa4', maxTicksLimit: 9, autoSkip: true }, grid: { color: 'rgba(58,58,66,.5)' } },
          y: { beginAtZero: true, ticks: { color: '#9a9aa4', precision: 0 }, grid: { color: 'rgba(58,58,66,.5)' } }
        }
      }
    };
    if (chart) chart.destroy();
    chart = new Chart(el, cfg);
  }
  sel.addEventListener('change', function(){ build(+this.value); });
  build(7);
})();
</script>
</body></html>

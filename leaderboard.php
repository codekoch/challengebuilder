<?php
// Erlaubt den Zugriff von überall (wichtig, wenn das HTML lokal oder woanders liegt)
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; } // Für Preflight-Requests

$now = time();
$timeout = 45;

// Garbage Collection: Alle vorhandenen data_*.json Dateien prüfen und bereinigen
// Da jede Polling-Anfrage die Datei überschreibt, ist die Dateizeit (filemtime) ein exakter Indikator.
$allFiles = glob('data_*.json');
if ($allFiles) {
    foreach ($allFiles as $file) {
        if (is_file($file) && ($now - filemtime($file) > $timeout)) {
            @unlink($file);
        }
    }
}

// Direkter Aufruf im Browser (Feedback)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Aktive Challenges</title>";
    echo "<meta http-equiv=\"refresh\" content=\"3\">";
    echo "<style>";
    echo "body { font-family: system-ui, sans-serif; background: #f0f2f5; padding: 2rem; max-width: 800px; margin: 0 auto; color: #333; }";
    echo ".card { background: #fff; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }";
    echo "h1 { color: #0056b3; } h3 { margin-top: 0; color: #000; border-bottom: 2px solid #0056b3; padding-bottom: 0.5rem; } ";
    echo "ul { list-style: none; padding-left: 0; margin-bottom: 0; } li { padding: 0.5rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; } li:last-child { border-bottom: none; }";
    echo "span.points { color: #28a745; font-weight: bold; background: #e8f5e9; padding: 0.2rem 0.5rem; border-radius: 4px; }";
    echo "span.time { color: #888; font-size: 0.85em; }";
    echo "</style></head><body>";
    echo "<h1>📡 Challenge Server - Status</h1>";
    
    // Dateien nach GC neu einlesen (verwaiste Dateien sind bereits gelöscht)
    $activeFiles = glob('data_*.json');
    if (empty($activeFiles)) {
        echo "<div class='card' style='text-align: center; color: #666;'>Aktuell laufen keine Challenges.</div>";
    } else {
        foreach ($activeFiles as $file) {
            $gameId = str_replace(['data_', '.json'], '', basename($file));
            $content = @file_get_contents($file);
            $data = $content ? json_decode($content, true) : [];
            // Kompatibilität mit altem Format (wo direkt ein Array von Spielern gespeichert wurde)
            $players = isset($data['players']) ? $data['players'] : (is_array($data) && !isset($data['state']) ? $data : []);
            $state = isset($data['state']) ? $data['state'] : 'lobby';
            
            if (!empty($players)) {
                uasort($players, function($a, $b) { return $b['points'] <=> $a['points']; });
                
                $playingCount = 0;
                foreach ($players as $id => $info) {
                    if (empty($info['isFinished'])) $playingCount++;
                }
                
                $stateBadge = $state === 'running' ? "<span style='color:red; font-size:0.8em;'>[LÄUFT" . ($playingCount > 0 ? " &mdash; $playingCount noch im Spiel" : "") . "]</span>" : "<span style='color:orange; font-size:0.8em;'>[LOBBY]</span>";
                echo "<div class='card'>";
                echo "<h3>🎮 " . htmlspecialchars($gameId) . " " . $stateBadge . " <span style='float: right; color: #0056b3; font-size: 0.9em;'>" . count($players) . " Spieler</span></h3><ul>";
                
                $rank = 1;
                foreach ($players as $id => $info) {
                    $sec = $now - $info['lastSeen'];
                    $secText = $sec === 0 ? "jetzt gerade" : "vor {$sec}s";
                    $pointsDisplay = !empty($info['isCheater']) ? "<span style='color:#dc3545;'>NICHT GEWERTET</span>" : $info['points'] . " Pkt";
                    $statusDisplay = empty($info['isFinished']) && $state === 'running' ? " <span style='color: #d39e00; font-size: 0.8em;'>(noch im Spiel)</span>" : "";
                    echo "<li><div><strong>#{$rank} " . htmlspecialchars($id) . $statusDisplay . "</strong> <span class='time'>(" . $secText . " aktiv)</span></div> <span class='points'>" . $pointsDisplay . "</span></li>";
                    $rank++;
                }
                echo "</ul></div>";
            }
        }
    }
    echo "</body></html>";
    exit;
}

// Ab hier: API-Aufrufe (POST) durch das Challenge Builder Spiel
header('Content-Type: application/json');

// Eingehende JSON-Daten lesen
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['gameId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Fehlende Daten']);
    exit;
}

$gameId = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['gameId']);

if (isset($input['action']) && $input['action'] === 'reset') {
    $filename = "data_" . $gameId . ".json";
    $fp = fopen($filename, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode(['state' => 'lobby', 'players' => new stdClass(), 'reset' => time()]));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    echo json_encode(['success' => true]);
    exit;
}

if (!isset($input['playerId']) || !isset($input['points'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Fehlende Spieler-Daten']);
    exit;
}

// Sicherheit: gameId bereinigen, damit niemand fremde Dateien überschreiben kann
$playerId = htmlspecialchars(strip_tags($input['playerId']));
$points = (int)$input['points'];
$isReady = isset($input['isReady']) ? (bool)$input['isReady'] : false;
$isCheaterObj = isset($input['isCheater']) ? (bool)$input['isCheater'] : false;
$isFinished = isset($input['isFinished']) ? (bool)$input['isFinished'] : false;

// Der Dateiname basiert auf der gameId (z.B. data_halleffekt_challenge_01.json)
$filename = "data_" . $gameId . ".json";

// Bisherige Daten laden, falls vorhanden
$data = [];
$state = 'lobby';
$resetTime = 0;

$fp = fopen($filename, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['error' => 'Konnte Datei nicht oeffnen']);
    exit;
}

flock($fp, LOCK_EX);

clearstatcache(true, $filename);
$size = filesize($filename);
$jsonString = $size > 0 ? fread($fp, $size) : '';

if ($jsonString) {
    $loadedData = json_decode($jsonString, true) ?: [];
    if (isset($loadedData['players'])) {
        $data = $loadedData['players'];
        $state = isset($loadedData['state']) ? $loadedData['state'] : 'lobby';
        $resetTime = isset($loadedData['reset']) ? $loadedData['reset'] : 0;
    } else {
        // Migration von altem Format
        $data = $loadedData; 
        $state = 'lobby';
    }
}

// Keine neuen Spieler ins laufende Spiel lassen
if (!isset($data[$playerId]) && $state === 'running') {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['error' => 'game_started']);
    exit;
}

// Existierenden Cheat-Status beibehalten (damit niemand es nach Cheat-Nutzung widerrufen kann)
$existingCheater = isset($data[$playerId]['isCheater']) ? $data[$playerId]['isCheater'] : false;
$finalCheater = $isCheaterObj || $existingCheater;

// Aktuellen Spieler eintragen/updaten
$data[$playerId] = [
    'points' => $points,
    'lastSeen' => $now,
    'isReady' => $isReady,
    'isCheater' => $finalCheater,
    'isFinished' => $isFinished
];

// Alte Spieler aussortieren (Timeout: 45 Sekunden) und überprüfen ob alle bereit sind
$activePlayers = [];
$allReady = true;

foreach ($data as $id => $info) {
    if ($now - $info['lastSeen'] <= $timeout) {
        $activePlayers[$id] = $info;
        if (empty($info['isReady'])) {
            $allReady = false;
        }
    }
}

// Wenn mindestens 1 Spieler da ist und ALLE bereit sind, startet das Spiel für alle
if (!empty($activePlayers) && $allReady) {
    $state = 'running';
}

$saveData = [
    'state' => $state,
    'players' => $activePlayers,
    'reset' => $resetTime
];

// Aktualisierte Liste sicher abspeichern
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($saveData));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// Liste nach Punkten absteigend sortieren
uasort($activePlayers, function($a, $b) {
    $aCheat = !empty($a['isCheater']);
    $bCheat = !empty($b['isCheater']);
    // Cheater immer nach unten
    if ($aCheat && !$bCheat) return 1;
    if (!$aCheat && $bCheat) return -1;
    // Ansonsten Punkte vergleichen
    return $b['points'] <=> $a['points'];
});

// Die sortierte Liste und den Status an den Browser des Schülers zurücksenden
echo json_encode(['players' => $activePlayers, 'state' => $state, 'reset' => $resetTime]);
?>
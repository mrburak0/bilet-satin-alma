<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'config_ai.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');   // JSON bozulmasın
ini_set('log_errors', '1');

function out_json($arr, $code = 200)
{
    http_response_code($code);
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE'))
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;

    $json = json_encode($arr, $flags);
    if ($json === false) {
        echo json_encode([
            'error' => 'JSON encode failed',
            'detail' => json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo $json;
    exit;
}

if (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} else {
    out_json(['error' => 'Database file not found.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_json(['error' => 'Only POST allowed.'], 405);
}

$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$origin = $_POST['origin'] ?? '';

if (!$startDate || !$endDate || !$origin) {
    out_json(['error' => 'Please select the origin and the date range.'], 400);
}
if ($endDate < $startDate) {
    out_json(['error' => 'End date cannot be before start date.'], 400);
}

global $db;
if (!isset($db)) {
    out_json(['error' => 'DB connection missing ($db).'], 500);
}

function tr_norm($s)
{
    $s = trim((string) $s);
    $map = ['İ' => 'I', 'ı' => 'i', 'Ş' => 'S', 'ş' => 's', 'Ğ' => 'G', 'ğ' => 'g', 'Ü' => 'U', 'ü' => 'u', 'Ö' => 'O', 'ö' => 'o', 'Ç' => 'C', 'ç' => 'c'];
    $s = strtr($s, $map);
    return mb_strtolower($s);
}

function unique_keep_order($arr)
{
    $seen = [];
    $out = [];
    foreach ($arr as $x) {
        $k = tr_norm($x);
        if ($k === '')
            continue;
        if (!isset($seen[$k])) {
            $seen[$k] = true;
            $out[] = $x;
        }
    }
    return $out;
}

function extract_cities_from_ai_text($text)
{
    $text = trim((string) $text);
    if ($text === '')
        return [];

    $text = preg_replace('/```.*?```/s', '', $text);

    $lines = preg_split('/\R+/', $text);
    $candidates = [];

    foreach ($lines as $ln) {
        $ln = trim($ln);
        $ln = preg_replace('/^[-*•\d\)\.]+\s*/u', '', $ln);
        if ($ln === '')
            continue;

        $parts = preg_split('/\s*,\s*/u', $ln);
        foreach ($parts as $p) {
            $p = trim($p);
            $p = preg_replace('/\s*\(.*?\)\s*/u', '', $p);
            $p = trim($p);
            if ($p !== '')
                $candidates[] = $p;
        }
    }

    $candidates = unique_keep_order($candidates);

    $clean = [];
    foreach ($candidates as $c) {
        if (mb_strlen($c) >= 2 && mb_strlen($c) <= 30)
            $clean[] = $c;
    }
    return $clean;
}

function meta_for_city($city)
{
    $k = tr_norm($city);
    $map = [
        'ankara' => ['Anıtkabir', 'Ankara Tava', '🌤️'],
        'istanbul' => ['Ayasofya', 'Balık Ekmek', '🌧️'],
        'izmir' => ['Efes Antik Kenti', 'Boyoz', '🌤️'],
        'antalya' => ['Kaleiçi', 'Piyaz', '☀️'],
        'bursa' => ['Uludağ', 'İskender', '🌨️'],
        'nevsehir' => ['Göreme Açık Hava Müzesi', 'Testi Kebabı', '🍂'],
        'trabzon' => ['Uzungöl', 'Kuymak', '🌧️'],
        'mardin' => ['Eski Mardin', 'Kaburga Dolması', '🍂'],
        'canakkale' => ['Truva Antik Kenti', 'Peynir Helvası', '🌬️'],
        'mugla' => ['Ölüdeniz', 'Çökertme Kebabı', '☀️'],
    ];
    if (isset($map[$k]))
        return ['place' => $map[$k][0], 'food' => $map[$k][1], 'weather' => $map[$k][2]];
    return ['place' => 'City center highlights', 'food' => 'Local cuisine', 'weather' => '🌤️'];
}

function norm_pair_2($a, $b)
{
    $n = [tr_norm($a), tr_norm($b)];
    sort($n);
    return implode('|', $n);
}

/* ===== GEMINI CALL HELPERS ===== */
function gemini_call($url, $promptText)
{
    $data = [
        "contents" => [
            ["parts" => [["text" => $promptText]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return [null, "API connection error: $err"];
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (!$result)
        return [null, "AI response is not valid JSON."];

    if (isset($result['error'])) {
        return [null, "AI Error: " . ($result['error']['message'] ?? 'Unknown')];
    }

    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return [$aiText, null];
}

/* 1) GEMINI */
$apiKey = GEMINI_API_KEY;
if (!$apiKey)
    out_json(['error' => 'Missing GEMINI_API_KEY in config_ai.php'], 500);

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

// last pair (session)
$lastPair = $_SESSION['last_ai_pair'] ?? []; // [city1, city2]
$lastPairText = $lastPair ? implode(", ", $lastPair) : "NONE";

$promptText = "You are a playful travel assistant.

User is free between $startDate and $endDate and will depart from $origin.
We will ALWAYS include Ankara separately, so DO NOT include Ankara in your answer.
Also DO NOT include the origin city ($origin).

Task:
- Recommend EXACTLY 2 DIFFERENT Turkish destination cities (not Ankara, not $origin).
- They MUST be different from the last recommendation pair: $lastPairText
- Make them popular and realistic.

Output format (STRICT):
CITY1, CITY2
No other words. No bullets. No numbers.";

list($aiText, $aiErr) = gemini_call($url, $promptText);
if ($aiErr)
    out_json(['error' => $aiErr], 502);

$geminiCities = extract_cities_from_ai_text($aiText);

// Filtre: Ankara + origin at
$filtered = [];
foreach ($geminiCities as $c) {
    $cn = tr_norm($c);
    if ($cn === tr_norm('ankara'))
        continue;
    if ($cn === tr_norm($origin))
        continue;
    $filtered[] = $c;
}
$filtered = unique_keep_order($filtered);
$pair = array_slice($filtered, 0, 2);

// Eğer 2 şehir yoksa: Gemini’ye 1 kere daha "STRICT" çağrı
if (count($pair) < 2) {
    $promptText2 = "STRICT MODE:
Return EXACTLY 2 DIFFERENT Turkish destination cities (not Ankara, not $origin).
Do NOT repeat last pair: $lastPairText.
Output only: CITY1, CITY2";
    list($aiText2, $aiErr2) = gemini_call($url, $promptText2);
    if (!$aiErr2) {
        $g2 = extract_cities_from_ai_text($aiText2);
        $filtered2 = [];
        foreach ($g2 as $c) {
            $cn = tr_norm($c);
            if ($cn === tr_norm('ankara'))
                continue;
            if ($cn === tr_norm($origin))
                continue;
            $filtered2[] = $c;
        }
        $filtered2 = unique_keep_order($filtered2);
        $pair = array_slice($filtered2, 0, 2);
    }
}

// Eğer hala eksikse: hata dön (havuz yok dedin)
if (count($pair) < 2) {
    out_json([
        'error' => 'AI could not produce exactly 2 cities. Please try again.',
        // debug istersen aç:
        // 'debug_ai_raw' => $aiText
    ], 502);
}

// Eğer last pair ile birebir aynı geldiyse: 1 kere daha retry
if ($lastPair && norm_pair_2($pair[0], $pair[1]) === norm_pair_2($lastPair[0], $lastPair[1])) {
    $promptText3 = "ABSOLUTE RULE:
Do NOT repeat: $lastPairText.
Return EXACTLY 2 DIFFERENT Turkish destination cities (not Ankara, not $origin).
Output only: CITY1, CITY2";
    list($aiText3, $aiErr3) = gemini_call($url, $promptText3);
    if (!$aiErr3) {
        $g3 = extract_cities_from_ai_text($aiText3);
        $filtered3 = [];
        foreach ($g3 as $c) {
            $cn = tr_norm($c);
            if ($cn === tr_norm('ankara'))
                continue;
            if ($cn === tr_norm($origin))
                continue;
            $filtered3[] = $c;
        }
        $filtered3 = unique_keep_order($filtered3);
        $pair3 = array_slice($filtered3, 0, 2);
        if (count($pair3) === 2)
            $pair = $pair3;
    }
}

// session'a kaydet (sadece ikiliyi)
$_SESSION['last_ai_pair'] = $pair;

// final 3 şehir
$finalCities = array_merge(['Ankara'], $pair);

// 2) DB: Sadece kalkış + tarih aralığı (varış önemsiz)
try {
    $stmtAll = $db->prepare("
        SELECT
          id,
          departure_city,
          destination_city,
          departure_time,
          arrival_time,
          date(departure_time) AS trip_date,
          time(departure_time) AS trip_time,
          price,
          capacity
        FROM Trips
        WHERE date(departure_time) BETWEEN date(?) AND date(?)
        ORDER BY departure_time ASC
        LIMIT 200
    ");
    $stmtAll->execute([$startDate, $endDate]);
    $allTripsInRange = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $originN = tr_norm($origin);
    $tickets = array_values(array_filter($allTripsInRange, function ($t) use ($originN) {
        return tr_norm($t['departure_city'] ?? '') === $originN;
    }));

    $tickets = array_slice($tickets, 0, 6);

    $ai = [];
    foreach (array_slice($finalCities, 0, 3) as $c) {
        $m = meta_for_city($c);
        $ai[] = ['city' => $c, 'place' => $m['place'], 'food' => $m['food'], 'weather' => $m['weather']];
    }

    out_json([
        'status' => 'success',
        'range' => ['origin' => $origin, 'start_date' => $startDate, 'end_date' => $endDate],
        'ai_suggestions' => array_slice($finalCities, 0, 3),
        'ai' => $ai,
        'tickets' => $tickets
        // debug:
        // 'debug_ai_raw' => $aiText
    ]);

} catch (Throwable $e) {
    out_json(['error' => 'Database error: ' . $e->getMessage()], 500);
}

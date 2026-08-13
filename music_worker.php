<?php
/**
 * music_worker.php — shared hostingda joylashadi
 * handlers/music.php tomonidan chaqiriladi
 * Railway'dagi /download endpointiga so'rov yuboradi
 *
 * SOZLASH:
 *   RAILWAY_URL  → Railway proyektingiz URL (masalan: https://ytdlp-search-production.up.railway.app)
 *   API_SECRET   → Railway Variables'dagi API_SECRET bilan bir xil bo'lishi SHART
 */

define('RAILWAY_URL', 'https://ytdlp-search-production.up.railway.app'); // ← o'zgartiring
define('API_SECRET',  'o'zgartiring_yaxshi_parol_qo\'ying');              // ← Railway'dagi API_SECRET bilan bir xil

// ── Xavfsizlik: faqat ichki chaqiruvlarga ruxsat ────────────
$incoming_secret = $_POST['secret'] ?? '';
if ($incoming_secret !== API_SECRET) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Parametrlarni olish ──────────────────────────────────────
$chat_id     = $_POST['chat_id']     ?? '';
$uid         = $_POST['uid']         ?? '';
$title       = $_POST['title']       ?? 'Musiqa';
$artist      = $_POST['artist']      ?? '';
$youtube_url = $_POST['youtube_url'] ?? '';

if (!$chat_id || !$youtube_url) {
    http_response_code(400);
    exit('Bad Request');
}

// ── Railway'ga so'rov yuborish ───────────────────────────────
$ch = curl_init(RAILWAY_URL . '/download');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'secret'      => API_SECRET,
        'chat_id'     => $chat_id,
        'youtube_url' => $youtube_url,
        'title'       => $title,
        'artist'      => $artist,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 200,        // Railway yuklab olish vaqtini kutadi
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code ?: 500);
echo $response;

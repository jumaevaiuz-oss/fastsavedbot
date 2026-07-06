<?php
// Musiqa yuklab olish worker'i — bot.php webhook so'roviga tezkor javob
// qaytarish uchun uzoq davom etadigan Cobalt konvertatsiyasi + yuklab olish +
// Telegram'ga yuklash ishi shu alohida so'rovga ajratilgan (bot.php buni
// "fire-and-forget" tarzda, javobni kutmasdan chaqiradi). bot.php o'zi qisqa
// timeout bilan ulanishni uzsa ham, ignore_user_abort(true) tufayli bu
// skript oxirigacha davom etadi.
ignore_user_abort(true);
@set_time_limit(150);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/lang/user_lang.php';
require_once __DIR__ . '/lang/translations.php';

// 🔒 Faqat bot.php'ning o'zi (bot tokenidan hosila qilingan maxfiy tokenni
// bilgan holda) shu faylni chaqira oladi.
$secret = $_POST['secret'] ?? '';
if (!hash_equals(youtube_worker_secret(), $secret)) {
    http_response_code(403);
    exit;
}

$chat_id     = $_POST['chat_id'] ?? null;
$uid         = $_POST['uid'] ?? null;
$title       = $_POST['title'] ?? null;
$artist      = $_POST['artist'] ?? null;
$youtube_url = $_POST['youtube_url'] ?? null;

if (!$chat_id || !$youtube_url) {
    http_response_code(400);
    exit;
}

// 🔍 PHP fatal xatosi (masalan xotira yetishmasligi yoki kutilmagan tur
// xatosi) yuz bersa ham, buni jim o'tkazib yubormasdan adminga xabar beramiz.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        bot('sendMessage', [
            'chat_id' => 7827538214,
            'text' => "🚨 music_worker FATAL xato: {$error['message']} ({$error['file']}:{$error['line']})",
        ]);
    }
});

$botusername = bot('getme')->result->username ?? '';

// 🎧 Yangi qidiruv API'si to'g'ridan-to'g'ri audio havolasi bermaydi, faqat
// YouTube havolasini beradi — shu sabab Cobalt API orqali audio (MP3,
// 320kbps) havolasini olamiz.
$music = cobalt_youtube($youtube_url, [
    'downloadMode' => 'audio',
    'audioFormat' => 'mp3',
    'audioBitrate' => '320'
]);

if (!$music) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => lang('music_not_found', $uid)
    ]);
    exit;
}

// 🔹 Top songs bazasini yangilash
$stmt = $connect->prepare("SELECT id FROM top_songs WHERE music_url = ? LIMIT 1");
$stmt->bind_param("s", $music);
$stmt->execute();
$check = $stmt->get_result();
$stmt->close();

if ($check->num_rows == 0) {
    $stmt = $connect->prepare("INSERT INTO top_songs (title, artist, music_url, downloads) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $title, $artist, $music);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $connect->prepare("UPDATE top_songs SET downloads = downloads + 1 WHERE music_url = ?");
    $stmt->bind_param("s", $music);
    $stmt->execute();
    $stmt->close();
}

// 🎧 Musiqani yuborish — Telegram ba'zan Cobalt tunnel havolasini
// to'g'ridan-to'g'ri o'zi ololmaydi ("Bad Request: failed to get HTTP URL
// content"), shu sabab avval o'zimiz yuklab olib, fayl sifatida yuboramiz.
// sys_get_temp_dir() (odatda /tmp) ko'p shared hostinglarda open_basedir
// tomonidan saytning o'z papkasidan tashqarida qoldirilgan bo'ladi — shu
// sabab tempnam() shu yerda "false" qaytarib, keyingi fopen() ValueError
// bilan yiqilib tushardi. O'rniga saytning o'zidagi (allaqachon yozish
// huquqi tasdiqlangan) step/ papkasidan foydalanamiz.
$tmp_music = tempnam(__DIR__ . '/step', 'music_');
$fh = fopen($tmp_music, 'w');
$ch_dl = curl_init($music);
curl_setopt_array($ch_dl, [
    CURLOPT_FILE => $fh,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
]);
curl_exec($ch_dl);
$dl_http_code = curl_getinfo($ch_dl, CURLINFO_HTTP_CODE);
curl_close($ch_dl);
fclose($fh);

if ($dl_http_code != 200 || filesize($tmp_music) < 1000) {
    @unlink($tmp_music);
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => lang('technical', $uid)
    ]);
    exit;
}

bot('sendAudio', [
    'chat_id' => $chat_id,
    'audio' => new CURLFile($tmp_music, 'audio/mpeg', 'audio.mp3'),
    'caption' => "<b>🎵 $artist – $title</b>\n\n<b>Via @$botusername</b>",
    'parse_mode' => 'HTML'
]);
@unlink($tmp_music);

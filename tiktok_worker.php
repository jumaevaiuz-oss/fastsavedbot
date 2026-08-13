<?php
// tiktok_worker.php — fire-and-forget worker
// tiktok.php tomonidan chaqiriladi, faylni yuklab Telegram'ga yuboradi

ignore_user_abort(true);
set_time_limit(300);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/functions.php';

// Xavfsizlik
$secret = $_POST['secret'] ?? '';
if ($secret !== youtube_worker_secret()) {
    http_response_code(403);
    exit('Forbidden');
}

$cid       = $_POST['cid']       ?? '';
$mid       = $_POST['mid']       ?? '';
$video_url = $_POST['video_url'] ?? '';
$caption   = $_POST['caption']   ?? '';
$keyboard  = $_POST['keyboard']  ?? '';
$duration  = (int)($_POST['duration'] ?? 0);
$width     = (int)($_POST['width']    ?? 0);
$height    = (int)($_POST['height']   ?? 0);
$wait_mid  = $_POST['wait_mid']  ?? '';

if (!$cid || !$video_url) exit('Bad Request');

// HTTP javobni darhol yuborish (fire-and-forget)
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_clean();
    header('Content-Length: 0');
    header('Connection: close');
    ob_start();
    echo ' ';
    ob_end_flush();
    flush();
}

// Faylni yuklab olish
$tmp_file = __DIR__ . '/api/' . uniqid('tt_') . '.mp4';
$fp = fopen($tmp_file, 'wb');
$ch = curl_init($video_url);
curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 240,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    CURLOPT_HTTPHEADER     => ['Referer: https://www.tiktok.com/'],
]);
curl_exec($ch);
curl_close($ch);
fclose($fp);

$file_size = file_exists($tmp_file) ? filesize($tmp_file) : 0;

// ⌛ xabarini o'chirish
if ($wait_mid) {
    bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait_mid]);
}

bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);

if ($file_size > 10000) {
    $params = [
        'chat_id'             => $cid,
        'video'               => new CURLFile($tmp_file, 'video/mp4', 'tiktok.mp4'),
        'caption'             => $caption,
        'parse_mode'          => 'html',
        'reply_to_message_id' => $mid,
        'supports_streaming'  => true,
        'reply_markup'        => $keyboard,
    ];
    if ($duration) $params['duration'] = $duration;
    if ($width)    $params['width']    = $width;
    if ($height)   $params['height']   = $height;

    bot('sendVideo', $params);
} else {
    // Fayl yuklanmadi — URL orqali oxirgi urinish
    bot('sendVideo', [
        'chat_id'             => $cid,
        'video'               => $video_url,
        'caption'             => $caption,
        'parse_mode'          => 'html',
        'reply_to_message_id' => $mid,
        'supports_streaming'  => true,
        'reply_markup'        => $keyboard,
    ]);
}

@unlink($tmp_file);

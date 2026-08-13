<?php
// TikTok video yuklab olish — TikWM API orqali (watermarksiz)
// bot.php'dan include qilinadi.

$platform = "tiktok";
$admin_id = 7827538214;

// ── 1. Havolani tozalash ─────────────────────────────────────
preg_match(
    '/https?:\/\/(vm\.|vt\.|www\.)?tiktok\.com\/[^\s]+/i',
    $tx, $matches
);
$tx_clean = !empty($matches[0]) ? trim($matches[0]) : trim($tx);

// ── 2. Progress bar ──────────────────────────────────────────
$wait = send_progress_message($cid, $mid, $uid, "🎬", 10, 200000, false);

// ── 3. TikWM API ─────────────────────────────────────────────
function tikwm_get($url) {
    // vt.tiktok.com short linkni to'liq linkka o'giramiz
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    if (in_array($host, ['vt.tiktok.com', 'vm.tiktok.com'])) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
    }

    $ch = curl_init('https://www.tikwm.com/api/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['url' => $url, 'hd' => 1]),
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;
    $data = json_decode($res, true);
    if (empty($data['data']) || ($data['code'] ?? -1) !== 0) return null;
    return $data['data'];
}

$info = tikwm_get($tx_clean);

// ── 4. Video URL ─────────────────────────────────────────────
if ($info && !empty($info['play'])) {
    $video_url = !empty($info['hdplay']) ? $info['hdplay'] : $info['play'];
} else {
    // Zaxira: Cobalt
    $video_url = cobalt_download($tx_clean);
    $info = null;
}

if (!$video_url) {
    bot('editMessageText', [
        'chat_id'    => $cid,
        'message_id' => $wait,
        'text'       => lang('not_found', $uid),
    ]);
    bot('sendMessage', [
        'chat_id'    => $admin_id,
        'text'       => "🚨 TikTok yuklab bo'lmadi!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 $tx_clean",
        'parse_mode' => 'html',
    ]);
    exit();
}

// ── 5. Faylni yuklab olish (TikWM URL Telegram'ga to'g'ridan kelmaydi) ──
$tmp_dir  = dirname(__DIR__) . '/api/';  // api/ papkasi mavjud
$tmp_file = $tmp_dir . uniqid('tt_') . '.mp4';

$fp = fopen($tmp_file, 'wb');
$ch = curl_init($video_url);
curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER     => [
        'Referer: https://www.tiktok.com/',
        'Accept: video/mp4,video/*;q=0.9,*/*;q=0.8',
    ],
]);
curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);
fclose($fp);

$file_size = file_exists($tmp_file) ? filesize($tmp_file) : 0;

// ── 6. Progress o'chirish ────────────────────────────────────
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait]);
bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);

// ── 7. Caption ───────────────────────────────────────────────
$author  = !empty($info['author']['nickname']) ? htmlspecialchars($info['author']['nickname']) : '';
$title   = !empty($info['title'])              ? htmlspecialchars(mb_substr($info['title'], 0, 100)) : '';
$caption = "<b>🎬 TikTok</b>";
if ($author) $caption .= "\n👤 <b>$author</b>";
if ($title)  $caption .= "\n📝 $title";
$caption .= "\n🔗 Via @$botusername";

// ── 8. Yuborish ──────────────────────────────────────────────
if ($file_size > 10000) {
    // Fayl muvaffaqiyatli yuklandi — Telegram'ga fayl sifatida yuborish
    $params = [
        'chat_id'             => $cid,
        'video'               => new CURLFile($tmp_file, 'video/mp4', 'tiktok.mp4'),
        'caption'             => $caption,
        'parse_mode'          => 'html',
        'reply_to_message_id' => $mid,
        'supports_streaming'  => true,
    ];
    if (!empty($info['duration'])) $params['duration'] = (int)$info['duration'];
    if (!empty($info['width']))    $params['width']    = (int)$info['width'];
    if (!empty($info['height']))   $params['height']   = (int)$info['height'];

    bot('sendVideo', $params);
} else {
    // Fayl yuklanmadi — URL orqali urinib ko'ramiz
    bot('sendVideo', [
        'chat_id'             => $cid,
        'video'               => $video_url,
        'caption'             => $caption,
        'parse_mode'          => 'html',
        'reply_to_message_id' => $mid,
        'supports_streaming'  => true,
    ]);
}

// Vaqtinchalik faylni o'chirish
@unlink($tmp_file);

// ── 9. Statistika ────────────────────────────────────────────
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

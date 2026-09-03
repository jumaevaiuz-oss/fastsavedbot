<?php
// TikTok video yuklab olish — TikWM API

$platform = "tiktok";
$admin_id = 7827538214;

// ── 1. Havolani tozalash ─────────────────────────────────────
preg_match(
    '/https?:\/\/(vm\.|vt\.|www\.)?tiktok\.com\/[^\s]+/i',
    $tx, $matches
);
$tx_clean = !empty($matches[0]) ? trim($matches[0]) : trim($tx);

// ── 2. Cache tekshirish ──────────────────────────────────────
$url_hash  = md5($tx_clean);
$cache_res = mysqli_query($connect, "SELECT file_id FROM video_cache WHERE url_hash = '$url_hash'");
$cache_row = mysqli_fetch_assoc($cache_res);

if ($cache_row) {
    bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);
    $result = bot('sendVideo', [
        'chat_id'             => $cid,
        'video'               => $cache_row['file_id'],
        'reply_to_message_id' => $mid,
        'supports_streaming'  => true,
    ]);
    if ($result && $result->ok) {
        $stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
        $stmt->bind_param("is", $uid, $platform);
        $stmt->execute();
        $stmt->close();
        exit();
    }
    // file_id ishlamasa cache dan o'chirib, qayta yuklaymiz
    mysqli_query($connect, "DELETE FROM video_cache WHERE url_hash = '$url_hash'");
}

// ── 3. Yuklanmoqda xabari ────────────────────────────────────
$wait_res = bot('sendMessage', [
    'chat_id'             => $cid,
    'text'                => '📥',
    'reply_to_message_id' => $mid,
]);
$wait = $wait_res->result->message_id ?? null;

// ── 4. TikWM API ─────────────────────────────────────────────
function tikwm_get($url) {
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
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Debug log
    error_log("[TikTok] TikWM response code: $code | curl_error: $err | body: " . substr($res, 0, 300));

    if (!$res) return null;
    $data = json_decode($res, true);
    if (empty($data['data']) || ($data['code'] ?? -1) !== 0) return null;
    return $data['data'];
}

$info = tikwm_get($tx_clean);

// Debug

// ── 5. Video URL ─────────────────────────────────────────────
if ($info && !empty($info['play'])) {
    $video_url = $info['play'];
} else {
    $video_url = cobalt_download($tx_clean);
    $info      = null;
    error_log("[TikTok] TikWM ishlamadi, Cobalt ga o'tildi: " . ($video_url ? 'topildi' : 'topilmadi'));
}

if (!$video_url) {
    if ($wait) bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait]);
    bot('sendMessage', [
        'chat_id'             => $cid,
        'text'                => lang('not_found', $uid),
        'reply_to_message_id' => $mid,
    ]);
    bot('sendMessage', [
        'chat_id'    => $admin_id,
        'text'       => "🚨 TikTok yuklab bo'lmadi!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 $tx_clean",
        'parse_mode' => 'html',
    ]);
    exit();
}

// ── 6. Caption va tugma ──────────────────────────────────────
$caption  = "📥 Video @$botusername orqali yuklab olindi.";
$keyboard = json_encode([
    'inline_keyboard' => [[
        ['text' => '⤴️ Botni ulashish', 'switch_inline_query' => '']
    ]]
]);

$params = [
    'chat_id'             => $cid,
    'caption'             => $caption,
    'parse_mode'          => 'html',
    'reply_to_message_id' => $mid,
    'supports_streaming'  => true,
    'reply_markup'        => $keyboard,
];
if (!empty($info['duration'])) $params['duration'] = (int)$info['duration'];
if (!empty($info['width']))    $params['width']     = (int)$info['width'];
if (!empty($info['height']))   $params['height']    = (int)$info['height'];

// ── 7. Yuborish ──────────────────────────────────────────────
if ($wait) bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait]);
bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);

$result = bot('sendVideo', array_merge($params, ['video' => $video_url]));

// URL ishlamasa — serverga yuklab Telegram'ga yuborish
if (!$result || !$result->ok) {
    error_log("[TikTok] URL ishlamadi, fayl sifatida yuborilmoqda...");
    $tmp = dirname(__DIR__) . '/api/' . uniqid('tt_') . '.mp4';
    $fp  = fopen($tmp, 'wb');
    $ch  = curl_init($video_url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_HTTPHEADER     => ['Referer: https://www.tiktok.com/'],
    ]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    $fsize = file_exists($tmp) ? filesize($tmp) : 0;
    error_log("[TikTok] Yuklab olindi: $fsize bayt");

    if ($fsize > 10000) {
        $result = bot('sendVideo', array_merge($params, [
            'video' => new CURLFile($tmp, 'video/mp4', 'tiktok.mp4'),
        ]));
        error_log("[TikTok] sendVideo (fayl) natija: " . ($result && $result->ok ? 'OK' : 'XATO - ' . json_encode($result)));
    }
    @unlink($tmp);
}

// ── 8. Cache saqlash ─────────────────────────────────────────
$file_id = $result->result->video->file_id ?? null;
if ($file_id) {
    $stmt = $connect->prepare("INSERT IGNORE INTO video_cache (url_hash, file_id, platform) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $url_hash, $file_id, $platform);
    $stmt->execute();
    $stmt->close();
}

// ── 9. Statistika ────────────────────────────────────────────
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

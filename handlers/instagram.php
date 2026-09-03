<?php
// Instagram video yuklab olish (Cobalt API orqali).

$platform = "instagram";
$admin_id = 7827538214;

// 🔍 Cache tekshirish
$url_hash  = md5($tx);
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
    mysqli_query($connect, "DELETE FROM video_cache WHERE url_hash = '$url_hash'");
}

// 📥 Yuklanmoqda xabari
$wait_res = bot('sendMessage', [
    'chat_id'             => $cid,
    'text'                => '📥',
    'reply_to_message_id' => $mid,
]);
$wait = $wait_res->result->message_id ?? null;

// Cobalt API orqali video olish
$video_url = cobalt_download($tx);

if (!$video_url) {
    if ($wait) bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait]);
    bot('sendMessage', [
        'chat_id'             => $cid,
        'text'                => lang('not_found', $uid),
        'reply_to_message_id' => $mid,
    ]);
    bot('sendMessage', [
        'chat_id'    => $admin_id,
        'text'       => "🚨 Cobalt API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: Instagram\nTekshiruv talab etiladi.",
        'parse_mode' => 'html',
    ]);
    exit();
}

// Caption va tugma
$caption  = "📥 Video @$botusername orqali yuklab olindi.";
$keyboard = json_encode([
    'inline_keyboard' => [[
        ['text' => '⤴️ Botni ulashish', 'switch_inline_query' => '']
    ]]
]);

if ($wait) bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait]);
bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);

$sent = bot('sendVideo', [
    'chat_id'             => $cid,
    'video'               => $video_url,
    'caption'             => $caption,
    'parse_mode'          => 'html',
    'reply_to_message_id' => $mid,
    'supports_streaming'  => true,
    'reply_markup'        => $keyboard,
]);

// URL ishlamasa — fayl sifatida yuborish
if (!$sent || !$sent->ok) {
    $tmp = dirname(__DIR__) . '/api/' . uniqid('ig_') . '.mp4';
    $fp  = fopen($tmp, 'wb');
    $ch  = curl_init($video_url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_HTTPHEADER     => ['Referer: https://www.instagram.com/'],
    ]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if (file_exists($tmp) && filesize($tmp) > 10000) {
        $sent = bot('sendVideo', [
            'chat_id'             => $cid,
            'video'               => new CURLFile($tmp, 'video/mp4', 'instagram.mp4'),
            'caption'             => $caption,
            'parse_mode'          => 'html',
            'reply_to_message_id' => $mid,
            'supports_streaming'  => true,
            'reply_markup'        => $keyboard,
        ]);
    }
    @unlink($tmp);
}

// Cache saqlash
$file_id = $sent->result->video->file_id ?? null;
if ($file_id) {
    $stmt = $connect->prepare("INSERT IGNORE INTO video_cache (url_hash, file_id, platform) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $url_hash, $file_id, $platform);
    $stmt->execute();
    $stmt->close();
}

// Statistika
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

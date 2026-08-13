<?php
// TikTok video yuklab olish — TikWM + fire-and-forget worker

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

// Progress tugagach ⌛
bot('editMessageText', [
    'chat_id'    => $cid,
    'message_id' => $wait,
    'text'       => "⌛",
]);

// ── 3. TikWM API ─────────────────────────────────────────────
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
    if ($video_url) {
        // Cobalt URL ni Telegram to'g'ridan qabul qiladi — oddiy yuborish
        bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $wait]);
        bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);
        bot('sendVideo', [
            'chat_id'             => $cid,
            'video'               => $video_url,
            'caption'             => "📥 Video @$botusername orqali yuklab olindi.",
            'parse_mode'          => 'html',
            'reply_to_message_id' => $mid,
            'supports_streaming'  => true,
            'reply_markup'        => json_encode(['inline_keyboard' => [[['text' => '📤 Ulashish', 'switch_inline_query' => '']]]]),
        ]);
        $stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
        $stmt->bind_param("is", $uid, $platform);
        $stmt->execute();
        $stmt->close();
        exit();
    }

    // Ikkala API ham ishlamadi
    bot('editMessageText', ['chat_id' => $cid, 'message_id' => $wait, 'text' => lang('not_found', $uid)]);
    bot('sendMessage', ['chat_id' => $admin_id, 'text' => "🚨 TikTok yuklab bo'lmadi!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 $tx_clean", 'parse_mode' => 'html']);
    exit();
}

// ── 5. Caption va tugma ──────────────────────────────────────
$caption  = "📥 Video @$botusername orqali yuklab olindi.";
$keyboard = json_encode(['inline_keyboard' => [[['text' => '📤 Ulashish', 'switch_inline_query' => '']]]]);

// ── 6. Fire-and-forget worker ga topshirish ──────────────────
// ⌛ turadi, worker yuklab bo'lgach o'chiradi va video yuboradi
fire_and_forget_worker_request('tiktok_worker.php', [
    'secret'    => youtube_worker_secret(),
    'cid'       => $cid,
    'mid'       => $mid,
    'video_url' => $video_url,
    'caption'   => $caption,
    'keyboard'  => $keyboard,
    'duration'  => $info['duration'] ?? 0,
    'width'     => $info['width']    ?? 0,
    'height'    => $info['height']   ?? 0,
    'wait_mid'  => $wait,
]);

// ── 7. Statistika ────────────────────────────────────────────
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

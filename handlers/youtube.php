<?php
// YouTube Shorts video yuklab olish.
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect
// kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "youtube";
$admin_id = 7827538214;

// 🔹 Havolani tozalash
preg_match('/https?:\/\/(www\.)?(youtube\.com\/shorts\/[^\s?]+|youtu\.be\/[^\s?]+)/i', $tx, $matches);
$tx_clean = $matches[0] ?? $tx;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "▶️", 10, 200000, false);

// 2️⃣ yt-dlp shortsapi orqali video olish
$api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/shortsapi.php?url=" . urlencode($tx_clean);
$ch = curl_init($api);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ❌ API ishlamayapti
if ($res === false || empty($res) || $http_code != 200) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('technical', $uid)
    ]);
    exit();
}

$get = json_decode($res, true);
$video_url = $get['url'] ?? null;

// ❌ Video topilmasa
if (!$video_url) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('not_found', $uid)
    ]);
    exit();
}

// ✅ Progressni o‘chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// 💬 Video yuborish effekti
bot('sendChatAction', [
    'chat_id' => $cid,
    'action' => 'upload_video'
]);

// ✅ Video yuborish (captionsiz)
bot('sendVideo', [
    'chat_id' => $cid,
    'video' => $video_url,
    'reply_to_message_id' => $mid
]);

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

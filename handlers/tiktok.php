<?php
// TikTok video yuklab olish.
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect,
// $botusername kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "tiktok";
$admin_id = 7827538214; // 🛠 Admin ID

// 🔹 Havolani tozalash
preg_match('/https?:\/\/(vm|vt|www)\.tiktok\.com\/[^\s]+/i', $tx, $matches);
$tx_clean = !empty($matches[0]) ? $matches[0] : $tx;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "🎬", 10, 200000, false);

// 2️⃣ API orqali video olish
$api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/tiktokapi.php";
$ch = curl_init($api . "?url=" . urlencode($tx_clean));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ❌ API ishlamayapti
if ($res === false || $http_code != 200) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: TikTok\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

// Muhim: global $data (callback_query ma'lumoti) bilan chalkashmasin deb
// alohida nom ishlatilgan.
$api_data = json_decode($res, true);
$video_url = null;

if (!empty($api_data['video_url'])) {
    $video_url = $api_data['video_url'];
} elseif (!empty($api_data['data']['medias'][0]['url'])) {
    $video_url = $api_data['data']['medias'][0]['url'];
}

// 🔍 fallback .mp4 link
if (!$video_url && preg_match('/https?:\/\/[^\'"\s<>]+\.mp4[^\s\'"]*/i', $res, $m)) {
    $video_url = $m[0];
}

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

// 💬 “video yubormoqda...” typing-style effekt
bot('sendChatAction', [
    'chat_id' => $cid,
    'action' => 'upload_video'
]);

// ✅ Video yuborish
bot('sendVideo', [
    'chat_id' => $cid,
    'video' => $video_url,
    'caption' => "<b>🎬 TikTok video\n🔗 Via @$botusername</b>",
    'parse_mode' => 'html',
    'reply_to_message_id' => $mid
]);

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

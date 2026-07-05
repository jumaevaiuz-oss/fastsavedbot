<?php
// Snapchat Spotlight video yuklab olish.
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect
// kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "snapchat";
$admin_id = 7827538214;

// 🔹 Havolani tozalash
preg_match('/https?:\/\/(www\.)?snapchat\.com\/spotlight\/[^\s?]+/i', $tx, $matches);
$tx_clean = $matches[0] ?? $tx;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "👻", 10, 200000, false);

// 2️⃣ universalDownloader orqali video olish
$api = "http://127.0.0.1:3001/api/snapchat/download?url=" . urlencode($tx_clean);
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
if ($res === false || $http_code != 200) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: Snapchat Spotlight\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

$api_data = json_decode($res, true);
$video_url = extract_downloader_video_url($api_data, $res);

// 🔍 fallback: eski "medias" ro'yxati shaklida bo'lsa
if (!$video_url && !empty($api_data['data']['medias']) && is_array($api_data['data']['medias'])) {
    foreach ($api_data['data']['medias'] as $media) {
        if (!empty($media['type']) && $media['type'] === "video" && !empty($media['url'])) {
            $video_url = $media['url'];
            break;
        }
    }
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

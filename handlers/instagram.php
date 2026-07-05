<?php
// Instagram video yuklab olish (universalDownloader orqali).
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect,
// $botusername kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "instagram";
$admin_id = 7827538214;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "📸", 20, 250000, true);

// 2️⃣ universalDownloader orqali video olish
$api = "http://127.0.0.1:3001/api/meta/download?url=" . urlencode($tx);
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

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: Instagram\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

$api_data = json_decode($res, true);
$video_url = extract_downloader_video_url($api_data, $res);

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

// 📤 Video yuborish
bot('sendChatAction', [
    'chat_id' => $cid,
    'action' => 'upload_video'
]);

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

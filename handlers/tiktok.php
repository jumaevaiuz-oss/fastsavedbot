<?php
// TikTok video yuklab olish (Cobalt API orqali).
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect,
// $botusername kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "tiktok";
$admin_id = 7827538214; // 🛠 Admin ID

// 🔹 Havolani tozalash
preg_match('/https?:\/\/(vm|vt|www)\.tiktok\.com\/[^\s]+/i', $tx, $matches);
$tx_clean = !empty($matches[0]) ? $matches[0] : $tx;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "🎬", 10, 200000, false);

// 2️⃣ Cobalt API orqali video olish
$video_url = cobalt_download($tx_clean);
error_log("TikTok URL: " . $tx_clean);
error_log("Cobalt javob video_url: " . var_export($video_url, true));

// ❌ Video topilmasa / API ishlamasa
if (!$video_url) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('not_found', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 Cobalt API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: TikTok\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

// ✅ Progressni o'chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// 💬 "video yubormoqda..." typing-style effekt
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
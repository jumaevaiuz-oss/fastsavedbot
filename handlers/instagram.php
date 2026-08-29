<?php
// Instagram video yuklab olish (Cobalt API orqali).
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect,
// $botusername kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "instagram";
$admin_id = 7827538214;

// 🔍 Cache tekshirish (avval DB dan qidiramiz)
$url_hash = md5($tx);
$cache_res = mysqli_query($connect, "SELECT file_id FROM video_cache WHERE url_hash = '$url_hash'");
$cache_row = mysqli_fetch_assoc($cache_res);

if ($cache_row) {
    // ✅ Cache topildi — Cobalt ga murojaat qilmasdan yuboramiz
    bot('sendChatAction', ['chat_id' => $cid, 'action' => 'upload_video']);
    bot('sendVideo', [
        'chat_id' => $cid,
        'video' => $cache_row['file_id'],
        'reply_to_message_id' => $mid
    ]);

    // 🧾 Yuklab olish tarixini saqlash
    $stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
    $stmt->bind_param("is", $uid, $platform);
    $stmt->execute();
    $stmt->close();
    exit();
}

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "📸", 20, 250000, true);

// 2️⃣ Cobalt API orqali video olish
$video_url = cobalt_download($tx);

// ❌ Video topilmasa / API ishlamasa
if (!$video_url) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('not_found', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 Cobalt API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: Instagram\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

// ✅ Progressni o'chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// 📤 Video yuborish
bot('sendChatAction', [
    'chat_id' => $cid,
    'action' => 'upload_video'
]);

$sent = bot('sendVideo', [
    'chat_id' => $cid,
    'video' => $video_url,
    'reply_to_message_id' => $mid
]);

// 💾 file_id ni cache ga saqlash
$file_id = $sent->result->video->file_id ?? null;
if ($file_id) {
    $stmt = $connect->prepare("INSERT IGNORE INTO video_cache (url_hash, file_id, platform) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $url_hash, $file_id, $platform);
    $stmt->execute();
    $stmt->close();
}

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

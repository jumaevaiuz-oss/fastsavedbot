<?php
// Instagram video yuklab olish.
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect,
// $botusername kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "instagram";
$admin_id = 7827538214;

// 🔁 Instagram → kksave.co
$link = preg_replace(
    '#https?://(www\.)?instagram\.com#i',
    'https://www.kksave.co',
    $tx
);

// 🔹 igsh va boshqa parametrlarni olib tashlash
$link = preg_replace('/\?.*$/', '', $link);

// 📊 Progress (lang orqali)
$wait = send_progress_message($cid, $mid, $uid, "📸", 20, 250000, true);

// ✅ Havola haqiqatan ham video ekanligini yuborishdan oldin tekshiramiz
// (kksave.co ba'zan video o'rniga oddiy sahifa/xato qaytarishi mumkin)
$ch = curl_init($link);
curl_setopt_array($ch, [
    CURLOPT_NOBODY => true,
    CURLOPT_HEADER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$is_video = $http_code == 200 && stripos($content_type, 'video') !== false;

// 🗑 Progressni o‘chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// ❌ Video emas (havola ishlamayapti)
if (!$is_video) {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 Instagram (kksave.co) ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n📡 HTTP: $http_code | Content-Type: $content_type\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

// 📤 Video yuborish
bot('sendChatAction', [
    'chat_id' => $cid,
    'action' => 'upload_video'
]);

bot('sendVideo', [
    'chat_id' => $cid,
    'video' => $link,
    'reply_to_message_id' => $mid
]);

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

<?php
// YouTube (Shorts bo'lmagan oddiy video/musiqa havolasi) — faqat audio
// (MP3, 320kbps) yuklab beradi, video yubormaydi. Cobalt API orqali.
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid, $connect
// kabi o'zgaruvchilar bu yerda ham bevosita mavjud.

$platform = "youtube";
$admin_id = 7827538214;

// 🔹 Havolani tozalash — xabarda boshqa matn/havolalar ham bo'lishi mumkin
// (masalan forward qilingan e'lonlar), shu sabab faqat YouTube havolasining
// o'zini ajratib olamiz, aks holda Cobalt API butun matnni tushunolmaydi.
preg_match('/https?:\/\/(www\.|m\.)?(youtube\.com\/watch\?[^\s]+|youtube\.com\/live\/[^\s]+|youtu\.be\/[^\s]+)/i', $tx, $matches);
$tx_clean = $matches[0] ?? $tx;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "🎵", 10, 200000, false);

// Audio konvertatsiyasi uzoq davom etishi mumkin — PHP skriptning o'zi
// vaqtidan oldin o'ldirilib, hech qanday xabar yubormay "qotib
// qolmasligi" uchun vaqt chegarasini kengaytiramiz.
@set_time_limit(150);

// 2️⃣ Cobalt API orqali audio (MP3) olish
$audio_url = cobalt_youtube($tx_clean, [
    'downloadMode' => 'audio',
    'audioFormat' => 'mp3',
    'audioBitrate' => '320'
]);

// ❌ Audio topilmasa / API ishlamasa
if (!$audio_url) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('not_found', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 Cobalt API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: YouTube (audio)\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

// ✅ Progressni o‘chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// 🎧 Audio yuborish
$send_result = bot('sendAudio', [
    'chat_id' => $cid,
    'audio' => $audio_url,
    'reply_to_message_id' => $mid
]);

// ❌ Telegram audio havolasini qabul qilmadi (masalan noto'g'ri
// Content-Type yoki tunnel havolasi yaroqsiz bo'lsa) — buni oldin
// hech kim tekshirmagani uchun bot hech narsa demay jim qolar edi.
if (empty($send_result->ok)) {
    $tg_error = $send_result->description ?? 'Noma\'lum xato';

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 sendAudio xato berdi!\n🕓 " . date('Y-m-d H:i:s') . "\n💬 Telegram xatosi: $tg_error\n🔗 Audio URL: $audio_url",
        'parse_mode' => 'html'
    ]);
    exit();
}

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

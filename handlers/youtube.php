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

// Yuklab olish + konvertatsiya + Telegram'ga yuborish uzoq davom etishi
// mumkin — PHP skriptning o'zi vaqtidan oldin o'ldirilib, hech qanday
// xabar yubormay "qotib qolmasligi" uchun vaqt chegarasini kengaytiramiz.
@set_time_limit(280);

// 2️⃣ Cobalt API orqali audio (MP3) havolasini olish
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

// 3️⃣ Telegram ba'zan Cobalt tunnel havolasini o'zi to'g'ridan-to'g'ri
// ololmaydi ("Bad Request: failed to get HTTP URL content" — tunnel
// vaqtinchalik/bir martalik bo'lgani yoki Telegram so'rovi bloklangani
// sabab bo'lishi mumkin). Shu sabab faylni avval o'zimiz yuklab olib,
// Telegram'ga havola sifatida emas, balki fayl sifatida (multipart) yuboramiz.
// sys_get_temp_dir() (odatda /tmp) ko'p shared hostinglarda open_basedir
// tomonidan saytning o'z papkasidan tashqarida qoldirilgan bo'ladi — shu
// sabab tempnam() shu yerda "false" qaytarib, keyingi fopen() ValueError
// bilan yiqilib tushardi. O'rniga saytning o'zidagi (allaqachon yozish
// huquqi tasdiqlangan) step/ papkasidan foydalanamiz.
$tmp_file = tempnam(dirname(__DIR__) . '/step', 'ytaudio_');

// 🔁 Tunnel havolasi ba'zan bir martalik "hiqichoq" beradi (HTTP 200 lekin
// 0 bayt) — shu sabab bitta muvaffaqiyatsiz urinishdan keyin darhol taslim
// bo'lmasdan, qisqa pauzadan so'ng yana bir marta urinib ko'ramiz.
for ($attempt = 1; $attempt <= 2; $attempt++) {
    $fh = fopen($tmp_file, 'w');
    $ch = curl_init($audio_url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        // Ba'zi CDN'lar (masalan Google Video) User-Agent yo'q so'rovlarga
        // HTTP 200 lekin bo'sh (0 bayt) javob qaytaradi — shu sabab oddiy
        // brauzer User-Agent yuboramiz.
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ]);
    curl_exec($ch);
    $dl_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $dl_err = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    $dl_size = file_exists($tmp_file) ? filesize($tmp_file) : 0;

    if ($dl_http_code == 200 && $dl_size >= 1000) {
        break;
    }
    if ($attempt < 2) {
        sleep(2);
    }
}

// ❌ Faylni tunnel havolasidan yuklab bo'lmadi
if ($dl_http_code != 200 || $dl_size < 1000) {
    @unlink($tmp_file);

    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 Cobalt tunnel havolasidan fayl yuklab bo'lmadi!\n🕓 " . date('Y-m-d H:i:s') . "\n📡 HTTP: $dl_http_code | Hajm: $dl_size bayt\n💬 cURL: " . ($dl_err ?: '-') . "\n🔗 URL: $audio_url",
        'parse_mode' => 'html'
    ]);
    exit();
}

// ✅ Progressni o‘chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// 🎧 Audio yuborish — fayl sifatida (multipart upload), havola sifatida emas
$post_fields = [
    'chat_id' => $cid,
    'audio' => new CURLFile($tmp_file, 'audio/mpeg', 'audio.mp3'),
    'reply_to_message_id' => $mid,
];
$ch = curl_init("https://api.telegram.org/bot" . API_KEY . "/sendAudio");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_fields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);
$send_res = curl_exec($ch);
curl_close($ch);
@unlink($tmp_file);

$send_result = json_decode($send_res);

// ❌ Telegram baribir rad etsa (masalan fayl buzuq), buni ko'rsatib beramiz
if (empty($send_result->ok)) {
    $tg_error = $send_result->description ?? 'Noma\'lum xato';

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 sendAudio (fayl) xato berdi!\n🕓 " . date('Y-m-d H:i:s') . "\n💬 Telegram xatosi: $tg_error",
        'parse_mode' => 'html'
    ]);
    exit();
}

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

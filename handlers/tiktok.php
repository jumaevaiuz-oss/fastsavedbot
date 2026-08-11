<?php

// =============================================
// TikTok video yuklab olish
// Cobalt API orqali
// =============================================

$platform = "tiktok";

$admin_id = 12345678;


// =============================================
// 1. TikTok URLni ajratib olish
// =============================================

$tx = trim($tx);

preg_match(
    '~https?://(?:www\.|vm\.|vt\.)?tiktok\.com/[^\s<>"\']+~i',
    $tx,
    $matches
);

$tx_clean = !empty($matches[0])
    ? $matches[0]
    : $tx;


// Oxiridagi Telegram/chat belgilarini tozalash
$tx_clean = trim(
    $tx_clean,
    " \t\n\r\0\x0B<>\"'"
);


// =============================================
// 2. Query parametrlarini saqlab qolamiz
// =============================================

// TikTok qisqa URL bo'lsa kengaytiramiz
$is_short_tiktok =
    stripos($tx_clean, 'vt.tiktok.com') !== false ||
    stripos($tx_clean, 'vm.tiktok.com') !== false;


if ($is_short_tiktok) {

    $ch = curl_init($tx_clean);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_FOLLOWLOCATION => true,

        CURLOPT_MAXREDIRS => 10,

        CURLOPT_TIMEOUT => 15,

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_USERAGENT =>
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
            'AppleWebKit/537.36 (KHTML, like Gecko) ' .
            'Chrome/131.0 Safari/537.36',

        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9'
        ]

    ]);

    curl_exec($ch);

    $full_url = curl_getinfo(
        $ch,
        CURLINFO_EFFECTIVE_URL
    );

    $http_code = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curl_error = curl_error($ch);

    curl_close($ch);


    // Faqat haqiqiy TikTok URLga redirect bo'lgan
    // bo'lsa almashtiramiz.
    if (
        $full_url &&
        preg_match(
            '~^https?://(?:www\.)?tiktok\.com/~i',
            $full_url
        )
    ) {

        $tx_clean = $full_url;

    } else {

        // Redirect ishlamasa original qisqa link
        // Cobalt'ga yuboriladi.
        error_log(
            "TikTok short URL redirect failed. " .
            "HTTP: {$http_code}, " .
            "Error: {$curl_error}, " .
            "URL: {$tx_clean}"
        );
    }
}


// =============================================
// 3. Keraksiz query parametrlarini olib tashlash
// =============================================

$parsed = parse_url($tx_clean);

if (
    isset($parsed['scheme']) &&
    isset($parsed['host'])
) {

    $clean_base =
        $parsed['scheme'] .
        '://' .
        $parsed['host'] .
        ($parsed['path'] ?? '');

    // Faqat path bilan ishlaymiz
    $tx_clean = $clean_base;
}


// =============================================
// 4. Link bo'shligini tekshirish
// =============================================

if (
    empty($tx_clean) ||
    !preg_match(
        '~^https?://~i',
        $tx_clean
    )
) {

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => lang('not_found', $uid),
        'reply_to_message_id' => $mid
    ]);

    exit();
}


// =============================================
// 5. Progress
// =============================================

// Eslatma:
// Eski 20 soniyalik progressni olib tashladik.
// Chunki API chaqirilishidan oldin 20 soniya kutish
// foydalanuvchiga ortiqcha kechikish beradi.

$wait = bot('sendMessage', [

    'chat_id' => $cid,

    'text' =>
        "🎬 ░░░░░░░░░░ 10%\n\n" .
        lang('downloading', $uid),

    'reply_to_message_id' => $mid

])->result->message_id ?? null;


// =============================================
// 6. Cobalt API
// =============================================

$video_url = cobalt_download($tx_clean);


// =============================================
// 7. Video topilmadi
// =============================================

if (!$video_url) {

    if ($wait) {

        bot('editMessageText', [

            'chat_id' => $cid,

            'message_id' => $wait,

            'text' => lang('not_found', $uid)

        ]);
    }


    // Cobalt'dan aniq xatolik
    $debug_info =
        function_exists('get_last_cobalt_error')
            ? get_last_cobalt_error()
            : "Cobalt xatosi aniqlanmadi.";


    // Admin uchun
    $safe_url = htmlspecialchars(
        $tx_clean,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safe_debug = htmlspecialchars(
        substr($debug_info, 0, 3500),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );


    bot('sendMessage', [

        'chat_id' => $admin_id,

        'text' =>
            "🚨 <b>Cobalt API xatosi (TikTok)</b>\n\n" .

            "🕓 <b>Vaqt:</b> " .
            date('Y-m-d H:i:s') .
            "\n\n" .

            "🔗 <b>URL:</b>\n" .
            "<code>{$safe_url}</code>\n\n" .

            "🛠 <b>Cobalt javobi:</b>\n" .
            "<code>{$safe_debug}</code>",

        'parse_mode' => 'HTML'

    ]);

    exit();
}


// =============================================
// 8. Progressni o'chirish
// =============================================

if ($wait) {

    bot('deleteMessage', [

        'chat_id' => $cid,

        'message_id' => $wait

    ]);
}


// =============================================
// 9. Telegram typing
// =============================================

bot('sendChatAction', [

    'chat_id' => $cid,

    'action' => 'upload_video'

]);


// =============================================
// 10. Videoni Telegramga yuborish
// =============================================

$send_result = bot('sendVideo', [

    'chat_id' => $cid,

    'video' => $video_url,

    'caption' =>
        "<b>🎬 TikTok video\n" .
        "🔗 Via @{$botusername}</b>",

    'parse_mode' => 'HTML',

    'reply_to_message_id' => $mid

]);


// =============================================
// 11. Telegramga yuborishda xato
// =============================================

if (
    !$send_result ||
    empty($send_result->ok)
) {

    $telegram_error =
        $send_result->description ??
        'Nomaʼlum Telegram xatosi.';

    bot('sendMessage', [

        'chat_id' => $admin_id,

        'text' =>
            "🚨 <b>TikTok Telegramga yuborilmadi</b>\n\n" .

            "🔗 <code>" .
            htmlspecialchars(
                $tx_clean,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) .
            "</code>\n\n" .

            "📦 <b>Cobalt URL:</b>\n" .
            "<code>" .
            htmlspecialchars(
                $video_url,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) .
            "</code>\n\n" .

            "❌ <b>Telegram:</b>\n" .
            "<code>" .
            htmlspecialchars(
                $telegram_error,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) .
            "</code>",

        'parse_mode' => 'HTML'

    ]);

    exit();
}


// =============================================
// 12. Statistikaga yozish
// =============================================

$stmt = $connect->prepare(
    "INSERT INTO video_downloads (user_id, platform)
     VALUES (?, ?)"
);

if ($stmt) {

    $stmt->bind_param(
        "is",
        $uid,
        $platform
    );

    $stmt->execute();

    $stmt->close();
}

<?php
// YouTube link kelganda: foydalanuvchiga video sifati / audio formatini
// tanlash uchun tugmalar ko'rsatadi. Haqiqiy yuklab olish bot.php'dagi
// yt_v_/yt_a_ callback handlerlarida, cobalt_youtube() orqali bajariladi.
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid kabi
// o'zgaruvchilar bu yerda ham bevosita mavjud.

// Havolani keyingi qadamda (callback bosilganda) ishlatish uchun
// vaqtincha saqlab qo'yamiz — callback_data uzunligi Telegram'da 64 bayt
// bilan cheklangani uchun URL'ni to'g'ridan-to'g'ri callback_data'ga
// qo'ymaymiz.
file_put_contents("step/yt_$cid.txt", $tx);

$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => '🎬 1080p', 'callback_data' => 'yt_v_1080'],
            ['text' => '📺 720p', 'callback_data' => 'yt_v_720']
        ],
        [
            ['text' => '📱 480p', 'callback_data' => 'yt_v_480'],
            ['text' => '🔹 360p', 'callback_data' => 'yt_v_360']
        ],
        [
            ['text' => '🎵 MP3 320kbps', 'callback_data' => 'yt_a_320'],
            ['text' => '🎵 MP3 128kbps', 'callback_data' => 'yt_a_128']
        ]
    ]
];

bot('sendMessage', [
    'chat_id' => $cid,
    'text' => "🎬 YouTube\nNimani yuklamoqchisiz?",
    'reply_markup' => json_encode($keyboard),
    'reply_to_message_id' => $mid
]);

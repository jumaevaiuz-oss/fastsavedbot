<?php
// handlers/inline_share.php

if (!$id) return;

$photo_url  = 'https://6831eecaafce3.xvest3.ru/fastsavedbot/assets/music/fastsaved.png';

$share_text =
"📥 <b>Videoni yuklab olish kerakmi?</b>\n\n" .
"Instagram, TikTok, Snapchat, YouTube Shorts va boshqa ijtimoiy tarmoqlardan videolarni tez va oson yuklab oling! 🚀\n\n" .
"🔗 Havolani @{$botusername} ga yuboring — qolganini botning o'zi bajaradi.\n\n" .
"👉 Hoziroq sinab ko'ring!";

$results = [[
    'type'          => 'photo',
    'id'            => 'share_fastsaved_1',
    'photo_url'     => $photo_url,
    'thumbnail_url' => $photo_url,
    'photo_width'   => 1024,
    'photo_height'  => 1024,
    'caption'       => $share_text,
    'parse_mode'    => 'HTML',
    'reply_markup'  => [
        'inline_keyboard' => [[
            ['text' => '🚀 Botni ochish', 'url' => "https://t.me/{$botusername}"]
        ]]
    ],
]];

bot('answerInlineQuery', [
    'inline_query_id' => $id,
    'results'         => json_encode($results),
    'cache_time'      => 300,
    'is_personal'     => false,
]);
exit();

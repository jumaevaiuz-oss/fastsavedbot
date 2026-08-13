<?php
// handlers/inline_share.php

if (!$id) return;

$share_text =
"📥 <b>Videoni yuklab olish kerakmi?</b>\n\n" .
"Instagram, TikTok, Snapchat, YouTube Shorts va boshqa ijtimoiy tarmoqlardan videolarni tez va oson yuklab oling! 🚀\n\n" .
"🔗 Havolani @{$botusername} ga yuboring — qolganini botning o'zi bajaradi.\n\n" .
"👉 Hoziroq sinab ko'ring!";

$results = [
    [
        'type'                  => 'article',
        'id'                    => 'share_fastsaved',
        'title'                 => '📤 FastSaved botni ulashish',
        'description'           => 'Instagram, TikTok, Snapchat, YouTube Shorts — bepul yuklab oling!',
        'thumbnail_url'         => 'http://6831eecaafce3.xvest3.ru/fastsavedbot/assets/music/fastsaved.png',
        'thumbnail_width'       => 512,
        'thumbnail_height'      => 512,
        'input_message_content' => [
            'message_text' => $share_text,
            'parse_mode'   => 'HTML',
        ],
        'reply_markup' => [
            'inline_keyboard' => [[
                ['text' => '🚀 Botni ochish', 'url' => "https://t.me/{$botusername}"]
            ]]
        ],
    ]
];

bot('answerInlineQuery', [
    'inline_query_id' => $id,
    'results'         => json_encode($results),
    'cache_time'      => 300,
    'is_personal'     => false,
]);
exit();

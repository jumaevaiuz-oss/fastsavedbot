<?php
// ============================================================
// handlers/inline_share.php
// Inline share tugmasi bosilganda ishlaydi.
// bot.php ga qo'shish: yuklovchi funksiyalar blokidan OLDIN
//   if ($id) { require __DIR__ . '/handlers/inline_share.php'; }
// ============================================================

if (!$id) return; // Inline query kelmaganda o'tkazib yuborish

// Bot rasmi — bu URL ni o'zingizning rasmingiz bilan almashtirishingiz mumkin
// Yoki Telegram file_id ishlatish (bir marta yuklab, file_id ni saqlash kerak)
$photo_url = 'https://github.com/jumaevaiuz-oss/fastsavedbot/blob/main/assets/music/fastsaved.png';

$share_text =
"📥 <b>Videoni yuklab olish kerakmi?</b>\n\n" .
"Instagram, TikTok, Snapchat, YouTube Shorts va boshqa ijtimoiy tarmoqlardan videolarni tez va oson yuklab oling! 🚀\n\n" .
"🔗 Havolani @{$botusername} ga yuboring — qolganini botning o'zi bajaradi.\n\n" .
"👉 Hoziroq sinab ko'ring!";

$share_kb = json_encode([
    'inline_keyboard' => [[
        ['text' => '🚀 Botni ochish', 'url' => "https://t.me/{$botusername}"]
    ]]
]);

// Inline query natijasi — rasm + matn
$results = [[
    'type'                  => 'photo',
    'id'                    => 'fastsaved_share_1',
    'photo_url'             => $photo_url,
    'thumbnail_url'         => $photo_url,
    'photo_width'           => 1024,
    'photo_height'          => 1024,
    'caption'               => $share_text,
    'parse_mode'            => 'HTML',
    'reply_markup'          => json_encode([
        'inline_keyboard' => [[
            ['text' => '🚀 Botni ochish', 'url' => "https://t.me/{$botusername}"]
        ]]
    ]),
]];

bot('answerInlineQuery', [
    'inline_query_id' => $id,
    'results'         => json_encode($results),
    'cache_time'      => 300,
    'is_personal'     => false,
]);
exit();

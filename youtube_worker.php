<?php
// YouTube audio worker — bot.php webhook so'roviga tezkor javob qaytarish
// uchun uzoq davom etadigan yuklab olish/konvertatsiya/yuborish ishi shu
// alohida so'rovga ajratilgan (bot.php buni "fire-and-forget" tarzda,
// javobni kutmasdan chaqiradi). bot.php o'zi qisqa timeout bilan ulanishni
// uzsa ham, ignore_user_abort(true) tufayli bu skript oxirigacha davom etadi.
ignore_user_abort(true);
@set_time_limit(280);

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/lang/user_lang.php';
require_once __DIR__ . '/lang/translations.php';

// 🔒 Faqat bot.php'ning o'zi (bot tokenidan hosila qilingan maxfiy tokenni
// bilgan holda) shu faylni chaqira oladi — begona odam to'g'ridan-to'g'ri
// so'rov yuborib, botni suiiste'mol qilib (ixtiyoriy chat'ga yuklab olingan
// fayl yuborishga majburlab) bo'lmasin.
$secret = $_POST['secret'] ?? '';
if (!hash_equals(youtube_worker_secret(), $secret)) {
    http_response_code(403);
    exit;
}

$cid = $_POST['cid'] ?? null;
$mid = $_POST['mid'] ?? null;
$uid = $_POST['uid'] ?? null;
$tx  = $_POST['tx'] ?? null;

if (!$cid || !$mid || !$uid || !$tx) {
    http_response_code(400);
    exit;
}

require __DIR__ . '/handlers/youtube.php';

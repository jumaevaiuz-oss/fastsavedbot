<?php
// TikTok video yuklab olish — TikWM API orqali (watermarksiz)
// bot.php'dan include qilinadi, shuning uchun $tx, $cid, $mid, $uid,
// $connect, $botusername kabi o'zgaruvchilar bevosita mavjud.

$platform  = "tiktok";
$admin_id  = 7827538214; // 🛠 Admin ID

// ── 1. Havolani tozalash ─────────────────────────────────────
// vm.tiktok.com / vt.tiktok.com / www.tiktok.com / tiktok.com
preg_match(
    '/https?:\/\/(vm\.|vt\.|www\.)?tiktok\.com\/[^\s]+/i',
    $tx,
    $matches
);
$tx_clean = !empty($matches[0]) ? trim($matches[0]) : trim($tx);

// ── 2. Progress bar ──────────────────────────────────────────
$wait = send_progress_message($cid, $mid, $uid, "🎬", 10, 200000, false);

// ── 3. TikWM API — video ma'lumotini olish ──────────────────
function tikwm_get_video(string $url): ?array
{
    $ch = curl_init('https://www.tikwm.com/api/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'url'   => $url,
            'count' => 12,
            'cursor'=> 0,
            'web'   => 1,
            'hd'    => 1,   // HD sifat so'rash
        ]),
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;

    $data = json_decode($res, true);
    if (empty($data['data']) || $data['code'] !== 0) return null;

    return $data['data'];
}

$info = tikwm_get_video($tx_clean);

// ── 4. API ishlamasa — Cobalt zaxirasi ──────────────────────
$video_url = null;
$used_api  = 'tikwm';

if ($info && !empty($info['play'])) {
    // Watermarksiz video URL (play — asosiy, hdplay — HD variant)
    $video_url = !empty($info['hdplay']) ? $info['hdplay'] : $info['play'];
} else {
    // TikWM ishlamasa — Cobalt bilan urinib ko'ramiz
    $used_api  = 'cobalt';
    $video_url = cobalt_download($tx_clean);
}

// ── 5. Ikkala API ham ishlamasa ──────────────────────────────
if (!$video_url) {
    bot('editMessageText', [
        'chat_id'    => $cid,
        'message_id' => $wait,
        'text'       => lang('not_found', $uid),
    ]);

    bot('sendMessage', [
        'chat_id'    => $admin_id,
        'text'       => "🚨 TikTok yuklab bo'lmadi!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 URL: $tx_clean\n⚙️ API: TikWM + Cobalt ikkala sinab ko'rildi.",
        'parse_mode' => 'html',
    ]);
    exit();
}

// ── 6. Progress o'chirish ────────────────────────────────────
bot('deleteMessage', [
    'chat_id'    => $cid,
    'message_id' => $wait,
]);

// ── 7. Upload animatsiyasi ───────────────────────────────────
bot('sendChatAction', [
    'chat_id' => $cid,
    'action'  => 'upload_video',
]);

// ── 8. Video yuborish ────────────────────────────────────────
// Caption uchun qo'shimcha ma'lumotlar (TikWM da bo'lsa)
$title    = !empty($info['title'])            ? htmlspecialchars($info['title'])            : '';
$author   = !empty($info['author']['nickname']) ? htmlspecialchars($info['author']['nickname']) : '';
$duration = !empty($info['duration'])         ? (int)$info['duration']                    : 0;

$caption_parts = ["<b>🎬 TikTok</b>"];
if ($author)   $caption_parts[] = "👤 <b>$author</b>";
if ($title)    $caption_parts[] = "📝 $title";
$caption_parts[] = "🔗 Via @$botusername";
$caption = implode("\n", $caption_parts);

$send_params = [
    'chat_id'             => $cid,
    'video'               => $video_url,
    'caption'             => $caption,
    'parse_mode'          => 'html',
    'reply_to_message_id' => $mid,
    'supports_streaming'  => true,
];

// Video o'lchamini qo'shish (TikWM bersa)
if (!empty($info['width']))  $send_params['width']  = (int)$info['width'];
if (!empty($info['height'])) $send_params['height'] = (int)$info['height'];
if ($duration)               $send_params['duration'] = $duration;

// Thumbnail (muqova rasm)
if (!empty($info['cover']))  $send_params['thumbnail'] = $info['cover'];

$result = bot('sendVideo', $send_params);

// ── 9. Agar Telegram URL orqali yuklay olmasa — to'g'ridan yuklab yuboramiz
if (!$result || !$result->ok) {
    // TikWM URL'larini Telegram ba'zan rad etadi — fayl sifatida yuklaymiz
    $tmp = dirname(__DIR__) . '/step/' . uniqid('tiktok_') . '.mp4';
    $fp  = fopen($tmp, 'wb');
    $ch  = curl_init($video_url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if (file_exists($tmp) && filesize($tmp) > 10000) {
        $send_params['video'] = new CURLFile($tmp, 'video/mp4', 'tiktok.mp4');
        bot('sendVideo', $send_params);
    } else {
        // So'nggi urinish: oddiy link sifatida
        bot('sendMessage', [
            'chat_id'             => $cid,
            'text'                => "$caption\n\n<a href=\"$video_url\">📥 Yuklab olish</a>",
            'parse_mode'          => 'html',
            'reply_to_message_id' => $mid,
        ]);
    }
    @unlink($tmp);
}

// ── 10. Statistikaga yozish ──────────────────────────────────
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

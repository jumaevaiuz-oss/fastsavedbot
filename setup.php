<?php
// =============================================
// FastSaved Bot - Webhook o'rnatish
// Bir marta ishga tushiring!
// =============================================

require_once __DIR__ . '/config.php';

$token = API_KEY; // config.php dagi bot tokeni

$webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . str_replace('setup.php', 'bot.php', $_SERVER['SCRIPT_NAME']);

$webhook_fields = ['url' => $webhookUrl, 'drop_pending_updates' => 'true'];
// 🔒 Agar config.php'da WEBHOOK_SECRET aniqlangan bo'lsa, uni ham Telegram'ga yuboramiz
if (defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '') {
    $webhook_fields['secret_token'] = WEBHOOK_SECRET;
}

$url = "https://api.telegram.org/bot{$token}/setWebhook";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $webhook_fields);
$res = curl_exec($ch);
curl_close($ch);
$result = json_decode($res, true);

echo "Webhook URL: <b>$webhookUrl</b><br>\n";
echo "Natija: " . ($result['ok'] ? '✅ O\'rnatildi' : '❌ Xato: ' . $result['description']) . "<br>\n";
echo "Maxfiy token (secret_token): " . (defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '' ? '✅ Yuborildi' : '⚪ Sozlanmagan (config.php\'da WEBHOOK_SECRET yo\'q)') . "<br>\n";

// SQL jadvallarni tekshirish
$tables = ['users', 'admins', 'kanallar', 'settings', 'send', 'groups', 'requests', 'left_users', 'music', 'top_songs', 'video_downloads'];
echo "<br><b>Jadvallar:</b><br>\n";
foreach ($tables as $table) {
    $result = $connect->query("SHOW TABLES LIKE '$table'");
    echo ($result->num_rows > 0 ? "✅" : "❌") . " $table<br>\n";
}

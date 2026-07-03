<?php
// =============================================
// FastSaved Bot - Webhook o'rnatish
// Bir marta ishga tushiring!
// =============================================

require_once __DIR__ . '/sql.php';

$token = 'bot_tokenini_yoz'; // bot_8.2.php dagi tokenni bu yerga ham kiriting

$webhookUrl = 'https://6831eecaafce3.xvest3.ru/fastsavedbot/bot.php' . $_SERVER['HTTP_HOST'] . str_replace('setup.php', 'bot.php', $_SERVER['SCRIPT_NAME']);

$url = "https://api.telegram.org/bot{$token}/setWebhook";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $webhookUrl, 'drop_pending_updates' => 'true']);
$res = curl_exec($ch);
curl_close($ch);
$result = json_decode($res, true);

echo "Webhook URL: <b>$webhookUrl</b><br>\n";
echo "Natija: " . ($result['ok'] ? '✅ O\'rnatildi' : '❌ Xato: ' . $result['description']) . "<br>\n";

// SQL jadvallarni tekshirish
$tables = ['users', 'admins', 'kanallar', 'settings', 'send', 'groups', 'requests', 'left_users', 'music', 'top_songs', 'video_downloads'];
echo "<br><b>Jadvallar:</b><br>\n";
foreach ($tables as $table) {
    $result = $connect->query("SHOW TABLES LIKE '$table'");
    echo ($result->num_rows > 0 ? "✅" : "❌") . " $table<br>\n";
}

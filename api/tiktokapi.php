<?php
header("Content-Type: application/json; charset=UTF-8");

$input_url = $_GET['url'] ?? '';

if (!$input_url) {
    echo json_encode([
        "error" => true,
        "message" => "URL yuborilmadi."
    ]);
    exit;
}

// 👉 vt.tiktok.com yo'naltiruvchi bo‘lsa, to‘g‘ridan-to‘g‘ri linkga aylantiramiz
function resolve_redirect($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Muhim
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_exec($ch);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return $final_url;
}

if (strpos($input_url, 'vt.tiktok.com') !== false) {
    $input_url = resolve_redirect($input_url);
}

// Endi API orqali video yuklashga urinamiz
$api_url = "https://tikwm.com/api/"; // Alternativ TikTok API
$params = [
    "url" => $input_url,
    "hd" => 1
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . "?" . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['data']['play']) && $data['data']['play']) {
    echo json_encode([
        "error" => false,
        "video_url" => $data['data']['play'],
        "thumbnail" => $data['data']['cover'],
        "desc" => $data['data']['title'],
        "author" => $data['data']['author']['unique_id']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        "error" => true,
        "message" => "❌ Xatolik: Video topilmadi yoki API ishlamayapti."
    ]);
}
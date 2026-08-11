<?php

function search_and_download_music($query)
{
    // Railway'dagi shaxsiy API manzilingiz
    $api_url = 'https://ytdlp-search-production.up.railway.app/';

    // Qidiruv so'zi yoki havola ekanligini aniqlaymiz
    $post_data = [];
    if (filter_var($query, FILTER_VALIDATE_URL)) {
        $post_data['url'] = $query;
    } else {
        $post_data['search'] = $query;
    }

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($post_data),
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || empty($res)) {
        error_log("ytdlp-search API: javob olinmadi.");
        return null;
    }

    $result = json_decode($res, true);

    // Agar API'dan muvaffaqiyatli javob kelsa
    if ($http_code === 200 && !empty($result['url'])) {
        return [
            'title' => $result['title'] ?? 'Musiqa',
            'url' => $result['url']
        ];
    }

    error_log("ytdlp-search API xatosi. HTTP: $http_code Javob: $res");
    return null;
}

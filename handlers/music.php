function search_and_download_music($query)
{
    $api_url = 'https://ytdlp-search-production.up.railway.app/';

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

    // Shu yerga log qo'shamiz, xato nima ekanini server logidan ko'rasiz
    error_log("Railway HTTP Code: $http_code, Response: $res");

    if ($res === false || empty($res)) {
        return null;
    }

    $result = json_decode($res, true);

    if ($http_code === 200 && !empty($result['url'])) {
        return [
            'title' => $result['title'] ?? 'Musiqa',
            'url' => $result['url']
        ];
    }

    return null;
}

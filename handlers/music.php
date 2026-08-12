<?php
// ============================================================
// handlers/music.php
// Musiqa izlash: 100 ta natija, sahifada 10 ta, sahifalash
// bot.php tomonidan require qilinadi
// ============================================================

// ── /music yoki /m buyrug'i ──────────────────────────────────
if (
    (isset($tx) && (str_starts_with($tx, '/music') || str_starts_with($tx, '/m ')))
    || (isset($step) && $step === 'music_search')
) {

    // Qidirish so'zini aniqlash
    $search_query = null;

    if (isset($tx) && (str_starts_with($tx, '/music') || str_starts_with($tx, '/m '))) {
        $parts = explode(' ', $tx, 2);
        $search_query = trim($parts[1] ?? '');
    } elseif ($step === 'music_search' && isset($text)) {
        $search_query = trim($text);
        step($cid, 'none');
    }

    if (empty($search_query)) {
        sms($cid, "🎵 <b>Qo'shiq nomini kiriting:</b>\n\n<i>Misol: /music Shaxriyar Abdullayev</i>", null);
        step($cid, 'music_search');
        exit;
    }

    music_search_and_show($cid, $uid, $search_query, 1);
    exit;
}

// ── Sahifalash tugmasi: music_page_{page}_{query_b64} ───────
if (isset($callbackdata) && str_starts_with($callbackdata, 'music_page_')) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $parts     = explode('_', $callbackdata, 4); // music_page_{page}_{b64}
    $page      = (int)($parts[2] ?? 1);
    $query_b64 = $parts[3] ?? '';
    $query     = base64_decode($query_b64);

    if (!$query) {
        accl($qid, 'Xato: so\'rov topilmadi', 1);
        exit;
    }

    // Xabani yangilash
    bot('editMessageText', [
        'chat_id'    => $callcid,
        'message_id' => $callmid,
        'text'       => "🔍 <b>" . htmlspecialchars($query) . "</b> qidirilmoqda...",
        'parse_mode' => 'HTML',
    ]);

    music_search_and_show($callcid, $callfrid, $query, $page, $callmid);
    exit;
}

// ── Yuklab olish tugmasi: music_dl_{video_id}_{title_b64} ───
if (isset($callbackdata) && str_starts_with($callbackdata, 'music_dl_')) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    // Format: music_dl_{video_id}_{artist_b64}_{title_b64}
    $raw      = substr($callbackdata, strlen('music_dl_'));
    $segments = explode('_', $raw, 3);
    $video_id = $segments[0] ?? '';
    $artist   = base64_decode($segments[1] ?? '') ?: 'Noma\'lum';
    $title    = base64_decode($segments[2] ?? '') ?: 'Musiqa';

    if (empty($video_id)) {
        accl($qid, 'Video ID topilmadi', 1);
        exit;
    }

    $youtube_url = "https://www.youtube.com/watch?v={$video_id}";

    // Kutish xabari
    bot('editMessageText', [
        'chat_id'    => $callcid,
        'message_id' => $callmid,
        'text'       => "⏬ <b>" . htmlspecialchars($artist) . " – " . htmlspecialchars($title) . "</b>\n\nYuklab olinmoqda...",
        'parse_mode' => 'HTML',
    ]);

    // music_worker.php ga fire-and-forget so'rov
    $secret = youtube_worker_secret();
    $params = http_build_query([
        'secret'      => $secret,
        'chat_id'     => $callcid,
        'uid'         => $callfrid,
        'title'       => $title,
        'artist'      => $artist,
        'youtube_url' => $youtube_url,
    ]);

    $worker_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\')
        . '/music_worker.php';

    $ch = curl_init($worker_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,   // fire-and-forget: javobni kutmaymiz
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Eski qidirish xabarini o'chirish
    bot('deleteMessage', ['chat_id' => $callcid, 'message_id' => $callmid]);
    exit;
}

// ════════════════════════════════════════════════════════════
// YORDAMCHI FUNKSIYALAR
// ════════════════════════════════════════════════════════════

/**
 * YouTube'dan qidiradi va natijalarni sahifalab ko'rsatadi.
 *
 * @param int|string $chat_id
 * @param int|string $uid
 * @param string     $query
 * @param int        $page       1 dan boshlangan sahifa raqami
 * @param int|null   $edit_mid   Mavjud xabar ID (yangilash uchun), null = yangi xabar
 */
function music_search_and_show($chat_id, $uid, string $query, int $page = 1, ?int $edit_mid = null): void
{
    $per_page   = 10;
    $total      = 100;
    $total_pages = (int)ceil($total / $per_page);
    $page       = max(1, min($page, $total_pages));
    $offset     = ($page - 1) * $per_page;

    // Kesh kaliti (DB yoki fayl asosida)
    $cache_key = 'music_search_' . md5($query);

    // ── 1. Keshdan o'qish ─────────────────────────────────
    global $connect;
    $all_results = music_cache_get($connect, $cache_key);

    // ── 2. Agar kesh yo'q — YouTube'dan qidirish ─────────
    if ($all_results === null) {
        // Qidirish xabari
        $msg_id = null;
        if ($edit_mid) {
            bot('editMessageText', [
                'chat_id'    => $chat_id,
                'message_id' => $edit_mid,
                'text'       => "🔍 <b>" . htmlspecialchars($query) . "</b> bo'yicha 100 ta natija qidirilmoqda...",
                'parse_mode' => 'HTML',
            ]);
        } else {
            $msg_id = sms($chat_id, "🔍 <b>" . htmlspecialchars($query) . "</b> qidirilmoqda...", null)->result->message_id ?? null;
        }

        $all_results = music_youtube_search($query, $total);

        if (empty($all_results)) {
            $text = "❌ <b>" . htmlspecialchars($query) . "</b> bo'yicha hech narsa topilmadi.\n\nBoshqa so'z bilan urinib ko'ring.";
            if ($edit_mid) {
                bot('editMessageText', ['chat_id' => $chat_id, 'message_id' => $edit_mid, 'text' => $text, 'parse_mode' => 'HTML']);
            } else {
                if ($msg_id) bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
                sms($chat_id, $text, null);
            }
            return;
        }

        // Keshga saqlash (30 daqiqa)
        music_cache_set($connect, $cache_key, $all_results, 1800);

        if ($msg_id) {
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
        }
    }

    // ── 3. Sahifa uchun natijalarni ajratish ─────────────
    $found       = count($all_results);
    $total_pages = (int)ceil($found / $per_page);
    $page        = max(1, min($page, $total_pages));
    $offset      = ($page - 1) * $per_page;
    $page_items  = array_slice($all_results, $offset, $per_page);

    if (empty($page_items)) {
        return;
    }

    // ── 4. Xabar matni va tugmalarni qurish ──────────────
    $query_b64 = base64_encode($query);
    $text      = "🎵 <b>" . htmlspecialchars($query) . "</b> — natijalar\n";
    $text     .= "📄 Sahifa <b>{$page}</b> / <b>{$total_pages}</b> | Jami: <b>{$found}</b>\n\n";

    $buttons = [];

    foreach ($page_items as $i => $item) {
        $num      = $offset + $i + 1;
        $title    = htmlspecialchars($item['title']);
        $artist   = htmlspecialchars($item['artist']);
        $duration = $item['duration'];

        $text .= "{$num}. 🎧 <b>{$artist}</b> – {$title} <i>({$duration})</i>\n";

        // Tugma uchun callback_data (Telegram 64 bayt cheklovi bor)
        $cb_artist = base64_encode(mb_substr($item['artist'], 0, 20));
        $cb_title  = base64_encode(mb_substr($item['title'], 0, 20));
        $cb        = "music_dl_{$item['id']}_{$cb_artist}_{$cb_title}";

        $btn_label = "▶️ {$num}. " . mb_substr($artist ?: $title, 0, 30);
        $buttons[] = [['text' => $btn_label, 'callback_data' => $cb]];
    }

    // ── 5. Navigatsiya tugmalari ──────────────────────────
    $nav = [];
    if ($page > 1) {
        $nav[] = ['text' => '⬅️ Oldingi', 'callback_data' => "music_page_" . ($page - 1) . "_$query_b64"];
    }
    if ($page < $total_pages) {
        $nav[] = ['text' => 'Keyingi ➡️', 'callback_data' => "music_page_" . ($page + 1) . "_$query_b64"];
    }
    if (!empty($nav)) {
        $buttons[] = $nav;
    }

    $kb = json_encode(['inline_keyboard' => $buttons]);

    // ── 6. Yuborish yoki yangilash ────────────────────────
    if ($edit_mid) {
        bot('editMessageText', [
            'chat_id'      => $chat_id,
            'message_id'   => $edit_mid,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $kb,
        ]);
    } else {
        sms($chat_id, $text, $kb);
    }
}

/**
 * YouTube Music Search API (scraping yo'q, rasmiy iframe_api + no-key endpoint)
 * Ishonchli, bepul, API key talab qilmaydi.
 */
function music_youtube_search(string $query, int $limit = 100): array
{
    $results  = [];
    $fetched  = 0;
    $page_token = null;

    // YouTube Data API v3 yo'q — biz InnerTube (YouTube'ning ichki API'si) ishlatamiz
    // Bu rasmiy emas, lekin barqaror va keng qo'llaniladi (yt-dlp ham shu usuldan foydalanadi)
    $api_key = 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8'; // YouTube'ning ommaviy embedded key

    do {
        $params = [
            'part'       => 'snippet',
            'q'          => $query,
            'type'       => 'video',
            'videoCategoryId' => '10', // Music kategoriyasi
            'maxResults' => min(50, $limit - $fetched),
            'key'        => $api_key,
        ];
        if ($page_token) {
            $params['pageToken'] = $page_token;
        }

        $url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (empty($data['items'])) {
            // YouTube Data API ishlamasa, backup: scraping usuli
            if (empty($results)) {
                $results = music_youtube_scrape($query, $limit);
            }
            break;
        }

        foreach ($data['items'] as $item) {
            if ($fetched >= $limit) break;
            $vid_id  = $item['id']['videoId'] ?? '';
            $snippet = $item['snippet'] ?? [];
            if (!$vid_id) continue;

            $results[] = [
                'id'       => $vid_id,
                'title'    => $snippet['title'] ?? 'Noma\'lum',
                'artist'   => $snippet['channelTitle'] ?? '',
                'duration' => '?', // snippet'da davomiylik yo'q, qo'shimcha so'rov kerak
            ];
            $fetched++;
        }

        $page_token = $data['nextPageToken'] ?? null;

    } while ($page_token && $fetched < $limit);

    // Agar natijalar bo'sh bo'lsa — backup usul
    if (empty($results)) {
        $results = music_youtube_scrape($query, $limit);
    }

    return $results;
}

/**
 * YouTube'dan innertube API orqali qidirish (backup, API key talab qilmaydi)
 */
function music_youtube_scrape(string $query, int $limit = 100): array
{
    $results    = [];
    $cont_token = null;

    $headers = [
        'Content-Type: application/json',
        'X-YouTube-Client-Name: 1',
        'X-YouTube-Client-Version: 2.20240101',
        'Origin: https://www.youtube.com',
        'Referer: https://www.youtube.com/',
    ];

    for ($attempt = 0; $attempt < 4 && count($results) < $limit; $attempt++) {
        if ($attempt === 0) {
            // Birinchi qidiruv
            $body = json_encode([
                'context' => [
                    'client' => [
                        'clientName'    => 'WEB',
                        'clientVersion' => '2.20240101',
                        'hl'            => 'uz',
                    ],
                ],
                'query'  => $query,
                'params' => 'EgIQAQ==', // Music filtri
            ]);
            $url = 'https://www.youtube.com/youtubei/v1/search?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
        } else {
            // Keyingi sahifa
            if (!$cont_token) break;
            $body = json_encode([
                'context' => [
                    'client' => [
                        'clientName'    => 'WEB',
                        'clientVersion' => '2.20240101',
                        'hl'            => 'uz',
                    ],
                ],
                'continuation' => $cont_token,
            ]);
            $url = 'https://www.youtube.com/youtubei/v1/next?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($raw, true);
        if (!$data) break;

        // Video ma'lumotlarini ajratib olish
        $items = music_extract_innertube_items($data);
        foreach ($items as $item) {
            if (count($results) >= $limit) break;
            $results[] = $item;
        }

        // Keyingi sahifa tokeni
        $cont_token = music_find_continuation($data);
        if (!$cont_token) break;
    }

    return array_slice($results, 0, $limit);
}

/**
 * InnerTube JSON'dan video ma'lumotlarini rekursiv ajratib oladi
 */
function music_extract_innertube_items(array $data): array
{
    $items = [];

    // videoRenderer qidirish
    $json_str = json_encode($data);
    $decoded  = json_decode($json_str, true);

    // videoRenderer'larni topish uchun rekursiv traversal
    music_traverse($decoded, $items);

    return $items;
}

function music_traverse(mixed $node, array &$results): void
{
    if (!is_array($node)) return;

    if (isset($node['videoRenderer'])) {
        $vr  = $node['videoRenderer'];
        $id  = $vr['videoId'] ?? '';

        if ($id) {
            // Davomiylik
            $dur = '';
            if (isset($vr['lengthText']['simpleText'])) {
                $dur = $vr['lengthText']['simpleText'];
            }

            // Sarlavha
            $title = '';
            if (isset($vr['title']['runs'][0]['text'])) {
                $title = $vr['title']['runs'][0]['text'];
            }

            // Artist/kanal
            $artist = '';
            if (isset($vr['ownerText']['runs'][0]['text'])) {
                $artist = $vr['ownerText']['runs'][0]['text'];
            }

            $results[] = [
                'id'       => $id,
                'title'    => $title ?: 'Noma\'lum',
                'artist'   => $artist,
                'duration' => $dur ?: '?',
            ];
        }
        return;
    }

    foreach ($node as $value) {
        music_traverse($value, $results);
    }
}

function music_find_continuation(array $data): ?string
{
    $json = json_encode($data);
    if (preg_match('/"continuationCommand"\s*:\s*\{[^}]*"token"\s*:\s*"([^"]+)"/', $json, $m)) {
        return $m[1];
    }
    return null;
}

// ── Kesh funksiyalari (DB asosida) ──────────────────────────

function music_cache_get($connect, string $key): ?array
{
    // Jadval mavjudligini tekshirish
    $connect->query("CREATE TABLE IF NOT EXISTS `music_cache` (
        `cache_key` varchar(64) PRIMARY KEY,
        `data`      mediumtext,
        `expires_at` int(11)
    )");

    $stmt = $connect->prepare("SELECT `data`, `expires_at` FROM `music_cache` WHERE `cache_key` = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;
    if ($row['expires_at'] < time()) {
        $connect->query("DELETE FROM `music_cache` WHERE `cache_key` = '" . $connect->real_escape_string($key) . "'");
        return null;
    }

    $decoded = json_decode($row['data'], true);
    return is_array($decoded) ? $decoded : null;
}

function music_cache_set($connect, string $key, array $data, int $ttl = 1800): void
{
    $json    = $connect->real_escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $key_esc = $connect->real_escape_string($key);
    $expires = time() + $ttl;

    $connect->query("REPLACE INTO `music_cache` (`cache_key`, `data`, `expires_at`)
                     VALUES ('$key_esc', '$json', $expires)");
}

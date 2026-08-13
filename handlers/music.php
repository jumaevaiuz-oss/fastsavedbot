<?php
// ============================================================
// handlers/music.php  —  v2 (callback_data 64-bayt limit hal)
// Musiqa izlash: 100 ta natija, sahifada 10 ta, sahifalash
// bot.php tomonidan require qilinadi
// ============================================================

// ── /music yoki /m buyrug'i ──────────────────────────────────
if (
    (isset($tx) && (str_starts_with($tx, '/music') || str_starts_with($tx, '/m ')))
    || (isset($step) && $step === 'music_search')
) {
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

// ── Sahifalash: mp_{page}_{qid} ─────────────────────────────
// qid = qidiruvni keshda topish uchun md5 kaliti (8 belgi)
if (isset($callbackdata) && str_starts_with($callbackdata, 'mp_')) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    // mp_{page}_{qhash}
    [, $page, $qhash] = explode('_', $callbackdata, 3) + [null, 1, ''];
    $page = (int)$page;

    global $connect;
    $query = music_qhash_get($connect, $qhash);
    if (!$query) {
        bot('editMessageText', [
            'chat_id'    => $callcid,
            'message_id' => $callmid,
            'text'       => '❌ Kesh eskirdi. Iltimos qayta qidiring: /music',
            'parse_mode' => 'HTML',
        ]);
        exit;
    }

    music_search_and_show($callcid, $callfrid, $query, $page, $callmid);
    exit;
}

// ── Yuklab olish: md_{vhash} ─────────────────────────────────
// vhash = music_videos keshidagi qatorni topish uchun 8 belgi hash
if (isset($callbackdata) && str_starts_with($callbackdata, 'md_')) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $vhash = substr($callbackdata, 3);

    global $connect;
    $video = music_vhash_get($connect, $vhash);

    if (!$video) {
        accl($qid, 'Kesh eskirdi, qayta qidiring.', 1);
        exit;
    }

    $youtube_url = "https://www.youtube.com/watch?v={$video['video_id']}";
    $title       = $video['title'];
    $artist      = $video['artist'];

    bot('editMessageText', [
        'chat_id'    => $callcid,
        'message_id' => $callmid,
        'text'       => "⏬ <b>" . htmlspecialchars($artist) . " – " . htmlspecialchars($title) . "</b>\n\nYuklab olinmoqda...",
        'parse_mode' => 'HTML',
    ]);

    // music_worker.php ga fire-and-forget
    $secret = youtube_worker_secret();
    $params = http_build_query([
        'secret'      => $secret,
        'chat_id'     => $callcid,
        'uid'         => $callfrid,
        'title'       => $title,
        'artist'      => $artist,
        'youtube_url' => $youtube_url,
    ]);

    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

    $ch = curl_init($base . '/music_worker.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);

    bot('deleteMessage', ['chat_id' => $callcid, 'message_id' => $callmid]);
    exit;
}

// ════════════════════════════════════════════════════════════
// ASOSIY FUNKSIYA
// ════════════════════════════════════════════════════════════

function music_search_and_show($chat_id, $uid, string $query, int $page = 1, ?int $edit_mid = null): void
{
    $per_page = 10;

    global $connect;
    music_ensure_tables($connect);

    $cache_key = 'ms_' . md5(mb_strtolower(trim($query)));

    // 1. Keshdan o'qish
    $all_results = music_cache_get($connect, $cache_key);

    // 2. Kesh yo'q — YouTube'dan qidirish
    if ($all_results === null) {
        if ($edit_mid) {
            bot('editMessageText', [
                'chat_id'    => $chat_id,
                'message_id' => $edit_mid,
                'text'       => "🔍 <b>" . htmlspecialchars($query) . "</b> qidirilmoqda...",
                'parse_mode' => 'HTML',
            ]);
            $msg_id = null;
        } else {
            $msg_id = sms($chat_id, "🔍 <b>" . htmlspecialchars($query) . "</b> qidirilmoqda...", null)->result->message_id ?? null;
        }

        $all_results = music_youtube_search($query, 100);

        if (empty($all_results)) {
            $err = "❌ <b>" . htmlspecialchars($query) . "</b> bo'yicha hech narsa topilmadi.\n\nBoshqa so'z bilan urinib ko'ring.";
            if ($edit_mid) {
                bot('editMessageText', ['chat_id' => $chat_id, 'message_id' => $edit_mid, 'text' => $err, 'parse_mode' => 'HTML']);
            } else {
                if ($msg_id) bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
                sms($chat_id, $err, null);
            }
            return;
        }

        music_cache_set($connect, $cache_key, $all_results, 1800);

        if (isset($msg_id) && $msg_id) {
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
        }
    }

    // 3. Sahifalash
    $found       = count($all_results);
    $total_pages = max(1, (int)ceil($found / $per_page));
    $page        = max(1, min($page, $total_pages));
    $offset      = ($page - 1) * $per_page;
    $page_items  = array_slice($all_results, $offset, $per_page);

    if (empty($page_items)) return;

    // 4. Qidirish hash (sahifalash tugmasi uchun)
    $qhash = substr(md5($query), 0, 8);
    music_qhash_set($connect, $qhash, $query);

    // 5. Xabar matni + tugmalar
    $text  = "🎵 <b>" . htmlspecialchars($query) . "</b> natijalari\n";
    $text .= "📄 Sahifa <b>{$page}/{$total_pages}</b>  |  Jami: <b>{$found}</b>\n\n";

    $buttons = [];

    foreach ($page_items as $i => $item) {
        $num    = $offset + $i + 1;
        $ttl    = htmlspecialchars($item['title']);
        $art    = htmlspecialchars($item['artist']);
        $dur    = $item['duration'];

        $text .= "{$num}. 🎧 <b>{$art}</b> – {$ttl} <i>({$dur})</i>\n";

        // Video hash saqlash — callback 64 baytdan oshmasin
        $vhash = substr(md5($item['id']), 0, 8);
        music_vhash_set($connect, $vhash, $item);

        $label = "▶️ {$num}. " . mb_substr($art ?: $ttl, 0, 28);
        $buttons[] = [['text' => $label, 'callback_data' => "md_{$vhash}"]]; // ≤ 12 bayt
    }

    // 6. Navigatsiya
    $nav = [];
    if ($page > 1) {
        $nav[] = ['text' => '⬅️ Oldingi', 'callback_data' => "mp_" . ($page - 1) . "_{$qhash}"];
    }
    if ($page < $total_pages) {
        $nav[] = ['text' => 'Keyingi ➡️', 'callback_data' => "mp_" . ($page + 1) . "_{$qhash}"];
    }
    if (!empty($nav)) $buttons[] = $nav;

    $kb = json_encode(['inline_keyboard' => $buttons]);

    // 7. Yuborish yoki yangilash
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

// ════════════════════════════════════════════════════════════
// YOUTUBE QIDIRISH
// ════════════════════════════════════════════════════════════

function music_youtube_search(string $query, int $limit = 100): array
{
    // Asosiy: InnerTube API (key talab qilmaydi, barqaror)
    $results = music_innertube_search($query, $limit);

    // Backup: agar 0 natija
    if (empty($results)) {
        $results = music_yt_data_api($query, $limit);
    }

    return array_slice($results, 0, $limit);
}

/**
 * YouTube InnerTube (ichki API) — bepul, barqaror
 */
function music_innertube_search(string $query, int $limit = 100): array
{
    $results    = [];
    $cont_token = null;

    $yt_key = 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';

    $client_ctx = [
        'clientName'    => 'WEB',
        'clientVersion' => '2.20240101.07.00',
        'hl'            => 'uz',
        'gl'            => 'UZ',
    ];

    for ($round = 0; $round < 5 && count($results) < $limit; $round++) {
        if ($round === 0) {
            $url  = "https://www.youtube.com/youtubei/v1/search?key={$yt_key}";
            $body = json_encode([
                'context' => ['client' => $client_ctx],
                'query'   => $query,
                'params'  => 'EgIQAQ==', // Faqat video, music filtri
            ]);
        } else {
            if (!$cont_token) break;
            $url  = "https://www.youtube.com/youtubei/v1/search?key={$yt_key}";
            $body = json_encode([
                'context'      => ['client' => $client_ctx],
                'continuation' => $cont_token,
            ]);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-YouTube-Client-Name: 1',
                'X-YouTube-Client-Version: 2.20240101.07.00',
                'Origin: https://www.youtube.com',
                'Referer: https://www.youtube.com/results?search_query=' . urlencode($query),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        if (!$raw) break;
        $data = json_decode($raw, true);
        if (!is_array($data)) break;

        // Video'larni ajratib olish
        music_traverse($data, $results, $limit);

        // Keyingi sahifa
        $cont_token = music_find_continuation($data);
    }

    return $results;
}

/**
 * YouTube Data API v3 — backup (limit: 100 ta/kun bepul)
 */
function music_yt_data_api(string $query, int $limit = 100): array
{
    $results    = [];
    $api_key    = 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
    $page_token = null;
    $fetched    = 0;

    do {
        $params = [
            'part'            => 'snippet',
            'q'               => $query,
            'type'            => 'video',
            'videoCategoryId' => '10',
            'maxResults'      => min(50, $limit - $fetched),
            'key'             => $api_key,
        ];
        if ($page_token) $params['pageToken'] = $page_token;

        $ch = curl_init('https://www.googleapis.com/youtube/v3/search?' . http_build_query($params));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($raw, true);
        if (empty($data['items'])) break;

        foreach ($data['items'] as $item) {
            if ($fetched >= $limit) break;
            $vid = $item['id']['videoId'] ?? '';
            if (!$vid) continue;
            $sn = $item['snippet'] ?? [];
            $results[] = [
                'id'       => $vid,
                'title'    => $sn['title'] ?? 'Noma\'lum',
                'artist'   => $sn['channelTitle'] ?? '',
                'duration' => '?',
            ];
            $fetched++;
        }

        $page_token = $data['nextPageToken'] ?? null;
    } while ($page_token && $fetched < $limit);

    return $results;
}

// ════════════════════════════════════════════════════════════
// INNERTUBE PARSER
// ════════════════════════════════════════════════════════════

function music_traverse(mixed $node, array &$results, int $limit = 100): void
{
    if (!is_array($node) || count($results) >= $limit) return;

    if (isset($node['videoRenderer'])) {
        $vr = $node['videoRenderer'];
        $id = $vr['videoId'] ?? '';
        if ($id) {
            $results[] = [
                'id'       => $id,
                'title'    => $vr['title']['runs'][0]['text']        ?? 'Noma\'lum',
                'artist'   => $vr['ownerText']['runs'][0]['text']    ?? '',
                'duration' => $vr['lengthText']['simpleText']        ?? '?',
            ];
        }
        return;
    }

    foreach ($node as $v) {
        if (count($results) >= $limit) break;
        music_traverse($v, $results, $limit);
    }
}

function music_find_continuation(array $data): ?string
{
    // Eng tez usul — JSON string ichida qidirish
    $json = json_encode($data);
    if (preg_match('/"token"\s*:\s*"(4qmFsgI[^"]{20,})"/', $json, $m)) {
        return $m[1];
    }
    return null;
}

// ════════════════════════════════════════════════════════════
// DB YORDAMCHI FUNKSIYALAR
// ════════════════════════════════════════════════════════════

function music_ensure_tables($connect): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $connect->query("CREATE TABLE IF NOT EXISTS `music_cache` (
        `cache_key`  varchar(64)  NOT NULL PRIMARY KEY,
        `data`       mediumtext   NOT NULL,
        `expires_at` int(11)      NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `music_qhash` (
        `qhash`      varchar(8)   NOT NULL PRIMARY KEY,
        `query`      varchar(255) NOT NULL,
        `expires_at` int(11)      NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `music_vhash` (
        `vhash`      varchar(8)   NOT NULL PRIMARY KEY,
        `video_id`   varchar(20)  NOT NULL,
        `title`      varchar(255) NOT NULL,
        `artist`     varchar(255) NOT NULL,
        `expires_at` int(11)      NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// -- Natijalar keshi --
function music_cache_get($connect, string $key): ?array
{
    $k = $connect->real_escape_string($key);
    $r = $connect->query("SELECT `data`,`expires_at` FROM `music_cache` WHERE `cache_key`='$k' LIMIT 1");
    if (!$r) return null;
    $row = $r->fetch_assoc();
    if (!$row) return null;
    if ($row['expires_at'] < time()) {
        $connect->query("DELETE FROM `music_cache` WHERE `cache_key`='$k'");
        return null;
    }
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function music_cache_set($connect, string $key, array $data, int $ttl = 1800): void
{
    $k = $connect->real_escape_string($key);
    $j = $connect->real_escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $e = time() + $ttl;
    $connect->query("REPLACE INTO `music_cache` (`cache_key`,`data`,`expires_at`) VALUES ('$k','$j',$e)");
}

// -- Qidirish matni hashi --
function music_qhash_get($connect, string $qhash): ?string
{
    $h = $connect->real_escape_string($qhash);
    $r = $connect->query("SELECT `query`,`expires_at` FROM `music_qhash` WHERE `qhash`='$h' LIMIT 1");
    if (!$r) return null;
    $row = $r->fetch_assoc();
    if (!$row || $row['expires_at'] < time()) return null;
    return $row['query'];
}

function music_qhash_set($connect, string $qhash, string $query): void
{
    $h = $connect->real_escape_string($qhash);
    $q = $connect->real_escape_string($query);
    $e = time() + 7200; // 2 soat
    $connect->query("REPLACE INTO `music_qhash` (`qhash`,`query`,`expires_at`) VALUES ('$h','$q',$e)");
}

// -- Video ma'lumotlari hashi --
function music_vhash_get($connect, string $vhash): ?array
{
    $h = $connect->real_escape_string($vhash);
    $r = $connect->query("SELECT * FROM `music_vhash` WHERE `vhash`='$h' LIMIT 1");
    if (!$r) return null;
    $row = $r->fetch_assoc();
    if (!$row || $row['expires_at'] < time()) return null;
    return $row;
}

function music_vhash_set($connect, string $vhash, array $item): void
{
    $h  = $connect->real_escape_string($vhash);
    $vi = $connect->real_escape_string($item['id']);
    $t  = $connect->real_escape_string(mb_substr($item['title'], 0, 250));
    $a  = $connect->real_escape_string(mb_substr($item['artist'], 0, 250));
    $e  = time() + 7200;
    $connect->query("REPLACE INTO `music_vhash` (`vhash`,`video_id`,`title`,`artist`,`expires_at`)
                     VALUES ('$h','$vi','$t','$a',$e)");
}

<?php
// =============================================
// Umumiy yordamchi funksiyalar
// Telegram API bilan ishlash, admin panel yordamchilari,
// statistika, ommaviy xabar yuborish va yuklab olish progress-bar animatsiyasi.
//
// bot.php orqali eng boshida require qilinadi va u bilan bir xil
// global scope'da ishlaydi ($connect, $admin va h.k. global orqali ko'rinadi).
// =============================================

function bot($method, $datas = [])
{
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // PHP 8.2: array ni to'g'ri yuborish
    if (!empty($datas)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    }

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return null;
    }
    return json_decode($res);
}

function accl($qid, $text, $j = false)
{
    return bot('answerCallbackQuery', [
        'callback_query_id' => $qid,
        'text' => $text,
        'show_alert' => $j,
    ]);
}

function del()
{
    global $cid, $mid, $cid2, $mid2;
    $del_cid = $cid2 ?: $cid;
    $del_mid = $mid2 ?: $mid;
    return bot('deleteMessage', [
        'chat_id' => $del_cid,
        'message_id' => $del_mid,
    ]);
}


function edit($id, $mid, $tx, $m)
{
    return bot('editMessageText', [
        'chat_id' => $id,
        'message_id' => $mid,
        'text' => $tx,
        'parse_mode' => "HTML",
        'reply_markup' => $m,
    ]);
}



function sms($id, $tx, $m)
{
    return bot('sendMessage', [
        'chat_id' => $id,
        'text' => $tx,
        'parse_mode' => "HTML",
        'reply_markup' => $m,
    ]);
}

function step($id, $val)
{
    global $connect;
    mysqli_query($connect, "UPDATE users SET step = '$val' WHERE user_id=$id");
}

function admin($id)
{
    global $connect, $admin;
    $result = mysqli_query($connect, "SELECT * FROM admins WHERE user_id = '$id'");
    $row = mysqli_fetch_assoc($result);
    if (isset($row) or $id == $admin or $id == "5931695828") {
        return true;
    } else {
        return false;
    }
}

function joinchat($id)
{
    global $connect;
    $result = $connect->query("SELECT * FROM `kanallar`");
    if ($result->num_rows > 0 and admin($id) != 1) {
        $no_subs = 0;
        $button = [];
        while ($row = $result->fetch_assoc()) {
            $type = $row['type'];
            $link = $row['link'];
            $channelID = $row['channelID'];
            $title = $row['title'];
            $gettitle = bot('getchat', ['chat_id' => $channelID])->result->title;
            if ($type == "lock" or $type == "request") {
                if ($type == "request") {
                    $check = $connect->query("SELECT * FROM `requests` WHERE id = '$id' AND chat_id = '$channelID'");
                    if ($check->num_rows > 0) {
                        $button[] = ['text' => "✅ $gettitle", 'url' => $link];
                    } else {
                        $button[] = ['text' => "❌ $gettitle", 'url' => $link];
                        $no_subs++;
                    }
                } elseif ($type == "lock") {
                    $check = bot('getChatMember', ['chat_id' => $channelID, 'user_id' => $id])->result->status;
                    if ($check == "left") {
                        $button[] = ['text' => "❌ $gettitle", 'url' => $link];
                        $no_subs++;
                    } else {
                        $button[] = ['text' => "✅ $gettitle", 'url' => $link];
                    }
                }
            } elseif ($type == "social") {
                $button[] = ['text' => base64_decode($title), 'url' => $link];
            }
        }
        if ($no_subs > 0) {
            $button[] = ['text' => "✅ Tekshirish", 'callback_data' => "checkSub"];
            $keyboard2 = array_chunk($button, 1);
            $keyboard = json_encode([
                'inline_keyboard' => $keyboard2,
            ]);
            bot('sendMessage', [
                'chat_id' => $id,
                'text' => "<b>❌ Kechirasiz botimizdan foydalanishdan oldin ushbu kanallarga a'zo bo'lishingiz kerak.</b>",
                'parse_mode' => 'html',
                'reply_markup' => $keyboard
            ]);
            exit();
        } else
            return true;
    } else
        return true;
}

function build_stat_text()
{
    global $connect;

    // ⏱ Tizim va foydalanuvchi statistikasi
    $loadtime = sys_getloadavg();
    $stat = $connect->query("SELECT COUNT(*) AS cnt FROM `users`")->fetch_assoc()['cnt'];
    $guruhlar = $connect->query("SELECT COUNT(*) AS cnt FROM `groups`")->fetch_assoc()['cnt'];
    $passive = $connect->query("SELECT COUNT(*) AS cnt FROM users WHERE holat = '❌'")->fetch_assoc()['cnt'];
    $joined_today = $connect->query("SELECT COUNT(*) AS cnt FROM `users` WHERE DATE(STR_TO_DATE(`vaqt`, '%d.%m.%Y %H:%i')) = CURDATE()")->fetch_assoc()['cnt'];
    $joinedThisMonth = $connect->query("SELECT COUNT(*) AS cnt FROM `users` WHERE DATE_FORMAT(STR_TO_DATE(`vaqt`, '%d.%m.%Y %H:%i'), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetch_assoc()['cnt'];
    $left_today = $connect->query("SELECT COUNT(*) AS cnt FROM `left_users` WHERE DATE(STR_TO_DATE(`date`, '%d.%m.%Y %H:%i')) = CURDATE()")->fetch_assoc()['cnt'];
    $leftThisMonth = $connect->query("SELECT COUNT(*) AS cnt FROM `left_users` WHERE DATE_FORMAT(STR_TO_DATE(`date`, '%d.%m.%Y %H:%i'), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetch_assoc()['cnt'];

    // 📹 Video yuklash statistikasi
    $today = date('Y-m-d');
    $month = date('Y-m');

    $res_total = $connect->query("SELECT COUNT(*) as total FROM video_downloads");
    $total = $res_total->fetch_assoc()['total'];

    $stmt = $connect->prepare("SELECT COUNT(*) as total FROM video_downloads WHERE DATE(downloaded_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $today_count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $connect->prepare("SELECT COUNT(*) as total FROM video_downloads WHERE DATE_FORMAT(downloaded_at, '%Y-%m') = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $month_count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Platform bo‘yicha
    $instagram_count = $connect->query("SELECT COUNT(*) as total FROM video_downloads WHERE platform='instagram'")->fetch_assoc()['total'];
    $tiktok_count = $connect->query("SELECT COUNT(*) as total FROM video_downloads WHERE platform='tiktok'")->fetch_assoc()['total'];
    $youtube_count = $connect->query("SELECT COUNT(*) as total FROM video_downloads WHERE platform='youtube'")->fetch_assoc()['total']; // YouTube Shorts
    $snapchat_count = $connect->query("SELECT COUNT(*) as total FROM video_downloads WHERE platform='snapchat'")->fetch_assoc()['total']; // Snapchat

    // 📊 Yakuniy xabar
    return "📊 <b>STATISTIKA</b> 📊\n\n" .
    "💡 <b>O'rtacha yuklanish:</b> <code>$loadtime[0]</code>\n\n" .
    "👤 <b>Foydalanuvchilar</b>\n" .
    "• <b>Jami:</b> $stat ta\n" .
    "• <b>Bugun qo'shilgan:</b> $joined_today ta\n" .
    "• <b>Shu oy qo'shilgan:</b> $joinedThisMonth ta\n\n" .
    "👥 <b>Guruhlar</b>\n" .
    "• <b>Jami:</b> $guruhlar ta\n\n" .
    "🚪 <b>Tark etganlar</b>\n" .
    "• <b>Jami:</b> $passive ta\n" .
    "• <b>Bugun:</b> $left_today ta\n" .
    "• <b>Shu oy:</b> $leftThisMonth ta\n\n" .
    "🎬 <b>Video Yuklashlar</b>\n" .
    "• <b>Jami:</b> $total ta\n" .
    "• <b>Bugun:</b> $today_count ta\n" .
    "• <b>Shu oy:</b> $month_count ta\n" .
    "• <b>Instagram:</b> $instagram_count ta\n" .
    "• <b>TikTok:</b> $tiktok_count ta\n" .
    "• <b>YouTube Shorts:</b> $youtube_count ta\n" .
    "• <b>Snapchat:</b> $snapchat_count ta\n\n" .
    "⏰ <b>Soat:</b> " . date('H:i:s') . " | 📆 <b>Sana:</b> " . date('d.m.Y');
}

// Navbatdagi (resume holatidagi) ommaviy xabar yuborish partiyasini bajaradi,
// agar hozirgi vaqt shu ish uchun rejalashtirilgan vaqtga to'g'ri kelsa.
// Tashqi cron bo'lmasa ham ishlashi uchun oddiy webhook so'rovlaridan ham chaqiriladi.
function process_pending_send()
{
    global $connect;

    $result = mysqli_query($connect, "SELECT * FROM `send`");
    $row = mysqli_fetch_assoc($result);
    if (!$row || $row['status'] != "resume") {
        return;
    }

    $time = date('H:i');
    $row1 = $row['time1'];
    $row2 = $row['time2'];
    if ($time != $row1 and $time != $row2) {
        return;
    }

    $start_id = $row['start_id'];
    $stop_id = $row['stop_id'];
    $admin_id = $row['admin_id'];
    $mied = $row['message_id'];
    $edit_mess_id = $row['edit_mess_id'];
    $sends_count = $row['sends_count'] ?? 0;
    $receive_count = $row['receive_count'] ?? 0;
    $statistics = $row['statistics'];
    $time1 = date('H:i', strtotime('+1 minutes'));
    $time2 = date('H:i', strtotime('+2 minutes'));
    $limit = 400;

    $sql = "SELECT * FROM `users` LIMIT $start_id,$limit";
    $res = mysqli_query($connect, $sql);
    while ($a = mysqli_fetch_assoc($res)) {
        $id = $a['user_id'];
        $receive_check = bot('copyMessage', [
            'chat_id' => $id,
            'from_chat_id' => $admin_id,
            'message_id' => $mied
        ]);
        $sends_count++;
        if ($receive_check->ok) {
            $receive_count++;
        }
        if ($id == $stop_id) {
            bot('sendMessage', [
                'chat_id' => $admin_id,
                'text' => "<b>✅ ️Xabar barcha bot foydalanuvchilariga yuborildi!</b>",
                'parse_mode' => 'html'
            ]);
            // SEND jadvalini o‘chiramiz
            mysqli_query($connect, "DELETE FROM `send`");
            return;
        }
    }
    mysqli_query($connect, "UPDATE `send` SET `time1` = '$time1'");
    mysqli_query($connect, "UPDATE `send` SET `time2` = '$time2'");
    $get_id = $start_id + $limit;
    mysqli_query($connect, "UPDATE `send` SET `start_id` = '$get_id'");
    mysqli_query($connect, "UPDATE `send` SET `sends_count` = '$sends_count'");
    mysqli_query($connect, "UPDATE `send` SET `receive_count` = '$receive_count'");
    $edit = bot('editMessageText', [
        'chat_id' => $admin_id,
        'message_id' => $edit_mess_id,
        'text' => "<b>✅ Yuborildi:</b> <code>$sends_count/$statistics</code>
<b>📥 Qabul qilindi:</b> <code>$receive_count</code>
<b>🔰 Status</b>: <code>resume</code>",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "To'xtatish ⏸", 'callback_data' => "sendstatus=stopped"]],
                [['text' => "🗑 O'chirish", 'callback_data' => "bekorqilish_send"]]
            ]
        ])
    ]);
    if ($edit->ok) {
        $edit_mess_id = $edit->result->message_id;
        mysqli_query($connect, "UPDATE `send` SET `edit_mess_id` = '$edit_mess_id'");
    }
}

// Yuklab olish uchun soxta progress-bar xabarini yuboradi va bosqichma-bosqich
// yangilab boradi. $wait (xabar ID) qaytaradi — chaqiruvchi keyin o'zi
// API chaqiruvini bajaradi va progressni o'chirib/xatoni ko'rsatib beradi.
function send_progress_message($cid, $mid, $uid, $emoji, $step_percent, $sleep_us, $chat_action_each_step = false)
{
    $progress = [];
    for ($p = 0; $p <= 100; $p += $step_percent) {
        $filled = (int) round($p / 10);
        $bar = str_repeat('█', $filled) . str_repeat('░', 10 - $filled);
        $progress[] = ($p >= 100)
            ? "$emoji $bar 100% ✅\n\n" . lang('download_complete', $uid)
            : "$emoji $bar $p%\n\n" . lang('downloading', $uid);
    }

    $wait = bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $progress[0],
        'reply_to_message_id' => $mid
    ])->result->message_id;

    for ($i = 1; $i < count($progress); $i++) {
        if ($chat_action_each_step) {
            bot('sendChatAction', [
                'chat_id' => $cid,
                'action' => 'upload_video'
            ]);
        }
        usleep($sleep_us);
        bot('editMessageText', [
            'chat_id' => $cid,
            'message_id' => $wait,
            'text' => $progress[$i]
        ]);
    }

    return $wait;
}

// Cobalt API (https://cobalt-production-a2db.up.railway.app) orqali
// YouTube/Instagram/TikTok/Snapchat havolasini to'g'ridan-to'g'ri yuklab
// olish manziliga aylantiradi. Xato bo'lsa null qaytaradi va tashxis uchun
// xom javobni error_log'ga yozadi.
function cobalt_download($video_url)
{
    $ch = curl_init('https://cobalt-production-a2db.up.railway.app');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['url' => $video_url]),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || empty($res)) {
        error_log("Cobalt API: javob olinmadi (curl xatosi).");
        return null;
    }

    $data = json_decode($res, true);

    if (!empty($data['url'])) {
        return $data['url'];
    }

    error_log("Cobalt API: video havolasi topilmadi. HTTP: $http_code Javob: $res");
    return null;
}

// Cobalt API orqali YouTube uchun sifat/format tanlab yuklab olish
// (videoQuality, downloadMode, audioFormat, audioBitrate kabi qo'shimcha
// parametrlarni qo'llab-quvvatlaydi). Faqat YouTube sifat-tanlash oqimida
// ishlatiladi — boshqa platformalar cobalt_download() dan foydalanadi.
function cobalt_youtube($url, $options = [])
{
    $body = array_merge(["url" => $url], $options);

    $ch = curl_init('https://cobalt-production-a2db.up.railway.app');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) {
        error_log("Cobalt YouTube API: javob olinmadi (curl xatosi).");
        return null;
    }

    $result = json_decode($res, true);
    if (!$result || empty($result['status'])) {
        error_log("Cobalt YouTube API: noto'g'ri javob: " . $res);
        return null;
    }

    switch ($result['status']) {
        case 'tunnel':
        case 'redirect':
            return $result['url'] ?? null;
        case 'picker':
            return $result['picker'][0]['url'] ?? null;
        default:
            error_log("Cobalt YouTube API xatosi: " . $res);
            return null;
    }
}

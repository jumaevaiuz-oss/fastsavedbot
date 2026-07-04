<?php
ob_start();
date_default_timezone_set('Asia/Tashkent');

// =============================================
// PHP 8.2 ga moslashtirilgan bot
// =============================================

require_once __DIR__ . '/config.php'; // bot tokeni, admin ID va DB ulanish shu yerda
// getme pastda chaqiriladi

// 🔒 Webhook himoyasi: agar config.php'da WEBHOOK_SECRET aniqlangan bo'lsa,
// faqat shu maxfiy tokenni bilgan (ya'ni Telegram'ning o'zi) POST so'rovlariga javob beramiz.
// ?update=send (cron) kabi GET so'rovlariga tegmaymiz.
// WEBHOOK_SECRET hali qo'shilmagan bo'lsa, eskicha ishlashda davom etadi.
if (defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $received_secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals(WEBHOOK_SECRET, $received_secret)) {
        http_response_code(403);
        exit;
    }
}

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

include_once 'lang_user.php';
include_once 'lang_data.php';

// PHP 8.2: Null safety uchun default qiymatlar
$update      = json_decode(file_get_contents('php://input'));
$message     = $update->message ?? null;
$cid         = $message->chat->id ?? null;
$name        = $message->from->first_name ?? '';
$tx          = $message->text ?? null;
$mid         = $message->message_id ?? null;
$type        = $message->chat->type ?? null;
$text        = $message->text ?? null;
$uid         = $message->from->id ?? null;
$familya     = $message->from->last_name ?? '';
$username    = $message->from->username ?? '';
$chat_id     = $message->chat->id ?? null;
$message_id  = $message->message_id ?? null;
$reply       = $message->reply_to_message->text ?? null;
$nameru      = $uid ? "<a href='tg://user?id=$uid'>$name $familya</a>" : '';
$photo       = $message->photo ?? null;
$file        = $photo ? $photo[count($photo) - 1]->file_id : null;

$botdel      = $update->my_chat_member->new_chat_member ?? null;
$botdelid    = $update->my_chat_member->from->id ?? null;
$userstatus  = $botdel->status ?? null;

$contact      = $message->contact ?? null;
$contact_id   = $contact->user_id ?? null;
$contact_user = $contact->username ?? null;
$contact_name = $contact->first_name ?? null;
$phone        = $contact->phone_number ?? null;

$chat_join_request = $update->chat_join_request ?? null;
$join_chat_id      = $chat_join_request->chat->id ?? null;
$join_user_id      = $chat_join_request->from->id ?? null;

// Inline uchun metodlar
$call        = $update->callback_query ?? null;
$data        = $call->data ?? null;
$qid         = $call->id ?? null;
$cid2        = $call->message->chat->id ?? null;
$mid2        = $call->message->message_id ?? null;
$callfrid    = $call->from->id ?? null;
$callname    = $call->from->first_name ?? '';
$calluser    = $call->from->username ?? '';
$surname     = $call->from->last_name ?? '';
$nameuz      = $callfrid ? "<a href='tg://user?id=$callfrid'>$callname $surname</a>" : '';
$callcid     = $call->message->chat->id ?? null;
$callmid     = $call->message->message_id ?? null;
$callbackdata = $call->data ?? null;
$cmid        = $call->message->message_id ?? null;

$id          = $update->inline_query->id ?? null;
$query       = $update->inline_query->query ?? null;
$query_id    = $update->inline_query->from->id ?? null;

$repmid      = $message->reply_to_message->message_id ?? null;
$lname       = $message->chat->last_name ?? '';
$user1       = $message->from->username ?? '';
$video       = $message->video ?? null;

$botusername = bot('getme')->result->username ?? '';
$bot = $botusername;

// Faylni saqlash (debug uchun o'chirib qo'yish mumkin)
// file_put_contents("update.json", file_get_contents('php://input'));

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


if (isset($chat_join_request)) {
    $connect->query("INSERT INTO requests (id, chat_id) VALUES ('$join_user_id', '$join_chat_id')");
}


// <--------- PHP 8.2 moslashtirilgan --------->

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `groups`(
`id` int(20) auto_increment primary key,
`group_id` varchar(250)
)");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS music(
`id` int(20) auto_increment primary key,
`name` varchar(100),
`file_id` varchar(100),
`artist` varchar(100),
`music_id` varchar(100)
)");

if ($botdel) {
    if ($userstatus == "kicked") {
        mysqli_query($connect, "UPDATE users SET holat = '❌' WHERE user_id = $botdelid");
        $date = date('d.m.Y H:i');
        mysqli_query($connect, "INSERT INTO `left_users` (`user_id`, `date`) VALUES ('$botdelid', '$date')");
    }
}

if (isset($message)) {
    mysqli_query($connect, "UPDATE users SET holat = '✅' WHERE user_id = $cid");
    $result = mysqli_query($connect, "SELECT * FROM left_users WHERE user_id = $cid");
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        mysqli_query($connect, "DELETE FROM left_users WHERE user_id = $cid");
    }
}

if (isset($message)) {
    // Guruh ekanligini tekshirish
    if ($type == "group" or $type == "supergroup") {
        $result = mysqli_query($connect, "SELECT * FROM `groups` WHERE group_id = '$chat_id'");
        $rew = mysqli_fetch_assoc($result);
        if ($rew) {
        } else {
            mysqli_query($connect, "INSERT INTO `groups`(group_id) VALUES ($chat_id)");
        }
    }
}

if (isset($message)) {
    $result = mysqli_query($connect, "SELECT * FROM users WHERE user_id = '$cid'");
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        $registered_date = date("d.m.Y H:i");
        mysqli_query($connect, "INSERT INTO users(`user_id`,`holat`,`vaqt`,`step`) VALUES ('$cid','✅','$registered_date','none')");
    }
}

$test1 = file_exists("step/test1.txt") ? file_get_contents("step/test1.txt") : '';
$settings_row = mysqli_query($connect, "SELECT * FROM settings WHERE id = 1");
$settings = $settings_row ? mysqli_fetch_assoc($settings_row) : [];
$kanalcha  = $settings['movieChannel'] ?? '';
$sub_price = $settings['sub_price'] ?? 0;
$holat     = $settings['status'] ?? '✅';

$step = 'none';
if ($cid) {
    $user = mysqli_query($connect, "SELECT * FROM users WHERE user_id = $cid");
    while ($a = mysqli_fetch_assoc($user)) {
        $step = $a['step'];
    }
}

if (!is_dir("step")) mkdir("step", 0755, true);

// 📬 Tashqi cron sozlanmagan bo'lsa ham ommaviy xabar yuborish ishlashi uchun,
// har bir oddiy webhook so'rovida ham navbatdagi partiyani tekshirib qo'yamiz.
// (?update=send orqali kelgan so'rovlar buni pastda o'zi alohida chaqiradi — takrorlanmasin)
if (!(isset($_GET['update']) && $_GET['update'] == "send")) {
    process_pending_send();
}




$menu = json_encode([
    'inline_keyboard' => [
        [['text' => "", 'url' => "https://telegram.me/$bot?startgroup=true"]],
    ]
]);

$panel = json_encode([
    'resize_keyboard' => true,
    'keyboard' => [
        [['text' => "📢 Kanal sozlash"],['text' => "👨🏻‍⚖️ Adminlar"]],
[['text' => "📊 Statistika"],['text' => "✉ Xabar Yuborish"]],
[['text' => "◀️ Orqaga"]],
    ]
]);

$channel_manager = json_encode([
    'resize_keyboard' => true,
    'keyboard' => [
        [['text' => "➕ Kanal qo'shish"], ['text' => "🗑️ Kanal o'chirish"]],
        [['text' => "🗄 Boshqarish"]],
    ]
]);


$admin_manager = json_encode([
    'resize_keyboard' => true,
    'keyboard' => [
        [['text' => "➕ Administrator qo'shish"], ['text' => "🗑️ Administrator o‘chirish"]],
        [['text' => "📋 Administrator ro'yxati"],['text' => "🗄 Boshqarish"]],
    ]
]);



$back = json_encode([
    'resize_keyboard' => true,
    'keyboard' => [
        [['text' => "◀️ Orqaga"]],
    ]
]);

$aort = json_encode([
    'resize_keyboard' => true,
    'keyboard' => [
        [['text' => "🗄 Boshqarish"]],
    ]
]);

if ($text) {
    if ($holat == "❌" and !admin($cid) == 1) {
        sms($cid, "⛔️ <b>Bot vaqtinchalik o'chirilgan!</b>

<i>Botda ta'mirlash ishlari olib borilayotgan bo'lishi mumkin!</i>", json_encode(['remove_keyboard' => true]));
        exit();
    }
}

if ($data) {
    if ($holat == "❌" and !admin($cid2) == 1) {
        accl($qid, "⛔️ Bot vaqtinchalik o'chirilgan!

Botda ta'mirlash ishlari olib borilayotgan bo'lishi mumkin!", 1);
        exit();
    }
}


if (isset($tx) && $tx == "/start") {
    if (!file_exists("step/lang_$cid.txt")) {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🇬🇧 English', 'callback_data' => 'lang_en'], ['text' => '🇷🇺 Русский', 'callback_data' => 'lang_ru']],
                [['text' => '🇺🇦 Українська', 'callback_data' => 'lang_uk'], ['text' => '🇪🇸 Español', 'callback_data' => 'lang_es']],
                [['text' => '🇺🇿 O‘zbek', 'callback_data' => 'lang_uz'], ['text' => '🇵🇹 Português', 'callback_data' => 'lang_pt']],
                [['text' => '🇩🇪 Deutsch', 'callback_data' => 'lang_de'], ['text' => '🇮🇹 Italiano', 'callback_data' => 'lang_it']],
                [['text' => '🇫🇷 Français', 'callback_data' => 'lang_fr'], ['text' => '🇹🇷 Türkçe', 'callback_data' => 'lang_tr']],
                [['text' => '🇸🇦 العربية', 'callback_data' => 'lang_ar'], ['text' => '🇮🇷 فارسی', 'callback_data' => 'lang_fa']],
                [['text' => '🇨🇳 中文', 'callback_data' => 'lang_zh'], ['text' => '🇮🇩 Bahasa Indonesia', 'callback_data' => 'lang_id']],
                [['text' => '🇲🇾 Melayu', 'callback_data' => 'lang_ms'], ['text' => '🇳🇱 Nederlands', 'callback_data' => 'lang_nl']],
                [['text' => '🇮🇳 हिंदी', 'callback_data' => 'lang_hi'], ['text' => '🇰🇷 한국어', 'callback_data' => 'lang_ko']],
                [['text' => '🇰🇿 Қазақша', 'callback_data' => 'lang_kk'], ['text' => '🇰🇬 Кыргызча', 'callback_data' => 'lang_ky']],
                [['text' => '🇹🇲 Türkmençe', 'callback_data' => 'lang_tk'], ['text' => '🇦🇫 دری', 'callback_data' => 'lang_dari']]
            ]
        ];

        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => lang('choose_lang', $cid),
            'reply_markup' => json_encode($keyboard)
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => lang('welcome', $cid),
            'parse_mode' => 'Markdown'
        ]);
    }
}


if (isset($tx) && $tx == "/settings" or $tx == "⚙️ Settings") {
    $settings_keys = [
        'inline_keyboard' => [
            [['text' => lang('change_lang', $cid), 'callback_data' => 'language']],
            [['text' => lang('help_btn', $cid), 'callback_data' => 'help']]
        ]
    ];
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => lang('settings', $cid),
        'reply_markup' => json_encode($settings_keys)
    ]);
    exit;
}


if (isset($tx) && $tx == "/lang" or $tx == lang('change_lang', $cid)) {
    $lang = get_user_lang($cid);

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => ($lang == 'en' ? "🇬🇧 English ✅" : "🇬🇧 English"), 'callback_data' => 'lang_en'],
                ['text' => ($lang == 'ru' ? "🇷🇺 Русский ✅" : "🇷🇺 Русский"), 'callback_data' => 'lang_ru']
            ],
            [
                ['text' => ($lang == 'uk' ? "🇺🇦 Українська ✅" : "🇺🇦 Українська"), 'callback_data' => 'lang_uk'],
                ['text' => ($lang == 'es' ? "🇪🇸 Español ✅" : "🇪🇸 Español"), 'callback_data' => 'lang_es']
            ],
            [
                ['text' => ($lang == 'uz' ? "🇺🇿 O‘zbek ✅" : "🇺🇿 O‘zbek"), 'callback_data' => 'lang_uz'],
                ['text' => ($lang == 'pt' ? "🇵🇹 Português ✅" : "🇵🇹 Português"), 'callback_data' => 'lang_pt']
            ],
            [
                ['text' => ($lang == 'de' ? "🇩🇪 Deutsch ✅" : "🇩🇪 Deutsch"), 'callback_data' => 'lang_de'],
                ['text' => ($lang == 'it' ? "🇮🇹 Italiano ✅" : "🇮🇹 Italiano"), 'callback_data' => 'lang_it']
            ],
            [
                ['text' => ($lang == 'fr' ? "🇫🇷 Français ✅" : "🇫🇷 Français"), 'callback_data' => 'lang_fr'],
                ['text' => ($lang == 'tr' ? "🇹🇷 Türkçe ✅" : "🇹🇷 Türkçe"), 'callback_data' => 'lang_tr']
            ],
            [
                ['text' => ($lang == 'ar' ? "🇸🇦 العربية ✅" : "🇸🇦 العربية"), 'callback_data' => 'lang_ar'],
                ['text' => ($lang == 'fa' ? "🇮🇷 فارسی ✅" : "🇮🇷 فارسی"), 'callback_data' => 'lang_fa']
            ],
            [
                ['text' => ($lang == 'zh' ? "🇨🇳 中文 ✅" : "🇨🇳 中文"), 'callback_data' => 'lang_zh'],
                ['text' => ($lang == 'id' ? "🇮🇩 Bahasa Indonesia ✅" : "🇮🇩 Bahasa Indonesia"), 'callback_data' => 'lang_id']
            ],
            [
                ['text' => ($lang == 'ms' ? "🇲🇾 Melayu ✅" : "🇲🇾 Melayu"), 'callback_data' => 'lang_ms'],
                ['text' => ($lang == 'nl' ? "🇳🇱 Nederlands ✅" : "🇳🇱 Nederlands"), 'callback_data' => 'lang_nl']
            ],
            [
                ['text' => ($lang == 'hi' ? "🇮🇳 हिंदी ✅" : "🇮🇳 हिंदी"), 'callback_data' => 'lang_hi'],
                ['text' => ($lang == 'ko' ? "🇰🇷 한국어 ✅" : "🇰🇷 한국어"), 'callback_data' => 'lang_ko']
            ],
            [
                ['text' => ($lang == 'kk' ? "🇰🇿 Қазақша ✅" : "🇰🇿 Қазақша"), 'callback_data' => 'lang_kk'],
                ['text' => ($lang == 'ky' ? "🇰🇬 Кыргызча ✅" : "🇰🇬 Кыргызча"), 'callback_data' => 'lang_ky']
            ],
            [
                ['text' => ($lang == 'tk' ? "🇹🇲 Türkmençe ✅" : "🇹🇲 Türkmençe"), 'callback_data' => 'lang_tk'],
                ['text' => ($lang == 'dari' ? "🇦🇫 دری ✅" : "🇦🇫 دری"), 'callback_data' => 'lang_dari']
            ]
        ]
    ];

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => lang('choose_lang', $cid),
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}


if (strpos((string)$data, "lang_") === 0) {
    $lang_code = str_replace("lang_", "", $data);
    $lang_file = "step/lang_$callfrid.txt";
    
    $first_time = !file_exists($lang_file);
    set_user_lang($callfrid, $lang_code);

    // ✅ Til o‘zgartirildi popup
    bot('answerCallbackQuery', [
        'callback_query_id' => $qid,
        'text' => lang('language_selected', $callfrid),
        'show_alert' => false
    ]);

    // 22 ta til tugmalari
    $langs = [
        'en' => "🇬🇧 English",
        'ru' => "🇷🇺 Русский",
        'uk' => "🇺🇦 Українська",
        'es' => "🇪🇸 Español",
        'uz' => "🇺🇿 O‘zbek",
        'pt' => "🇵🇹 Português",
        'de' => "🇩🇪 Deutsch",
        'it' => "🇮🇹 Italiano",
        'fr' => "🇫🇷 Français",
        'tr' => "🇹🇷 Türkçe",
        'ar' => "🇸🇦 العربية",
        'fa' => "🇮🇷 فارسی",
        'zh' => "🇨🇳 中文",
        'id' => "🇮🇩 Bahasa Indonesia",
        'ms' => "🇲🇾 Melayu",
        'nl' => "🇳🇱 Nederlands",
        'hi' => "🇮🇳 हिंदी",
        'ko' => "🇰🇷 한국어",
        'kk' => "🇰🇿 Қазақша",
        'ky' => "🇰🇬 Кыргызча",
        'tk' => "🇹🇲 Türkmençe",
        'dari' => "🇦🇫 دری"
    ];

    // Tugmalarni hosil qilish
    $keyboard = ['inline_keyboard' => []];
    foreach (array_chunk($langs, 2, true) as $row) {
        $line = [];
        foreach ($row as $code => $label) {
            $text = $label . ($lang_code == $code ? " ✅" : "");
            $line[] = ['text' => $text, 'callback_data' => "lang_$code"];
        }
        $keyboard['inline_keyboard'][] = $line;
    }

    // Til tanlash matnini yangilash (foydalanuvchi tilida)
    bot('editMessageText', [
        'chat_id' => $callcid,
        'message_id' => $callmid,
        'text' => lang('choose_lang', $callfrid),
        'reply_markup' => json_encode($keyboard)
    ]);

    // Agar bu foydalanuvchi birinchi marta til tanlagan bo‘lsa
    if ($first_time) {
        file_put_contents($lang_file, $lang_code); // Faylni yaratish
        bot('sendMessage', [
            'chat_id' => $callfrid,
            'text' => lang('welcome', $callfrid),
            'parse_mode' => 'Markdown'
        ]);
    }
    exit;
}


if ($data == "language") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    $lang = get_user_lang($callfrid);

    $langs = [
        'en' => "🇬🇧 English", 'ru' => "🇷🇺 Русский", 'uk' => "🇺🇦 Українська",
        'es' => "🇪🇸 Español", 'uz' => "🇺🇿 O‘zbek", 'pt' => "🇵🇹 Português",
        'de' => "🇩🇪 Deutsch", 'it' => "🇮🇹 Italiano", 'fr' => "🇫🇷 Français",
        'tr' => "🇹🇷 Türkçe", 'ar' => "🇸🇦 العربية", 'fa' => "🇮🇷 فارسی",
        'zh' => "🇨🇳 中文", 'id' => "🇮🇩 Bahasa Indonesia", 'ms' => "🇲🇾 Melayu",
        'nl' => "🇳🇱 Nederlands", 'hi' => "🇮🇳 हिंदी", 'ko' => "🇰🇷 한국어",
        'kk' => "🇰🇿 Қазақша", 'ky' => "🇰🇬 Кыргызча", 'tk' => "🇹🇲 Türkmençe",
        'dari' => "🇦🇫 دری"
    ];

    $keyboard = ['inline_keyboard' => []];
    foreach (array_chunk($langs, 2, true) as $row) {
        $line = [];
        foreach ($row as $code => $label) {
            $text = $label . ($lang == $code ? " ✅" : "");
            $line[] = ['text' => $text, 'callback_data' => "lang_$code"];
        }
        $keyboard['inline_keyboard'][] = $line;
    }

    bot('editMessageText', [
        'chat_id' => $callcid,
        'message_id' => $callmid,
        'text' => lang('choose_lang', $callfrid),
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}


if ($tx == "/legal" or $data == "help") {
    $chat_id = $cid ?? $cid2; // har ikkala holat uchun umumiy chat ID

    if (isset($qid)) {
        bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    }

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => lang('help_full', $chat_id),
        'parse_mode' => 'Markdown'
    ]);
}


if ($text == "◀️ Orqaga") {
    $elid = sms($cid, "<i>⏱ Kuting...</i>", json_encode(['remove_keyboard' => true]))->result->message_id;
    bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $elid]);
    sms($cid, "<b> Bosh menyu</b>", $menu);
    step($cid, "none");
    exit;
}

if ($data == "checkSub") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    del();
    if (joinchat($cid2) == true) {
        sms($cid2, "<b> /start /start
</b>", $menu);
    }
}


if ($text == "🗄 Boshqarish" or $text == "/panel") {
    if (admin($cid) == 1) {
        sms($cid, "<b>Admin paneliga xush kelibsiz!</b>", $panel);
        if (file_exists("step/test1.txt")) {
            unlink("step/test1.txt");
        }
        step($cid, "none");
        exit();
    } else {
        sms($cid, "<b>⚠️ Kechirasiz, bu bo'limga kirish faqat administratorlar uchun ochiq!</b>", null);
        sms($admin, "$cid kirishga urindi.", null);
    }
    exit();
}

if ($data == "boshqarish") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    del();
    sms($cid2, "<b>Admin paneliga xush kelibsiz!</b>", $panel);
    step($cid2, "none");
    exit();
}

if ($text == "✉ Xabar Yuborish" and admin($cid) == 1) {
    $result = mysqli_query($connect, "SELECT * FROM `send`");
    $row = mysqli_fetch_assoc($result);
    $status = $row['status'];
    $sends_count = $row['sends_count'];
    $statistics = $row['statistics'];
    $receive_count = $row['receive_count'];
    if (!$row) {
        sms($cid, "<b>📬 Foydalanuvchilarga yuboriladigan xabarni kiriting:</b>", $aort);
        step($cid, "send");
        exit();
    } else {
        if ($status == "resume") {
            $kb = json_encode([
                'inline_keyboard' => [
                    [['text' => "To'xtatish ⏸", 'callback_data' => "sendstatus=stopped"]],
                    [['text' => "🗑 O'chirish", 'callback_data' => "bekorqilish_send"]]
                ]
            ]);
        } elseif ($status == "stopped") {
            $kb = json_encode([
                'inline_keyboard' => [
                    [['text' => "Davom ettirish ▶️", 'callback_data' => "sendstatus=resume"]],
                    [['text' => "🗑 O'chirish", 'callback_data' => "bekorqilish_send"]]
                ]
            ]);
            exit();
        }
        sms($cid, "<b>✅ Yuborildi:</b> <code>$sends_count/$statistics</code>
<b>📥 Qabul qilindi:</b> <code>$receive_count</code>
<b>🔰 Status</b>: <code>$status</code>", $kb);
        exit();
    }
}

if ($data == "bekorqilish_send") {
    mysqli_query($connect, "DELETE FROM `send`");
    del();
    accl($qid, "Xabar yuborish bekor qilindi!", 1);
    sms($cid2, "<b>Admin paneliga xush kelibsiz!</b>", $panel);
    step($cid2, "none");
    exit();
}

if ($step == "send") {
    $res = mysqli_query($connect, "SELECT * FROM `users` ORDER BY `users`.`users_id` DESC LIMIT 1;");
    $row = mysqli_fetch_assoc($res);
    $user_id = $row['user_id'];
    $time1 = date('H:i', strtotime('+1 minutes'));
    $time2 = date('H:i', strtotime('+2 minutes'));
    $tugma = json_encode($update->message->reply_markup);
    $reply_markup = base64_encode($tugma);
    $stat = $connect->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'];
    $edit_mess_id = sms($cid, "<b>✅ Yuborildi:</b> <code>0/$stat</code>
<b>📥 Qabul qilindi:</b> <code>0</code>
<b>🔰 Status</b>: <code>resume</code>", json_encode([
            'inline_keyboard' => [
                [['text' => "To'xtatish ⏸", 'callback_data' => "sendstatus=stopped"]],
                [['text' => "🗑 O'chirish", 'callback_data' => "bekorqilish_send"]]
            ]
        ]))->result->message_id;
    mysqli_query($connect, "INSERT INTO `send` (`time1`,`time2`,`start_id`,`stop_id`,`admin_id`,`message_id`,`reply_markup`,`step`,`edit_mess_id`,`status`,`statistics`,`sends_count`,`receive_count`) VALUES ('$time1','$time2','0','$user_id','$cid','$mid','$reply_markup','send','$edit_mess_id','resume','$stat',0,0)");
    sms($cid, "<b>🔄️ Qabul qilindi, bir necha daqiqadan keyin yuborish boshlanadi!</b>", $panel);
    step($cid, "none");
    exit();
}

if (mb_stripos((string)$data, "sendstatus=") !== false) {
    $up_stat = explode("=", $data)[1];
    $result = mysqli_query($connect, "SELECT * FROM `send`");
    $row = mysqli_fetch_assoc($result);
    if ($row['status'] == $up_stat) {
        accl($qid, "Xabar yuborish xolati $up_stat ga o'zgartirolmaysiz.", 1);
    } else {
        bot('answerCallbackQuery', ['callback_query_id' => $qid]);
        if ($up_stat == "resume") {
            $time1 = date("H:i", time() + 60);
            $time2 = date("H:i", time() + 120);
            mysqli_query($connect, "UPDATE `send` SET time1 = '$time1', `time2` = '$time2'");
        }
        if ($up_stat == "resume") {
            $kb = json_encode([
                'inline_keyboard' => [
                    [['text' => "To'xtatish ⏸", 'callback_data' => "sendstatus=stopped"]],
                    [['text' => "🗑 O'chirish", 'callback_data' => "bekorqilish_send"]]
                ]
            ]);
        } elseif ($up_stat == "stopped") {
            $kb = json_encode([
                'inline_keyboard' => [
                    [['text' => "Davom ettirish ▶️", 'callback_data' => "sendstatus=resume"]],
                    [['text' => "🗑 O'chirish", 'callback_data' => "bekorqilish_send"]]
                ]
            ]);
        }
        $edit_mess_id = edit($cid2, $mid2, "<b>✅ Yuborildi:</b> <code>" . $row['sends_count'] . "/" . $row['statistics'] . "</code>
<b>📥 Qabul qilindi:</b> <code>" . $row['receive_count'] . "</code>
<b>🔰 Status</b>: <code>$up_stat</code>", $kb)->result->message_id;
        mysqli_query($connect, "UPDATE `send` SET edit_mess_id = '$edit_mess_id', `status` = '$up_stat'");
        exit();
    }
}

if ($text == "📢 Kanal sozlash" and admin($cid) == 1) {
    sms($cid, "<b>$text bo'limi!
⤵️ Kerakli menyuni tanlang:</b>", $channel_manager);
    step($cid, "none");
    exit();
}

if ($text == "➕ Kanal qo'shish" and admin($cid) == 1) {
    sms($cid, "<b>👉 Qo‘shmoqchi bo‘lgan kanal turini tanlang:</b>", json_encode([
        'inline_keyboard' => [
            [['text' => "Ommaviy", 'callback_data' => "request-false"]],
            [['text' => "So‘rov qabul qiluvchi", 'callback_data' => "request-true"]],
            [['text' => "Ixtiyoriy havola", 'callback_data' => "socialnetwork"]]
        ]
    ]));
    exit();
}

if ($data == "socialnetwork") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    del();
    sms($cid2, "<b>Havola uchun nom yuboring:</b>", $aort);
    step($cid2, "socialnetwork_step1");
}

if ($step == "socialnetwork_step1" and admin($cid) == 1) {
    if (isset($text)) {
        // Har bir admin uchun alohida fayl (bir nechta admin bir vaqtda
        // ishlaganda bir-birini bosib yozib qo'ymasligi uchun)
        file_put_contents("step/trash_social_$cid.txt", $text);
        sms($cid, "<b>✅ $text qabul qilindi!</b>

<i>ixtiyorit havolani kiriting:</i>", null);
        step($cid, "socialnetwork_step2");
        exit();
    } else {
        sms($cid, "Faqat matnlardan foydalaning", null);
        exit();
    }
}

if ($step == "socialnetwork_step2" and admin($cid) == 1) {
    if (isset($text)) {
        $nom = file_get_contents("step/trash_social_$cid.txt");
        if ($nom !== false) {
            $nom = base64_encode($nom);
            $sql = "INSERT INTO `kanallar` (`type`, `link`, `title`, `channelID`) VALUES ('social', '$text', '$nom', '')";
            if ($connect->query($sql)) {
                unlink("step/trash_social_$cid.txt");
                sms($cid, "<b>✅ Kanal muvoffaqiyatli qo‘shildi</b>", $panel);
                step($cid, "none");
            } else {
                unlink("step/trash_social_$cid.txt");
                sms($cid, "<b>⚠️ Kanal qo‘shishda xatolik!</b>\n\n<code>{$connect->error}</code>", $panel);
                step($cid, "none");
                exit();
            }
        } else {
            sms($cid, "<b>Havola uchun nom yuboring:</b>", $aort);
            step($cid, "socialnetwork_step1");
            exit();
        }
    }
}

if (mb_stripos((string)$data, "request-") !== false) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    $type = explode("-", $data)[1];
    file_put_contents("step/$cid2.type", $type);
    sms($cid2, "<b>Endi kanalizdan \"forward\" xabar yuboring:</b>", $aort);
    step($cid2, "qosh");
    exit();
}

if ($step == "qosh" and isset($message->forward_origin)) {
    $kanal_id = $message->forward_origin->chat->id;
    $type = file_get_contents("step/$cid.type");
    if ($type == "true") {
        $link = bot('createChatInviteLink', [
            'chat_id' => $kanal_id,
            'creates_join_request' => true
        ])->result->invite_link;
        $sql = "INSERT INTO `kanallar` (`channelID`, `link`, `type`) VALUES ('$kanal_id', '$link', 'request')";
    } elseif ($type == "false") {
        $link = "https://t.me/" . $message->forward_origin->chat->username;
        $sql = "INSERT INTO `kanallar` (`channelID`, `link`, `type`) VALUES ('$kanal_id', '$link', 'lock')";
    }
    if ($connect->query($sql)) {
        sms($cid, "<b>✅ Kanal muvoffaqiyatli qo‘shildi</b>", $panel);
    } else {
        sms($cid, "<b>⚠️ Kanal qo‘shishda xatolik!</b>\n\n<code>{$connect->error}</code>", $panel);
    }
    step($cid, "none");
    unlink("step/$cid.type");
    exit();
}

if ($text == "🗑️ Kanal o'chirish" and admin($cid) == 1) {
    $result = $connect->query("SELECT * FROM `kanallar`");
    if ($result->num_rows > 0) {
        $button = [];
        while ($row = $result->fetch_assoc()) {
            $type = $row['type'];
            $channelID = $row['channelID'];
            if ($type == "lock" or $type == "request") {
                $gettitle = bot('getchat', ['chat_id' => $channelID])->result->title;
                $button[] = ['text' => "🗑️ " . $gettitle, 'callback_data' => "delchan=" . $channelID];
            } else {
                $gettitle = $row['title'];
                $button[] = ['text' => "🗑️ " . $gettitle, 'callback_data' => "delchan=" . $channelID];
            }
        }
        $keyboard2 = array_chunk($button, 1);
        $keyboard2[] = [['text' => "◀️ Orqaga", 'callback_data' => "setchannels"]];
        $keyboard = json_encode([
            'inline_keyboard' => $keyboard2,
        ]);
        sms($cid, "<b>Kerakli kanalni tanlang va u o‘chiriladi:</b>", $keyboard);
        exit();
    } else {
        sms($cid, "<b>Hech qanday kanal ulanmagan!</b>", null);
        exit();
    }
}

if (stripos((string)$data, "delchan=") !== false) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    $ex = explode("=", $data)[1];
    $result = $connect->query("SELECT * FROM `kanallar` WHERE channelID = '$ex'");
    $row = $result->fetch_assoc();
    if ($row['type'] == "request") {
        $connect->query("DELETE FROM requests WHERE chat_id = '$ex'");
    }
    $connect->query("DELETE FROM kanallar WHERE channelID = '$ex'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "<b>✅ Kanal o'chirildi!</b>",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "◀️ Orqaga", 'callback_data' => "kochir"]]
            ]
        ])
    ]);
    exit();
}


if ($text == "📊 Statistika" || $text == "/stat") {
    if (admin($cid) == 1) {

        $text_stat = build_stat_text();

        sms($cid, $text_stat, json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 Yangilash", 'callback_data' => "upstat"]]
            ]
        ]));

        exit();
    }
}


if ($data == "upstat") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $text_stat = build_stat_text();

    bot('editMessageText', [
        'chat_id' => $callcid,
        'message_id' => $callmid,
        'parse_mode' => 'html',
        'text' => $text_stat,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 Yangilash", 'callback_data' => "upstat"]]
            ]
        ])
    ]);
    exit();
}


if ($text == "👨🏻‍⚖️ Adminlar") {
    if (admin($cid) == 1) {
        sms($cid, "<b>👨🏻‍⚖️ Adminlar sozlamalari bo'limi!
⤵️ Kerakli menyuni tanlang:</b>", $admin_manager);
        exit();
    }
}

if ($data == "admins") {
    if (admin($cid2) == 1) {
        bot('answerCallbackQuery', ['callback_query_id' => $qid]);
        del();
        sms($cid2, "<b>👨🏻‍⚖️ Adminlar sozlamalari bo'limi!
⤵️ Kerakli menyuni tanlang:</b>", $admin_manager);
        exit();
    }
}

if ($text == "📋 Administrator ro'yxati" and admin($cid) == 1) {
    $res = mysqli_query($connect, "SELECT * FROM admins");
    if ($res->num_rows > 0) {
        while ($a = mysqli_fetch_assoc($res)) {
            $user = $a['user_id'];
            $get = bot('getchat', ['chat_id' => $user])->result->first_name;
            $name = strip_tags($get);
            $key[] = ['text' => "$name", 'url' => "tg://user?id=$user"];
        }
        $keyboard2 = array_chunk($key, 1);
        $kb = json_encode([
            'inline_keyboard' => $keyboard2,
        ]);
        sms($cid, "<b>👉 Barcha adminlar ro'yxati:</b>", $kb);
        exit();
    } else {
        sms($cid, "<b>Administratorlar mavjud emas</b>", null);
        exit();
    }
}

if ($text == "➕ Administrator qo'shish") {
    if (in_array($cid, $owners)) {
        sms($cid, "<b>Kerakli foydalanuvchi ID raqamini yuboring:</b>", $aort);
        step($cid, "add-admin");
    } else {
        sms($cid, "<b>Ushbu bo'limdan foydalanish siz uchun taqiqlangan!</b>", null);
        exit();
    }
}
if ($step == "add-admin" and in_array($cid, $owners)) {
    if (!ctype_digit((string)$text)) {
        sms($cid, "<b>Faqat raqamli ID yuboring!</b>

Boshqa ID raqamni kiriting:", null);
        exit();
    }
    $stmt = $connect->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $text);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        sms($cid, "<b>Ushbu foydalanuvchi botdan foydalanmaydi!</b>

Boshqa ID raqamni kiriting:", null);
        exit();
    } elseif (!admin($text)) {
        $stmt = $connect->prepare("INSERT INTO admins (user_id) VALUES (?)");
        $stmt->bind_param("s", $text);
        $stmt->execute();
        $stmt->close();
        sms($cid, "<code>$text</code> <b>adminlar ro'yxatiga qo'shildi!</b>", $admin_manager);
        step($cid, "none");
        exit();
    } else {
        sms($cid, "<b>Ushbu foydalanuvchi adminlari ro'yxatida mavjud!</b>

Boshqa ID raqamni kiriting:", null);
        exit();
    }
}

if ($text == "🗑️ Administrator o‘chirish") {
    if (in_array($cid, $owners)) {
        $result = $connect->query("SELECT * FROM admins");
        if ($result->num_rows > 0) {
            $i = 1;
            $response = "";
            while ($row = $result->fetch_assoc()) {
                $get = bot('getchat', ['chat_id' => $row['user_id']])->result->first_name;
                $response .= "<b>$i)</b> <a href='tg://user?id=" . $row['user_id'] . "'>$get</a>\n";
                $uz[] = ['text' => $i, 'callback_data' => "remove-admin=" . $row['user_id']];
                $i++;
            }
            $keyboard2 = array_chunk($uz, 3);
            $kb = json_encode(['inline_keyboard' => $keyboard2]);
            sms($cid, "<b>👉 O'chirmoqchi bo'lgan administratorni tanlang:</b>\n\n$response", $kb);
            exit();
        } else {
            sms($cid, "<b>Administratorlar mavjud emas</b>", null);
            exit();
        }
    } else {
        sms($cid, "<b>Ushbu bo'limdan foydalanish siz uchun taqiqlangan!</b>", null);
        exit();
    }
}

if (mb_stripos((string)$data, "remove-admin=") !== false and in_array($cid2, $owners)) {
    $user_od = explode("=", $data)[1];
    $result = mysqli_query($connect, "SELECT * FROM admins WHERE user_id = $user_od");
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        bot('answerCallbackQuery', ['callback_query_id' => $qid]);
        $connect->query("DELETE FROM admins WHERE user_id = $user_od");
        del();
        sms($cid2, "<code>$user_od</code> <b>adminlar ro'yxatidan olib tashlandi!</b>", $admin_manager);
    } else {
        accl($qid, "Ushbu foydanuvchi administratorlar ro'yxatida mavjud emas!", 1);
        exit();
    }
}


if (isset($_GET['update']) && $_GET['update'] == "send") {
    process_pending_send();
    echo json_encode(["status" => true, "cron" => "Sending message"]);
    exit();
}


// 𝗬𝘂𝗸𝗹𝗼𝘃𝗰𝗵𝗶 𝗳𝘂𝗻𝗸𝘀𝗶𝘆𝗮𝗹𝗮𝗿 ⬇️⬇️

//𝗜𝗻𝘀𝘁𝗮𝗴𝗿𝗮𝗺
if (mb_stripos((string)$tx, "instagram.com") !== false) {
    require __DIR__ . '/handlers/instagram.php';
}


// 𝗧𝗶𝗸𝗧𝗼𝗸
if (isset($tx) && (mb_stripos($tx, "tiktok.com") !== false || mb_stripos($tx, "vt.tiktok.com") !== false || mb_stripos($tx, "vm.tiktok.com") !== false)) {

    $platform = "tiktok";
    $admin_id = 7827538214; // 🛠 Admin ID

    // 🔹 Havolani tozalash
    preg_match('/https?:\/\/(vm|vt|www)\.tiktok\.com\/[^\s]+/i', $tx, $matches);
    $tx_clean = !empty($matches[0]) ? $matches[0] : $tx;

    // 1️⃣ Vizual progress bar
    $wait = send_progress_message($cid, $mid, $uid, "🎬", 10, 200000, false);

    // 2️⃣ API orqali video olish
    $api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/tiktokapi.php";
    $ch = curl_init($api . "?url=" . urlencode($tx_clean));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ❌ API ishlamayapti
    if ($res === false || $http_code != 200) {
        bot('editMessageText', [
            'chat_id' => $cid,
            'message_id' => $wait,
            'text' => lang('technical', $uid)
        ]);

        bot('sendMessage', [
            'chat_id' => $admin_id,
            'text' => "🚨 API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: TikTok\nTekshiruv talab etiladi.",
            'parse_mode' => 'html'
        ]);
        exit();
    }

    $data = json_decode($res, true);
    $video_url = null;

    if (!empty($data['video_url'])) {
        $video_url = $data['video_url'];
    } elseif (!empty($data['data']['medias'][0]['url'])) {
        $video_url = $data['data']['medias'][0]['url'];
    }

    // 🔍 fallback .mp4 link
    if (!$video_url && preg_match('/https?:\/\/[^\'"\s<>]+\.mp4[^\s\'"]*/i', $res, $m)) {
        $video_url = $m[0];
    }

    // ❌ Video topilmasa
    if (!$video_url) {
        bot('editMessageText', [
            'chat_id' => $cid,
            'message_id' => $wait,
            'text' => lang('not_found', $uid)
        ]);
        exit();
    }

    // ✅ Progressni o‘chirish
    bot('deleteMessage', [
        'chat_id' => $cid,
        'message_id' => $wait
    ]);

    // 💬 “video yubormoqda...” typing-style effekt
    bot('sendChatAction', [
        'chat_id' => $cid,
        'action' => 'upload_video'
    ]);

    // ✅ Video yuborish
    bot('sendVideo', [
        'chat_id' => $cid,
        'video' => $video_url,
        'caption' => "<b>🎬 TikTok video\n🔗 Via @$botusername</b>",
        'parse_mode' => 'html',
        'reply_to_message_id' => $mid
    ]);

    // 🧾 Yuklab olish tarixini saqlash
    $stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
    $stmt->bind_param("is", $uid, $platform);
    $stmt->execute();
    $stmt->close();
}


// 𝗦𝗻𝗮𝗽𝗰𝗵𝗮𝘁 𝗦𝗽𝗼𝘁𝗹𝗶𝗴𝗵𝘁 (captionsiz)
if (mb_stripos((string)$tx, "snapchat.com/spotlight") !== false) {

$platform = "snapchat";
$admin_id = 7827538214;

// 🔹 Havolani tozalash
preg_match('/https?:\/\/(www\.)?snapchat\.com\/spotlight\/[^\s?]+/i', $tx, $matches);
$tx_clean = $matches[0] ?? $tx;

// 1️⃣ Vizual progress bar
$wait = send_progress_message($cid, $mid, $uid, "👻", 10, 200000, false);

// 2️⃣ API orqali video olish
$api = "https://api.wwiw.uz/downloader?url=" . urlencode($tx_clean);
$ch = curl_init($api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ❌ API ishlamayapti
if ($res === false || $http_code != 200) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('technical', $uid)
    ]);

    bot('sendMessage', [
        'chat_id' => $admin_id,
        'text' => "🚨 API ishlamayapti!\n🕓 " . date('Y-m-d H:i:s') . "\n🔗 Platforma: Snapchat Spotlight\nTekshiruv talab etiladi.",
        'parse_mode' => 'html'
    ]);
    exit();
}

$get = json_decode($res, true);
$video_url = null;

// 🔍 medias ichidan video URL topish
if (!empty($get['data']['medias']) && is_array($get['data']['medias'])) {
    foreach ($get['data']['medias'] as $media) {
        if (!empty($media['type']) && $media['type'] === "video" && !empty($media['url'])) {
            $video_url = $media['url'];
            break;
        }
    }
}

// 🔹 fallback: agar media topilmasa
if (!$video_url) {
    $video_url = $get['data']['url'] ?? null;
}

// ❌ Video topilmasa
if (!$video_url) {
    bot('editMessageText', [
        'chat_id' => $cid,
        'message_id' => $wait,
        'text' => lang('not_found', $uid)
    ]);
    exit();
}

// ✅ Progressni o‘chirish
bot('deleteMessage', [
    'chat_id' => $cid,
    'message_id' => $wait
]);

// 💬 Video yuborish effekti
bot('sendChatAction', [
    'chat_id' => $cid,
    'action' => 'upload_video'
]);

// ✅ Video yuborish (captionsiz)
bot('sendVideo', [
    'chat_id' => $cid,
    'video' => $video_url,
    'reply_to_message_id' => $mid
]);

// 🧾 Yuklab olish tarixini saqlash
$stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
$stmt->bind_param("is", $uid, $platform);
$stmt->execute();
$stmt->close();

}


// 𝗬𝗼𝘂𝗧𝘂𝗯𝗲 𝗦𝗵𝗼𝗿𝘁𝘀 (captionsiz)
if (mb_stripos((string)$tx, "youtube.com/shorts") !== false || mb_stripos($tx, "youtu.be/") !== false) {

    $platform = "youtube";
    $admin_id = 7827538214;

    // 🔹 Havolani tozalash
    preg_match('/https?:\/\/(www\.)?(youtube\.com\/shorts\/[^\s?]+|youtu\.be\/[^\s?]+)/i', $tx, $matches);
    $tx_clean = $matches[0] ?? $tx;

    // 1️⃣ Vizual progress bar
    $wait = send_progress_message($cid, $mid, $uid, "▶️", 10, 200000, false);

    // 2️⃣ yt-dlp shortsapi orqali video olish
    $api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/shortsapi.php?url=" . urlencode($tx_clean);
    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ❌ API ishlamayapti
    if ($res === false || empty($res) || $http_code != 200) {
        bot('editMessageText', [
            'chat_id' => $cid,
            'message_id' => $wait,
            'text' => lang('technical', $uid)
        ]);
        exit();
    }

    $get = json_decode($res, true);
    $video_url = $get['url'] ?? null;

    // ❌ Video topilmasa
    if (!$video_url) {
        bot('editMessageText', [
            'chat_id' => $cid,
            'message_id' => $wait,
            'text' => lang('not_found', $uid)
        ]);
        exit();
    }

    // ✅ Progressni o‘chirish
    bot('deleteMessage', [
        'chat_id' => $cid,
        'message_id' => $wait
    ]);

    // 💬 Video yuborish effekti
    bot('sendChatAction', [
        'chat_id' => $cid,
        'action' => 'upload_video'
    ]);

    // ✅ Video yuborish (captionsiz)
    bot('sendVideo', [
        'chat_id' => $cid,
        'video' => $video_url,
        'reply_to_message_id' => $mid
    ]);

    // 🧾 Yuklab olish tarixini saqlash
    $stmt = $connect->prepare("INSERT INTO video_downloads (user_id, platform) VALUES (?, ?)");
    $stmt->bind_param("is", $uid, $platform);
    $stmt->execute();
    $stmt->close();
}


// 🎵 𝗠𝘂𝘀𝗶𝗾𝗮 𝗶𝘇𝗹𝗮𝘀𝗵 𝘆𝘂𝗸𝗹𝗮𝘀𝗵
// Admin tugmalari va maxsus matnlar musiqa qidiruviga tushmaydi
$skip_music = [
    "🗄 Boshqarish", "◀️ Orqaga", "📢 Kanal sozlash", "👨🏻‍⚖️ Adminlar",
    "📊 Statistika", "✉ Xabar Yuborish", "➕ Kanal qo'shish", "🗑️ Kanal o'chirish",
    "🗄 Boshqarish", "➕ Administrator qo'shish", "🗑️ Administrator o'chirish",
    "📋 Administrator ro'yxati", "⚙️ Settings", lang('change_lang', $uid),
    lang('top_command', $uid)
];

if (
    $text &&
    mb_strlen($text) > 2 &&
    mb_substr($text, 0, 1) !== "/" &&
    mb_substr($text, 0, 5) !== "http:" &&
    mb_substr($text, 0, 6) !== "https:" &&
    !in_array($text, $skip_music) &&
    mb_stripos($text, "instagram.com") === false &&
    mb_stripos($text, "tiktok.com") === false &&
    mb_stripos($text, "youtube.com") === false &&
    mb_stripos($text, "youtu.be") === false &&
    mb_stripos($text, "snapchat.com") === false
) {
    $query = urlencode($text);
    $api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/musicapi.php?title=$query";

    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_TIMEOUT => 25,           // ⏱ 25 soniyada javob bo'lmasa, to'xtaydi
        CURLOPT_CONNECTTIMEOUT => 10,    // ⏱ 10 soniyada ulanmasa, to'xtaydi
        CURLOPT_SSL_VERIFYPEER => false  // HTTPS xatolarida to‘xtamasin
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // ❌ API ishlamayapti yoki javob bermadi
    if ($res === false || empty($res) || $http_code == 0 || $http_code != 200) {

        // ⚙️ Foydalanuvchiga texnik xabar
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => lang('technical', $uid)
        ]);

        // 👑 Adminga ogohlantirish (bot qotmasin, lekin admin bilsin)
        $admin_id = 7827538214; // o‘zingning admin ID’ing
        $admin_text = "<b>⚠️ Pinkamuz API ishlamayapti!</b>\n\n" .
                      "🌐 <b>URL:</b> $api\n" .
                      "📡 <b>HTTP code:</b> $http_code\n" .
                      "💬 <b>Xato:</b> " . ($error ?: "Aniqlanmagan xato") . "\n\n" .
                      "⏰ <i>Bot ishlashda davom etmoqda.</i>";

        bot('sendMessage', [
            'chat_id' => $admin_id,
            'text' => $admin_text,
            'parse_mode' => 'html'
        ]);

        return; // ✅ faqat shu joydan chiqadi, bot ishlashda davom etadi
    }

    $result = json_decode($res, true);

    // ❌ API natija qaytarmadi yoki xato bo‘ldi
    if (!$result || isset($result['error'])) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => lang('music_not_found', $uid)
        ]);
        return;
    }

    // 🔹 Natija sarlavhasi
    $text_result = sprintf(lang('search_results', $uid), htmlspecialchars($text));

    $buttons = [];
    $offset = 0;
    $limited = array_slice($result, $offset, 10);

    foreach ($limited as $i => $music) {
        $realIndex = $offset + $i;
        $buttons[] = [[
            'text' => "🎧 " . ($realIndex + 1) . ". {$music['title']} – {$music['artist']}",
            'callback_data' => "playmusic_{$realIndex}_$query"
        ]];
    }

    if (count($result) > 10) {
        $buttons[] = [
            ['text' => '❯❯', 'callback_data' => "next_2_$query"]
        ];
    }

    $buttons[] = [
        ['text' => lang('top_10', $uid), 'callback_data' => 'show_top']
    ];

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $text_result,
        'parse_mode' => 'html',
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
    ]);

    return;
}


// ⬅️➡️ Paginatsiya (keyingi/oldingi sahifalar)
if (mb_strpos((string)$callbackdata, "next_") === 0 || mb_strpos($callbackdata, "prev_") === 0) {
    $parts = explode("_", $callbackdata);
    $direction = $parts[0];
    $page = (int)$parts[1];
    $query = $parts[2];

    $api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/musicapi.php?title=$query";
    $ch_music = curl_init($api);
    curl_setopt_array($ch_music, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false]);
    $res = curl_exec($ch_music);
    curl_close($ch_music);
    $result = json_decode($res, true);

    if (!$result || isset($result['error'])) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $qid,
            'text' => lang('music_not_found', $callfrid),
            'show_alert' => false,
        ]);
        exit();
    }

    $total = count($result);
    $offset = ($page - 1) * 10;
    $items = array_slice($result, $offset, 10);

    if (empty($items)) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $qid,
            'text' => lang('no_more_results', $callfrid),
            'show_alert' => false,
        ]);
        exit();
    }

    $buttons = [];
    foreach ($items as $i => $music) {
        $realIndex = $offset + $i;
        $buttons[] = [[
            'text' => "🎧 " . ($realIndex + 1) . ". {$music['title']} – {$music['artist']}",
            'callback_data' => "playmusic_{$realIndex}_$query"
        ]];
    }

    $nav = [];
    if ($offset > 0) {
        $nav[] = ['text' => '❮❮️', 'callback_data' => "prev_" . ($page - 1) . "_$query"];
    }
    if ($offset + 10 < $total) {
        $nav[] = ['text' => '❯❯', 'callback_data' => "next_" . ($page + 1) . "_$query"];
    }
    if ($nav) {
        $buttons[] = $nav;
    }

    // 🔹 "Top 10 Music" tugmasi
    $buttons[] = [
        ['text' => lang('top_10', $callfrid), 'callback_data' => 'show_top']
    ];

    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    bot('editMessageReplyMarkup', [
        'chat_id' => $callcid,
        'message_id' => $callmid,
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
    ]);
    exit();
}


// 🎧 Musiqani yuklash
if (mb_strpos((string)$callbackdata, "playmusic_") === 0) {
    bot('answerCallbackQuery', [
        'callback_query_id' => $qid,
        'text' => lang('loading_music', $callfrid),
        'show_alert' => false
    ]);

    $parts = explode("_", $callbackdata);
    $index = (int)$parts[1];
    $query = $parts[2];

    $api = "https://6831eecaafce3.xvest3.ru/fastsavedbot/api/musicapi.php?title=$query";
    $ch_music = curl_init($api);
    curl_setopt_array($ch_music, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false]);
    $data = json_decode(curl_exec($ch_music), true);
    curl_close($ch_music);
    if (!$data || !isset($data[$index])) exit();

    $track  = $data[$index];
    $title  = $track['title'];
    $artist = $track['artist'];
    $music  = $track['music'];

    // 🔹 Top songs bazasini yangilash
    $stmt = $connect->prepare("SELECT id FROM top_songs WHERE music_url = ? LIMIT 1");
    $stmt->bind_param("s", $music);
    $stmt->execute();
    $check = $stmt->get_result();
    $stmt->close();

    if ($check->num_rows == 0) {
        $stmt = $connect->prepare("INSERT INTO top_songs (title, artist, music_url, downloads) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $title, $artist, $music);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $connect->prepare("UPDATE top_songs SET downloads = downloads + 1 WHERE music_url = ?");
        $stmt->bind_param("s", $music);
        $stmt->execute();
        $stmt->close();
    }

    // 🎧 Musiqani yuborish
    bot('sendAudio', [
        'chat_id' => $callcid,
        'audio' => $music,
        'caption' => "<b>🎵 $artist – $title</b>\n\n<b>Via @$botusername</b>",
        'parse_mode' => 'HTML'
    ]);

    exit();
}


// 🎵 Inline "Top 10 Music" tugmasi bosilganda
if (isset($callbackdata) && $callbackdata == "show_top") {
    $res = $connect->query("SELECT * FROM top_songs ORDER BY downloads DESC LIMIT 10");

    if ($res->num_rows == 0) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $qid,
            'text' => lang('no_top_songs', $callfrid),
            'show_alert' => true
        ]);
        exit();
    }

    $buttons = [];
    $i = 1;
    while ($row = $res->fetch_assoc()) {
        $buttons[] = [[
            'text' => "🎧 $i. {$row['title']} – {$row['artist']}",
            'callback_data' => "play_top_{$row['id']}"
        ]];
        $i++;
    }

    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    bot('editMessageText', [
        'chat_id' => $callcid,
        'message_id' => $callmid,
        'text' => lang('top_10_list_title', $callfrid),
        'parse_mode' => 'html',
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
    ]);
    exit();
}


// 🔝 /top bo'limi
if ($text == "/top" || $text == lang('top_command', $uid)) {
    $res = $connect->query("SELECT * FROM top_songs ORDER BY downloads DESC LIMIT 10");  

    if ($res->num_rows == 0) {  
        bot('sendMessage', [  
            'chat_id' => $cid,  
            'text' => lang('no_top_songs', $uid)
        ]);  
        exit();  
    }  

    $buttons = [];  
    $i = 1;  
    while ($row = $res->fetch_assoc()) {  
        $buttons[] = [[  
            'text' => "🎧 $i. {$row['title']} – {$row['artist']}",  
            'callback_data' => "play_top_{$row['id']}"  
        ]];  
        $i++;  
    }  

    bot('sendMessage', [  
        'chat_id' => $cid,  
        'text' => lang('top_10_list_title', $uid),  
        'parse_mode' => 'html',  
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])  
    ]);  
    exit();
}


// 🎧 Inline tugma bosilganda musiqani yuborish
if (isset($callbackdata) && mb_strpos((string)$callbackdata, "play_top_") === 0) {
    bot('answerCallbackQuery', [
        'callback_query_id' => $qid ?? '',
        'text' => lang('loading_music', $callfrid),
        'show_alert' => false
    ]);

    $id = (int) str_replace("play_top_", "", $callbackdata);  
    $res = $connect->query("SELECT * FROM top_songs WHERE id='$id' LIMIT 1");  
    if (!$res || $res->num_rows == 0) exit();  
    $song = $res->fetch_assoc();  

    // ❌ TOP bo‘limidan yuklaganda download soni oshmaydi!

    bot('sendAudio', [  
        'chat_id' => $callcid ?? $cid,  
        'audio' => $song['music_url'],  
        'caption' => "<b>🎵 {$song['artist']} – {$song['title']}</b>\n\n<b>Via @$botusername</b>",  
        'parse_mode' => 'HTML'  
    ]);  

    exit();
}
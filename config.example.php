<?php
// =============================================
// FastSaved Bot - Konfiguratsiya SHABLONI
// =============================================
// 1. Ushbu faylni serverda "config.php" nomi bilan saqlang
//    (config.php .gitignore qilingan, git orqali serverga
//    yuborilmaydi va yangilanmaydi — shu sababli bu shablon kerak).
// 2. Pastdagi barcha joy-belgilarni haqiqiy qiymatlar bilan almashtiring.
// 3. config.php faylini hech qachon git'ga qo'shmang / commit qilmang.
// =============================================

// Bot sozlamalari
$botttt = "BOT_TOKENINI_BU_YERGA_YOZING"; // @BotFather dan olingan token
define('API_KEY', $botttt);

$admin  = "ADMIN_TELEGRAM_ID"; // Sizning Telegram ID raqamingiz (masalan: 123456789)
$owners = [$admin];

// 🔒 Webhook xavfsizligi (ixtiyoriy, lekin qat'iy tavsiya etiladi):
// istalgan uzun-tasodifiy matn kiriting. setup.php shuni Telegram'ga yuboradi,
// bot.php esa har bir kiruvchi so'rovda shu tokenni tekshiradi — begona odam
// webhook manzilini bilsa ham, soxta update yubora olmaydi.
define('WEBHOOK_SECRET', 'UZUN_TASODIFIY_MAXFIY_MATN_KIRITING');

// Database ulanish
$servername = "localhost";
$username   = "DB_USERNAME_KIRITING";
$password   = "DB_PAROL_KIRITING";
$dbname     = "DB_NOMI_KIRITING";

mysqli_report(MYSQLI_REPORT_OFF); // ulanish xatosida exception emas, connect_error orqali tekshirish uchun
$connect = new mysqli($servername, $username, $password, $dbname);

if ($connect->connect_error) {
    // Xatoni log ga yozib, botni to'xtatmaslik
    error_log("DB xato: " . $connect->connect_error);
    http_response_code(200);
    exit;
}

$connect->set_charset("utf8mb4");

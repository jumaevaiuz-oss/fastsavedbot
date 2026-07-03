<?php
// =============================================
// FastSaved Bot - Konfiguratsiya (token, admin, DB)
// =============================================

// Bot sozlamalari
$botttt = "bot_tokenini_yoz"; // BotFather dan tokenni shu yerga kiriting
define('API_KEY', $botttt);

$admin  = "12345678"; // Admin Telegram ID
$owners = [$admin];

// Database ulanish
$servername = "localhost";
$username   = "********";   // DB username kiriting
$password   = "*********";   // DB parol kiriting
$dbname     = "*********";       // DB nomi kiriting

$connect = new mysqli($servername, $username, $password, $dbname);

if ($connect->connect_error) {
    // Xatoni log ga yozib, botni to'xtatmaslik
    error_log("DB xato: " . $connect->connect_error);
    http_response_code(200);
    exit;
}

$connect->set_charset("utf8mb4");

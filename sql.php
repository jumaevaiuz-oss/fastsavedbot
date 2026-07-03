<?php
// =============================================
// FastSaved Bot - Database ulanish
// =============================================

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

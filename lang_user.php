<?php
function get_user_lang($user_id) {
    $file = "step/lang_$user_id.txt";
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return 'uz'; // default til
}

function set_user_lang($user_id, $lang) {
    file_put_contents("step/lang_$user_id.txt", $lang);
}
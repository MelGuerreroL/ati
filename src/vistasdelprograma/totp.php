<?php
// totp.php - funciones para TOTP (Google Authenticator)
function base32_decode_custom($b32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(str_replace('=', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) return false;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) == 8) $bytes .= chr(bindec($byte));
    }
    return $bytes;
}

function generate_totp_secret($length = 16) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
}

function get_totp_code($secret, $timeSlice = null) {
    if ($timeSlice === null) $timeSlice = floor(time() / 30);
    $secretKey = base32_decode_custom($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = (ord($hash[$offset]) & 0x7f) << 24 |
              (ord($hash[$offset + 1]) & 0xff) << 16 |
              (ord($hash[$offset + 2]) & 0xff) << 8 |
              (ord($hash[$offset + 3]) & 0xff);
    $otp = $binary % 1000000;
    return str_pad($otp, 6, '0', STR_PAD_LEFT);
}

function verify_totp($secret, $code, $window = 1) {
    $timeSlice = floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(get_totp_code($secret, $timeSlice + $i), $code)) return true;
    }
    return false;
}
?>

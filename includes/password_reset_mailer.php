<?php
/**
 * password_reset_mailer.php - CourseFlow
 * Optimized for Port 465 (SSL) to prevent timeouts
 */

define('RESET_HOST', 'ssl://smtp.hostinger.com');
define('RESET_PORT', 465);
define('RESET_USER', 'password_reset1@yourdomain.com');
define('RESET_PASS', 'your_db_password_here');

function vs_send_reset_mail($to, $toName, $subject, $htmlBody) {
    $timeout = 10;
    $socket = fsockopen(RESET_HOST, RESET_PORT, $errno, $errstr, $timeout);

    if (!$socket) {
        return false;
    }

    function smtp_chat($socket, $msg, $expected) {
        if($msg) fwrite($socket, $msg . "\r\n");
        $res = "";
        while ($line = fgets($socket, 515)) {
            $res .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        return (strpos($res, (string)$expected) !== false);
    }

    smtp_chat($socket, null, 220); // Banner
    smtp_chat($socket, "EHLO " . $_SERVER['HTTP_HOST'], 250);
    smtp_chat($socket, "AUTH LOGIN", 334);
    smtp_chat($socket, base64_encode(RESET_USER), 334);
    smtp_chat($socket, base64_encode(RESET_PASS), 235);
    smtp_chat($socket, "MAIL FROM: <" . RESET_USER . ">", 250);
    smtp_chat($socket, "RCPT TO: <$to>", 250);
    smtp_chat($socket, "DATA", 354);

    $headers = "MIME-Version: 1.0\r\n" .
               "Content-Type: text/html; charset=UTF-8\r\n" .
               "From: CourseFlow Security <" . RESET_USER . ">\r\n" .
               "To: $toName <$to>\r\n" .
               "Subject: $subject\r\n\r\n";

    smtp_chat($socket, $headers . $htmlBody . "\r\n.", 250);
    smtp_chat($socket, "QUIT", 221);
    fclose($socket);
    return true;
}
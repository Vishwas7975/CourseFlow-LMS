<?php
/**
 * mailer.php — Self-contained SMTP mailer (NO Composer, NO PHPMailer needed)
 * Upload to: public_html/includes/mailer.php
 *
 * ONLY THING TO CHANGE: Set your SMTP password on line below.
 * Get it from: Hostinger hPanel → Emails → noreply@yourdomain.com → SMTP Settings
 */

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURATION — loaded from config/secrets.php (no credentials stored here)
// In production, move secrets.php ABOVE public_html and update the path below.
// NEVER commit secrets.php to Git — add it to .gitignore
// ─────────────────────────────────────────────────────────────────────────────
$_secrets_path = dirname(__DIR__) . '/config/secrets.php';
if (file_exists($_secrets_path)) {
    require_once $_secrets_path;
} else {
    error_log('CourseFlow Mailer: secrets.php not found at ' . $_secrets_path);
}
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Send an HTML email via Hostinger SMTP — no external libraries.
 *
 * @param string $to       Recipient email
 * @param string $toName   Recipient display name
 * @param string $subject  Email subject
 * @param string $htmlBody Full HTML body
 * @param string $replyTo  Optional reply-to address
 * @return bool  true = sent, false = failed (check PHP error log)
 */
function vs_send_mail(
    string $to,
    string $toName,
    string $subject,
    string $htmlBody,
    string $replyTo = 'support@yourdomain.com'
): bool {
    $host     = VS_SMTP_HOST;
    $port     = VS_SMTP_PORT;
    $user     = VS_SMTP_USER;
    $pass     = VS_SMTP_PASS;
    $from     = VS_SMTP_FROM;
    $fromName = VS_SMTP_FROM_NAME;

    // ── helpers ─────────────────────────────────────────────────────────────
    $read = function($conn) {
        $data = '';
        while ($line = fgets($conn, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;   // last line of response
        }
        return $data;
    };

    $send = function($conn, $cmd) use ($read) {
        fputs($conn, $cmd . "\r\n");
        return $read($conn);
    };

    $code = function($resp) {
        return (int)substr(trim($resp), 0, 3);
    };

    // ── open plain TCP connection to port 587 ────────────────────────────────
    $errno = 0; $errstr = '';
    $conn = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$conn) {
        error_log("CourseFlow Mailer: Cannot connect to {$host}:{$port} — {$errstr}");
        return false;
    }
    stream_set_timeout($conn, 15);

    $read($conn);                                      // 220 greeting

    // ── EHLO ────────────────────────────────────────────────────────────────
    $resp = $send($conn, "EHLO " . gethostname());
    if ($code($resp) !== 250) {
        error_log("CourseFlow Mailer: EHLO failed: {$resp}");
        fclose($conn); return false;
    }

    // ── STARTTLS ─────────────────────────────────────────────────────────────
    $resp = $send($conn, "STARTTLS");
    if ($code($resp) !== 220) {
        error_log("CourseFlow Mailer: STARTTLS failed: {$resp}");
        fclose($conn); return false;
    }

    // Upgrade to TLS
    if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log("CourseFlow Mailer: TLS upgrade failed");
        fclose($conn); return false;
    }

    // ── EHLO again after TLS ─────────────────────────────────────────────────
    $send($conn, "EHLO " . gethostname());

    // ── AUTH LOGIN ───────────────────────────────────────────────────────────
    $resp = $send($conn, "AUTH LOGIN");
    if ($code($resp) !== 334) {
        error_log("CourseFlow Mailer: AUTH LOGIN failed: {$resp}");
        fclose($conn); return false;
    }

    $resp = $send($conn, base64_encode($user));
    if ($code($resp) !== 334) {
        error_log("CourseFlow Mailer: Username rejected: {$resp}");
        fclose($conn); return false;
    }

    $resp = $send($conn, base64_encode($pass));
    if ($code($resp) !== 235) {
        error_log("CourseFlow Mailer: Authentication failed. Check SMTP_PASS in mailer.php. Response: {$resp}");
        fclose($conn); return false;
    }

    // ── MAIL FROM ────────────────────────────────────────────────────────────
    $resp = $send($conn, "MAIL FROM:<{$from}>");
    if ($code($resp) !== 250) {
        error_log("CourseFlow Mailer: MAIL FROM failed: {$resp}");
        fclose($conn); return false;
    }

    // ── RCPT TO ──────────────────────────────────────────────────────────────
    $resp = $send($conn, "RCPT TO:<{$to}>");
    if ($code($resp) !== 250) {
        error_log("CourseFlow Mailer: RCPT TO failed for {$to}: {$resp}");
        fclose($conn); return false;
    }

    // ── DATA ─────────────────────────────────────────────────────────────────
    $resp = $send($conn, "DATA");
    if ($code($resp) !== 354) {
        error_log("CourseFlow Mailer: DATA failed: {$resp}");
        fclose($conn); return false;
    }

    // Build MIME message
    $boundary  = '----=_Part_' . md5(uniqid());
    $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</td>'], "\n", $htmlBody));
    $plainText = html_entity_decode(preg_replace('/\n{3,}/', "\n\n", $plainText), ENT_QUOTES, 'UTF-8');

    $encodedSubject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedToName   = '=?UTF-8?B?' . base64_encode($toName)   . '?=';

    $headers  = "From: {$encodedFromName} <{$from}>\r\n";
    $headers .= "To: {$encodedToName} <{$to}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: CourseFlow-NativeMailer/1.0\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plainText)) . "\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    fputs($conn, $headers . "\r\n" . $body . "\r\n.\r\n");
    $resp = $read($conn);
    if ($code($resp) !== 250) {
        error_log("CourseFlow Mailer: Message send failed: {$resp}");
        fclose($conn); return false;
    }

    $send($conn, "QUIT");
    fclose($conn);
    return true;
}
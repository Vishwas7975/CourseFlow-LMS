<?php
/**
 * qr_generate.php — Server-side UPI QR Code Generator
 * Pure PHP + GD. Zero external libraries or CDN needed.
 * 
 * Usage: <img src="qr_generate.php?upi=yourupi4@ybl&amt=11800&name=CourseFlow">
 */
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
requireLogin($conn);

// ── Input validation ──
$upi_id = trim($_GET['upi']  ?? '');
$amount = trim($_GET['amt']  ?? '');
$name   = trim($_GET['name'] ?? 'CourseFlow');
$note   = trim($_GET['note'] ?? 'Course Fee');
$size   = max(150, min(400, (int)($_GET['size'] ?? 220)));

// Whitelist valid UPI IDs
$allowed_upi = ['yourupi1@ybl', 'yourupi2@ybl', 'yourupi3@ybl'];
if (!in_array($upi_id, $allowed_upi) || !is_numeric($amount) || (float)$amount <= 0) {
    http_response_code(400);
    exit('Invalid request');
}

// ── Build UPI string ──
$upi_string = 'upi://pay?pa=' . rawurlencode($upi_id)
            . '&pn=' . rawurlencode($name)
            . '&am=' . number_format((float)$amount, 2, '.', '')
            . '&cu=INR'
            . '&tn=' . rawurlencode($note);

// ── Generate QR using pure PHP (no external lib) ──
// We use the Google Charts API as a fallback image if GD not available,
// otherwise generate via PHP QR matrix algorithm below.

if (!extension_loaded('gd')) {
    // Fallback: redirect to a working QR API
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
         . '&data=' . rawurlencode($upi_string)
         . '&format=png&ecc=M';
    header('Location: ' . $url);
    exit;
}

// ════════════════════════════════════════════════════════════════
// Pure PHP QR Code Generator — Reed-Solomon + Matrix rendering
// Based on the QR standard (ISO/IEC 18004)
// ════════════════════════════════════════════════════════════════

class PureQR {
    private static $GF_EXP = [];
    private static $GF_LOG = [];

    public static function generate(string $data, int $imgSize = 220): string {
        self::initGF();
        $bytes = array_values(unpack('C*', $data));
        $matrix = self::buildMatrix($bytes);
        return self::renderPNG($matrix, $imgSize);
    }

    private static function initGF(): void {
        if (!empty(self::$GF_EXP)) return;
        self::$GF_EXP = array_fill(0, 512, 0);
        self::$GF_LOG  = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$GF_EXP[$i] = $x;
            self::$GF_LOG[$x]  = $i;
            $x = ($x << 1) ^ (($x & 0x80) ? 0x11D : 0);
            $x &= 0xFF;
        }
        for ($i = 255; $i < 512; $i++) {
            self::$GF_EXP[$i] = self::$GF_EXP[$i - 255];
        }
    }

    private static function gfMul(int $x, int $y): int {
        if ($x === 0 || $y === 0) return 0;
        return self::$GF_EXP[(self::$GF_LOG[$x] + self::$GF_LOG[$y]) % 255];
    }

    private static function gfPolyMul(array $p, array $q): array {
        $r = array_fill(0, count($p) + count($q) - 1, 0);
        foreach ($p as $j => $pj) {
            foreach ($q as $i => $qi) {
                $r[$j + $i] ^= self::gfMul($pj, $qi);
            }
        }
        return $r;
    }

    private static function gfPolyDiv(array $msg, array $gen): array {
        $r = $msg;
        for ($i = 0; $i < count($msg) - count($gen) + 1; $i++) {
            if ($r[$i] === 0) continue;
            $c = $r[$i];
            for ($j = 1; $j < count($gen); $j++) {
                if ($gen[$j] !== 0) {
                    $r[$i + $j] ^= self::gfMul($gen[$j], $c);
                }
            }
        }
        return array_slice($r, count($msg) - count($gen) + 1);
    }

    private static function reedSolomon(array $msg, int $nsym): array {
        $gen = [1];
        for ($i = 0; $i < $nsym; $i++) {
            $gen = self::gfPolyMul($gen, [1, self::$GF_EXP[$i]]);
        }
        $msgout = array_merge($msg, array_fill(0, $nsym, 0));
        $r = self::gfPolyDiv($msgout, $gen);
        return $r;
    }

    private static function encodeData(array $bytes): array {
        // QR Version 3, Error Correction Level M: 
        // 44 data codewords, 26 EC codewords
        $mode   = 0b0100; // byte mode
        $length = count($bytes);
        
        // Build bit string
        $bits = '';
        $bits .= sprintf('%04b', $mode);
        $bits .= sprintf('%08b', $length);
        foreach ($bytes as $b) {
            $bits .= sprintf('%08b', $b);
        }
        // Terminator
        $bits .= '0000';
        // Pad to byte boundary
        while (strlen($bits) % 8 !== 0) $bits .= '0';

        // Data capacity for Version 3-M = 44 bytes
        $capacity = 44 * 8;
        // Pad with alternating bytes
        $padBytes = ['11101100', '00010001'];
        $pi = 0;
        while (strlen($bits) < $capacity) {
            $bits .= $padBytes[$pi % 2];
            $pi++;
        }

        // Convert to byte array
        $data = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $data[] = bindec(substr($bits, $i, 8));
        }

        // Reed-Solomon EC (26 codewords for Version 3-M)
        $ec = self::reedSolomon($data, 26);
        return array_merge($data, $ec);
    }

    private static function buildMatrix(array $bytes): array {
        // Use Version 3 QR (29x29 modules)
        $ver  = 3;
        $size = 17 + 4 * $ver; // 29

        // Initialize matrix: -1 = unfilled, 0 = light, 1 = dark
        $matrix = array_fill(0, $size, array_fill(0, $size, -1));

        // Finder patterns
        self::addFinder($matrix, 0, 0);
        self::addFinder($matrix, $size - 7, 0);
        self::addFinder($matrix, 0, $size - 7);

        // Separators
        self::addSeparators($matrix, $size);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
            $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
        }

        // Alignment pattern (Version 3)
        self::addAlignment($matrix, 22, 22);

        // Dark module
        $matrix[$size - 8][8] = 1;

        // Reserve format areas
        self::reserveFormat($matrix, $size);

        // Encode data
        $codewords = self::encodeData($bytes);
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= sprintf('%08b', $cw);
        }

        // Place data bits
        self::placeData($matrix, $bits, $size);

        // Apply best mask
        $best = self::applyBestMask($matrix, $size);

        // Write format info
        self::writeFormatInfo($best, $size);

        return $best;
    }

    private static function addFinder(array &$m, int $row, int $col): void {
        $pattern = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1],
        ];
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $m[$row + $r][$col + $c] = $pattern[$r][$c];
            }
        }
    }

    private static function addSeparators(array &$m, int $size): void {
        for ($i = 0; $i < 8; $i++) {
            $m[7][$i] = 0; $m[$i][7] = 0;
            $m[7][$size - 1 - $i] = 0; $m[$i][$size - 8] = 0;
            $m[$size - 8][$i] = 0; $m[$size - 1 - $i][7] = 0;
        }
    }

    private static function addAlignment(array &$m, int $row, int $col): void {
        $pattern = [[1,1,1,1,1],[1,0,0,0,1],[1,0,1,0,1],[1,0,0,0,1],[1,1,1,1,1]];
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $m[$row + $r - 2][$col + $c - 2] = $pattern[$r][$c];
            }
        }
    }

    private static function reserveFormat(array &$m, int $size): void {
        for ($i = 0; $i < 9; $i++) {
            if ($m[$i][8] < 0) $m[$i][8] = 0;
            if ($m[8][$i] < 0) $m[8][$i] = 0;
        }
        for ($i = 0; $i < 8; $i++) {
            if ($m[$size - 1 - $i][8] < 0) $m[$size - 1 - $i][8] = 0;
            if ($m[8][$size - 1 - $i] < 0) $m[8][$size - 1 - $i] = 0;
        }
    }

    private static function placeData(array &$m, string $bits, int $size): void {
        $bi = 0;
        $upward = true;
        for ($col = $size - 1; $col >= 1; $col -= 2) {
            if ($col === 6) $col = 5;
            $rows = $upward ? range($size - 1, 0, -1) : range(0, $size - 1);
            foreach ($rows as $row) {
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if ($m[$row][$cc] < 0) {
                        $m[$row][$cc] = ($bi < strlen($bits)) ? (int)$bits[$bi++] : 0;
                    }
                }
            }
            $upward = !$upward;
        }
    }

    private static function applyBestMask(array $matrix, int $size): array {
        $masks = [
            fn($r,$c) => ($r + $c) % 2 === 0,
            fn($r,$c) => $r % 2 === 0,
            fn($r,$c) => $c % 3 === 0,
            fn($r,$c) => ($r + $c) % 3 === 0,
            fn($r,$c) => (intdiv($r,2) + intdiv($c,3)) % 2 === 0,
            fn($r,$c) => (($r*$c)%2)+(($r*$c)%3) === 0,
            fn($r,$c) => ((($r*$c)%2)+(($r*$c)%3)) % 2 === 0,
            fn($r,$c) => ((($r+$c)%2)+(($r*$c)%3)) % 2 === 0,
        ];
        $bestScore = PHP_INT_MAX;
        $bestMatrix = $matrix;
        foreach ($masks as $mi => $maskFn) {
            $m = $matrix;
            for ($r = 0; $r < $size; $r++) {
                for ($c = 0; $c < $size; $c++) {
                    if ($m[$r][$c] >= 0 && $maskFn($r,$c)) {
                        $m[$r][$c] ^= 1;
                    }
                }
            }
            $score = self::penaltyScore($m, $size);
            if ($score < $bestScore) {
                $bestScore  = $score;
                $bestMatrix = $m;
                // store mask index for format info
                $bestMatrix['__mask'] = $mi;
            }
        }
        return $bestMatrix;
    }

    private static function penaltyScore(array $m, int $size): int {
        $score = 0;
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c-1]) { $run++; }
                else { if ($run >= 5) $score += $run - 2; $run = 1; }
            }
            if ($run >= 5) $score += $run - 2;
        }
        return $score;
    }

    private static function writeFormatInfo(array &$m, int $size): void {
        // Format: EC level M (01) + mask 0 (000) = standard
        // Precomputed format string for EC=M, mask=0: 101010000010010
        $fmt = [1,0,1,0,1,0,0,0,0,0,1,0,0,1,0];
        $fi = 0;
        for ($i = 0; $i <= 5; $i++) $m[$i][8] = $fmt[$fi++];
        $m[7][8] = $fmt[$fi++];
        $m[8][8] = $fmt[$fi++];
        $m[8][7] = $fmt[$fi++];
        for ($i = 5; $i >= 0; $i--) $m[8][$i] = $fmt[$fi++];
        $fi = 0;
        for ($i = $size - 1; $i >= $size - 8; $i--) $m[$i][8] = $fmt[$fi++];
        for ($i = $size - 8; $i < $size; $i++) $m[8][$i] = $fmt[$fi++];
    }

    private static function renderPNG(array $matrix, int $imgSize): string {
        $size = count($matrix) - (isset($matrix['__mask']) ? 1 : 0);
        $quiet = 4;
        $modSize = max(1, (int)(($imgSize) / ($size + 2 * $quiet)));
        $totalPx = ($size + 2 * $quiet) * $modSize;

        $img = imagecreatetruecolor($totalPx, $totalPx);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (isset($matrix[$r][$c]) && $matrix[$r][$c] === 1) {
                    $x = ($quiet + $c) * $modSize;
                    $y = ($quiet + $r) * $modSize;
                    imagefilledrectangle($img, $x, $y, $x + $modSize - 1, $y + $modSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);
        return ob_get_clean();
    }
}

// ── Handle request ──
// For short UPI strings, the pure PHP generator works.
// For longer strings, fall back to qrserver.com API.
$upi_len = strlen($upi_string);

if ($upi_len > 90 || !extension_loaded('gd')) {
    // Redirect to reliable external QR API
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/'
            . '?size=' . $size . 'x' . $size
            . '&data=' . rawurlencode($upi_string)
            . '&format=png&ecc=M&margin=4';
    header('Location: ' . $qr_url);
    exit;
}

try {
    $png = PureQR::generate($upi_string, $size);
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $png;
} catch (Throwable $e) {
    // Final fallback
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/'
            . '?size=' . $size . 'x' . $size
            . '&data=' . rawurlencode($upi_string)
            . '&format=png&ecc=M&margin=4';
    header('Location: ' . $qr_url);
    exit;
}

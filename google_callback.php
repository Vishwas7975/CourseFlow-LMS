<?php
/**
 * google_callback.php — Handles Google's redirect back to CourseFlow LMS
 *
 * Flow:
 *   1. Google redirects here with ?code=...  (or no code, if the user cancelled)
 *   2. Exchange the code for an access token
 *   3. Fetch the signed-in user's Google profile (email, name, picture)
 *   4. Look the email up in `users`:
 *        - exists -> log them in, and link google_id/profile_pic if this
 *                    is their first time signing in with Google
 *        - new    -> create a 'student' account (same defaults register.php
 *                    uses), then log them in
 *   5. Set the exact same $_SESSION keys login.php sets, then redirect
 *      to the correct role dashboard.
 *
 * Upload to: public_html/google_callback.php
 */
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/includes/GoogleOAuth.php';

// ── Make sure `users` has the columns Google login needs ──
// (Safe to run on every request — same self-migrating style already used
//  in admin/dashboard.php and student/dashboard.php)
@$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL");
@$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(500) DEFAULT NULL");

// ── Step 0: No code at all means the user cancelled on Google's screen ──
if (empty($_GET['code'])) {
    header("Location: login.php?error=oauth_failed");
    exit();
}

// ── Step 1: Exchange the code for an access token ──
$accessToken = GoogleOAuth::getAccessToken($_GET['code']);
if (!$accessToken) {
    header("Location: login.php?error=oauth_failed");
    exit();
}

// ── Step 2: Fetch the Google profile ──
$googleUser = GoogleOAuth::getUserInfo($accessToken);
if (!$googleUser) {
    header("Location: login.php?error=oauth_failed");
    exit();
}

$email       = strtolower(trim($googleUser['email']));
$google_id   = $googleUser['id']      ?? null;
$google_name = $googleUser['name']    ?? 'Google User';
$google_pic  = $googleUser['picture'] ?? null;

// ── Step 3: Does this email already have an account? ──
$stmt = $conn->prepare("SELECT id, name, role, status, google_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {

    // Same inactive-account rule as login.php / auth_check.php
    if ($user['status'] !== 'active') {
        header("Location: login.php?error=inactive");
        exit();
    }

    // First time this existing account uses Google sign-in -> link it
    if (empty($user['google_id'])) {
        $upd = $conn->prepare("UPDATE users SET google_id = ?, profile_pic = COALESCE(profile_pic, ?) WHERE id = ?");
        $upd->bind_param("ssi", $google_id, $google_pic, $user['id']);
        $upd->execute();
        $upd->close();
    }

    $user_id = $user['id'];
    $name    = $user['name'];
    $role    = $user['role'];

} else {

    // New account — same defaults register.php uses for self-signups
    $role   = "student";
    $status = "active";

    $ins = $conn->prepare("
        INSERT INTO users (name, email, password, role, status, google_id, profile_pic)
        VALUES (?, ?, NULL, ?, ?, ?, ?)
    ");
    $ins->bind_param("ssssss", $google_name, $email, $role, $status, $google_id, $google_pic);
    $ins->execute();
    $user_id = $conn->insert_id;
    $ins->close();

    $name = $google_name;
}

// ── Step 4: Log them in — identical session keys to login.php ──
$_SESSION['user_id'] = $user_id;
$_SESSION['name']    = $name;
$_SESSION['role']    = $role;
$_SESSION['email']   = $email;

// ── Step 5: Off to the right dashboard ──
if     ($role == "admin")   header("Location: admin/dashboard.php");
elseif ($role == "trainer") header("Location: trainer/dashboard.php");
elseif ($role == "finance") header("Location: finance/dashboard.php");
else                          header("Location: student/dashboard.php");
exit();
<?php
/**
 * google_login.php — "Continue with Google" entry point
 *
 * The button on login.php / register.php links here. This script just
 * builds Google's consent-screen URL and redirects the browser to it.
 * All the real work happens in google_callback.php once Google sends
 * the browser back.
 *
 * Upload to: public_html/google_login.php
 */
session_start();
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/includes/GoogleOAuth.php';

header('Location: ' . GoogleOAuth::getAuthUrl());
exit();
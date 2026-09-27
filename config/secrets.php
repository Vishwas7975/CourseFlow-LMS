<?php
/**
 * secrets.php — Sensitive credentials (keep outside public_html in production)
 *
 * In production move this file one directory ABOVE public_html:
 *   /home/youraccount/secrets.php
 * Then update the require path in mailer.php accordingly:
 *   require_once dirname(__DIR__, 2) . '/secrets.php';
 *
 * NEVER commit this file to Git. Add it to .gitignore.
 */

// SMTP credentials
define('VS_SMTP_HOST',      'smtp.hostinger.com');
define('VS_SMTP_PORT',      587);
define('VS_SMTP_USER',      'noreply@yourdomain.com');
define('VS_SMTP_PASS',      'your_smtp_password_here');          // ← SMTP password
define('VS_SMTP_FROM',      'noreply@yourdomain.com');
define('VS_SMTP_FROM_NAME', 'CourseFlow');

// Admin contact
define('VS_ADMIN_EMAIL', 'admin@yourdomain.com');
define('VS_ADMIN_NAME',  'CourseFlow Admin');

// Google OAuth credentials (Cloud project: courseflow-lms)
define('VS_GOOGLE_CLIENT_ID',     'your-google-client-id.apps.googleusercontent.com');
define('VS_GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('VS_GOOGLE_REDIRECT_URI',  'https://yourdomain.com/google_callback.php');

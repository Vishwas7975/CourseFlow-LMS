<?php
/*
 * admin/save_template.php — CourseFlow LMS
 * Saves or returns the certificate template config (JSON + background base64).
 * Admin-only. Called via AJAX from certificate_template.php and student certificates page.
 */
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole("admin");
}

header("Content-Type: application/json; charset=UTF-8");

// ── Auto-create table if it doesn't exist ──────────────────
$conn->query("CREATE TABLE IF NOT EXISTS certificate_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    config_json LONGTEXT NOT NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── GET → return saved template ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = $conn->query("SELECT config_json FROM certificate_templates ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if ($row) {
        echo json_encode(['success' => true, 'config' => json_decode($row['config_json'], true)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No template saved yet.']);
    }
    exit;
}

// ── POST → save template ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    $json = json_encode($data);

    // Upsert: always keep only one row
    $existing = $conn->query("SELECT id FROM certificate_templates LIMIT 1")->fetch_assoc();
    if ($existing) {
        $stmt = $conn->prepare("UPDATE certificate_templates SET config_json = ? WHERE id = ?");
        $stmt->bind_param("si", $json, $existing['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO certificate_templates (config_json) VALUES (?)");
        $stmt->bind_param("s", $json);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Template saved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
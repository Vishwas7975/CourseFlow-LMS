<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// ── Filters (same as workshop_registrations.php so export respects active filter) ──
$search = trim($_GET['search']  ?? '');
$domain = trim($_GET['domain']  ?? '');
$year   = trim($_GET['year']    ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $s = '%' . $search . '%';
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR hall_ticket LIKE ? OR college_name LIKE ?)";
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= "ssss";
}
if ($domain !== '') {
    $where .= " AND interested_domain = ?";
    $params[] = $domain;
    $types .= "s";
}
if ($year !== '') {
    $where .= " AND year_of_study = ?";
    $params[] = (int)$year;
    $types .= "i";
}

// ── Build filename with date ──
$date_str = date('Y-m-d_H-i');
$filename = "workshop_registrations_{$date_str}.csv";

// ── CSV download headers ──
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ── Open output stream ──
$output = fopen('php://output', 'w');

// ── BOM for Excel UTF-8 compatibility ──
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ── Column headers ──
fputcsv($output, [
    'Reg ID',
    'Full Name',
    'Hall Ticket',
    'Email',
    'Year of Study',
    'Contact Number',
    'WhatsApp Number',
    'College Name',
    'College Location',
    'Department',
    'Interested Domain',
    'Source',
    'IP Address',
    'Registered On',
]);

// ── Fetch all matching rows ──
$year_labels = [1=>'1st Year', 2=>'2nd Year', 3=>'3rd Year', 4=>'4th Year'];

$st = $conn->prepare("SELECT * FROM workshop_registrations $where ORDER BY registered_at DESC");
if ($st) {
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            '#WS' . str_pad($row['id'], 6, '0', STR_PAD_LEFT),
            $row['full_name'],
            $row['hall_ticket'],
            $row['email'],
            $year_labels[$row['year_of_study']] ?? $row['year_of_study'],
            $row['contact_number'],
            $row['whatsapp_number'],
            $row['college_name'],
            $row['college_location'],
            $row['department'],
            $row['interested_domain'],
            $row['source'],
            $row['ip_address'],
            date('d M Y h:i A', strtotime($row['registered_at'])),
        ]);
    }
    $st->close();
}

fclose($output);
exit;
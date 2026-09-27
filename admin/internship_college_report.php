<?php
/**
 * internship_college_report.php — CourseFlow
 * Full admin panel: internship registrations management.
 * Source-page aware payment links, rich notifications, modern UI.
 */

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php';

requireLogin($conn);
requireRole('admin');

/* ═══════════════════════════════════════════════════════
   HELPERS — source_page → payment page & amount
═══════════════════════════════════════════════════════ */
function payment_page_for_source(string $src): string {
    $src = strtolower(trim($src));
    if ($src === 'index')   return 'internship_payment1.php';
    if ($src === 'offline') return 'internship_payment_offline.php';
    return 'internship_payment.php';
}
function amount_for_source(string $src): int {
    $src = strtolower(trim($src));
    if ($src === 'index')   return 299;
    if ($src === 'offline') return 7080;
    return 500;
}

/* ═══════════════════════════════════════════════════════
   AJAX HANDLERS
═══════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    /* ── UPDATE STUDENT ── */
    if ($_POST['action'] === 'update_student') {
        $id      = intval($_POST['id']);
        $name    = trim($_POST['full_name']);
        $email   = trim($_POST['email']);
        $phone   = trim($_POST['mobile_number']);
        $reg     = trim($_POST['registration_number']);
        $dept    = trim($_POST['department']);
        $college = trim($_POST['college_name']);
        $domain  = trim($_POST['internship_chosen']);
        $utr     = trim($_POST['utr_number'] ?? '');
        $amount  = trim($_POST['amount'] ?? '');

        $conn->begin_transaction();
        try {
            // Step 1: Update internship_registrations
            $s1 = $conn->prepare("UPDATE internship_registrations SET full_name=?,email=?,mobile_number=?,registration_number=?,department=?,college_name=?,internship_chosen=? WHERE id=?");
            $s1->bind_param("sssssssi",$name,$email,$phone,$reg,$dept,$college,$domain,$id);
            $s1->execute(); $s1->close();

            // Step 2: Auto-cleanup ghost payment rows (terminal_name='Admin', amount=0).
            // These were created by a bug in the old edit logic. Delete them silently.
            $cleanup = $conn->prepare("DELETE FROM internship_payments WHERE reg_id=? AND terminal_name='Admin' AND (amount=0 OR amount='0' OR amount='0.00' OR amount IS NULL OR amount='')");
            $cleanup->bind_param("i", $id);
            $cleanup->execute(); $cleanup->close();

            // Step 2b: Now check if a REAL payment row exists (submitted by student).
            $chk = $conn->prepare("SELECT id FROM internship_payments WHERE reg_id=? LIMIT 1");
            $chk->bind_param("i", $id);
            $chk->execute();
            $existing_pay = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing_pay) {
                // Real payment exists — update the domain on it too
                $s2 = $conn->prepare("UPDATE internship_payments SET internship_chosen=?,utr_number=?,amount=? WHERE reg_id=?");
                $s2->bind_param("sssi",$domain,$utr,$amount,$id);
                $s2->execute(); $s2->close();
            }
            // If no payment row — student hasn't paid yet, nothing to do.
            // Their row will be created correctly when they submit payment.

            // Step 3: Sync users.internship_course if a user account already exists for this email.
            // This fixes the bug where users table still showed old domain after admin changed it.
            $s3 = $conn->prepare("UPDATE users SET internship_course=? WHERE email=?");
            $s3->bind_param("ss", $domain, $email);
            $s3->execute(); $s3->close();

            $conn->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        exit;
    }

    /* ── DELETE STUDENT ── */
    if ($_POST['action'] === 'delete_student') {
        $id = intval($_POST['id']);
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM internship_payments WHERE reg_id=$id");
            $conn->query("DELETE FROM internship_registrations WHERE id=$id");
            $conn->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        exit;
    }

    /* ── SEND PAYMENT MAIL (source-page aware) ── */
    if ($_POST['action'] === 'send_payment_mail') {
        $reg_id   = intval($_POST['id']);
        $to_email = trim($_POST['email']);
        $name     = trim($_POST['name']);
        $domain   = trim($_POST['domain']);
        $source   = strtolower(trim($_POST['source_page'] ?? 'internship_registration'));

        $pay_page   = payment_page_for_source($source);
        $amount_due = amount_for_source($source);
        $link       = "https://yourdomain.com/internship/{$pay_page}?reg_id={$reg_id}";
        $formatted  = number_format($amount_due);

        if ($source === 'index')        { $badge = "🏷️ Early-Bird — ₹299";         $btn_color = "#059669"; }
        elseif ($source === 'offline')  { $badge = "🏛️ Offline Program — ₹7,080"; $btn_color = "#7c3aed"; }
        else                            { $badge = "💼 Standard — ₹500";           $btn_color = "#1e40af"; }

        $subject = "Payment Request: {$domain} Internship — ₹{$formatted} Due | CourseFlow";
        $body = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'/></head>
<body style='margin:0;padding:0;background:#f3f6fb;font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f3f6fb;padding:32px 0;'>
<tr><td align='center'><table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%;'>
  <tr><td style='background:linear-gradient(135deg,#0f1b3d,#2448c8);border-radius:16px 16px 0 0;padding:36px 40px;text-align:center;'>
    <div style='font-size:2rem;margin-bottom:12px;'>💳</div>
    <div style='font-size:1.5rem;font-weight:800;color:#fff;'>Complete Your Payment</div>
    <div style='font-size:.85rem;color:rgba(255,255,255,.7);margin-top:6px;'>{$domain} Internship — CourseFlow</div>
    <div style='margin-top:14px;background:rgba(255,255,255,.15);border-radius:40px;padding:6px 18px;display:inline-block;font-size:.75rem;color:#fff;font-weight:600;'>{$badge}</div>
  </td></tr>
  <tr><td style='background:#fff;padding:32px 40px;'>
    <p style='font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 8px;'>Hello {$name}! 👋</p>
    <p style='font-size:.88rem;color:#64748b;margin:0 0 22px;line-height:1.7;'>Your internship registration for <strong>{$domain}</strong> is confirmed. Please complete your payment of <strong style='color:{$btn_color};'>₹{$formatted}</strong> to secure your seat.</p>
    <div style='text-align:center;margin:28px 0;'>
      <a href='{$link}' style='display:inline-block;padding:15px 42px;background:{$btn_color};color:#fff;text-decoration:none;border-radius:12px;font-weight:800;font-size:.95rem;'>
        💳 Pay ₹{$formatted} Now →
      </a>
    </div>
    <p style='font-size:.78rem;color:#94a3b8;word-break:break-all;margin:0;'>Or copy: {$link}</p>
  </td></tr>
  <tr><td style='background:#0f1b3d;border-radius:0 0 16px 16px;padding:18px 40px;text-align:center;'>
    <div style='font-size:.75rem;color:rgba(255,255,255,.5);'>© " . date('Y') . " CourseFlow Pvt. Ltd.</div>
  </td></tr>
</table></td></tr></table></body></html>";

        try {
            $ok = vs_send_mail($to_email, $name, $subject, $body, 'support@yourdomain.com');
            if (!$ok) {
            error_log("CourseFlow: Failed to send payment link email to {$to_email} (reg_id={$reg_id}, domain={$domain})");
            }
            echo json_encode(['success'=>(bool)$ok,'amount'=>$formatted,'page'=>$pay_page]);
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        exit;
    }

    /* ── MARK NOTIFICATIONS READ ── */
    if ($_POST['action'] === 'mark_notifs_read') {
        $conn->query("UPDATE notifications SET is_read=1 WHERE type='new_registration' AND for_role='admin'");
        echo json_encode(['success'=>true]);
        exit;
    }

    /* ── BULK CLEANUP GHOST PAYMENT ROWS ── */
    if ($_POST['action'] === 'cleanup_ghost_payments') {
        $result = $conn->query("DELETE FROM internship_payments WHERE terminal_name='Admin' AND (amount=0 OR amount='0' OR amount='0.00' OR amount IS NULL OR amount='')");
        $deleted = $conn->affected_rows;
        echo json_encode(['success'=>true, 'deleted'=>$deleted]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']); exit;
}

/* ═══════════════════════════════════════════════════════
   DATA FETCHING
═══════════════════════════════════════════════════════ */
$sel_college = trim($_GET['college'] ?? '');
$sel_depts   = $_GET['depts']   ?? [];
$sel_domains = $_GET['domains'] ?? [];
$search_user = trim($_GET['user_q'] ?? '');
$pay_filter  = trim($_GET['pay'] ?? 'all');
$src_filter  = trim($_GET['src'] ?? 'all');

$all_colleges = $conn->query("SELECT DISTINCT college_name FROM internship_registrations WHERE college_name!='' ORDER BY college_name")->fetch_all(MYSQLI_ASSOC);
$all_depts    = $conn->query("SELECT DISTINCT department FROM internship_registrations WHERE department!='' ORDER BY department")->fetch_all(MYSQLI_ASSOC);
$all_domains  = $conn->query("SELECT DISTINCT internship_chosen FROM internship_registrations WHERE internship_chosen!='' ORDER BY internship_chosen")->fetch_all(MYSQLI_ASSOC);

$base_where = "WHERE 1=1";
if ($sel_college) $base_where .= " AND r.college_name='".$conn->real_escape_string($sel_college)."'";
if (!empty($sel_depts))   { $esc=array_map(fn($d)=>"'".$conn->real_escape_string($d)."'",$sel_depts);   $base_where.=" AND r.department IN (".implode(',',$esc).")"; }
if (!empty($sel_domains)) { $esc=array_map(fn($d)=>"'".$conn->real_escape_string($d)."'",$sel_domains); $base_where.=" AND r.internship_chosen IN (".implode(',',$esc).")"; }
if ($search_user) { $sq=$conn->real_escape_string($search_user); $base_where.=" AND (r.full_name LIKE '%$sq%' OR r.email LIKE '%$sq%' OR r.registration_number LIKE '%$sq%' OR r.mobile_number LIKE '%$sq%')"; }
if ($src_filter!=='all') $base_where.=" AND COALESCE(r.source_page,'internship_registration')='".$conn->real_escape_string($src_filter)."'";

/* ─────────────────────────────────────────────────────
   STATS — subquery picks the best payment row per student
   Priority: approved(1) → pending(2) → rejected(3) → payment_later(4)
───────────────────────────────────────────────────── */
$sq = $conn->query("SELECT COUNT(r.id) as total,
    SUM(CASE WHEN p.payment_status='approved' THEN 1 ELSE 0 END) as paid,
    SUM(CASE WHEN p.payment_status='pending'  THEN 1 ELSE 0 END) as pending_pay,
    SUM(CASE WHEN p.payment_status='payment_later' OR p.payment_status IS NULL THEN 1 ELSE 0 END) as not_paid,
    SUM(CASE WHEN p.payment_status='rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN COALESCE(r.source_page,'internship_registration')='index'                   THEN 1 ELSE 0 END) as src_index,
    SUM(CASE WHEN COALESCE(r.source_page,'internship_registration')='internship_registration' THEN 1 ELSE 0 END) as src_online,
    SUM(CASE WHEN COALESCE(r.source_page,'internship_registration')='offline'                 THEN 1 ELSE 0 END) as src_offline
    FROM internship_registrations r
    LEFT JOIN (
        SELECT reg_id, id, utr_number, amount, payment_status, submitted_at,
               ROW_NUMBER() OVER (PARTITION BY reg_id ORDER BY
                   CASE payment_status
                       WHEN 'approved'      THEN 1
                       WHEN 'pending'       THEN 2
                       WHEN 'rejected'      THEN 3
                       WHEN 'payment_later' THEN 4
                       ELSE 5
                   END, id DESC
               ) AS rn
        FROM internship_payments
    ) p ON p.reg_id = r.id AND p.rn = 1
    $base_where");
$stats = $sq ? $sq->fetch_assoc() : ['total'=>0,'paid'=>0,'pending_pay'=>0,'not_paid'=>0,'rejected'=>0,'src_index'=>0,'src_online'=>0,'src_offline'=>0];

$final_where = $base_where;
if ($pay_filter==='paid')        $final_where.=" AND p.payment_status='approved'";
elseif ($pay_filter==='pending') $final_where.=" AND p.payment_status='pending'";
elseif ($pay_filter==='not_paid')$final_where.=" AND (p.payment_status='payment_later' OR p.payment_status IS NULL)";
elseif ($pay_filter==='rejected')$final_where.=" AND p.payment_status='rejected'";

/* ─────────────────────────────────────────────────────
   STUDENTS — subquery picks the best payment row per student
   Priority: approved(1) → pending(2) → rejected(3) → payment_later(4)
───────────────────────────────────────────────────── */
$students = $conn->query("SELECT r.*, COALESCE(r.source_page,'internship_registration') AS source_page,
    p.id as pay_id, p.utr_number, p.amount, IFNULL(p.payment_status,'not_paid') as pay_status, p.submitted_at as pay_submitted
    FROM internship_registrations r
    LEFT JOIN (
        SELECT reg_id, id, utr_number, amount, payment_status, submitted_at,
               ROW_NUMBER() OVER (PARTITION BY reg_id ORDER BY
                   CASE payment_status
                       WHEN 'approved'      THEN 1
                       WHEN 'pending'       THEN 2
                       WHEN 'rejected'      THEN 3
                       WHEN 'payment_later' THEN 4
                       ELSE 5
                   END, id DESC
               ) AS rn
        FROM internship_payments
    ) p ON p.reg_id = r.id AND p.rn = 1
    $final_where ORDER BY r.id DESC")->fetch_all(MYSQLI_ASSOC);

/* CSV Export */
if (isset($_GET['export']) && $_GET['export']==='csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="internship_registrations_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output','w'); fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['#','Name','Email','Mobile','Reg No.','College','Dept','Domain','Source','Registered','UTR','Amount','Pay Status']);
    foreach($students as $r) fputcsv($out,[$r['id'],$r['full_name'],$r['email'],$r['mobile_number'],$r['registration_number'],$r['college_name'],$r['department'],$r['internship_chosen'],$r['source_page'],$r['registered_at']??'',$r['utr_number'],$r['amount'],$r['pay_status']]);
    fclose($out); exit;
}

/* Notifications */
$nq = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE type='new_registration' AND for_role='admin' AND is_read=0");
$unread_notifs = $nq ? (int)$nq->fetch_assoc()['c'] : 0;
$recent_notifs = $conn->query("SELECT * FROM notifications WHERE type='new_registration' AND for_role='admin' ORDER BY created_at DESC LIMIT 12")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Internship Registrations — CourseFlow Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.ir-page-hdr{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:26px;}
.ir-page-hdr h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px;margin:0;}
.ir-hdr-ico{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#7c3aed,#a78bfa);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;box-shadow:0 4px 12px rgba(124,58,237,.35);flex-shrink:0;}
.ir-page-hdr p{color:#64748b;font-size:.83rem;margin:4px 0 0 48px;}

.ir-kpi-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px;}
@media(max-width:1100px){.ir-kpi-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:640px){.ir-kpi-row{grid-template-columns:repeat(2,1fr);}}
.ir-kpi{background:#fff;border-radius:14px;padding:16px 18px;border:1px solid #eef2f7;box-shadow:0 2px 10px rgba(15,23,42,.05);position:relative;overflow:hidden;transition:all .2s;cursor:pointer;text-decoration:none;display:block;}
.ir-kpi:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(15,23,42,.09);}
.ir-kpi.active-kpi{box-shadow:0 0 0 3px rgba(124,58,237,.2);border-color:#a78bfa;}
.ir-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;}
.ir-kpi.kv-purple::before{background:linear-gradient(90deg,#7c3aed,#c4b5fd);}
.ir-kpi.kv-green::before{background:linear-gradient(90deg,#10b981,#6ee7b7);}
.ir-kpi.kv-amber::before{background:linear-gradient(90deg,#f59e0b,#fcd34d);}
.ir-kpi.kv-blue::before{background:linear-gradient(90deg,#3b82f6,#93c5fd);}
.ir-kpi.kv-red::before{background:linear-gradient(90deg,#ef4444,#fca5a5);}
.kpi-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.82rem;margin-bottom:10px;}
.ir-kpi.kv-purple .kpi-ico{background:#f5f3ff;color:#7c3aed;}
.ir-kpi.kv-green  .kpi-ico{background:#f0fdf4;color:#059669;}
.ir-kpi.kv-amber  .kpi-ico{background:#fffbeb;color:#b45309;}
.ir-kpi.kv-blue   .kpi-ico{background:#eff6ff;color:#2563eb;}
.ir-kpi.kv-red    .kpi-ico{background:#fff1f2;color:#dc2626;}
.kpi-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:900;color:#0f172a;line-height:1;margin-bottom:3px;}
.kpi-lbl{font-size:.71rem;color:#64748b;font-weight:500;}

.src-pills{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;}
.src-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;font-size:.78rem;font-weight:700;border:2px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none;transition:all .2s;}
.src-pill:hover{border-color:#a78bfa;color:#7c3aed;}
.src-pill.sa{border-color:#7c3aed;background:#7c3aed;color:#fff;}
.src-pill.si{border-color:#059669;background:#059669;color:#fff;}
.src-pill.so{border-color:#1e40af;background:#1e40af;color:#fff;}
.src-pill.sof{border-color:#7c3aed;background:#7c3aed;color:#fff;}
.src-pill .spc{background:rgba(255,255,255,.25);padding:1px 6px;border-radius:999px;font-size:.68rem;}
.src-pill:not([class*=" s"]) .spc,.src-pill:not([class*=" s"]):not([class$="pill"]) .spc{background:#f1f5f9;color:#64748b;}
:is(.src-pill:not(.sa):not(.si):not(.so):not(.sof)) .spc{background:#f1f5f9;color:#64748b;}

.ir-filter{background:#fff;border-radius:14px;border:1px solid #eef2f7;box-shadow:0 2px 8px rgba(15,23,42,.04);padding:18px 20px;margin-bottom:18px;display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:14px;align-items:flex-end;}
@media(max-width:900px){.ir-filter{grid-template-columns:1fr 1fr;}}
@media(max-width:500px){.ir-filter{grid-template-columns:1fr;}}
.ir-fg label{display:block;font-size:.67rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.ir-fg select,.ir-fg input{width:100%;padding:9px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:.83rem;color:#1e293b;background:#f8fafc;outline:none;font-family:inherit;transition:border-color .2s;box-sizing:border-box;}
.ir-fg select:focus,.ir-fg input:focus{border-color:#7c3aed;background:#fff;}
.filter-btn{padding:10px 20px;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;border:none;border-radius:10px;font-size:.83rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;white-space:nowrap;}
.filter-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(124,58,237,.3);}

.ms-wrap{position:relative;}
.ms-summary{padding:9px 12px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;font-size:.83rem;list-style:none;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;color:#1e293b;transition:border-color .2s;}
.ms-summary:hover{border-color:#a78bfa;}
.ms-content{position:absolute;top:105%;left:0;width:100%;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;z-index:200;max-height:220px;overflow-y:auto;padding:10px;box-shadow:0 10px 30px rgba(0,0,0,.12);}
.ms-content label{display:flex;gap:9px;padding:7px 6px;font-size:.8rem;cursor:pointer;border-radius:6px;color:#334155;align-items:center;}
.ms-content label:hover{background:#f5f3ff;}

.ir-tbl-wrap{background:#fff;border-radius:16px;border:1px solid #eef2f7;box-shadow:0 2px 12px rgba(15,23,42,.05);overflow:hidden;}
.ir-tbl{width:100%;border-collapse:collapse;min-width:900px;}
.ir-tbl th{padding:12px 14px;text-align:left;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.88);background:linear-gradient(135deg,#0a2540,#7c3aed);white-space:nowrap;}
.ir-tbl td{padding:6px 14px;font-size:.70rem;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle;}
.ir-tbl tbody tr:last-child td{border-bottom:none;}
.ir-tbl tbody tr:hover td{background:#faf5ff;}
.td-name{font-weight:700;color:#0f172a;font-size:.84rem;}
.td-sub{font-size:.71rem;color:#94a3b8;margin-top:1px;}

.src-badge{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:999px;font-size:.65rem;font-weight:700;}
.src-badge.bi{background:#f0fdf4;color:#059669;border:1px solid #bbf7d0;}
.src-badge.bo{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
.src-badge.bof{background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;}

.pay-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:999px;font-size:.68rem;font-weight:700;}
.pay-badge.approved{background:#dcfce7;color:#14532d;}
.pay-badge.pending{background:#fef3c7;color:#92400e;}
.pay-badge.payment_later{background:#eff6ff;color:#1e40af;}
.pay-badge.not_paid{background:#f1f5f9;color:#475569;}
.pay-badge.rejected{background:#ffe4e6;color:#881337;}

.act-row{display:flex;gap:4px;flex-wrap: nowrap;align-items: center;}
.act-btn{width:20px;height:20px;border-radius:4px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;transition:all .18s;text-decoration:none; flex-shrink: 0;}
.btn-edit{background:#f5f3ff;color:#7c3aed;}.btn-edit:hover{background:#ede9fe;}
.btn-pay{background:#eff6ff;color:#2563eb;}.btn-pay:hover{background:#dbeafe;}
.btn-wa{background:#f0fdf4;color:#059669;}.btn-wa:hover{background:#dcfce7;}
.btn-mail{background:#fff1f2;color:#e11d48;}.btn-mail:hover{background:#ffe4e6;}
.btn-del{background:#fff1f2;color:#dc2626;}.btn-del:hover{background:#fee2e2;}

.ir-empty{text-align:center;padding:60px 24px;}
.ir-empty i{font-size:2.4rem;color:#cbd5e1;display:block;margin-bottom:14px;}
.tbl-footer{padding:12px 18px;background:#f8fafc;border-top:1px solid #f1f5f9;font-size:.75rem;color:#94a3b8;font-weight:600;display:flex;align-items:center;justify-content:space-between;}

.ir-export-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;border:none;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;box-shadow:0 3px 10px rgba(124,58,237,.25);transition:all .2s;white-space:nowrap;}
.ir-export-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(124,58,237,.35);}

.ir-modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;}
.ir-modal-overlay.show{display:flex;animation:ovIn .2s;}
@keyframes ovIn{from{opacity:0}to{opacity:1}}
.ir-modal-box{background:#fff;border-radius:20px;width:100%;max-width:580px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 70px rgba(0,0,0,.22);animation:mIn .25s cubic-bezier(.34,1.56,.64,1);}
@keyframes mIn{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
.ir-modal-head{border-radius:20px 20px 0 0;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;}
.ir-modal-head h2{color:#fff;font-size:.95rem;font-weight:700;margin:0;}
.ir-modal-close{background:rgba(255,255,255,.18);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.82rem;transition:background .2s;}
.ir-modal-close:hover{background:rgba(255,255,255,.3);}
.ir-modal-body{padding:20px 24px;}
.ir-modal-footer{padding:12px 24px 20px;display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #f1f5f9;}
.mgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.mfg{display:flex;flex-direction:column;gap:4px;}
.mfg.full{grid-column:span 2;}
.mfg label{font-size:.67rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;}
.mfg input,.mfg select{padding:9px 12px;border:2px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#1e293b;font-family:inherit;outline:none;transition:border-color .2s;}
.mfg input:focus,.mfg select:focus{border-color:#7c3aed;}
.btn-full{flex:1;padding:11px;border-radius:10px;font-size:.88rem;font-weight:700;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;font-family:inherit;transition:all .2s;}
.btn-full.bpurple{background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;box-shadow:0 4px 12px rgba(124,58,237,.3);}
.btn-full.bdanger{background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;box-shadow:0 4px 12px rgba(239,68,68,.3);}
.btn-full.bcancel{background:#f1f5f9;color:#475569;flex:0.4;}
.btn-full:hover:not(:disabled){transform:translateY(-1px);}
.btn-full:disabled{opacity:.55;cursor:not-allowed;transform:none;}

.notif-panel{position:fixed;top:0;right:-380px;width:360px;height:100vh;background:#fff;box-shadow:-8px 0 40px rgba(0,0,0,.15);z-index:10000;transition:right .3s cubic-bezier(.4,0,.2,1);overflow-y:auto;display:flex;flex-direction:column;}
.notif-panel.open{right:0;}
.notif-panel-head{padding:18px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#0a2540,#7c3aed);color:#fff;position:sticky;top:0;z-index:1;flex-shrink:0;}
.notif-panel-head h3{font-size:.95rem;font-weight:700;margin:0;}
.notif-close-btn{background:rgba(255,255,255,.18);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.82rem;}
.notif-item{padding:14px 18px;border-bottom:1px solid #f8fafc;transition:background .15s;}
.notif-item:hover{background:#faf5ff;}
.notif-item.unread{background:#fdf4ff;border-left:3px solid #7c3aed;}
.notif-item .ni-title{font-size:.83rem;font-weight:700;color:#1e293b;margin-bottom:2px;}
.notif-item .ni-msg{font-size:.76rem;color:#64748b;line-height:1.5;}
.notif-item .ni-time{font-size:.68rem;color:#94a3b8;margin-top:4px;}
.notif-bell-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:#f5f3ff;border:2px solid #e9d5ff;border-radius:10px;cursor:pointer;color:#7c3aed;font-size:.95rem;transition:all .2s;}
.notif-bell-btn:hover{background:#ede9fe;border-color:#c4b5fd;}
.nb-count{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:.58rem;font-weight:800;min-width:18px;height:18px;border-radius:999px;display:flex;align-items:center;justify-content:center;padding:0 4px;animation:pulse 2s infinite;}

.ir-toast{position:fixed;bottom:28px;right:28px;z-index:10001;background:#1e293b;color:#fff;border-radius:12px;padding:13px 20px;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 8px 28px rgba(0,0,0,.25);transform:translateY(80px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);}
.ir-toast.show{transform:translateY(0);opacity:1;}
.ir-toast.success i{color:#4ade80;}
.ir-toast.error   i{color:#f87171;}
.ir-toast.info    i{color:#60a5fa;}

@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}
</style>
</head>
<body>
<div class="dashboard">
<?php include "../includes/sidebar.php"; ?>
<div class="main">
<?php include "../includes/topbar.php"; ?>
<div class="content-wrapper">

<!-- PAGE HEADER -->
<div class="ir-page-hdr">
  <div>
    <h1>
      <span class="ir-hdr-ico"><i class="fas fa-user-graduate"></i></span>
      Internship Registrations
    </h1>
    <p>Manage all student registrations, send payment links, and track enrollment status</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <button class="notif-bell-btn" onclick="openNotifPanel()" title="New Registration Notifications">
      <i class="fas fa-bell"></i>
      <?php if($unread_notifs>0): ?><span class="nb-count"><?= $unread_notifs ?></span><?php endif; ?>
    </button>
    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.82rem;font-weight:700;text-decoration:none;">
      <i class="fas fa-arrow-left"></i> Dashboard
    </a>
    <a href="admin_internship_payments.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f0fdf4;color:#15803d;font-size:.82rem;font-weight:700;text-decoration:none;border:1px solid #bbf7d0;">
      <i class="fas fa-briefcase"></i> Payments Panel
    </a>
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="ir-export-btn">
      <i class="fas fa-file-csv"></i> Export CSV
    </a>
  </div>
</div>

<!-- KPI CARDS -->
<div class="ir-kpi-row">
  <?php
  $kpis = [
    ['filter'=>'all',      'label'=>'Total Registrations','count'=>$stats['total'],      'icon'=>'fa-users',       'cls'=>'kv-purple'],
    ['filter'=>'paid',     'label'=>'Paid / Approved',    'count'=>$stats['paid']??0,    'icon'=>'fa-check-circle','cls'=>'kv-green'],
    ['filter'=>'pending',  'label'=>'Payment Pending',    'count'=>$stats['pending_pay']??0,'icon'=>'fa-hourglass-half','cls'=>'kv-amber'],
    ['filter'=>'not_paid', 'label'=>'Not Paid / Pay Later','count'=>$stats['not_paid']??0,'icon'=>'fa-clock',     'cls'=>'kv-blue'],
    ['filter'=>'rejected', 'label'=>'Rejected',           'count'=>$stats['rejected']??0,'icon'=>'fa-times-circle','cls'=>'kv-red'],
  ];
  foreach($kpis as $k):
    $active = $pay_filter===$k['filter'] ? 'active-kpi' : '';
  ?>
  <a href="?<?= http_build_query(array_merge($_GET,['pay'=>$k['filter'],'src'=>$src_filter])) ?>" class="ir-kpi <?= $k['cls'] ?> <?= $active ?>">
    <div class="kpi-ico"><i class="fas <?= $k['icon'] ?>"></i></div>
    <div class="kpi-num"><?= $k['count'] ?></div>
    <div class="kpi-lbl"><?= $k['label'] ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- SOURCE FILTER PILLS -->
<div class="src-pills">
  <?php
  $srcs = [
    ['key'=>'all',                     'label'=>'All Sources',       'icon'=>'fa-th',       'count'=>$stats['total'],          'cls'=>'sa'],
    ['key'=>'index',                   'label'=>'index.html (₹299)', 'icon'=>'fa-tag',      'count'=>$stats['src_index']??0,   'cls'=>'si'],
    ['key'=>'internship_registration', 'label'=>'Online Reg (₹500)', 'icon'=>'fa-laptop',   'count'=>$stats['src_online']??0,  'cls'=>'so'],
    ['key'=>'offline',                 'label'=>'Offline (₹7,080)',  'icon'=>'fa-building', 'count'=>$stats['src_offline']??0, 'cls'=>'sof'],
  ];
  foreach($srcs as $s):
    $active = $src_filter===$s['key'] ? $s['cls'] : '';
  ?>
  <a href="?<?= http_build_query(array_merge($_GET,['src'=>$s['key'],'pay'=>$pay_filter])) ?>" class="src-pill <?= $active ?>">
    <i class="fas <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
    <span class="spc"><?= $s['count'] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- FILTER PANEL -->
<form class="ir-filter" method="GET">
  <input type="hidden" name="pay" value="<?= htmlspecialchars($pay_filter) ?>">
  <input type="hidden" name="src" value="<?= htmlspecialchars($src_filter) ?>">
  <div class="ir-fg"><label>College</label>
    <select name="college">
      <option value="">All Colleges</option>
      <?php foreach($all_colleges as $c): ?><option value="<?= htmlspecialchars($c['college_name']) ?>" <?= $sel_college===$c['college_name']?'selected':'' ?>><?= htmlspecialchars($c['college_name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="ir-fg"><label>Domains <span style="font-weight:400;color:#94a3b8;">(<?= count($sel_domains) ?>)</span></label>
    <div class="ms-wrap"><details>
      <summary class="ms-summary">Pick Domains <i class="fas fa-chevron-down" style="font-size:.6rem;"></i></summary>
      <div class="ms-content">
        <?php foreach($all_domains as $d): ?><label><input type="checkbox" name="domains[]" value="<?= htmlspecialchars($d['internship_chosen']) ?>" <?= in_array($d['internship_chosen'],$sel_domains)?'checked':'' ?>> <?= htmlspecialchars($d['internship_chosen']) ?></label><?php endforeach; ?>
      </div>
    </details></div>
  </div>
  <div class="ir-fg"><label>Departments <span style="font-weight:400;color:#94a3b8;">(<?= count($sel_depts) ?>)</span></label>
    <div class="ms-wrap"><details>
      <summary class="ms-summary">Pick Depts <i class="fas fa-chevron-down" style="font-size:.6rem;"></i></summary>
      <div class="ms-content">
        <?php foreach($all_depts as $d): ?><label><input type="checkbox" name="depts[]" value="<?= htmlspecialchars($d['department']) ?>" <?= in_array($d['department'],$sel_depts)?'checked':'' ?>> <?= htmlspecialchars($d['department']) ?></label><?php endforeach; ?>
      </div>
    </details></div>
  </div>
  <div class="ir-fg"><label>Search</label>
    <input type="text" name="user_q" value="<?= htmlspecialchars($search_user) ?>" placeholder="Name / Email / Reg No / Mobile…">
  </div>
  <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Filter</button>
</form>

<!-- TABLE -->
<div class="ir-tbl-wrap">
  <div style="overflow-x:auto;">
    <table class="ir-tbl">
      <thead>
        <tr><th>#</th><th>Student</th><th>College / Dept</th><th>Domain</th><th>Source</th><th>Pay Status</th><th>Amount</th><th>Registered</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if(empty($students)): ?>
        <tr><td colspan="9"><div class="ir-empty"><i class="fas fa-inbox"></i><p style="font-weight:700;font-size:.9rem;color:#475569;">No registrations found.</p><p style="color:#94a3b8;font-size:.82rem;">Try adjusting your filters or search.</p></div></td></tr>
        <?php else: ?>
        <?php foreach($students as $s):
          $src      = $s['source_page'];
          $pay_page = payment_page_for_source($src);
          $amt_due  = amount_for_source($src);
          $pay_link = "https://yourdomain.com/internship/{$pay_page}?reg_id={$s['id']}";
          $wa_text  = urlencode("Hello {$s['full_name']}, please complete your internship payment of ₹".number_format($amt_due)." here: {$pay_link}");
          $src_label= $src==='index'?'index.html':($src==='offline'?'Offline':'Online Reg');
          $src_cls  = $src==='index'?'bi':($src==='offline'?'bof':'bo');
        ?>
        <tr>
          <td style="color:#cbd5e1;font-size:.72rem;font-weight:600;">#<?= $s['id'] ?></td>
          <td>
            <div class="td-name"><?= htmlspecialchars($s['full_name']) ?></div>
            <div class="td-sub"><?= htmlspecialchars($s['email']) ?></div>
            <div class="td-sub"><i class="fas fa-phone" style="font-size:.6rem;"></i> <?= htmlspecialchars($s['mobile_number']) ?></div>
            <div class="td-sub" style="font-family:monospace;color:#7c3aed;"><?= htmlspecialchars($s['registration_number']) ?></div>
          </td>
          <td>
            <div style="font-weight:600;font-size:.83rem;"><?= htmlspecialchars($s['college_name']) ?></div>
            <div class="td-sub"><?= htmlspecialchars($s['department']) ?></div>
          </td>
          <td><span style="font-weight:800;color:#7c3aed;font-size:.83rem;"><?= htmlspecialchars($s['internship_chosen']) ?></span></td>
          <td>
            <span class="src-badge <?= $src_cls ?>"><?= $src_label ?></span>
            <div style="font-size:.66rem;color:#94a3b8;margin-top:3px;">₹<?= number_format($amt_due) ?></div>
          </td>
          <td>
            <?php $icons=['approved'=>'fa-check','pending'=>'fa-clock','payment_later'=>'fa-hourglass-half','not_paid'=>'fa-minus-circle','rejected'=>'fa-times']; ?>
            <span class="pay-badge <?= $s['pay_status'] ?>">
              <i class="fas <?= $icons[$s['pay_status']]??'fa-circle' ?>"></i>
              <?= $s['pay_status']==='payment_later'?'Pay Later':ucfirst(str_replace('_',' ',$s['pay_status'])) ?>
            </span>
            <?php if(!empty($s['utr_number']) && $s['utr_number']!=='PENDING'): ?>
            <div style="font-family:monospace;font-size:.66rem;color:#059669;background:#f0fdf4;padding:2px 6px;border-radius:5px;margin-top:3px;display:inline-block;">UTR: <?= htmlspecialchars($s['utr_number']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-weight:800;color:<?= $s['pay_status']==='approved'?'#059669':'#64748b' ?>;font-size:.88rem;">
            <?= $s['amount'] ? '₹'.number_format((float)$s['amount'],2) : '—' ?>
          </td>
          <td style="font-size:.73rem;color:#64748b;white-space:nowrap;"><?= !empty($s['registered_at']) ? date('d M y',strtotime($s['registered_at'])) : '—' ?></td>
          <td>
            <div class="act-row">
              <button class="act-btn btn-edit" title="Edit" onclick='openEditModal(<?= json_encode($s,JSON_HEX_QUOT|JSON_HEX_APOS) ?>)'><i class="fas fa-pencil"></i></button>
              <a href="<?= htmlspecialchars($pay_link) ?>" target="_blank" class="act-btn btn-pay" title="Payment Page (₹<?= number_format($amt_due) ?>)"><i class="fas fa-credit-card"></i></a>
              <?php if($s['pay_status']!=='approved'): ?>
              <a href="https://api.whatsapp.com/send?phone=91<?= $s['mobile_number'] ?>&text=<?= $wa_text ?>" target="_blank" class="act-btn btn-wa" title="WhatsApp ₹<?= number_format($amt_due) ?> link"><i class="fab fa-whatsapp"></i></a>
              <button class="act-btn btn-mail" title="Email ₹<?= number_format($amt_due) ?> link" onclick="sendMailAJAX(this,'<?= htmlspecialchars($s['email'],ENT_QUOTES) ?>','<?= addslashes(htmlspecialchars($s['full_name'])) ?>','<?= $s['id'] ?>','<?= addslashes(htmlspecialchars($s['internship_chosen'])) ?>','<?= $src ?>')"><i class="fas fa-envelope"></i></button>
              <?php endif; ?>
              <button class="act-btn btn-del" title="Delete" onclick="openDeleteModal(<?= $s['id'] ?>,'<?= addslashes(htmlspecialchars($s['full_name'])) ?>')"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if(!empty($students)): ?>
  <div class="tbl-footer">
    <span><i class="fas fa-table" style="margin-right:5px;color:#7c3aed;"></i><?= count($students) ?> record<?= count($students)!==1?'s':'' ?>
      <?php if($pay_filter!=='all') echo ' &nbsp;·&nbsp; Pay: '.ucfirst($pay_filter); ?>
      <?php if($src_filter!=='all') echo ' &nbsp;·&nbsp; Source: '.$src_filter; ?>
      <?php if($search_user!=='') echo ' &nbsp;·&nbsp; "'.htmlspecialchars($search_user).'"'; ?>
    </span>
    <?php $tot=array_sum(array_filter(array_column($students,'amount'))); if($tot>0): ?>
    <span style="color:#059669;font-weight:800;">Collected: ₹<?= number_format($tot,2) ?></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>

<!-- NOTIFICATION PANEL -->
<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-head">
    <h3><i class="fas fa-bell" style="margin-right:8px;"></i> New Registrations</h3>
    <button class="notif-close-btn" onclick="closeNotifPanel()"><i class="fas fa-times"></i></button>
  </div>
  <?php if(empty($recent_notifs)): ?>
  <div style="text-align:center;padding:40px 20px;color:#94a3b8;"><i class="fas fa-bell-slash" style="font-size:2rem;display:block;margin-bottom:12px;"></i><p style="font-size:.85rem;">No recent notifications</p></div>
  <?php else: ?>
  <div style="flex:1;overflow-y:auto;">
    <?php foreach($recent_notifs as $n): ?>
    <div class="notif-item <?= !$n['is_read']?'unread':'' ?>">
      <div class="ni-title"><?= htmlspecialchars($n['title']) ?></div>
      <div class="ni-msg"><?= htmlspecialchars($n['message']) ?></div>
      <div class="ni-time"><i class="fas fa-clock" style="font-size:.6rem;"></i> <?= isset($n['created_at'])?date('d M Y, H:i',strtotime($n['created_at'])):'—' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="padding:14px 18px;border-top:1px solid #f1f5f9;">
    <button onclick="markNotifsRead()" style="width:100%;padding:10px;background:#f5f3ff;color:#7c3aed;border:2px solid #e9d5ff;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;">
      <i class="fas fa-check-double"></i> Mark All as Read
    </button>
  </div>
  <?php endif; ?>
</div>
<div id="notifOverlay" style="display:none;position:fixed;inset:0;z-index:9999;" onclick="closeNotifPanel()"></div>

<!-- EDIT MODAL -->
<div class="ir-modal-overlay" id="editModal">
  <div class="ir-modal-box">
    <div class="ir-modal-head" style="background:linear-gradient(135deg,#0a2540,#7c3aed);">
      <h2><i class="fas fa-pencil" style="margin-right:8px;"></i> Edit Registration</h2>
      <button class="ir-modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="ir-modal-body">
      <form id="editForm">
        <input type="hidden" name="action" value="update_student">
        <input type="hidden" name="id" id="edit_id">
        <div class="mgrid">
          <div class="mfg"><label>Full Name</label><input type="text" name="full_name" id="edit_name" required></div>
          <div class="mfg"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
          <div class="mfg"><label>Mobile</label><input type="text" name="mobile_number" id="edit_phone" required></div>
          <div class="mfg"><label>Reg. Number</label><input type="text" name="registration_number" id="edit_reg"></div>
          <div class="mfg"><label>Department</label>
            <select name="department" id="edit_dept">
              <?php foreach($all_depts as $d): ?><option value="<?= htmlspecialchars($d['department']) ?>"><?= htmlspecialchars($d['department']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mfg full"><label>College Name</label><input type="text" name="college_name" id="edit_college"></div>
          <div class="mfg"><label>Internship Domain</label>
            <select name="internship_chosen" id="edit_domain">
              <?php foreach($all_domains as $d): ?><option value="<?= htmlspecialchars($d['internship_chosen']) ?>"><?= htmlspecialchars($d['internship_chosen']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mfg"><label>UTR Number</label><input type="text" name="utr_number" id="edit_utr"></div>
          <div class="mfg"><label>Amount (₹)</label><input type="number" name="amount" id="edit_amount" step="0.01"></div>
        </div>
      </form>
    </div>
    <div class="ir-modal-footer">
      <button class="btn-full bpurple" id="editSubmitBtn" onclick="submitEdit()"><i class="fas fa-save"></i> Save Changes</button>
      <button class="btn-full bcancel" onclick="closeModal('editModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="ir-modal-overlay" id="deleteModal">
  <div class="ir-modal-box" style="max-width:420px;">
    <div class="ir-modal-head" style="background:linear-gradient(135deg,#dc2626,#ef4444);">
      <h2><i class="fas fa-trash" style="margin-right:8px;"></i> Confirm Delete</h2>
      <button class="ir-modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="ir-modal-body">
      <div style="text-align:center;padding:10px 0;">
        <div style="width:60px;height:60px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;color:#dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
        <p style="font-size:.9rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Delete this registration?</p>
        <p style="font-size:.83rem;color:#64748b;margin:0;">This will permanently remove the registration and any associated payment record for <strong id="del_name"></strong>.</p>
      </div>
    </div>
    <div class="ir-modal-footer">
      <input type="hidden" id="del_id">
      <button class="btn-full bdanger" id="deleteConfirmBtn" onclick="confirmDelete()"><i class="fas fa-trash"></i> Yes, Delete</button>
      <button class="btn-full bcancel" onclick="closeModal('deleteModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="ir-toast" id="irToast"><i class="fas fa-check-circle"></i><span id="toastMsg">Done!</span></div>

<?php include "../includes/footer.php"; ?>

<script>
function closeModal(id){document.getElementById(id).classList.remove('show');}
function showModal(id){document.getElementById(id).classList.add('show');}
document.querySelectorAll('.ir-modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===this)closeModal(this.id);});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.ir-modal-overlay.show').forEach(function(m){closeModal(m.id);});});

function showToast(msg,type){
  var t=document.getElementById('irToast');
  document.getElementById('toastMsg').textContent=msg;
  t.className='ir-toast show '+(type||'success');
  var icons={success:'fa-check-circle',error:'fa-times-circle',info:'fa-info-circle'};
  t.querySelector('i').className='fas '+(icons[type]||'fa-check-circle');
  setTimeout(function(){t.classList.remove('show');},3500);
}

function openEditModal(s){
  document.getElementById('edit_id').value    =s.id;
  document.getElementById('edit_name').value  =s.full_name;
  document.getElementById('edit_email').value =s.email;
  document.getElementById('edit_phone').value =s.mobile_number;
  document.getElementById('edit_reg').value   =s.registration_number||'';
  document.getElementById('edit_dept').value  =s.department;
  document.getElementById('edit_college').value=s.college_name;
  document.getElementById('edit_domain').value=s.internship_chosen;
  document.getElementById('edit_utr').value   =(s.utr_number&&s.utr_number!=='PENDING')?s.utr_number:'';
  document.getElementById('edit_amount').value=s.amount||'';
  showModal('editModal');
}
function submitEdit(){
  var btn=document.getElementById('editSubmitBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
  fetch('',{method:'POST',body:new FormData(document.getElementById('editForm'))})
    .then(function(r){return r.json();})
    .then(function(d){
      closeModal('editModal');
      if(d.success){showToast('✅ Record updated!','success');setTimeout(function(){location.reload();},1500);}
      else{showToast('❌ '+(d.error||'Update failed'),'error');btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save Changes';}
    })
    .catch(function(){showToast('❌ Network error.','error');btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save Changes';});
}

function openDeleteModal(id,name){
  document.getElementById('del_id').value=id;
  document.getElementById('del_name').textContent=name;
  showModal('deleteModal');
}
function confirmDelete(){
  var btn=document.getElementById('deleteConfirmBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Deleting…';
  var fd=new FormData(); fd.append('action','delete_student'); fd.append('id',document.getElementById('del_id').value);
  fetch('',{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      closeModal('deleteModal');
      if(d.success){showToast('🗑️ Registration deleted.','info');setTimeout(function(){location.reload();},1500);}
      else{showToast('❌ '+(d.error||'Delete failed'),'error');btn.disabled=false;btn.innerHTML='<i class="fas fa-trash"></i> Yes, Delete';}
    })
    .catch(function(){showToast('❌ Network error.','error');btn.disabled=false;btn.innerHTML='<i class="fas fa-trash"></i> Yes, Delete';});
}

function sendMailAJAX(btn,email,name,id,domain,source){
  if(btn.disabled)return;
  btn.disabled=true; var old=btn.innerHTML; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
  var fd=new FormData();
  fd.append('action','send_payment_mail');
  fd.append('email',email); fd.append('name',name);
  fd.append('id',id); fd.append('domain',domain); fd.append('source_page',source);
  fetch('',{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      btn.disabled=false; btn.innerHTML=old;
      if(d.success) showToast('📧 Payment link (₹'+d.amount+') sent to '+email+'!','success');
      else showToast('❌ Mail failed: '+(d.error||'Check mailer.php'),'error');
    })
    .catch(function(){btn.disabled=false;btn.innerHTML=old;showToast('❌ Network error.','error');});
}

function openNotifPanel(){
  document.getElementById('notifPanel').classList.add('open');
  document.getElementById('notifOverlay').style.display='block';
}
function closeNotifPanel(){
  document.getElementById('notifPanel').classList.remove('open');
  document.getElementById('notifOverlay').style.display='none';
}
function markNotifsRead(){
  var fd=new FormData(); fd.append('action','mark_notifs_read');
  fetch('',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    if(d.success){
      document.querySelectorAll('.notif-item.unread').forEach(function(el){el.classList.remove('unread');});
      var bell=document.querySelector('.nb-count'); if(bell) bell.remove();
      showToast('All notifications marked as read','info');
    }
  });
}
</script>
</body>
</html>
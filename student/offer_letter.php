<?php
/*
 * ============================================================
 * OFFER LETTER PAGE — TEMPORARILY DISABLED
 * ============================================================
 * Reason: Offer letters are created MANUALLY by staff.
 * The auto-generated intern ID (VAI-26-B4-{id}) is based on
 * the internship_payments table row ID, which does NOT match
 * the ID used by the person creating the letter manually.
 * This causes an ID mismatch on every printed offer letter.
 *
 * TODO: Re-enable once a unified ID system is in place,
 *       OR switch to a staff-assigned intern ID stored in DB.
 * ============================================================
 */
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
requireLogin($conn);
requireRole("student");
header("Location: ../student/dashboard.php");
exit();
?>

<?php /* ---- ORIGINAL CODE BELOW (COMMENTED OUT) ----
$email    = trim(strtolower($_SESSION['email'] ?? ''));  // ← FIX: trim + lowercase for safe matching

/* ── Guard: email must be in session (set by login.php) ── */
if (empty($email)) {
    // Session email missing — redirect to login to re-authenticate
    session_unset();
    session_destroy();
    header("Location: ../login.php?msg=session_expired");
    exit();
}

/* ── Fetch student record (users table) ── */
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ── Fetch approved internship payment + registration data ── */
$pay = null;
$ip_check = $conn->query("SHOW TABLES LIKE 'internship_payments'");
if ($ip_check && $ip_check->num_rows > 0) {
    $stmt2 = $conn->prepare("
        SELECT ip.*, ir.registration_number, ir.college_name, ir.department
        FROM internship_payments ip
        JOIN internship_registrations ir ON ip.reg_id = ir.id
        WHERE LOWER(TRIM(ip.email)) = ?
          AND ip.payment_status = 'approved'
        ORDER BY ip.id DESC LIMIT 1
    ");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $pay = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
}

/* ── Derived display values ── */
$id = isset($pay['id']) ? intval($pay['id']) : 0;
$intern_id = "VAI-26-B4-" . $id;

/* $intern_id   = $student['certificate_number'] ?? ($pay['registration_number'] ?? '—');*/
$full_name   = $pay['full_name']        ?? $student['name']              ?? '—';
$internship  = $pay['internship_chosen'] ?? $student['internship_course'] ?? '—';
$reg_no      = $pay['registration_number'] ?? '—';
$department  = $pay['department']        ?? $student['dept']              ?? '—';
$college     = $pay['college_name']      ?? $student['college']           ?? '—';
$issue_date  = !empty($pay['reviewed_at']) ? date('d F Y', strtotime($pay['reviewed_at'])) : date('d F Y');

$payment_done = !empty($pay);
$page_title   = "Offer Letter";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Offer Letter — CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
/* ── Print / preview letter ── */
.letter-wrap {
    max-width: 860px;
    margin: 0 auto;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 4px 32px rgba(0,0,0,.09);
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

/* right blue accent strip */
.letter-wrap::after {
    display: none;
}

.letter-inner {
    position: relative;
    padding: 0;
}

/* Blue right‑side decorative bar (matches the real letter) */
.letter-inner::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0;
    width: 14px;
    background: linear-gradient(180deg, #1e40af 0%, #3b82f6 50%, #93c5fd 100%);
    border-radius: 0 0 0 0;
}

.letter-body {
    padding: 36px 48px 36px 40px;
}

/* ── Header row ── */
.lh-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 28px;
}
.lh-address {
    font-size: .78rem;
    color: #475569;
    line-height: 1.6;
    text-align: right;
}
.lh-logo {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.lh-logo .brand-name {
    font-size: 1.45rem;
    font-weight: 800;
    color: #1e3a8a;
    letter-spacing: .04em;
}
.lh-logo .brand-sub {
    font-size: .7rem;
    color: #64748b;
    letter-spacing: .08em;
    font-weight: 600;
}
.lh-badges {
    display: flex;
    gap: 8px;
    align-items: center;
}
.lh-badge-img {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .55rem;
    font-weight: 700;
    color: #64748b;
    text-align: center;
    line-height: 1.2;
    border: 1px solid #e2e8f0;
}

/* ── Intern ID line ── */
.intern-id-line {
    font-size: .88rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}
.intern-id-line span {
    color: #1e40af;
    font-size: .93rem;
}

/* ── Letter title ── */
.letter-title {
    text-align: center;
    font-size: 1.35rem;
    font-weight: 800;
    color: #1e293b;
    text-decoration: underline;
    text-underline-offset: 5px;
    margin-bottom: 22px;
    letter-spacing: .05em;
    text-transform: uppercase;
}

/* ── Body text ── */
.letter-text {
    font-size: .9rem;
    line-height: 1.85;
    color: #334155;
    text-align: justify;
    margin-bottom: 14px;
}
.letter-text strong {
    color: #1e293b;
}

/* ── Details grid ── */
.letter-details {
    margin: 20px 0 24px;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 6px 16px;
    font-size: .88rem;
}
.ld-key {
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
}
.ld-val {
    color: #334155;
    font-weight: 500;
}

/* ── Signature row ── */
.sig-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-top: 20px;
    flex-wrap: wrap;
    gap: 20px;
}
.sig-block {
    font-size: .88rem;
    line-height: 1.7;
}
.sig-name {
    font-size: 1.1rem;
    font-style: italic;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
}
.seal-block {
    display: flex;
    align-items: center;
    gap: 16px;
}
.seal-circle {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 3px double #1e40af;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: .5rem;
    font-weight: 800;
    color: #1e40af;
    letter-spacing: .08em;
    line-height: 1.4;
    background: #f0f7ff;
}
.qr-placeholder {
    width: 72px;
    height: 72px;
    border: 1.5px dashed #94a3b8;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: .6rem;
    color: #94a3b8;
    gap: 4px;
}

/* ── Footer logos bar ── */
.letter-footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 14px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.footer-cin {
    font-size: .72rem;
    font-weight: 700;
    color: #334155;
    line-height: 1.6;
}
.footer-contact {
    font-size: .72rem;
    color: #475569;
    text-align: right;
    line-height: 1.8;
}
.footer-logos {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.fl-badge {
    font-size: .58rem;
    font-weight: 700;
    color: #64748b;
    background: #e2e8f0;
    padding: 3px 8px;
    border-radius: 5px;
    letter-spacing: .05em;
    text-transform: uppercase;
}

/* ── Top action bar ── */
.action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 10px;
}
.action-bar h1 {
    font-size: 1.3rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ── Locked state ── */
.locked-card {
    background: #fff;
    border-radius: 18px;
    padding: 56px 32px;
    text-align: center;
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
    border: 1px solid #e2e8f0;
    max-width: 500px;
    margin: 0 auto;
}
.locked-icon {
    width: 72px;
    height: 72px;
    background: #fef3c7;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    font-size: 1.8rem;
    color: #d97706;
}

/* ── Print ── */
@media print {
    .sidebar, .main > .topbar, .action-bar, .no-print { display: none !important; }
    .main { margin: 0 !important; padding: 0 !important; }
    .content-wrapper { padding: 0 !important; }
    .letter-wrap { box-shadow: none; border: none; border-radius: 0; }
    .letter-body { padding: 24px 40px; }
}
</style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <div class="action-bar no-print">
                <h1>
                    <i class="fas fa-file-contract" style="color:#1e40af"></i>
                    Offer Letter
                </h1>
                <?php if ($payment_done): ?>
                <div style="display:flex;gap:10px;">
                    <button onclick="window.print()" class="btn btn-ghost btn-sm">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="downloadLetter()" class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$payment_done): ?>
            <!-- ── LOCKED: payment not done ── -->
            <div class="locked-card">
                <div class="locked-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 style="font-size:1.15rem;margin-bottom:8px;color:#1e293b;">Offer Letter Not Available Yet</h2>
                <p style="color:#64748b;font-size:.88rem;line-height:1.7;margin-bottom:24px;">
                    Your offer letter will be generated once your internship payment is <strong>verified and approved</strong> by our team.
                </p>
                <a href="pending_payments.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-credit-card"></i> Complete Payment
                </a>
            </div>

            <?php else: ?>
            <!-- ── OFFER LETTER ── -->
            <div class="letter-wrap" id="offerLetter">
                <div class="letter-inner">
                    <div class="letter-body">

                        <!-- Header -->
                        <div class="lh-row">
                            <div class="lh-logo">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                                    <img src="../assets/images/logo1.png" alt="CourseFlow Logo" style="height:180px;width:180px;">
                                </div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                                <div class="lh-address">
                                    #263, 2nd Floor, 21st Main, 3rd Cross, BTM 2nd<br>
                                    Stage, Bangalore – 560076
                                </div>
                                <div class="lh-badges">
                                    <img src="../assets/images/apsche.png" alt="APSCHE" style="height:88px;width:auto;border-radius:6px;">
                                    <img src="../assets/images/aicte.png" alt="AICTE" style="height:48px;width:auto;border-radius:6px;">
                                </div>
                            </div>
                        </div>

                        <!-- Intern ID -->
                        <div class="intern-id-line">
    INTERN ID : <span><?php echo htmlspecialchars($intern_id); ?></span>
</div>

                        <!-- Title -->
                        <div class="letter-title">Offer Letter</div>

                        <!-- Salutation -->
                        <p class="letter-text">Dear <strong><?php echo htmlspecialchars($full_name); ?></strong>,</p>

                        <!-- Body paragraphs -->
                        <p class="letter-text">
                            We are delighted to welcome you for the internship under our fellowship. This internship is
                            observed by <strong>CourseFlow</strong> as being a learning opportunity for you.
                        </p>
                        <p class="letter-text">
                            In essence, your Short term internship will embrace orientation and give emphasis on learning
                            new skills with a deeper understanding of concepts through hands-on application of the
                            knowledge you gained as an intern. Our team is confident that you will acknowledge your
                            obligation to perform all work allocated to you to the best of your ability within lawful and
                            reasonable direction given to you.
                        </p>
                        <p class="letter-text">
                            We look forward to a worthwhile and fruitful association which will make you equipped for
                            future projects. Wishing you the most enjoyable and truly meaningful internship program
                            experience.
                        </p>
                        <p class="letter-text">Thank you Team CourseFlow, Bangalore.</p>

                        <!-- Details -->
                        <div class="letter-details">
                            <span class="ld-key">Internship:</span>
                            <span class="ld-val"><?php echo htmlspecialchars($internship); ?></span>

                            <span class="ld-key">Reg No:</span>
                            <span class="ld-val"><?php echo htmlspecialchars($reg_no); ?></span>

                            <span class="ld-key">Department:</span>
                            <span class="ld-val"><?php echo htmlspecialchars($department); ?></span>

                            <span class="ld-key">College:</span>
                            <span class="ld-val"><?php echo htmlspecialchars($college); ?></span>
                        </div>

                        <!-- Signature row -->
                        <div style="margin-top:10px;margin-bottom:0px;">
                            <p class="letter-text" style="margin-bottom:0px;">Best regards,</p>
                       

                        <div class="sig-row">
                            <div class="sig-block">
                                <img src="../assets/images/sign.png" alt="Signature" style="height:58px;width:auto;display:block;margin-bottom:4px;">
                                <div style="font-size:.83rem;font-weight:600;color:#334155;">Founder &amp; Chairman</div>
                                <div style="font-size:.78rem;font-weight:700;color:#1e40af;">CourseFlow PVT. LTD.</div>
                            </div>
                            <div class="seal-block">
                                <img src="../assets/images/seal" alt="Company Seal" style="width:150px;height:150px;object-fit:contain;">
                                <!-- <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                  <!--  <img src="../assets/images/QR1.jpeg" alt="QR Code" style="width:72px;height:72px;object-fit:contain;border-radius:6px;">-->
                                    <!--<span style="font-size:.58rem;font-weight:700;color:#475569;text-align:center;">SCAN HERE<br>TO VERIFY</span>-->
                                </div>
                            </div> </div>
                        </div>

                    </div><!-- /letter-body -->

                    <!-- Footer -->
                    <div class="letter-footer">
                        <div class="footer-cin">
                            CIN: XXXXXXXXXXXXXXXXX<br>
                            GSTIN: XXXXXXXXXXXXXXX
                        </div>
                
                        <div class="footer-contact">
                            <i class="fas fa-phone-alt"></i> +91 XXXXXXXXXX, +91 XXXXXXXXXX<br>
                            <i class="fas fa-envelope"></i> hr@yourdomain.com
                        </div>
                    </div>

                </div><!-- /letter-inner -->
            </div><!-- /letter-wrap -->
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
function downloadLetter() {
    // Use browser print-to-PDF as primary download method
    // (no external library dependency needed)
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            .sidebar, .main > *:not(.content-wrapper), .action-bar { display: none !important; }
            body { margin: 0; }
            .content-wrapper { padding: 0 !important; }
            .letter-wrap { box-shadow: none; border: none; border-radius: 0; max-width: 100%; }
        }
    `;
    document.head.appendChild(style);
    window.print();
    setTimeout(() => document.head.removeChild(style), 1000);
}
</script>
</body>
</html>
---- END OF ORIGINAL CODE ---- */ ?>
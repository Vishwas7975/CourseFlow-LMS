<?php
/*
 * student/certificates.php — CourseFlow LMS
 * Certificate status & dynamic PDF generation.
 * Replaces the old static-link version.
 */
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
requireLogin($conn);
requireRole("student");

$email = $_SESSION['email'];

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Check whether a template has been saved by admin
$templateRow = $conn->query("SELECT config_json FROM certificate_templates ORDER BY id DESC LIMIT 1")->fetch_assoc();
$hasTemplate = !empty($templateRow);

// Certificate is "ready" when the student has a certificate_number AND admin saved a template
$hasCertNumber = !empty($student['certificate_number']);
$certReady     = $hasCertNumber && $hasTemplate;

// Backward-compat: if admin manually set a download_link, honour that too
$manualLink = !empty($student['download_link']) ? $student['download_link'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Certificates – CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
/* ── Certificate hero card ─────────────────────────────────── */
.cert-hero {
  background: var(--card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  overflow: hidden;
  margin-bottom: 24px;
}
.cert-hero-header {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 60%, var(--primary-deeper) 100%);
  padding: 28px 32px;
  display: flex;
  align-items: center;
  gap: 18px;
  position: relative;
  overflow: hidden;
}
.cert-hero-header::before {
  content: '';
  position: absolute;
  right: -30px; top: -30px;
  width: 160px; height: 160px;
  border-radius: 50%;
  background: rgba(255,255,255,0.06);
}
.cert-hero-header::after {
  content: '';
  position: absolute;
  right: 60px; bottom: -40px;
  width: 100px; height: 100px;
  border-radius: 50%;
  background: rgba(255,255,255,0.04);
}
.cert-icon-wrap {
  width: 60px; height: 60px;
  border-radius: var(--radius-md);
  background: rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.7rem; color: #fff;
  flex-shrink: 0;
  position: relative; z-index: 1;
}
.cert-hero-header h2 {
  font-size: 1.2rem; font-weight: 800;
  color: #fff; margin-bottom: 4px;
  position: relative; z-index: 1;
}
.cert-hero-header p {
  font-size: 0.84rem;
  color: rgba(255,255,255,0.78);
  margin: 0;
  position: relative; z-index: 1;
}
.cert-hero-body {
  padding: 28px 32px;
}

/* ── Info grid ─────────────────────────────────────────────── */
.cert-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.cert-info-item {
  background: var(--bg);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  transition: border-color var(--transition);
}
.cert-info-item:hover { border-color: var(--primary); }
.cert-info-item .ci-label {
  font-size: 0.7rem; font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.06em;
  margin-bottom: 5px;
  display: flex; align-items: center; gap: 5px;
}
.cert-info-item .ci-label i { color: var(--primary); font-size: 0.65rem; }
.cert-info-item .ci-value {
  font-size: 0.92rem; font-weight: 600;
  color: var(--text-primary);
  word-break: break-word;
}

/* ── Status badges ─────────────────────────────────────────── */
.status-pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 12px; border-radius: var(--radius-full);
  font-size: 0.75rem; font-weight: 700;
}
.status-pill.ready   { background: var(--success-light); color: #16a34a; }
.status-pill.pending { background: var(--warning-light); color: #b45309; }
.status-pill.waiting { background: var(--danger-light);  color: #b91c1c; }

/* ── Action row ────────────────────────────────────────────── */
.cert-actions {
  display: flex; flex-wrap: wrap; gap: 12px;
  padding-top: 20px;
  border-top: 1px solid var(--border-light);
}
.btn-cert-generate {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 11px 22px; border-radius: var(--radius-md);
  font-size: 0.9rem; font-weight: 700;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff; border: none; cursor: pointer;
  box-shadow: var(--shadow-blue);
  transition: all var(--transition);
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.btn-cert-generate:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--primary-dark), var(--primary-deeper));
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(77,168,255,0.4);
}
.btn-cert-generate:disabled {
  opacity: 0.55; cursor: not-allowed; transform: none;
}
.btn-cert-link {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 11px 22px; border-radius: var(--radius-md);
  font-size: 0.9rem; font-weight: 700;
  background: linear-gradient(135deg, var(--success), #16a34a);
  color: #fff; text-decoration: none;
  box-shadow: 0 2px 8px rgba(34,197,94,0.28);
  transition: all var(--transition);
}
.btn-cert-link:hover { opacity: 0.9; color: #fff; transform: translateY(-1px); }

/* ── Alert info box ────────────────────────────────────────── */
.cert-alert {
  display: flex; align-items: flex-start; gap: 14px;
  border-radius: var(--radius-md); padding: 16px 20px;
  margin-top: 20px;
}
.cert-alert.info  { background: var(--primary-light);  border: 1px solid rgba(77,168,255,0.3); }
.cert-alert.warn  { background: var(--warning-light);  border: 1px solid rgba(245,158,11,0.3); }
.cert-alert .ca-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 2px; }
.cert-alert.info  .ca-icon { color: var(--primary-dark); }
.cert-alert.warn  .ca-icon { color: #b45309; }
.cert-alert h4    { font-size: 0.88rem; font-weight: 700; margin-bottom: 3px; color: var(--text-primary); }
.cert-alert p     { font-size: 0.8rem; color: var(--text-secondary); margin: 0; }

/* ── Render zone (off-screen, for html2canvas) ─────────────── */
#certRenderZone {
  position: fixed; left: -9999px; top: 0;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  pointer-events: none;
}

/* ── Spinner animation ─────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
.fa-spin { animation: spin 1s linear infinite; }

/* ── Mobile responsive ─────────────────────────────────────── */
@media (max-width: 768px) {
  .cert-hero-header { padding: 20px 18px; gap: 14px; }
  .cert-hero-body   { padding: 18px; }
  .cert-info-grid   { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
  .cert-actions     { flex-direction: column; }
  .btn-cert-generate,
  .btn-cert-link    { width: 100%; justify-content: center; }
}
</style>
</head>
<body>
<div class="dashboard">
  <?php include("../includes/sidebar.php"); ?>
  <div class="main">
    <?php include("../includes/topbar.php"); ?>
    <div class="content-wrapper">

      <div class="page-header">
        <h1><i class="fas fa-certificate"></i> My Certificate</h1>
        <p>View and download your internship completion certificate</p>
      </div>

      <div class="cert-hero">

        <!-- Header -->
        <div class="cert-hero-header">
          <div class="cert-icon-wrap">
            <i class="fas fa-certificate"></i>
          </div>
          <div>
            <h2><?php echo htmlspecialchars($student['name'] ?? 'Student'); ?></h2>
            <p><?php echo htmlspecialchars($student['internship_course'] ?? 'Internship Program'); ?></p>
          </div>
        </div>

        <!-- Body -->
        <div class="cert-hero-body">

          <!-- Info grid -->
          <div class="cert-info-grid">
            <div class="cert-info-item">
              <div class="ci-label"><i class="fas fa-hashtag"></i> Certificate No</div>
              <div class="ci-value"><?php echo $hasCertNumber ? htmlspecialchars($student['certificate_number']) : '—'; ?></div>
            </div>
            <div class="cert-info-item">
              <div class="ci-label"><i class="fas fa-graduation-cap"></i> Course</div>
              <div class="ci-value"><?php echo htmlspecialchars($student['internship_course'] ?? '—'); ?></div>
            </div>
            <div class="cert-info-item">
              <div class="ci-label"><i class="fas fa-university"></i> College</div>
              <div class="ci-value"><?php echo htmlspecialchars($student['college'] ?? '—'); ?></div>
            </div>
            <div class="cert-info-item">
              <div class="ci-label"><i class="fas fa-calendar-plus"></i> Start Date</div>
              <div class="ci-value"><?php echo !empty($student['start_date']) ? date('d M Y', strtotime($student['start_date'])) : '—'; ?></div>
            </div>
            <div class="cert-info-item">
              <div class="ci-label"><i class="fas fa-calendar-check"></i> End Date</div>
              <div class="ci-value"><?php echo !empty($student['end_date']) ? date('d M Y', strtotime($student['end_date'])) : '—'; ?></div>
            </div>
            <div class="cert-info-item">
              <div class="ci-label"><i class="fas fa-info-circle"></i> Status</div>
              <div class="ci-value">
                <?php if ($certReady || $manualLink): ?>
                  <span class="status-pill ready"><i class="fas fa-check-circle"></i> Completed</span>
                <?php elseif ($hasCertNumber): ?>
                  <span class="status-pill pending"><i class="fas fa-clock"></i> Template Pending</span>
                <?php else: ?>
                  <span class="status-pill waiting"><i class="fas fa-hourglass-half"></i> In Progress</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="cert-actions">
            <?php if ($manualLink): ?>
              <a href="<?php echo htmlspecialchars($manualLink); ?>" target="_blank" class="btn-cert-link">
                <i class="fas fa-download"></i> Download Certificate
              </a>
            <?php endif; ?>

            <?php if ($certReady): ?>
              <button class="btn-cert-generate" id="generateBtn" onclick="generateCertificate()">
                <i class="fas fa-file-pdf"></i> Generate &amp; Download PDF
              </button>
            <?php else: ?>
              <button class="btn-cert-generate" disabled>
                <i class="fas fa-file-pdf"></i> Generate Certificate
              </button>
            <?php endif; ?>
          </div>

          <!-- Status info alerts -->
          <?php if (!$hasCertNumber): ?>
          <div class="cert-alert info">
            <i class="fas fa-info-circle ca-icon"></i>
            <div>
              <h4>Certificate not yet issued</h4>
              <p>Your certificate will be available once your internship program is completed. Contact admin if you believe this is a mistake.</p>
            </div>
          </div>
          <?php elseif (!$hasTemplate && !$manualLink): ?>
          <div class="cert-alert warn">
            <i class="fas fa-tools ca-icon"></i>
            <div>
              <h4>Certificate template is being set up</h4>
              <p>Your certificate number is assigned. The admin is finalizing the certificate design — please check back soon.</p>
            </div>
          </div>
          <?php endif; ?>

        </div><!-- /cert-hero-body -->
      </div><!-- /cert-hero -->

    </div><!-- /content-wrapper -->
  </div><!-- /main -->
</div><!-- /dashboard -->

<!-- Off-screen render zone for html2canvas -->
<div id="certRenderZone"></div>

<script>
const { jsPDF } = window.jspdf;

const studentData = {
  candidate_name:     <?php echo json_encode($student['name']               ?? ''); ?>,
  email:              <?php echo json_encode($student['email']              ?? ''); ?>,
  mobile_no:          <?php echo json_encode($student['mobile']             ?? ''); ?>,
  college:            <?php echo json_encode($student['college']            ?? ''); ?>,
  certificate_number: <?php echo json_encode($student['certificate_number'] ?? ''); ?>,
  internship_course:  <?php echo json_encode($student['internship_course']  ?? ''); ?>,
  start_date:         <?php echo json_encode($student['start_date']         ?? ''); ?>,
  end_date:           <?php echo json_encode($student['end_date']           ?? ''); ?>,
};

function formatDate(str) {
  if (!str) return '';
  const d = new Date(str);
  return isNaN(d) ? str : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
}

async function generateCertificate() {
  const btn = document.getElementById('generateBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

  try {
    // 1. Fetch template config from DB
    const res = await fetch('../admin/save_template.php');
    const d   = await res.json();
    if (!d.success) {
      alert('Certificate template not found. Please contact admin.');
      return;
    }

    const cfg = d.config;

    // 2. Build the certificate in the hidden render zone
    const zone = document.getElementById('certRenderZone');
    zone.innerHTML = '';
    zone.style.backgroundImage = `url(${cfg.background})`;
    zone.style.width  = (cfg.canvasWidth  || 860) + 'px';
    zone.style.height = (cfg.canvasHeight || 600) + 'px';

    cfg.fields.forEach(f => {
      const el = document.createElement('div');
      el.style.cssText = `position:absolute;left:${f.x}px;top:${f.y}px;font-size:${f.fontSize}px;font-family:${f.fontFamily};font-weight:${f.fontWeight};color:${f.color};text-align:${f.textAlign || 'left'};white-space:nowrap`;
      let val = studentData[f.type] || '';
      if ((f.type === 'start_date' || f.type === 'end_date') && val) val = formatDate(val);
      el.textContent = val;
      zone.appendChild(el);
    });

    // Small delay so fonts render
    await new Promise(r => setTimeout(r, 300));

    // 3. Capture with html2canvas
    const canvas = await html2canvas(zone, { scale: 2, useCORS: true, allowTaint: true, logging: false });

    // 4. Export as PDF
    const orientation = (cfg.canvasWidth || 860) > (cfg.canvasHeight || 600) ? 'landscape' : 'portrait';
    const pdf = new jsPDF(orientation, 'px', 'a4');
    const pw  = pdf.internal.pageSize.getWidth();
    const ph  = pdf.internal.pageSize.getHeight();
    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, pw, ph);
    pdf.save(`certificate_${studentData.certificate_number || 'download'}.pdf`);

  } catch (e) {
    alert('Error generating certificate: ' + e.message);
    console.error(e);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-pdf"></i> Generate &amp; Download PDF';
  }
}
</script>
</body>
</html>
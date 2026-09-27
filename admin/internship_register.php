<?php
/**
 * internship_registrations.php — CourseFlow
 * Admin panel: view all internship registrations.
 * Links to payment status for each student.
 */

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';

requireLogin($conn);
requireRole('admin');

/* ── CSV Export ── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $conn->query("SELECT r.id, r.full_name, r.registration_number, r.email, r.mobile_number,
                                 r.department, r.college_name, r.admission_type, r.internship_chosen,
                                 r.source, r.ip_address, r.registered_at,
                                 IFNULL(p.payment_status,'not_paid') as payment_status,
                                 IFNULL(p.utr_number,'—') as utr_number
                          FROM internship_registrations r
                          LEFT JOIN internship_payments p ON p.reg_id = r.id
                          ORDER BY r.registered_at DESC")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="internship_registrations_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Reg No','Email','Mobile','Dept','College','Admission','Internship','Payment','UTR','Registered At']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['full_name'], $r['registration_number'], $r['email'],
                       $r['mobile_number'], $r['department'], $r['college_name'],
                       $r['admission_type'], $r['internship_chosen'],
                       $r['payment_status'], $r['utr_number'], $r['registered_at']]);
    }
    fclose($out); exit;
}

/* ── Filters ── */
$filter = $_GET['filter'] ?? 'all'; // all | paid | pending | not_paid
$search = trim($_GET['q'] ?? '');
$domain = trim($_GET['domain'] ?? '');

$where  = "WHERE 1=1";
$params = []; $types  = "";

if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= " AND (r.full_name LIKE ? OR r.email LIKE ? OR r.registration_number LIKE ? OR r.mobile_number LIKE ? OR r.college_name LIKE ?)";
    $params = array_merge($params, [$like,$like,$like,$like,$like]);
    $types .= "sssss";
}
if ($domain !== '') {
    $where .= " AND r.internship_chosen = ?";
    $params[] = $domain; $types .= "s";
}
if ($filter === 'paid') {
    $where .= " AND p.payment_status = 'approved'";
} elseif ($filter === 'pending') {
    $where .= " AND p.payment_status = 'pending'";
} elseif ($filter === 'not_paid') {
    $where .= " AND p.id IS NULL";
}

$sql = "SELECT r.*, IFNULL(p.payment_status,'not_paid') as pay_status,
               p.id as payment_id, p.utr_number, p.amount, p.submitted_at as paid_at
        FROM internship_registrations r
        LEFT JOIN internship_payments p ON p.reg_id = r.id
        {$where}
        ORDER BY r.registered_at DESC";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$regs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Stats ── */
$totals = ['all' => 0, 'paid' => 0, 'pending' => 0, 'not_paid' => 0];
$sq = $conn->query("SELECT IFNULL(p.payment_status,'not_paid') as ps, COUNT(*) as c
                    FROM internship_registrations r
                    LEFT JOIN internship_payments p ON p.reg_id = r.id
                    GROUP BY ps");
while ($row = $sq->fetch_assoc()) {
    $key = $row['ps'] === 'approved' ? 'paid' : ($row['ps'] === 'pending' ? 'pending' : 'not_paid');
    $totals[$key] += (int)$row['c'];
    $totals['all'] += (int)$row['c'];
}

/* ── Domain list for filter dropdown ── */
$domains = [];
$dq = $conn->query("SELECT DISTINCT internship_chosen FROM internship_registrations ORDER BY internship_chosen");
while ($d = $dq->fetch_assoc()) { $domains[] = $d['internship_chosen']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Internship Registrations — CourseFlow Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
*,*::before,*::after{box-sizing:border-box;}
body{font-family:'Plus Jakarta Sans',sans-serif;}

.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px;}
.page-header h1{font-size:1.4rem;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:10px;margin:0;}
.ph-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#1e40af,#3b82f6);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;}
.page-header p{color:#64748b;font-size:.82rem;margin:3px 0 0;}
.btn-export{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border-radius:10px;font-weight:700;font-size:.83rem;text-decoration:none;box-shadow:0 3px 10px rgba(16,185,129,.3);transition:all .2s;}
.btn-export:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(16,185,129,.4);}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
.stat-card{background:#fff;border-radius:14px;padding:16px 18px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.04);cursor:pointer;transition:all .18s;text-decoration:none;display:block;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.08);}
.stat-card.active{border-color:var(--sc);box-shadow:0 0 0 3px color-mix(in srgb,var(--sc) 18%,transparent);}
.sc-label{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-bottom:6px;}
.sc-val{font-size:1.8rem;font-weight:800;color:#1e293b;line-height:1;}
.sc-sub{font-size:.72rem;color:#94a3b8;margin-top:4px;}
.sc-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px;}

/* Filters */
.filters-bar{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;min-width:220px;}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.85rem;}
.search-inp{width:100%;padding:9px 12px 9px 34px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;color:#1e293b;background:#f8fafc;outline:none;transition:border-color .2s;}
.search-inp:focus{border-color:#3b82f6;background:#fff;}
.domain-sel{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.83rem;color:#475569;background:#f8fafc;outline:none;cursor:pointer;min-width:180px;}
.domain-sel:focus{border-color:#3b82f6;}
.filter-count{font-size:.78rem;color:#64748b;white-space:nowrap;}

/* Table */
.table-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;}
.table-head{padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px;}
.table-head h3{font-size:.9rem;font-weight:700;color:#1e293b;margin:0;}
.table-scroll{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{padding:10px 14px;text-align:left;font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;background:#f8fafc;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#f8fafc;}
tbody td{padding:11px 14px;font-size:.83rem;color:#1e293b;vertical-align:middle;}
.td-name{font-weight:700;}
.td-reg{font-family:monospace;font-size:.78rem;color:#64748b;}
.td-email{color:#3b82f6;font-size:.8rem;}
.td-mobile{font-size:.82rem;}
.td-domain{font-size:.78rem;font-weight:600;}
.td-college{font-size:.77rem;color:#64748b;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.td-date{font-size:.75rem;color:#94a3b8;white-space:nowrap;}

/* Pay badge */
.pay-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:.72rem;font-weight:700;}
.pay-badge.approved{background:#dcfce7;color:#14532d;}
.pay-badge.pending{background:#fef3c7;color:#92400e;}
.pay-badge.not_paid{background:#f1f5f9;color:#64748b;}
.pay-badge.rejected{background:#ffe4e6;color:#881337;}

/* Actions */
.td-actions{display:flex;align-items:center;gap:6px;}
.act-btn{width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;transition:all .18s;text-decoration:none;cursor:pointer;}
.act-btn.view{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
.act-btn.view:hover{background:#dbeafe;}
.act-btn.pay{background:#f0fdf4;color:#14532d;border:1px solid #bbf7d0;}
.act-btn.pay:hover{background:#dcfce7;}

/* Empty state */
.empty{text-align:center;padding:60px 20px;color:#94a3b8;}
.empty i{font-size:2.5rem;margin-bottom:14px;display:block;}
.empty p{font-size:.9rem;}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;}
.modal-overlay.show{display:flex;}
.modal-box{background:#fff;border-radius:20px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.2);}
.modal-head{padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;}
.modal-head h3{font-size:1rem;font-weight:800;color:#1e293b;margin:0;}
.modal-close{width:32px;height:32px;border-radius:8px;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:.85rem;transition:background .15s;}
.modal-close:hover{background:#e2e8f0;}
.modal-body{padding:20px 24px;}
.mrow{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;}
.mico{width:32px;height:32px;border-radius:9px;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.mlbl{font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.mval{font-size:.88rem;font-weight:600;color:#1e293b;}
.modal-footer{padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end;}
.btn-modal{padding:9px 20px;border-radius:9px;font-size:.84rem;font-weight:700;border:none;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
.btn-modal.primary{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;}
.btn-modal.secondary{background:#f1f5f9;color:#64748b;}

@media(max-width:860px){.stats-row{grid-template-columns:1fr 1fr;}}
@media(max-width:500px){.stats-row{grid-template-columns:1fr 1fr;}.page-header{flex-direction:column;align-items:flex-start;}}
</style>
</head>
<body>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>

<div class="main-content">
<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php'; ?>

<div class="content-wrap" style="padding:28px 28px 60px;">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><span class="ph-icon"><i class="fas fa-users"></i></span> Internship Registrations</h1>
      <p>All students who have registered for the Summer Short Term Internship program</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a href="admin_internship_payments.php" class="btn-export" style="background:linear-gradient(135deg,#1e40af,#3b82f6);">
        <i class="fas fa-credit-card"></i> View Payments
      </a>
      <a href="?export=csv<?php echo $search ? '&q=' . urlencode($search) : ''; ?><?php echo $domain ? '&domain=' . urlencode($domain) : ''; ?>" class="btn-export">
        <i class="fas fa-file-csv"></i> Export CSV
      </a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <a href="?" class="stat-card<?php echo $filter==='all'?' active':''; ?>" style="--sc:#3b82f6;">
      <div class="sc-label"><span class="sc-dot" style="background:#3b82f6;"></span>Total</div>
      <div class="sc-val"><?php echo $totals['all']; ?></div>
      <div class="sc-sub">All registrations</div>
    </a>
    <a href="?filter=paid" class="stat-card<?php echo $filter==='paid'?' active':''; ?>" style="--sc:#16a34a;">
      <div class="sc-label"><span class="sc-dot" style="background:#16a34a;"></span>Payment Approved</div>
      <div class="sc-val"><?php echo $totals['paid']; ?></div>
      <div class="sc-sub">Enrolled students</div>
    </a>
    <a href="?filter=pending" class="stat-card<?php echo $filter==='pending'?' active':''; ?>" style="--sc:#d97706;">
      <div class="sc-label"><span class="sc-dot" style="background:#d97706;"></span>Payment Pending</div>
      <div class="sc-val"><?php echo $totals['pending']; ?></div>
      <div class="sc-sub">Awaiting review</div>
    </a>
    <a href="?filter=not_paid" class="stat-card<?php echo $filter==='not_paid'?' active':''; ?>" style="--sc:#94a3b8;">
      <div class="sc-label"><span class="sc-dot" style="background:#94a3b8;"></span>Not Paid</div>
      <div class="sc-val"><?php echo $totals['not_paid']; ?></div>
      <div class="sc-sub">No payment submitted</div>
    </a>
  </div>

  <!-- Filters -->
  <div class="filters-bar">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" class="search-inp" id="searchInp" placeholder="Search name, email, reg no, mobile, college…"
             value="<?php echo htmlspecialchars($search); ?>" oninput="doSearch()"/>
    </div>
    <select class="domain-sel" id="domainSel" onchange="doSearch()">
      <option value="">All Domains</option>
      <?php foreach ($domains as $d): ?>
      <option value="<?php echo htmlspecialchars($d); ?>"<?php echo $domain===$d?' selected':''; ?>><?php echo htmlspecialchars($d); ?></option>
      <?php endforeach; ?>
    </select>
    <span class="filter-count"><?php echo count($regs); ?> result<?php echo count($regs)!==1?'s':''; ?></span>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-head">
      <h3><i class="fas fa-list" style="color:#3b82f6;margin-right:7px;"></i> Registration Records</h3>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Reg No</th>
            <th>Mobile</th>
            <th>Domain</th>
            <th>College</th>
            <th>Admission</th>
            <th>Payment</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($regs)): ?>
          <tr><td colspan="10"><div class="empty"><i class="fas fa-inbox"></i><p>No registrations found.</p></div></td></tr>
        <?php else: foreach ($regs as $r):
          $ps = $r['pay_status'];
          $badge_map = ['approved'=>'approved','pending'=>'pending','rejected'=>'rejected','not_paid'=>'not_paid'];
          $badge_cls = $badge_map[$ps] ?? 'not_paid';
          $badge_lbl = ['approved'=>'✓ Approved','pending'=>'⏳ Pending','rejected'=>'✗ Rejected','not_paid'=>'— Not Paid'][$ps] ?? $ps;
          $reg_id_fmt = '#VS-INT-' . str_pad($r['id'], 4, '0', STR_PAD_LEFT);
        ?>
          <tr>
            <td style="color:#94a3b8;font-size:.75rem;font-weight:700;"><?php echo $reg_id_fmt; ?></td>
            <td>
              <div class="td-name"><?php echo htmlspecialchars($r['full_name']); ?></div>
              <div class="td-email"><?php echo htmlspecialchars($r['email']); ?></div>
            </td>
            <td><span class="td-reg"><?php echo htmlspecialchars($r['registration_number']); ?></span></td>
            <td class="td-mobile"><?php echo htmlspecialchars($r['mobile_number']); ?></td>
            <td><span class="td-domain"><?php echo htmlspecialchars($r['internship_chosen']); ?></span></td>
            <td><span class="td-college" title="<?php echo htmlspecialchars($r['college_name']); ?>"><?php echo htmlspecialchars($r['college_name']); ?></span></td>
            <td style="font-size:.78rem;"><?php echo htmlspecialchars($r['admission_type']); ?></td>
            <td><span class="pay-badge <?php echo $badge_cls; ?>"><?php echo $badge_lbl; ?></span></td>
            <td class="td-date"><?php echo date('d M Y', strtotime($r['registered_at'])); ?><br><span style="color:#cbd5e1;"><?php echo date('h:i A', strtotime($r['registered_at'])); ?></span></td>
            <td>
              <div class="td-actions">
                <button class="act-btn view" title="View Details"
                        onclick="openModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>)">
                  <i class="fas fa-eye"></i>
                </button>
                <a class="act-btn pay" title="View Payment"
                   href="../internship/internship_payment.php?reg_id=<?php echo $r['id']; ?>" target="_blank">
                  <i class="fas fa-credit-card"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /content-wrap -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
</div><!-- /main-content -->

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-user-circle" style="color:#3b82f6;margin-right:8px;"></i> Registration Details</h3>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer">
      <button class="btn-modal secondary" onclick="closeModal()">Close</button>
      <a class="btn-modal primary" id="modalPayLink" href="#" target="_blank">
        <i class="fas fa-credit-card"></i> View Payment Page
      </a>
    </div>
  </div>
</div>

<script>
function doSearch(){
  var q  = document.getElementById('searchInp').value;
  var d  = document.getElementById('domainSel').value;
  var url = '?filter=<?php echo urlencode($filter); ?>';
  if(q)  url += '&q='      + encodeURIComponent(q);
  if(d)  url += '&domain=' + encodeURIComponent(d);
  window.location.href = url;
}
document.getElementById('searchInp').addEventListener('keydown', function(e){
  if(e.key === 'Enter') doSearch();
});

function openModal(r){
  var payLabels = {approved:'✓ Approved',pending:'⏳ Pending',rejected:'✗ Rejected',not_paid:'— Not Paid'};
  var payColors = {approved:'#14532d',pending:'#92400e',rejected:'#881337',not_paid:'#64748b'};
  var payBg    = {approved:'#dcfce7',pending:'#fef3c7',rejected:'#ffe4e6',not_paid:'#f1f5f9'};
  var ps = r.pay_status || 'not_paid';
  var idFmt = '#VS-INT-' + String(r.id).padStart(4,'0');

  document.getElementById('modalPayLink').href = '../internship/internship_payment.php?reg_id=' + r.id;

  document.getElementById('modalBody').innerHTML = `
    <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:12px;padding:16px 18px;margin-bottom:18px;display:flex;align-items:center;gap:14px;">
      <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;color:#fff;">${r.full_name[0].toUpperCase()}</div>
      <div>
        <div style="font-weight:800;font-size:1rem;color:#fff;">${escHtml(r.full_name)}</div>
        <div style="font-size:.78rem;color:rgba(255,255,255,.7);">${escHtml(r.email)}</div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.5);margin-top:2px;">${idFmt}</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      ${mrow('fa-id-badge','Registration No',r.registration_number)}
      ${mrow('fa-phone','Mobile',r.mobile_number)}
      ${mrow('fa-graduation-cap','Department',r.department)}
      ${mrow('fa-user-tag','Admission Type',r.admission_type)}
      ${mrow('fa-university','College',r.college_name,'full')}
      ${mrow('fa-briefcase','Internship Domain',r.internship_chosen,'full')}
      ${mrow('fa-calendar','Registered At', new Date(r.registered_at).toLocaleString('en-IN'),'full')}
    </div>

    <div style="margin-top:14px;padding:12px 14px;background:${payBg[ps]};border-radius:10px;display:flex;align-items:center;gap:10px;">
      <i class="fas fa-credit-card" style="color:${payColors[ps]};"></i>
      <div>
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:${payColors[ps]};opacity:.7;">Payment Status</div>
        <div style="font-weight:800;color:${payColors[ps]};">${payLabels[ps]||ps}</div>
        ${r.utr_number && r.utr_number !== '—' ? `<div style="font-size:.72rem;color:${payColors[ps]};opacity:.7;margin-top:2px;font-family:monospace;">UTR: ${escHtml(r.utr_number)}</div>` : ''}
      </div>
    </div>
  `;
  document.getElementById('detailModal').classList.add('show');
}

function mrow(icon, label, val, span){
  if(!val) val = '—';
  return `<div style="${span==='full'?'grid-column:1/-1;':''}background:#f8fafc;border-radius:10px;padding:10px 12px;">
    <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:3px;"><i class="fas ${icon}" style="margin-right:4px;"></i>${label}</div>
    <div style="font-size:.87rem;font-weight:600;color:#1e293b;">${escHtml(String(val))}</div>
  </div>`;
}

function escHtml(s){
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function closeModal(){
  document.getElementById('detailModal').classList.remove('show');
}
document.getElementById('detailModal').addEventListener('click', function(e){
  if(e.target === this) closeModal();
});
</script>
</body>
</html>
<?php
/**
 * topbar.php — Shared topbar for ALL roles (admin, student, trainer)
 * Renders the top navigation bar with dynamic notification bell.
 */

if (!isset($conn)) { include(__DIR__ . "/../config/db.php"); }

$topbar_user_id  = $_SESSION['user_id'] ?? 0;
$topbar_role     = $_SESSION['role']    ?? '';
$topbar_name     = $_SESSION['name']    ?? 'User';
$topbar_is_admin = ($topbar_role === 'admin');

/* ── Handle AJAX mark-all-read ── */
if (isset($_GET['notif_action']) && $_GET['notif_action'] === 'mark_read') {
    if ($topbar_is_admin) {
        $conn->query("UPDATE notifications SET is_read=1 WHERE for_role='admin' AND is_read=0");
    } elseif ($topbar_role === 'trainer') {
        $stmt_mr = $conn->prepare("UPDATE notifications SET is_read=1 WHERE for_role='trainer' AND for_user_id=? AND is_read=0");
        $stmt_mr->bind_param("i", $topbar_user_id);
        $stmt_mr->execute();
    } else {
        $stmt_mr = $conn->prepare("UPDATE notifications SET is_read=1 WHERE for_role='student' AND for_user_id=? AND is_read=0");
        $stmt_mr->bind_param("i", $topbar_user_id);
        $stmt_mr->execute();
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

/* ── One-time fix: update old internship registration notification types ── */
$conn->query("UPDATE notifications SET type='new_registration' WHERE type='internship' AND link=''");

/* ── Handle AJAX mark-single-read ── */
if (isset($_GET['notif_action']) && $_GET['notif_action'] === 'mark_one' && isset($_GET['notif_id'])) {
    $nid = (int)$_GET['notif_id'];
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid");
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

/* ── Fetch notifications for current role ── */
if ($topbar_is_admin) {
    $stmt_notif = $conn->prepare(
        "SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE for_role='admin'
         ORDER BY created_at DESC LIMIT 20"
    );
    $stmt_notif->execute();
} elseif ($topbar_role === 'trainer') {
    $stmt_notif = $conn->prepare(
        "SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE for_role='trainer' AND for_user_id=?
         ORDER BY created_at DESC LIMIT 20"
    );
    $stmt_notif->bind_param("i", $topbar_user_id);
    $stmt_notif->execute();
} else {
    $stmt_notif = $conn->prepare(
        "SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE for_role='student' AND for_user_id=?
         ORDER BY created_at DESC LIMIT 20"
    );
    $stmt_notif->bind_param("i", $topbar_user_id);
    $stmt_notif->execute();
}

$notif_result = $stmt_notif->get_result();
$all_notifs   = $notif_result->fetch_all(MYSQLI_ASSOC);
$unread_count = count(array_filter($all_notifs, fn($n) => !$n['is_read']));

/* ── Time-ago helper ── */
function notif_time_ago($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days > 6)  return $then->format('d M Y');
    if ($diff->days >= 1) return $diff->days . 'd ago';
    if ($diff->h  >= 1)   return $diff->h  . 'h ago';
    if ($diff->i  >= 1)   return $diff->i  . 'm ago';
    return 'Just now';
}

/* ── Icon picker helper ── */
function notif_icon($type, $title, $message) {
    $t = strtolower($title . ' ' . $message);
    if (strpos($t, 'approved') !== false) return ['icon' => 'fa-check-circle',  'class' => 'success'];
    if (strpos($t, 'rejected') !== false) return ['icon' => 'fa-times-circle',  'class' => 'danger'];
    if (strpos($type, 'note') !== false)  return ['icon' => 'fa-sticky-note',   'class' => 'note'];
    if (strpos($type, 'live_class') !== false) return ['icon' => 'fa-video',    'class' => 'live'];
    if (strpos($type, 'project') !== false) return ['icon' => 'fa-folder-open', 'class' => 'project'];
    if (strpos($type, 'payment') !== false) return ['icon' => 'fa-money-bill-wave', 'class' => 'payment'];
    return ['icon' => 'fa-bell', 'class' => 'info'];
}
?>

<div class="topbar" id="mainTopbar">
    <div class="topbar-left">
        <!-- Hamburger toggle: only visible on mobile. Triggers sidebar open/close via JS. -->
        <button class="sidebar-hamburger" id="sidebarHamburger" aria-label="Toggle menu" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

    </div>

    <div class="topbar-right">


        <!-- Notification Bell -->
        <div class="notif-wrap" id="notifWrap">
            <button class="topbar-icon-btn notif-bell" id="notifBell" aria-label="Notifications" onclick="toggleNotifPanel(event)">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                <span class="notif-badge" id="notifBadge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-panel" id="notifPanel">

                <!-- Panel Header -->
                <div class="notif-panel-header">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span class="notif-panel-title">Notifications</span>
                        <?php if ($unread_count > 0): ?>
                        <span class="notif-panel-badge" id="notifPanelBadge"><?php echo $unread_count; ?> new</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unread_count > 0): ?>
                    <button class="notif-mark-all" id="markAllBtn" onclick="markAllRead()">
                        <i class="fas fa-check-double"></i> Mark all read
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Notification List -->
                <div class="notif-list" id="notifList">
                    <?php if (empty($all_notifs)): ?>
                    <div class="notif-empty">
                        <div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
                        <p>No notifications yet</p>
                        <span>You're all caught up!</span>
                    </div>
                    <?php else: ?>
                        <?php foreach ($all_notifs as $n):
                            $ni   = notif_icon($n['type'] ?? '', $n['title'], $n['message']);
                            $link = !empty($n['link']) ? $n['link'] : '#';
                        ?>
                        <a class="notif-item <?php echo $n['is_read'] ? 'read' : 'unread'; ?>"
                           href="<?php echo htmlspecialchars($link); ?>"
                           onclick="markOneRead(<?php echo (int)$n['id']; ?>, event)"
                           data-id="<?php echo (int)$n['id']; ?>"
                           data-type="<?php echo htmlspecialchars($n['type'] ?? ''); ?>">
                            <div class="notif-icon-wrap <?php echo $ni['class']; ?>">
                                <i class="fas <?php echo $ni['icon']; ?>"></i>
                            </div>
                            <div class="notif-item-body">
                                <div class="notif-item-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="notif-item-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                                <div class="notif-item-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo notif_time_ago($n['created_at']); ?>
                                </div>
                            </div>
                            <?php if (!$n['is_read']): ?>
                            <div class="notif-unread-dot"></div>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <!-- NO footer / payment link — notifications only -->

            </div><!-- /notif-panel -->
        </div><!-- /notif-wrap -->

        <!-- User Avatar Dropdown -->
        <div class="user-wrap" id="userWrap" style="position:relative;">
            <div class="topbar-user" id="userAvatarBtn" onclick="toggleUserPanel(event)" style="cursor:pointer; padding: 0;">
                <div class="topbar-avatar">
                    <?php echo strtoupper(mb_substr($topbar_name, 0, 1)); ?>
                </div>
            </div>

            <!-- Dropdown Panel -->
            <div class="user-panel" id="userPanel">
                <div class="user-panel-header">
                    <span class="user-name"><?php echo htmlspecialchars($topbar_name); ?></span>
                    <span class="user-role"><?php echo ucfirst(htmlspecialchars($topbar_role)); ?></span>
                </div>
                <div class="user-panel-links">
                    <a href="../<?php echo $topbar_role; ?>/profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                    <hr style="border:none; border-top:1px solid #f1f5f9; margin: 5px 0;">
                    <a href="../logout.php" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Sidebar overlay: dark backdrop shown on mobile when sidebar is open.
     Clicking it closes the sidebar without touching any page logic. -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<style>
/* ── Topbar ── */
.topbar {
    height: 64px;
    background: var(--card, #fff);
    border-bottom: 1px solid var(--border-light, #e8edf5);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px;
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 1px 0 rgba(0,0,0,0.04);
}
.topbar-left  { display:flex; align-items:center; gap:12px; }
.topbar-right { display:flex; align-items:center; gap:16px; }
.topbar-page-title { font-size:0.9rem; font-weight:600; color:var(--text-secondary,#64748b); }

/* ── Bell and topbar icon buttons ── */
.notif-wrap { position:relative; }
.topbar-icon-btn {
    width:38px; height:38px; border-radius:50%;
    background:transparent;
    border:none;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:var(--text-secondary,#64748b);
    font-size:1.05rem; position:relative;
    transition:all .2s; outline:none; text-decoration:none;
}
.topbar-icon-btn:hover,
.topbar-icon-btn.active {
    background:var(--bg-alt,#f1f5f9);
    color:var(--text-primary,#1e293b);
}

/* ── Unread badge on bell ── */
.notif-badge {
    position:absolute; top:-5px; right:-5px;
    min-width:18px; height:18px;
    background:#ef4444; color:#fff;
    font-size:0.62rem; font-weight:800;
    border-radius:999px; display:flex; align-items:center; justify-content:center;
    padding:0 4px; border:2px solid #fff; line-height:1;
    animation:notifPulse 2s ease-in-out infinite;
}
@keyframes notifPulse {
    0%,100% { transform:scale(1);   box-shadow:0 0 0 0   rgba(239,68,68,.4); }
    50%      { transform:scale(1.05);box-shadow:0 0 0 5px rgba(239,68,68,0); }
}

/* ── Dropdown panel ── */
.notif-panel {
    position:absolute; top:calc(100% + 10px); right:0;
    width:360px; background:#fff; border-radius:20px;
    box-shadow:0 20px 60px rgba(0,0,0,.14), 0 4px 16px rgba(0,0,0,.07);
    border:1px solid rgba(0,0,0,.06);
    opacity:0; visibility:hidden;
    transform:translateY(-8px) scale(.97);
    transform-origin:top right;
    transition:all .22s cubic-bezier(.4,0,.2,1);
    z-index:9999; overflow:hidden;
}
.notif-panel.open { opacity:1; visibility:visible; transform:translateY(0) scale(1); }

/* ── Panel header ── */
.notif-panel-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px 14px;
    background:linear-gradient(135deg,#1e40af,#3b82f6);
    border-bottom:1px solid rgba(255,255,255,.1);
}
.notif-panel-title  { font-size:.95rem; font-weight:800; color:#fff; }
.notif-panel-badge  {
    background:rgba(255,255,255,.25); color:#fff;
    font-size:.68rem; font-weight:700;
    padding:2px 8px; border-radius:999px;
}
.notif-mark-all {
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.3);
    color:#fff; font-size:.72rem; font-weight:600;
    padding:5px 11px; border-radius:999px; cursor:pointer;
    display:flex; align-items:center; gap:5px;
    transition:background .2s; white-space:nowrap;
}
.notif-mark-all:hover { background:rgba(255,255,255,.28); }

/* ── Scrollable list ── */
.notif-list {
    max-height:360px; overflow-y:auto;
    scrollbar-width:thin; scrollbar-color:#e2e8f0 transparent;
}
.notif-list::-webkit-scrollbar       { width:4px; }
.notif-list::-webkit-scrollbar-track { background:transparent; }
.notif-list::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:2px; }

/* ── Each notification row ── */
.notif-item {
    display:flex; align-items:flex-start; gap:12px;
    padding:13px 18px;
    border-bottom:1px solid #f8fafc;
    text-decoration:none;
    transition:background .15s; cursor:pointer; position:relative;
}
.notif-item:last-child { border-bottom:none; }
.notif-item:hover      { background:#f8fafc; }
.notif-item.unread     { background:#f0f7ff; }
.notif-item.unread:hover { background:#e6f0fd; }

/* ── Icon badge ── */
.notif-icon-wrap {
    width:36px; height:36px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:.9rem;
    margin-top:1px;
}
.notif-icon-wrap.success { background:#dcfce7; color:#16a34a; }
.notif-icon-wrap.danger  { background:#fee2e2; color:#dc2626; }
.notif-icon-wrap.project { background:#f5f3ff; color:#7c3aed; }
.notif-icon-wrap.payment { background:#eff6ff; color:#3b82f6; }
.notif-icon-wrap.note    { background:#fefce8; color:#ca8a04; }
.notif-icon-wrap.live    { background:#fce7f3; color:#db2777; }
.notif-icon-wrap.info    { background:#f0fdf4; color:#10b981; }

/* ── Text content ── */
.notif-item-body  { flex:1; min-width:0; }
.notif-item-title {
    font-size:.83rem; font-weight:700; color:#1e293b; margin-bottom:3px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.notif-item-msg {
    font-size:.77rem; color:#64748b; line-height:1.45;
    display:-webkit-box; -webkit-line-clamp:2;
    -webkit-box-orient:vertical; overflow:hidden;
}
.notif-item-time {
    font-size:.7rem; color:#94a3b8; margin-top:4px;
    display:flex; align-items:center; gap:4px;
}
.notif-unread-dot {
    width:8px; height:8px; border-radius:50%;
    background:#3b82f6; flex-shrink:0; margin-top:6px;
}

/* ── Empty state ── */
.notif-empty { text-align:center; padding:44px 24px; }
.notif-empty-icon {
    width:56px; height:56px; border-radius:16px; background:#f1f5f9;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; color:#94a3b8; margin:0 auto 14px;
}
.notif-empty p    { font-size:.9rem; font-weight:700; color:#1e293b; margin:0 0 4px; }
.notif-empty span { font-size:.78rem; color:#94a3b8; }

/* ── User Avatar Dropdown ── */
.user-panel {
    position:absolute; top:calc(100% + 10px); right:0;
    width:220px; background:#fff; border-radius:12px;
    box-shadow:0 10px 40px rgba(0,0,0,.1);
    border:1px solid rgba(0,0,0,.06);
    opacity:0; visibility:hidden;
    transform:translateY(-8px) scale(.97);
    transform-origin:top right;
    transition:all .2s cubic-bezier(.4,0,.2,1);
    z-index:9999; overflow:hidden;
}
.user-panel.open { opacity:1; visibility:visible; transform:translateY(0) scale(1); }
.user-panel-header {
    padding:16px; background:#f8fafc; border-bottom:1px solid #f1f5f9;
    display:flex; flex-direction:column; gap:4px;
}
.user-name { font-size:.85rem; font-weight:700; color:#1e293b; }
.user-role { font-size:.7rem; color:#64748b; }
.user-panel-links { padding:8px 0; display:flex; flex-direction:column; }
.user-panel-links a {
    padding:10px 16px; text-decoration:none; color:#475569;
    font-size:.85rem; font-weight:600; display:flex; align-items:center; gap:10px;
    transition:background .15s;
}
.user-panel-links a:hover { background:#f8fafc; color:#1e293b; }

.topbar-user {
    display:flex; align-items:center; gap:10px;
    border-radius:50%; cursor:pointer;
    transition: transform .15s;
}
.topbar-user:hover { transform:scale(1.05); }
.topbar-avatar {
    width:38px; height:38px; border-radius:50%;
    background:linear-gradient(135deg,var(--primary,#3b82f6),#1e40af);
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:.9rem; color:#fff; flex-shrink:0;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);
}

/* ── Hamburger button: hidden on desktop, shown on mobile ── */
.sidebar-hamburger {
    display: none;                   /* Hidden by default on desktop */
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--bg-alt, #f8fafc);
    border: 1.5px solid var(--border-light, #e8edf5);
    align-items: center; justify-content: center;
    cursor: pointer;
    color: var(--text-secondary, #64748b);
    font-size: 1rem;
    transition: all .2s;
    flex-shrink: 0;
}
.sidebar-hamburger:hover {
    background: var(--primary, #3b82f6);
    border-color: var(--primary, #3b82f6);
    color: #fff;
}

/* ── Sidebar overlay: dark backdrop on mobile when sidebar is open ── */
.sidebar-overlay {
    display: none;                   /* Hidden by default */
    position: fixed;
    inset: 0;                        /* Covers full viewport */
    background: rgba(15, 37, 64, 0.55);
    z-index: 1100;                   /* Below sidebar (z:1200) but above content */
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    cursor: pointer;
    transition: opacity .25s ease;
}
.sidebar-overlay.active {
    display: block;
}

@media (max-width: 768px) {
    /* Show hamburger button on mobile */
    .sidebar-hamburger { display: flex; }

    /* Notification panel: full width on very small screens */
    .notif-panel { width: calc(100vw - 32px); right: -8px; }
}

@media (max-width: 600px) {
    /* Hide user name text on very small screens, keep avatar */
    .topbar-user-info { display: none; }
}
</style>

<script>
/* ── Sidebar mobile toggle ──────────────────────────────────────────
   Opens/closes the sidebar on mobile by toggling the .open class.
   The sidebar CSS already has: @768px { transform: translateX(-100%) }
   and .sidebar.open { transform: translateX(0) }
   We just wire up the JS here — no logic is changed.
─────────────────────────────────────────────────────────────────── */
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var hamburger = document.getElementById('sidebarHamburger');
    if (!sidebar) return;
    var isOpen = sidebar.classList.contains('open');
    if (isOpen) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
    } else {
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        if (hamburger) hamburger.setAttribute('aria-expanded', 'true');
    }
}

function closeSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var hamburger = document.getElementById('sidebarHamburger');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
    if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
}

/* Close sidebar if window is resized to desktop width */
window.addEventListener('resize', function () {
    if (window.innerWidth > 768) { closeSidebar(); }
});

/* ── Toggle panel open/close ── */
function toggleNotifPanel(e) {
    e.stopPropagation();
    var panel = document.getElementById('notifPanel');
    var bell  = document.getElementById('notifBell');
    var open  = panel.classList.contains('open');
    panel.classList.toggle('open',  !open);
    bell.classList.toggle('active', !open);
}

/* Close when clicking outside */
document.addEventListener('click', function(e) {
    var notifWrap = document.getElementById('notifWrap');
    if (notifWrap && !notifWrap.contains(e.target)) {
        document.getElementById('notifPanel').classList.remove('open');
        document.getElementById('notifBell').classList.remove('active');
    }
    var userWrap = document.getElementById('userWrap');
    if (userWrap && !userWrap.contains(e.target)) {
        var uPanel = document.getElementById('userPanel');
        if (uPanel) uPanel.classList.remove('open');
    }
});

/* ── Toggle User Panel ── */
function toggleUserPanel(e) {
    e.stopPropagation();
    var panel = document.getElementById('userPanel');
    if (panel) {
        var open = panel.classList.contains('open');
        panel.classList.toggle('open', !open);
    }
}

/* ── Mark ONE notification read then navigate (except new_registration) ── */
function markOneRead(id, e) {
    e.preventDefault();
    var item = document.querySelector('.notif-item[data-id="' + id + '"]');
    var link = e.currentTarget.getAttribute('href');
    var type = item ? item.getAttribute('data-type') : '';

    fetch(window.location.pathname + '?notif_action=mark_one&notif_id=' + id)
        .catch(function(){})
        .finally(function() {
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                var dot = item.querySelector('.notif-unread-dot');
                if (dot) dot.remove();
            }
            updateBadgeCount(-1);
            // Navigate for all notifications EXCEPT new_registration (static only)
            if (type !== 'new_registration' && link && link !== '#') {
                window.location.href = link;
            }
        });
}

/* ── Mark ALL notifications read ── */
function markAllRead() {
    fetch(window.location.pathname + '?notif_action=mark_read')
        .then(function(r){ return r.json(); })
        .then(function() {
            // Remove all unread dots and highlights
            document.querySelectorAll('.notif-unread-dot').forEach(function(d){ d.remove(); });
            document.querySelectorAll('.notif-item.unread').forEach(function(i){
                i.classList.remove('unread');
                i.classList.add('read');
            });
            // Hide bell badge
            var badge = document.getElementById('notifBadge');
            if (badge) badge.remove();
            // Hide "X new" in panel header
            var pb = document.getElementById('notifPanelBadge');
            if (pb) pb.remove();
            // Hide mark-all button
            var btn = document.getElementById('markAllBtn');
            if (btn) btn.style.display = 'none';
        })
        .catch(function(){});
}

/* ── Helper: decrement bell badge ── */
function updateBadgeCount(delta) {
    var badge = document.getElementById('notifBadge');
    var pb    = document.getElementById('notifPanelBadge');
    if (!badge) return;
    var current = parseInt(badge.textContent) || 0;
    var next = current + delta;
    if (next <= 0) {
        badge.remove();
        if (pb) pb.remove();
        var btn = document.getElementById('markAllBtn');
        if (btn) btn.style.display = 'none';
    } else {
        badge.textContent = next > 9 ? '9+' : next;
        if (pb) pb.textContent = next + ' new';
    }
}
</script>
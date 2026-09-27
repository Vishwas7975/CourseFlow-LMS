<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");
requireLogin($conn);
requireRole("student");

/* ── Challenge Data ──────────────────────────────────────── */
$difficulties  = ['Easy','Medium','Hard'];
$categories    = ['Array','String','Tree','Graph','Dynamic Programming','Linked List','Sorting','Searching','Mathematics','Bit Manipulation'];
$companies     = ['Google','Amazon','Microsoft','Infosys','TCS','Wipro','HCL','Cognizant'];

$challenges = [
    ['id'=>1,  'title'=>'Reverse a String',                      'difficulty'=>'Easy',   'topic'=>'String',               'desc'=>'Write a function to reverse a given string without using built-in reverse methods.'],
    ['id'=>2,  'title'=>'Two Sum',                               'difficulty'=>'Easy',   'topic'=>'Array',                'desc'=>'Given an array of integers, find two numbers that add up to a specific target.'],
    ['id'=>3,  'title'=>'Valid Palindrome',                      'difficulty'=>'Easy',   'topic'=>'String',               'desc'=>'Determine if a given string is a palindrome considering only alphanumeric characters.'],
    ['id'=>4,  'title'=>'Fibonacci Number',                      'difficulty'=>'Easy',   'topic'=>'Mathematics',          'desc'=>'Find the nth Fibonacci number using recursion or dynamic programming.'],
    ['id'=>5,  'title'=>'Binary Search',                         'difficulty'=>'Easy',   'topic'=>'Searching',            'desc'=>'Implement binary search on a sorted array and return the index of the target element.'],
    ['id'=>6,  'title'=>'Merge Sorted Arrays',                   'difficulty'=>'Easy',   'topic'=>'Array',                'desc'=>'Merge two sorted arrays into one sorted array without using extra space.'],
    ['id'=>7,  'title'=>'Count Vowels in a String',              'difficulty'=>'Easy',   'topic'=>'String',               'desc'=>'Count the number of vowels in a given string.'],
    ['id'=>8,  'title'=>'Find Maximum Element',                  'difficulty'=>'Easy',   'topic'=>'Array',                'desc'=>'Find the maximum element in an unsorted array without using built-in max functions.'],
    ['id'=>9,  'title'=>'Bubble Sort',                           'difficulty'=>'Easy',   'topic'=>'Sorting',              'desc'=>'Implement bubble sort algorithm and sort an array in ascending order.'],
    ['id'=>10, 'title'=>'Check Prime Number',                    'difficulty'=>'Easy',   'topic'=>'Mathematics',          'desc'=>'Determine whether a given number is prime or not.'],
    ['id'=>11, 'title'=>'Longest Common Prefix',                 'difficulty'=>'Medium', 'topic'=>'String',               'desc'=>'Find the longest common prefix string amongst an array of strings.'],
    ['id'=>12, 'title'=>'Validate Brackets',                     'difficulty'=>'Medium', 'topic'=>'String',               'desc'=>'Given a string containing only (){}[], determine if the input string is valid.'],
    ['id'=>13, 'title'=>'Linked List Cycle Detection',           'difficulty'=>'Medium', 'topic'=>'Linked List',          'desc'=>'Detect if a linked list has a cycle using Floyd\'s cycle detection algorithm.'],
    ['id'=>14, 'title'=>'Binary Tree Level Order Traversal',     'difficulty'=>'Medium', 'topic'=>'Tree',                 'desc'=>'Return the level order traversal of a binary tree\'s node values.'],
    ['id'=>15, 'title'=>'Maximum Subarray (Kadane\'s)',          'difficulty'=>'Medium', 'topic'=>'Dynamic Programming',  'desc'=>'Find the contiguous subarray which has the largest sum using Kadane\'s algorithm.'],
    ['id'=>16, 'title'=>'Number of Islands',                     'difficulty'=>'Medium', 'topic'=>'Graph',                'desc'=>'Count the number of islands in a 2D grid using DFS/BFS traversal.'],
    ['id'=>17, 'title'=>'Merge Intervals',                       'difficulty'=>'Medium', 'topic'=>'Array',                'desc'=>'Merge all overlapping intervals and return the non-overlapping intervals.'],
    ['id'=>18, 'title'=>'Count Bits',                            'difficulty'=>'Medium', 'topic'=>'Bit Manipulation',     'desc'=>'For every number in range [0, n], count the number of 1\'s in binary representation.'],
    ['id'=>19, 'title'=>'Quick Sort',                            'difficulty'=>'Medium', 'topic'=>'Sorting',              'desc'=>'Implement quick sort and analyze its best, average, and worst case complexities.'],
    ['id'=>20, 'title'=>'Graph BFS Shortest Path',               'difficulty'=>'Medium', 'topic'=>'Graph',                'desc'=>'Find the shortest path between two nodes in an unweighted graph using BFS.'],
    ['id'=>21, 'title'=>'Longest Palindromic Substring',         'difficulty'=>'Hard',   'topic'=>'String',               'desc'=>'Find the longest palindromic substring in a string using dynamic programming.'],
    ['id'=>22, 'title'=>'Serialize & Deserialize Binary Tree',   'difficulty'=>'Hard',   'topic'=>'Tree',                 'desc'=>'Design an algorithm to serialize and deserialize a binary tree.'],
    ['id'=>23, 'title'=>'Word Ladder',                           'difficulty'=>'Hard',   'topic'=>'Graph',                'desc'=>'Find the length of shortest transformation sequence from beginWord to endWord.'],
    ['id'=>24, 'title'=>'Trapping Rain Water',                   'difficulty'=>'Hard',   'topic'=>'Dynamic Programming',  'desc'=>'Given n non-negative integers representing elevation map, compute how much water it can trap.'],
    ['id'=>25, 'title'=>'N-Queens Problem',                      'difficulty'=>'Hard',   'topic'=>'Searching',            'desc'=>'Place N queens on an N×N chess board so no two queens attack each other.'],
    ['id'=>26, 'title'=>'Minimum Window Substring',              'difficulty'=>'Hard',   'topic'=>'String',               'desc'=>'Find the minimum window in string S which contains all characters of string T.'],
    ['id'=>27, 'title'=>'Median of Two Sorted Arrays',           'difficulty'=>'Hard',   'topic'=>'Array',                'desc'=>'Find the median of two sorted arrays with overall run time complexity O(log(m+n)).'],
    ['id'=>28, 'title'=>'Alien Dictionary',                      'difficulty'=>'Hard',   'topic'=>'Graph',                'desc'=>'Given a sorted dictionary of alien language, find the order of characters.'],
    ['id'=>29, 'title'=>'Reverse Linked List',                   'difficulty'=>'Easy',   'topic'=>'Linked List',          'desc'=>'Reverse a singly linked list iteratively and recursively.'],
    ['id'=>30, 'title'=>'First Non-Repeating Character',         'difficulty'=>'Medium', 'topic'=>'String',               'desc'=>'Find the first non-repeating character in a string and return its index.'],
];

/* ── Filters ─────────────────────────────────────────────── */
$f_diff  = $_GET['difficulty'] ?? 'All';
$f_topic = $_GET['topic']      ?? 'All';
$f_q     = trim($_GET['q']     ?? '');

$filtered = $challenges;
if ($f_diff  !== 'All') $filtered = array_filter($filtered, fn($c)=>$c['difficulty']===$f_diff);
if ($f_topic !== 'All') $filtered = array_filter($filtered, fn($c)=>$c['topic']===$f_topic);
if ($f_q !== '')        $filtered = array_filter($filtered, fn($c)=>stripos($c['title'],$f_q)!==false||stripos($c['topic'],$f_q)!==false);
$filtered = array_values($filtered);

/* ── Stats ───────────────────────────────────────────────── */
$total = count($challenges);
$easy  = count(array_filter($challenges,fn($c)=>$c['difficulty']==='Easy'));
$med   = count(array_filter($challenges,fn($c)=>$c['difficulty']==='Medium'));
$hard  = count(array_filter($challenges,fn($c)=>$c['difficulty']==='Hard'));

$page_title = "Coding Challenges";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Coding Challenges — CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
/* ── Page layout ───────────────────────────────────────────── */
.cc-wrap{display:grid;grid-template-columns:220px 1fr 200px;gap:20px;align-items:start;}
@media(max-width:1050px){.cc-wrap{grid-template-columns:1fr;} .cc-sidebar-right{display:none;}}
@media(max-width:720px){.cc-sidebar-left{display:none;}}

/* ── Cards ─────────────────────────────────────────────────── */
.cc-panel{background:#fff;border-radius:16px;border:1px solid #e8edf5;padding:18px;box-shadow:0 1px 4px rgba(0,0,0,.05);}

/* ── Difficulty badges ──────────────────────────────────────── */
.diff{display:inline-block;padding:2px 10px;border-radius:40px;font-size:.7rem;font-weight:700;letter-spacing:.04em;}
.diff-Easy{background:#dcfce7;color:#16a34a;}
.diff-Medium{background:#fef9c3;color:#ca8a04;}
.diff-Hard{background:#fee2e2;color:#dc2626;}

/* ── Challenge table ────────────────────────────────────────── */
.cc-table{width:100%;border-collapse:collapse;}
.cc-table th{font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;padding:10px 14px;border-bottom:2px solid #e8edf5;text-align:left;background:#f8fafc;}
.cc-table td{padding:12px 14px;font-size:.85rem;color:#334155;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.cc-table tr:hover td{background:#f8fafc;cursor:pointer;}
.cc-table tr:last-child td{border-bottom:none;}

/* ── Filter bar ─────────────────────────────────────────────── */
.filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:center;}
.filter-bar select,.filter-bar input{
  padding:8px 12px;border-radius:9px;border:1.5px solid #e2e8f0;
  font-size:.82rem;color:#334155;background:#fff;outline:none;
  font-family:inherit;transition:border-color .2s;
}
.filter-bar select:focus,.filter-bar input:focus{border-color:#2563eb;}
.filter-bar .btn-filter{padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:9px;
  font-size:.82rem;font-weight:700;cursor:pointer;transition:background .2s;font-family:inherit;}
.filter-bar .btn-filter:hover{background:#1d4ed8;}
.filter-bar .btn-reset{padding:8px 14px;background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0;
  border-radius:9px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;}

/* ── Topic sidebar pills ─────────────────────────────────────── */
.topic-pill{display:flex;justify-content:space-between;align-items:center;
  padding:7px 10px;border-radius:8px;font-size:.78rem;color:#475569;font-weight:500;
  cursor:pointer;transition:all .15s;border:1px solid transparent;text-decoration:none;}
.topic-pill:hover,.topic-pill.active{background:#eff6ff;color:#2563eb;border-color:#bfdbfe;}
.topic-count{font-size:.68rem;background:#e2e8f0;border-radius:999px;padding:1px 6px;color:#64748b;}

/* ── Stats circle ────────────────────────────────────────────── */
.stat-ring-wrap{position:relative;width:80px;height:80px;margin:0 auto 10px;}
.stat-ring-wrap svg{transform:rotate(-90deg);}
.stat-ring-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  flex-direction:column;font-size:1.2rem;font-weight:800;color:#0f172a;}

/* ── Solved breakdown ────────────────────────────────────────── */
.solved-row{display:flex;justify-content:space-between;align-items:center;font-size:.78rem;padding:3px 0;}

/* ── Streak card ─────────────────────────────────────────────── */
.list-item{display:flex;align-items:center;gap:10px;background:#f8fafc;border-radius:10px;
  padding:9px 12px;font-size:.8rem;font-weight:600;color:#334155;margin-bottom:8px;border:1px solid #e8edf5;}
</style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Page Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin:0 0 4px;display:flex;align-items:center;gap:10px;">
                        <span style="width:38px;height:38px;background:linear-gradient(135deg,#2563eb,#0ea5e9);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;">
                            <i class="fas fa-code"></i>
                        </span>
                        Coding Challenges
                    </h1>
                    <p style="margin:0;color:#64748b;font-size:.83rem;">Practice problems across topics and difficulty levels</p>
                </div>
                <a href="dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <div class="cc-wrap">

                <!-- ── LEFT SIDEBAR ─────────────────────── -->
                <div class="cc-sidebar-left">
                    <div class="cc-panel" style="margin-bottom:16px;">
                        <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em;">Topics</div>
                        <a href="coding_challenges.php?difficulty=<?php echo urlencode($f_diff); ?>" class="topic-pill <?php echo $f_topic==='All'?'active':''; ?>">
                            All Topics <span class="topic-count"><?php echo $total; ?></span>
                        </a>
                        <?php
                        $topic_counts = array_count_values(array_column($challenges,'topic'));
                        foreach ($categories as $cat):
                            $cnt = $topic_counts[$cat] ?? 0;
                            $active = $f_topic===$cat ? 'active' : '';
                            $url = '?topic='.urlencode($cat).($f_diff!=='All'?'&difficulty='.urlencode($f_diff):'').($f_q!==''?'&q='.urlencode($f_q):'');
                        ?>
                        <a href="<?php echo $url; ?>" class="topic-pill <?php echo $active; ?>">
                            <?php echo htmlspecialchars($cat); ?>
                            <span class="topic-count"><?php echo $cnt; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="cc-panel">
                        <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em;">Companies</div>
                        <?php foreach ($companies as $co): ?>
                        <div class="topic-pill" style="cursor:default;">
                            <span><?php echo $co; ?></span>
                            <span class="topic-count">—</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── MAIN CONTENT ──────────────────────── -->
                <div>
                    <div class="cc-panel" style="margin-bottom:16px;">

                        <!-- Filter Bar -->
                        <form method="GET" class="filter-bar">
                            <select name="difficulty">
                                <option value="All" <?php echo $f_diff==='All'?'selected':''; ?>>All Difficulties</option>
                                <?php foreach ($difficulties as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $f_diff===$d?'selected':''; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="topic">
                                <option value="All" <?php echo $f_topic==='All'?'selected':''; ?>>All Topics</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $f_topic===$cat?'selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="q" placeholder="🔍  Search challenges..." value="<?php echo htmlspecialchars($f_q); ?>" style="flex:1;min-width:160px;">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                            <a href="coding_challenges.php" class="btn-reset">Reset</a>
                        </form>

                        <!-- Results count -->
                        <div style="font-size:.78rem;color:#64748b;margin-bottom:12px;">
                            Showing <strong><?php echo count($filtered); ?></strong> of <strong><?php echo $total; ?></strong> challenges
                            <?php if ($f_q!==''): ?> for "<em><?php echo htmlspecialchars($f_q); ?></em>"<?php endif; ?>
                        </div>

                        <!-- Table -->
                        <div style="overflow-x:auto;border-radius:12px;border:1px solid #e8edf5;">
                            <table class="cc-table">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th>Title</th>
                                        <th>Topic</th>
                                        <th>Difficulty</th>
                                        <th style="width:80px;text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($filtered)): ?>
                                    <tr><td colspan="5" style="text-align:center;padding:32px;color:#94a3b8;">
                                        <i class="fas fa-search" style="font-size:1.5rem;margin-bottom:8px;display:block;"></i>
                                        No challenges found. Try different filters.
                                    </td></tr>
                                <?php else: ?>
                                    <?php foreach ($filtered as $ch): ?>
                                    <tr onclick="window.location.href='coding_challenge_detail.php?id=<?php echo $ch['id']; ?>'">
                                        <td style="font-weight:700;color:#94a3b8;"><?php echo $ch['id']; ?></td>
                                        <td>
                                            <div style="font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($ch['title']); ?></div>
                                            <div style="font-size:.72rem;color:#94a3b8;margin-top:2px;"><?php echo htmlspecialchars(mb_strimwidth($ch['desc'],0,70,'…')); ?></div>
                                        </td>
                                        <td><span style="background:#f1f5f9;border-radius:6px;padding:3px 8px;font-size:.72rem;font-weight:600;color:#475569;"><?php echo htmlspecialchars($ch['topic']); ?></span></td>
                                        <td><span class="diff diff-<?php echo $ch['difficulty']; ?>"><?php echo $ch['difficulty']; ?></span></td>
                                        <td style="text-align:center;">
                                            <a href="coding_challenge_detail.php?id=<?php echo $ch['id']; ?>"
                                               onclick="event.stopPropagation();"
                                               style="display:inline-flex;align-items:center;gap:5px;background:#2563eb;color:#fff;padding:5px 12px;border-radius:7px;font-size:.72rem;font-weight:700;text-decoration:none;">
                                                <i class="fas fa-code"></i> Solve
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT SIDEBAR ─────────────────────── -->
                <div class="cc-sidebar-right">

                    <!-- Total Solved Ring -->
                    <div class="cc-panel" style="text-align:center;margin-bottom:16px;">
                        <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:12px;text-transform:uppercase;letter-spacing:.06em;">Total Solved</div>
                        <?php
                        $solved = 0; // TODO: track from DB
                        $pct = $total > 0 ? ($solved / $total * 100) : 0;
                        $r = 32; $circ = 2*M_PI*$r; $fill = $circ*(1-$pct/100);
                        ?>
                        <div class="stat-ring-wrap">
                            <svg width="80" height="80" viewBox="0 0 80 80">
                                <circle cx="40" cy="40" r="<?php echo $r; ?>" fill="none" stroke="#e2e8f0" stroke-width="7"/>
                                <circle cx="40" cy="40" r="<?php echo $r; ?>" fill="none" stroke="#16a34a" stroke-width="7"
                                    stroke-dasharray="<?php echo $circ; ?>"
                                    stroke-dashoffset="<?php echo $fill; ?>"
                                    stroke-linecap="round"/>
                            </svg>
                            <div class="stat-ring-text">
                                <span><?php echo $solved; ?></span>
                            </div>
                        </div>
                        <div style="font-size:.75rem;color:#64748b;margin-bottom:10px;"><?php echo $solved; ?> / <?php echo $total; ?></div>
                        <div class="solved-row"><span style="color:#dc2626;font-weight:600;">Hard</span><span><?php echo $hard; ?></span></div>
                        <div class="solved-row"><span style="color:#ca8a04;font-weight:600;">Medium</span><span><?php echo $med; ?></span></div>
                        <div class="solved-row"><span style="color:#16a34a;font-weight:600;">Easy</span><span><?php echo $easy; ?></span></div>
                    </div>

                    <!-- Curated Lists -->
                    <div class="cc-panel">
                        <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em;">Curated Lists</div>
                        <div class="list-item">🏆 <span>Top Interview</span></div>
                        <div class="list-item">📚 <span>Beginner Friendly</span></div>
                        <div class="list-item">⚡ <span>Speed Round</span></div>
                        <div class="list-item">🧠 <span>DSA Essentials</span></div>
                    </div>

                </div>

            </div><!-- /cc-wrap -->
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
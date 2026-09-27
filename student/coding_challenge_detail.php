<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");
requireLogin($conn);
requireRole("student");

/* ── Same challenge data (shared definition) ─────────────── */
$challenges = [
    ['id'=>1,  'title'=>'Reverse a String',                      'difficulty'=>'Easy',   'topic'=>'String',               'desc'=>'Write a function to reverse a given string without using built-in reverse methods.', 'sample_input'=>'"hello"', 'sample_output'=>'"olleh"', 'constraints'=>"1 ≤ length ≤ 10^5\nOnly printable ASCII characters"],
    ['id'=>2,  'title'=>'Two Sum',                               'difficulty'=>'Easy',   'topic'=>'Array',                'desc'=>'Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target. You may assume each input would have exactly one solution.', 'sample_input'=>'nums = [2,7,11,15], target = 9', 'sample_output'=>'[0, 1]', 'constraints'=>"2 ≤ nums.length ≤ 10^4\n-10^9 ≤ nums[i] ≤ 10^9"],
    ['id'=>3,  'title'=>'Valid Palindrome',                      'difficulty'=>'Easy',   'topic'=>'String',               'desc'=>'A phrase is a palindrome if, after converting all uppercase letters into lowercase and removing all non-alphanumeric characters, it reads the same forward and backward.', 'sample_input'=>'"A man, a plan, a canal: Panama"', 'sample_output'=>'true', 'constraints'=>"1 ≤ s.length ≤ 2×10^5"],
    ['id'=>4,  'title'=>'Fibonacci Number',                      'difficulty'=>'Easy',   'topic'=>'Mathematics',          'desc'=>'The Fibonacci numbers, commonly denoted F(n) form a sequence. Given n, calculate F(n).', 'sample_input'=>'n = 10', 'sample_output'=>'55', 'constraints'=>"0 ≤ n ≤ 30"],
    ['id'=>5,  'title'=>'Binary Search',                         'difficulty'=>'Easy',   'topic'=>'Searching',            'desc'=>'Given an array of integers sorted in ascending order, implement binary search and return the index of target. Return -1 if not found.', 'sample_input'=>'nums=[-1,0,3,5,9,12], target=9', 'sample_output'=>'4', 'constraints'=>"1 ≤ nums.length ≤ 10^4"],
    ['id'=>6,  'title'=>'Merge Sorted Arrays',                   'difficulty'=>'Easy',   'topic'=>'Array',                'desc'=>'You are given two integer arrays nums1 and nums2, sorted in non-decreasing order. Merge nums2 into nums1 as one sorted array.', 'sample_input'=>'nums1=[1,2,3], nums2=[2,5,6]', 'sample_output'=>'[1,2,2,3,5,6]', 'constraints'=>"0 ≤ m, n ≤ 200"],
    ['id'=>7,  'title'=>'Count Vowels in a String',              'difficulty'=>'Easy',   'topic'=>'String',               'desc'=>'Count the number of vowels (a, e, i, o, u) in a given string. Ignore case.', 'sample_input'=>'"Hello World"', 'sample_output'=>'3', 'constraints'=>"1 ≤ length ≤ 10^4"],
    ['id'=>8,  'title'=>'Find Maximum Element',                  'difficulty'=>'Easy',   'topic'=>'Array',                'desc'=>'Find the maximum element in an unsorted array. Do not use sort() or max() built-in functions.', 'sample_input'=>'[3,1,4,1,5,9,2,6]', 'sample_output'=>'9', 'constraints'=>"1 ≤ nums.length ≤ 10^5"],
    ['id'=>9,  'title'=>'Bubble Sort',                           'difficulty'=>'Easy',   'topic'=>'Sorting',              'desc'=>'Implement bubble sort. Repeatedly step through the list, compare adjacent elements and swap them if they are in the wrong order.', 'sample_input'=>'[64,34,25,12,22,11,90]', 'sample_output'=>'[11,12,22,25,34,64,90]', 'constraints'=>"1 ≤ nums.length ≤ 10^4"],
    ['id'=>10, 'title'=>'Check Prime Number',                    'difficulty'=>'Easy',   'topic'=>'Mathematics',          'desc'=>'Determine whether a given integer n is prime. A prime number is a natural number greater than 1 that has no positive divisors other than 1 and itself.', 'sample_input'=>'n = 17', 'sample_output'=>'true', 'constraints'=>"2 ≤ n ≤ 10^6"],
    ['id'=>11, 'title'=>'Longest Common Prefix',                 'difficulty'=>'Medium', 'topic'=>'String',               'desc'=>'Write a function to find the longest common prefix string amongst an array of strings. If there is no common prefix, return an empty string.', 'sample_input'=>'["flower","flow","flight"]', 'sample_output'=>'"fl"', 'constraints'=>"1 ≤ strs.length ≤ 200"],
    ['id'=>12, 'title'=>'Validate Brackets',                     'difficulty'=>'Medium', 'topic'=>'String',               'desc'=>'Given a string s containing just the characters (, ), {, }, [ and ], determine if the input string is valid. An input string is valid if open brackets are closed in the correct order.', 'sample_input'=>'"()[]{}"', 'sample_output'=>'true', 'constraints'=>"1 ≤ s.length ≤ 10^4"],
    ['id'=>13, 'title'=>'Linked List Cycle Detection',           'difficulty'=>'Medium', 'topic'=>'Linked List',          'desc'=>'Given the head of a linked list, determine if the linked list has a cycle using Floyd\'s cycle detection algorithm (slow/fast pointers).', 'sample_input'=>'head=[3,2,0,-4], pos=1', 'sample_output'=>'true', 'constraints'=>"0 ≤ number of nodes ≤ 10^4"],
    ['id'=>14, 'title'=>'Binary Tree Level Order Traversal',     'difficulty'=>'Medium', 'topic'=>'Tree',                 'desc'=>'Given the root of a binary tree, return the level order traversal of its nodes\' values (i.e., from left to right, level by level).', 'sample_input'=>'root=[3,9,20,null,null,15,7]', 'sample_output'=>'[[3],[9,20],[15,7]]', 'constraints'=>"0 ≤ number of nodes ≤ 2000"],
    ['id'=>15, 'title'=>'Maximum Subarray (Kadane\'s)',          'difficulty'=>'Medium', 'topic'=>'Dynamic Programming',  'desc'=>'Given an integer array nums, find the subarray which has the largest sum and return its sum. Use Kadane\'s algorithm.', 'sample_input'=>'[-2,1,-3,4,-1,2,1,-5,4]', 'sample_output'=>'6 (subarray: [4,-1,2,1])', 'constraints'=>"1 ≤ nums.length ≤ 10^5"],
    ['id'=>16, 'title'=>'Number of Islands',                     'difficulty'=>'Medium', 'topic'=>'Graph',                'desc'=>'Given a 2D grid of 1s (land) and 0s (water), count the number of islands. An island is surrounded by water and is formed by connecting adjacent lands horizontally or vertically.', 'sample_input'=>'grid=[ ["1","1","0"],["1","1","0"],["0","0","1"] ]', 'sample_output'=>'2', 'constraints'=>"1 ≤ rows, cols ≤ 300"],
    ['id'=>17, 'title'=>'Merge Intervals',                       'difficulty'=>'Medium', 'topic'=>'Array',                'desc'=>'Given an array of intervals, merge all overlapping intervals and return an array of the non-overlapping intervals that cover all the intervals in the input.', 'sample_input'=>'[[1,3],[2,6],[8,10],[15,18]]', 'sample_output'=>'[[1,6],[8,10],[15,18]]', 'constraints'=>"1 ≤ intervals.length ≤ 10^4"],
    ['id'=>18, 'title'=>'Count Bits',                            'difficulty'=>'Medium', 'topic'=>'Bit Manipulation',     'desc'=>'Given an integer n, return an array of the number of 1\'s in the binary representation of every number in the range [0, n].', 'sample_input'=>'n = 5', 'sample_output'=>'[0,1,1,2,1,2]', 'constraints'=>"0 ≤ n ≤ 10^5"],
    ['id'=>19, 'title'=>'Quick Sort',                            'difficulty'=>'Medium', 'topic'=>'Sorting',              'desc'=>'Implement the quick sort algorithm using a pivot. Analyze its time complexity for best, average, and worst cases.', 'sample_input'=>'[10,7,8,9,1,5]', 'sample_output'=>'[1,5,7,8,9,10]', 'constraints'=>"1 ≤ nums.length ≤ 10^5"],
    ['id'=>20, 'title'=>'Graph BFS Shortest Path',               'difficulty'=>'Medium', 'topic'=>'Graph',                'desc'=>'Find the shortest path between two nodes in an unweighted undirected graph using Breadth First Search.', 'sample_input'=>'n=6, edges=[[0,1],[1,2],[2,3],[3,4],[4,5]], src=0, dst=5', 'sample_output'=>'5', 'constraints'=>"2 ≤ n ≤ 10^4"],
    ['id'=>21, 'title'=>'Longest Palindromic Substring',         'difficulty'=>'Hard',   'topic'=>'String',               'desc'=>'Given a string s, return the longest palindromic substring in s. Use dynamic programming or expand-around-center approach.', 'sample_input'=>'"babad"', 'sample_output'=>'"bab" or "aba"', 'constraints'=>"1 ≤ s.length ≤ 1000"],
    ['id'=>22, 'title'=>'Serialize & Deserialize Binary Tree',   'difficulty'=>'Hard',   'topic'=>'Tree',                 'desc'=>'Design an algorithm to serialize and deserialize a binary tree. Ensure that a binary tree can be serialized to a string and this string can be deserialized back to the original tree structure.', 'sample_input'=>'root=[1,2,3,null,null,4,5]', 'sample_output'=>'"1,2,3,null,null,4,5" and back', 'constraints'=>"0 ≤ nodes ≤ 10^4"],
    ['id'=>23, 'title'=>'Word Ladder',                           'difficulty'=>'Hard',   'topic'=>'Graph',                'desc'=>'A transformation sequence from word beginWord to endWord using a dictionary wordList is a sequence where each pair of adjacent words differs by exactly one letter. Return the length of the shortest such sequence.', 'sample_input'=>'beginWord="hit", endWord="cog", wordList=["hot","dot","dog","lot","log","cog"]', 'sample_output'=>'5', 'constraints'=>"1 ≤ wordList.length ≤ 5000"],
    ['id'=>24, 'title'=>'Trapping Rain Water',                   'difficulty'=>'Hard',   'topic'=>'Dynamic Programming',  'desc'=>'Given n non-negative integers representing an elevation map where the width of each bar is 1, compute how much water it can trap after raining.', 'sample_input'=>'[0,1,0,2,1,0,1,3,2,1,2,1]', 'sample_output'=>'6', 'constraints'=>"n == height.length, 1 ≤ n ≤ 2×10^4"],
    ['id'=>25, 'title'=>'N-Queens Problem',                      'difficulty'=>'Hard',   'topic'=>'Searching',            'desc'=>'Place n queens on an n×n chess board such that no two queens attack each other. Return all distinct solutions.', 'sample_input'=>'n = 4', 'sample_output'=>'[[".Q..","...Q","Q...","..Q."],["..Q.","Q...","...Q",".Q.."]]', 'constraints'=>"1 ≤ n ≤ 9"],
    ['id'=>26, 'title'=>'Minimum Window Substring',              'difficulty'=>'Hard',   'topic'=>'String',               'desc'=>'Given two strings s and t, return the minimum window substring of s such that every character in t is included in the window.', 'sample_input'=>'s="ADOBECODEBANC", t="ABC"', 'sample_output'=>'"BANC"', 'constraints'=>"1 ≤ s.length, t.length ≤ 10^5"],
    ['id'=>27, 'title'=>'Median of Two Sorted Arrays',           'difficulty'=>'Hard',   'topic'=>'Array',                'desc'=>'Given two sorted arrays nums1 and nums2 of size m and n respectively, return the median of the two sorted arrays. The overall run time complexity should be O(log(m+n)).', 'sample_input'=>'nums1=[1,3], nums2=[2]', 'sample_output'=>'2.0', 'constraints'=>"0 ≤ m, n ≤ 1000"],
    ['id'=>28, 'title'=>'Alien Dictionary',                      'difficulty'=>'Hard',   'topic'=>'Graph',                'desc'=>'There is a new alien language that uses the English alphabet. Given a sorted dictionary of alien words, find the order of characters in this language.', 'sample_input'=>'words=["wrt","wrf","er","ett","rftt"]', 'sample_output'=>'"wertf"', 'constraints'=>"1 ≤ words.length ≤ 100"],
    ['id'=>29, 'title'=>'Reverse Linked List',                   'difficulty'=>'Easy',   'topic'=>'Linked List',          'desc'=>'Given the head of a singly linked list, reverse the list and return the reversed list. Implement both iteratively and recursively.', 'sample_input'=>'head=[1,2,3,4,5]', 'sample_output'=>'[5,4,3,2,1]', 'constraints'=>"0 ≤ nodes ≤ 5000"],
    ['id'=>30, 'title'=>'First Non-Repeating Character',         'difficulty'=>'Medium', 'topic'=>'String',               'desc'=>'Given a string s, find the first non-repeating character in it and return its index. If it does not exist, return -1.', 'sample_input'=>'"loveleetcode"', 'sample_output'=>'2', 'constraints'=>"1 ≤ s.length ≤ 10^5"],
];

$id = (int)($_GET['id'] ?? 0);
$ch = null;
foreach ($challenges as $c) { if ($c['id']===$id) { $ch=$c; break; } }
if (!$ch) { header("Location: coding_challenges.php"); exit(); }

$prev_id = max(1, $id - 1);
$next_id = min(count($challenges), $id + 1);

$diff_colors = ['Easy'=>['bg'=>'#dcfce7','color'=>'#16a34a'], 'Medium'=>['bg'=>'#fef9c3','color'=>'#ca8a04'], 'Hard'=>['bg'=>'#fee2e2','color'=>'#dc2626']];
$dc = $diff_colors[$ch['difficulty']] ?? ['bg'=>'#f1f5f9','color'=>'#64748b'];

$page_title = $ch['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($ch['title']); ?> — CourseFlow Challenges</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
.cd-wrap{display:grid;grid-template-columns:1fr 1fr;gap:20px;height:calc(100vh - 180px);}
@media(max-width:900px){.cd-wrap{grid-template-columns:1fr;height:auto;}}

.cd-panel{background:#fff;border-radius:16px;border:1px solid #e8edf5;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);}

/* Tab navigation */
.cd-tabs{display:flex;border-bottom:2px solid #f1f5f9;}
.cd-tab{padding:11px 18px;font-size:.8rem;font-weight:700;color:#94a3b8;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;}
.cd-tab.active{color:#2563eb;border-bottom-color:#2563eb;}
.cd-tab:hover:not(.active){color:#475569;}

.cd-body{flex:1;overflow-y:auto;padding:20px;}
.cd-body::-webkit-scrollbar{width:4px;}
.cd-body::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px;}

/* Difficulty badge */
.diff-badge{display:inline-block;padding:3px 12px;border-radius:40px;font-size:.72rem;font-weight:700;}

/* Code block */
pre.code-sample{background:#0f172a;color:#e2e8f0;border-radius:10px;padding:14px 16px;font-family:'JetBrains Mono',monospace;font-size:.78rem;overflow-x:auto;line-height:1.7;white-space:pre;}

/* Section headings inside panel */
.pd-sec{font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin:18px 0 8px;}
.pd-sec:first-child{margin-top:0;}

/* Collapsible details */
details.pd-details{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;margin-bottom:8px;}
details.pd-details summary{font-size:.82rem;font-weight:700;color:#334155;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;}
details.pd-details summary::after{content:'﹀';font-size:.9rem;color:#94a3b8;}
details.pd-details[open] summary::after{content:'︿';}
details.pd-details p{font-size:.8rem;color:#64748b;margin:8px 0 0;line-height:1.7;}

/* Editor header */
.editor-topbar{background:#0f172a;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.lang-badge{background:#1e293b;color:#94a3b8;border-radius:6px;padding:4px 10px;font-size:.75rem;font-weight:700;font-family:'JetBrains Mono',monospace;border:1px solid #334155;}

/* Nav bar */
.cd-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.cd-nav-center{display:flex;align-items:center;gap:10px;}
.nav-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;font-size:.8rem;font-weight:700;text-decoration:none;border:1.5px solid #e2e8f0;color:#475569;background:#fff;transition:all .15s;cursor:pointer;}
.nav-btn:hover{border-color:#2563eb;color:#2563eb;}
.nav-btn.primary{background:#2563eb;color:#fff;border-color:#2563eb;}
.nav-btn.primary:hover{background:#1d4ed8;}

/* Result area */
.result-box{background:#0f172a;border-radius:10px;padding:16px;min-height:80px;font-family:'JetBrains Mono',monospace;font-size:.78rem;color:#94a3b8;line-height:1.7;}
</style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper" style="padding-bottom:10px;">

            <!-- Nav Bar -->
            <div class="cd-nav">
                <a href="coding_challenges.php" class="nav-btn"><i class="fas fa-arrow-left"></i> All Challenges</a>
                <div class="cd-nav-center">
                    <a href="coding_challenge_detail.php?id=<?php echo $prev_id; ?>" class="nav-btn" <?php echo $id<=1?'style="opacity:.4;pointer-events:none;"':''; ?>>
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                    <span style="font-size:.8rem;color:#64748b;font-weight:600;"><?php echo $ch['id']; ?> / <?php echo count($challenges); ?></span>
                    <a href="coding_challenge_detail.php?id=<?php echo $next_id; ?>" class="nav-btn" <?php echo $id>=count($challenges)?'style="opacity:.4;pointer-events:none;"':''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="diff-badge" style="background:<?php echo $dc['bg']; ?>;color:<?php echo $dc['color']; ?>;"><?php echo $ch['difficulty']; ?></span>
                    <span style="background:#f1f5f9;border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:600;color:#475569;"><?php echo htmlspecialchars($ch['topic']); ?></span>
                </div>
            </div>

            <!-- Two-panel layout -->
            <div class="cd-wrap">

                <!-- LEFT: Problem Statement -->
                <div class="cd-panel">
                    <div class="cd-tabs">
                        <div class="cd-tab active" onclick="switchTab(this,'prob')"><i class="fas fa-file-alt"></i> &nbsp;Problem</div>
                        <div class="cd-tab" onclick="switchTab(this,'hint')"><i class="fas fa-lightbulb"></i> &nbsp;Hints</div>
                        <div class="cd-tab" onclick="switchTab(this,'sub')"><i class="fas fa-history"></i> &nbsp;Submissions</div>
                    </div>

                    <!-- Problem tab -->
                    <div class="cd-body" id="tab-prob">
                        <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                            <span style="background:#f1f5f9;color:#64748b;border-radius:6px;padding:3px 8px;font-size:.72rem;font-weight:700;">Q.<?php echo $ch['id']; ?></span>
                            <h2 style="margin:0;font-size:1.05rem;font-weight:800;color:#0f172a;flex:1;"><?php echo htmlspecialchars($ch['title']); ?></h2>
                        </div>

                        <div class="pd-sec">Description</div>
                        <p style="font-size:.85rem;color:#334155;line-height:1.8;margin:0 0 16px;"><?php echo htmlspecialchars($ch['desc']); ?></p>

                        <div class="pd-sec">Sample Input</div>
                        <pre class="code-sample"><?php echo htmlspecialchars($ch['sample_input']); ?></pre>

                        <div class="pd-sec">Expected Output</div>
                        <pre class="code-sample"><?php echo htmlspecialchars($ch['sample_output']); ?></pre>

                        <div class="pd-sec">Constraints</div>
                        <pre class="code-sample"><?php echo htmlspecialchars($ch['constraints']); ?></pre>

                        <div class="pd-sec" style="margin-top:20px;">More Info</div>
                        <details class="pd-details">
                            <summary>Topic</summary>
                            <p><?php echo htmlspecialchars($ch['topic']); ?></p>
                        </details>
                        <details class="pd-details">
                            <summary>Related Companies</summary>
                            <p>Google &nbsp;·&nbsp; Amazon &nbsp;·&nbsp; Microsoft &nbsp;·&nbsp; Infosys</p>
                        </details>
                        <details class="pd-details">
                            <summary>Similar Challenges</summary>
                            <p>Explore more <?php echo htmlspecialchars($ch['topic']); ?> problems in the challenge list.</p>
                        </details>
                    </div>

                    <!-- Hints tab -->
                    <div class="cd-body" id="tab-hint" style="display:none;">
                        <div class="pd-sec">Approach Hints</div>
                        <details class="pd-details" open>
                            <summary>Hint 1 — Understand the Problem</summary>
                            <p>Read the problem carefully. Identify the input format, output format, and any edge cases mentioned in the constraints.</p>
                        </details>
                        <details class="pd-details">
                            <summary>Hint 2 — Brute Force First</summary>
                            <p>Start with the simplest solution first, even if it's O(n²). Make it work, then optimize.</p>
                        </details>
                        <details class="pd-details">
                            <summary>Hint 3 — Optimize</summary>
                            <p>Think about data structures that could speed up your solution. Hash maps, stacks, queues, and sliding windows are often useful for <?php echo htmlspecialchars($ch['topic']); ?> problems.</p>
                        </details>
                        <details class="pd-details">
                            <summary>Hint 4 — Edge Cases</summary>
                            <p>Test with empty input, single elements, negative numbers, and very large inputs as mentioned in the constraints.</p>
                        </details>
                    </div>

                    <!-- Submissions tab -->
                    <div class="cd-body" id="tab-sub" style="display:none;">
                        <div style="text-align:center;padding:40px 16px;color:#94a3b8;">
                            <i class="fas fa-history" style="font-size:2rem;margin-bottom:12px;display:block;"></i>
                            <div style="font-weight:700;color:#475569;margin-bottom:6px;">No Submissions Yet</div>
                            <div style="font-size:.8rem;">Write and run your solution in the editor. Submission history will appear here.</div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Code Editor -->
                <div class="cd-panel" style="border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,.2);">

                    <!-- Editor topbar -->
                    <div class="editor-topbar">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
                            <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block;"></span>
                            <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                            <span class="lang-badge" style="margin-left:8px;">Java</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-size:.72rem;color:#475569;">Powered by JDoodle</span>
                            <a href="https://www.jdoodle.com/" target="_blank" style="color:#64748b;font-size:.78rem;"><i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>

                    <!-- JDoodle Embed -->
                    <div style="flex:1;background:#0f172a;overflow:hidden;min-height:350px;">
                        <div data-pym-src='https://www.jdoodle.com/embed/v1/b26799ff8ccca2e3' style="width:100%;height:100%;min-height:350px;"></div>
                    </div>

                    <!-- Result section -->
                    <div style="background:#fff;padding:14px 16px;border-top:1px solid #e8edf5;flex-shrink:0;">
                        <div style="font-size:.78rem;font-weight:700;color:#0f172a;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-terminal" style="color:#2563eb;"></i> Output / Result
                        </div>
                        <div class="result-box">
                            Click <strong style="color:#22c55e;">Execute</strong> inside the JDoodle editor above to run your code. The output will appear directly in the editor console.
                        </div>
                        <div style="margin-top:8px;font-size:.72rem;color:#94a3b8;">
                            <i class="fas fa-info-circle"></i> Code runs securely in JDoodle's sandboxed environment. Supports Java, Python, C, C++, and more.
                        </div>
                    </div>

                </div>
            </div><!-- /cd-wrap -->

        </div>
    </div>
</div>

<script src="https://www.jdoodle.com/assets/jdoodle-pym.min.js" type="text/javascript"></script>
<script>
function switchTab(el, tab) {
    document.querySelectorAll('.cd-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    ['prob','hint','sub'].forEach(t => {
        const el2 = document.getElementById('tab-' + t);
        if (el2) el2.style.display = t === tab ? 'block' : 'none';
    });
}
</script>
<?php include("../includes/footer.php"); ?>
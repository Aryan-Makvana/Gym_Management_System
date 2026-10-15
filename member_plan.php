<?php
// ============================================================
// RAJKOT FITNESS CLUB - My Plan (member view)
// A member sees ONLY the diet + workout of the plan the trainer assigned to them.
// ============================================================
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['role'] ?? '') !== 'member') { header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? 'admin.php' : 'trainer.php')); exit; }

$conn = mysqli_connect("localhost", "root", "", "gym_db");
if (!$conn) die("Connection failed: " . mysqli_connect_error());
mysqli_set_charset($conn, "utf8mb4");
if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'")) == 0) {
    die("Tables not created yet. Open login.php once and reload.");
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function run($conn, $sql, $types = '', $params = []) {
    $st = mysqli_prepare($conn, $sql);
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    return mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
}

// The member's plan comes from the JOIN FORM (gym_enquiries), matched by the same email they log in with.
// It always comes from the database (never from the URL), so a member cannot view another plan.
$acc = run($conn, "SELECT full_name, email FROM users WHERE id = ? AND role = 'member'", "i", [(int)$_SESSION['user_id']])[0] ?? null;
if (!$acc) { header('Location: login.php?logout=1'); exit; }
$u = run($conn, "SELECT m.enquiry_id AS member_id, e.name AS full_name, p.name plan, m.starts_on, m.ends_on, e.fitness_level, e.health_notes
       FROM memberships m
       JOIN packages p ON p.id = m.package_id
       LEFT JOIN gym_enquiries e ON e.id = m.enquiry_id
       WHERE m.user_id = ? AND m.status = 'active' AND m.ends_on >= CURDATE()
       ORDER BY m.id DESC LIMIT 1", "i", [(int)$_SESSION['user_id']])[0]
  ?? ['member_id' => 0, 'full_name' => $acc['full_name'], 'plan' => null, 'starts_on' => null, 'ends_on' => null, 'fitness_level' => 'Beginner', 'health_notes' => null];

$diets = []; $works = [];
if ($u['plan']) {
    $diets = run($conn, "SELECT * FROM diet_plans WHERE plan_tier = ? ORDER BY FIELD(meal_time,'Breakfast','Mid-Morning','Lunch','Snack','Dinner'), id", "s", [$u['plan']]);
    $works = run($conn, "SELECT * FROM workout_plans WHERE plan_tier = ? AND fitness_level = ? ORDER BY id", "ss", [$u['plan'], $u['fitness_level']]);
}

// this month's attendance
$first = date('Y-m-01'); $last = date('Y-m-t');
$att = run($conn, "SELECT status, COUNT(*) c FROM attendance WHERE member_id = ? AND attend_date BETWEEN ? AND ? GROUP BY status", "iss", [(int)$u['member_id'], $first, $last]);
$present = 0; $absent = 0;
foreach ($att as $a) { if ($a['status'] === 'Present') $present = (int)$a['c']; else $absent = (int)$a['c']; }
$payments = run($conn, "SELECT id, amount, method, receipt_no, paid_at FROM payments WHERE user_id = ? ORDER BY id DESC", "i", [(int)$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Plan - Rajkot Fitness Club</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700&family=Raleway:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --gold:#c9a84c; --gold-light:#e8c97a; --gold-dark:#a07830; --card:#1c1c1c; --border:rgba(201,168,76,.25); }
body { background:#0a0a0a; color:#f5f5f5; font-family:'Raleway',sans-serif; }
h1,h2,h3,h4 { font-family:'Bebas Neue',sans-serif; letter-spacing:2px; }
a { text-decoration:none; color:inherit; }
.top { border-bottom:1px solid var(--border); padding:14px 0; }
.btn-gold { background:linear-gradient(135deg,var(--gold),var(--gold-dark)); color:#000; font-family:'Montserrat',sans-serif; font-weight:700; font-size:.78rem; letter-spacing:1.5px; text-transform:uppercase; padding:9px 20px; border-radius:3px; border:0; }
.panel { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.5rem; margin-bottom:1.5rem; }
.panel h3 { color:var(--gold-light); font-size:1.5rem; margin-bottom:1rem; }
.pill { display:inline-block; background:rgba(201,168,76,.15); border:1px solid var(--gold); color:var(--gold-light); font-size:.72rem; letter-spacing:1.5px; text-transform:uppercase; padding:4px 14px; border-radius:20px; font-family:'Montserrat',sans-serif; font-weight:700; }
table { width:100%; border-collapse:collapse; font-size:.88rem; }
th { color:var(--gold-light); font-size:.7rem; letter-spacing:1.5px; text-transform:uppercase; padding:10px; border-bottom:2px solid var(--gold); font-family:'Montserrat',sans-serif; white-space:nowrap; }
td { padding:10px; border-bottom:1px solid rgba(201,168,76,.1); color:#ccc; vertical-align:top; }
.warn { color:#ffb347; font-size:.8rem; }
.alertbox { background:rgba(255,179,71,.08); border:1px solid #ffb347; color:#ffcf8a; border-radius:8px; padding:.9rem 1.1rem; margin-bottom:1.3rem; font-size:.88rem; }
.n { font-family:'Bebas Neue',sans-serif; font-size:2.2rem; color:var(--gold-light); line-height:1; }
.l { font-size:.7rem; letter-spacing:2px; text-transform:uppercase; color:#aaa; }
</style>
</head>
<body>
<div class="top">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h3 class="m-0" style="color:var(--gold-light)">Rajkot Fitness Club</h3>
    <div class="d-flex gap-2 align-items-center">
      <span style="color:var(--gold-light);font-size:.85rem;"><?= h($u['full_name']) ?></span>
      <a href="payment.php" class="btn-gold">Payments</a>
      <a href="gym.php" class="btn-gold" style="background:transparent;border:1.5px solid var(--gold);color:var(--gold-light);">Main Site</a>
      <a href="login.php?logout=1" class="btn-gold">Logout</a>
    </div>
  </div>
</div>

<div class="container py-4">
  <h2 class="mb-3">My Plan</h2>

  <?php if (!$u['plan']): ?>
    <div class="panel">We could not find your membership. Please fill the Join form on the website using the same email you log in with, or ask at the front desk.</div>
  <?php else: ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="panel mb-0"><div class="l">Plan</div><div class="n"><?= h($u['plan']) ?></div><small style="color:#aaa">Until <?= h(date('d M Y', strtotime($u['ends_on']))) ?></small></div></div>
    <div class="col-6 col-md-3"><div class="panel mb-0"><div class="l">Level</div><div class="n"><?= h($u['fitness_level']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="panel mb-0"><div class="l">Present (this month)</div><div class="n" style="color:#2ecc71"><?= $present ?></div></div></div>
    <div class="col-6 col-md-3"><div class="panel mb-0"><div class="l">Absent (this month)</div><div class="n" style="color:#ff6b78"><?= $absent ?></div></div></div>
  </div>

  <?php if ($u['health_notes']): ?>
    <div class="alertbox"><b>⚠ Note from your trainer:</b> <?= h($u['health_notes']) ?></div>
  <?php endif; ?>

  <div class="panel">
    <h3>Diet Plan</h3>
    <?php if (!$diets): ?><p class="mb-0" style="color:#aaa">No diet plan added for your plan yet.</p><?php else: ?>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>Meal</th><th>Title</th><th>What to eat</th><th>Cal</th></tr></thead>
      <tbody>
      <?php foreach ($diets as $d): ?>
        <tr><td><b style="color:#fff"><?= h($d['meal_time']) ?></b></td><td><?= h($d['title']) ?></td><td><?= nl2br(h($d['details'])) ?></td><td><?= h($d['calories']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>Payment History</h3>
    <?php if (!$payments): ?><p class="mb-0" style="color:#aaa">No payments recorded yet. <a href="payment.php" style="color:var(--gold-light)">Make a dummy payment</a>.</p><?php else: ?>
    <div style="overflow-x:auto"><table><thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead><tbody>
      <?php foreach ($payments as $payment): ?><tr><td><?= h($payment['receipt_no']) ?></td><td>&#8377;<?= number_format((float)$payment['amount'], 2) ?></td><td><?= h($payment['method']) ?></td><td><?= h(date('d M Y', strtotime($payment['paid_at']))) ?></td><td><a href="receipt.php?id=<?= (int)$payment['id'] ?>" style="color:var(--gold-light)">Receipt</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>Workout Plan — <?= h($u['fitness_level']) ?></h3>
    <div class="alertbox">If you cannot perform an exercise safely, <b>do not do it</b>. Stop at once if you feel pain, dizziness or breathlessness, and tell your trainer.</div>
    <?php if (!$works): ?><p class="mb-0" style="color:#aaa">No workout added for your plan and level yet.</p><?php else: ?>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>Exercise</th><th>Sets</th><th>Reps / time</th><th>Rest</th><th>Warning</th></tr></thead>
      <tbody>
      <?php foreach ($works as $w): ?>
        <tr><td><b style="color:#fff"><?= h($w['exercise_name']) ?></b></td><td><?= (int)$w['sets'] ?></td><td><?= h($w['reps']) ?></td><td><?= (int)$w['rest_seconds'] ?>s</td>
            <td class="warn"><?= $w['warning'] ? '⚠ ' . h($w['warning']) : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>
</body>
</html>

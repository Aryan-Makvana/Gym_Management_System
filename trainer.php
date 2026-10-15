<?php
// ============================================================
// RAJKOT FITNESS CLUB - Trainer Panel
// File: trainer.php   (trainer and admin can open this page)
// Tabs: Members | Diet Plans | Workout Plans | Attendance
// Tables are created by login.php (users, diet_plans, workout_plans, attendance)
// ============================================================
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['trainer', 'admin'], true)) { header('Location: gym.php'); exit; }

$conn = mysqli_connect("localhost", "root", "", "gym_db");
if (!$conn) die("Connection failed: " . mysqli_connect_error());
mysqli_set_charset($conn, "utf8mb4");
if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'")) == 0) {
    die("Tables not created yet. Open login.php once (the new version) and then reload this page.");
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$PLANS  = ['Basic', 'Standard', 'Premium', 'Annual'];
$LEVELS = ['Beginner', 'Intermediate', 'Advanced'];
$MEALS  = ['Breakfast', 'Mid-Morning', 'Lunch', 'Snack', 'Dinner'];
$me     = (int)$_SESSION['user_id'];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function run($conn, $sql, $types = '', $params = []) {
    $st = mysqli_prepare($conn, $sql);
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    return $st;
}
function rows($st) { return mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC); }
function go($tab, $msg, $extra = '') { header("Location: trainer.php?tab=$tab&msg=$msg$extra"); exit; }
function valid_date($d) {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/* =================== HANDLE POST ACTIONS =================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die("Invalid request. Go back and reload the page.");
    $act = $_POST['act'] ?? '';

    // ---- member: trainer edits ONLY fitness level + health notes (plan comes from the join form) ----
    if ($act === 'save_member') {
        $id    = (int)$_POST['id'];
        $level = in_array($_POST['level'] ?? '', $LEVELS, true) ? $_POST['level'] : 'Beginner';
        $notes = mb_substr(trim($_POST['notes'] ?? ''), 0, 255);
        run($conn, "UPDATE gym_enquiries SET fitness_level = ?, health_notes = ? WHERE id = ?", "ssi", [$level, $notes, $id]);
        go('members', 'member_saved');
    }

    // ---- diet: add / update / delete ----
    if ($act === 'save_diet') {
        $id   = (int)($_POST['id'] ?? 0);
        $tier = in_array($_POST['tier'] ?? '', $PLANS, true) ? $_POST['tier'] : null;
        $meal = in_array($_POST['meal'] ?? '', $MEALS, true) ? $_POST['meal'] : null;
        $title = trim($_POST['title'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $cal = ($_POST['calories'] ?? '') === '' ? null : max(0, (int)$_POST['calories']);
        if (!$tier || !$meal || $title === '' || $details === '') go('diet', 'missing');
        if ($id > 0) {
            run($conn, "UPDATE diet_plans SET plan_tier=?, meal_time=?, title=?, details=?, calories=?, created_by=? WHERE id=?",
                "ssssiii", [$tier, $meal, $title, $details, $cal, $me, $id]);
            go('diet', 'diet_updated');
        }
        run($conn, "INSERT INTO diet_plans (plan_tier, meal_time, title, details, calories, created_by) VALUES (?,?,?,?,?,?)",
            "ssssii", [$tier, $meal, $title, $details, $cal, $me]);
        go('diet', 'diet_added');
    }
    if ($act === 'del_diet') {
        run($conn, "DELETE FROM diet_plans WHERE id = ?", "i", [(int)$_POST['id']]);
        go('diet', 'deleted');
    }

    // ---- workout: add / update / delete ----
    if ($act === 'save_workout') {
        $id    = (int)($_POST['id'] ?? 0);
        $tier  = in_array($_POST['tier'] ?? '', $PLANS, true) ? $_POST['tier'] : null;
        $level = in_array($_POST['level'] ?? '', $LEVELS, true) ? $_POST['level'] : null;
        $name  = trim($_POST['exercise'] ?? '');
        $sets  = max(1, (int)($_POST['sets'] ?? 3));
        $reps  = trim($_POST['reps'] ?? '');
        $rest  = max(0, (int)($_POST['rest'] ?? 60));
        $warn  = mb_substr(trim($_POST['warning'] ?? ''), 0, 255);
        if (!$tier || !$level || $name === '' || $reps === '') go('workout', 'missing');
        if ($id > 0) {
            run($conn, "UPDATE workout_plans SET plan_tier=?, fitness_level=?, exercise_name=?, sets=?, reps=?, rest_seconds=?, warning=?, created_by=? WHERE id=?",
                "sssisisii", [$tier, $level, $name, $sets, $reps, $rest, $warn, $me, $id]);
            go('workout', 'workout_updated');
        }
        run($conn, "INSERT INTO workout_plans (plan_tier, fitness_level, exercise_name, sets, reps, rest_seconds, warning, created_by) VALUES (?,?,?,?,?,?,?,?)",
            "sssisisi", [$tier, $level, $name, $sets, $reps, $rest, $warn, $me]);
        go('workout', 'workout_added');
    }
    if ($act === 'del_workout') {
        run($conn, "DELETE FROM workout_plans WHERE id = ?", "i", [(int)$_POST['id']]);
        go('workout', 'deleted');
    }

    // ---- attendance: save the whole sheet for one date ----
    if ($act === 'save_attendance') {
        $date = $_POST['date'] ?? '';
        if (!valid_date($date) || $date > date('Y-m-d')) go('attendance', 'bad_date');
        $att = $_POST['att'] ?? [];
        $checkIns = $_POST['check_in'] ?? [];
        $checkOuts = $_POST['check_out'] ?? [];
        $st = mysqli_prepare($conn,
          "INSERT INTO attendance (member_id, attend_date, status, marked_by, check_in, check_out) VALUES (?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), check_in = VALUES(check_in), check_out = VALUES(check_out)");
        $ids = array_column(rows(run($conn, "SELECT id FROM gym_enquiries")), 'id');
        foreach ($ids as $uid) {
            $status = (($att[$uid] ?? 'Absent') === 'Present') ? 'Present' : 'Absent';
            $checkIn = preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $checkIns[$uid] ?? '') ? $checkIns[$uid] : null;
            $checkOut = preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $checkOuts[$uid] ?? '') ? $checkOuts[$uid] : null;
            mysqli_stmt_bind_param($st, "ississ", $uid, $date, $status, $me, $checkIn, $checkOut);
            mysqli_stmt_execute($st);
        }
        go('attendance', 'att_saved', '&date=' . urlencode($date));
    }
}

/* =================== LOAD DATA FOR THE PAGE =================== */
$tab = in_array($_GET['tab'] ?? '', ['members', 'diet', 'workout', 'attendance'], true) ? $_GET['tab'] : 'members';

// members = people who registered with the JOIN FORM on the website (gym_enquiries)
$search = trim($_GET['search'] ?? '');
$cols = "id, name AS full_name, email, phone, plan, goal, fitness_level, health_notes, created_at";
if ($search !== '') {
    $like = "%$search%";
    $members = rows(run($conn, "SELECT $cols FROM gym_enquiries WHERE (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY id", "sss", [$like, $like, $like]));
} else {
    $members = rows(run($conn, "SELECT $cols FROM gym_enquiries ORDER BY id"));
}
$allMembers = rows(run($conn, "SELECT id, name AS full_name, plan FROM gym_enquiries ORDER BY id"));
$planCount = array_fill_keys($PLANS, 0); $noPlan = 0;
foreach ($allMembers as $m) { if (isset($planCount[$m['plan']])) $planCount[$m['plan']]++; else $noPlan++; }

// diet + workout
$editDiet = null; $editWo = null;
if (isset($_GET['edit_diet']))    { $r = rows(run($conn, "SELECT * FROM diet_plans WHERE id=?", "i", [(int)$_GET['edit_diet']])); $editDiet = $r[0] ?? null; }
if (isset($_GET['edit_workout'])) { $r = rows(run($conn, "SELECT * FROM workout_plans WHERE id=?", "i", [(int)$_GET['edit_workout']])); $editWo = $r[0] ?? null; }
$diets = rows(run($conn, "SELECT * FROM diet_plans ORDER BY FIELD(plan_tier,'Basic','Standard','Premium','Annual'), FIELD(meal_time,'Breakfast','Mid-Morning','Lunch','Snack','Dinner'), id"));
$works = rows(run($conn, "SELECT * FROM workout_plans ORDER BY FIELD(plan_tier,'Basic','Standard','Premium','Annual'), FIELD(fitness_level,'Beginner','Intermediate','Advanced'), id"));

// attendance - daily sheet
$attDate = $_GET['date'] ?? date('Y-m-d');
if (!valid_date($attDate) || $attDate > date('Y-m-d')) $attDate = date('Y-m-d');
$attMap = [];
foreach (rows(run($conn, "SELECT member_id AS user_id, status, check_in, check_out FROM attendance WHERE attend_date = ?", "s", [$attDate])) as $a) $attMap[$a['user_id']] = $a;

// attendance - monthly report
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
$first = "$month-01"; $daysInMonth = (int)date('t', strtotime($first)); $last = "$month-" . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
$grid = [];
foreach (rows(run($conn, "SELECT member_id AS user_id, DAY(attend_date) d, status FROM attendance WHERE attend_date BETWEEN ? AND ?", "ss", [$first, $last])) as $a)
    $grid[$a['user_id']][(int)$a['d']] = $a['status'];

$flash = [
    'member_saved' => ['ok', 'Member details saved.'],
    'diet_added' => ['ok', 'Diet plan added.'], 'diet_updated' => ['ok', 'Diet plan updated.'],
    'workout_added' => ['ok', 'Workout added.'], 'workout_updated' => ['ok', 'Workout updated.'],
    'deleted' => ['bad', 'Record deleted.'], 'missing' => ['bad', 'Please fill all required fields.'],
    'att_saved' => ['ok', 'Attendance saved.'], 'bad_date' => ['bad', 'Choose a valid date (not in the future).'],
];
$msg = $flash[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RFC Trainer Panel</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700&family=Raleway:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --black:#0a0a0a; --darker:#181818; --card:#1c1c1c; --gold:#c9a84c; --gold-light:#e8c97a; --gold-dark:#a07830;
        --red:#e63946; --green:#2ecc71; --white:#f5f5f5; --muted:#aaa; --border:rgba(201,168,76,.25); }
* { box-sizing:border-box; }
body { background:var(--black); color:var(--white); font-family:'Raleway',sans-serif; }
h1,h2,h3,h4,h5 { font-family:'Bebas Neue',sans-serif; letter-spacing:2px; }
a { text-decoration:none; color:inherit; }
#nav { background:rgba(10,10,10,.97); border-bottom:1px solid var(--border); padding:12px 0; position:sticky; top:0; z-index:1030; }
.brand { font-family:'Bebas Neue',sans-serif; font-size:1.4rem; letter-spacing:3px; line-height:1.1; }
.brand small { display:block; color:var(--gold-light); font-size:.8rem; letter-spacing:5px; }
.tag { background:rgba(201,168,76,.15); border:1px solid var(--gold); color:var(--gold-light); font-size:.7rem; letter-spacing:2px;
       text-transform:uppercase; padding:3px 12px; border-radius:2px; font-family:'Montserrat',sans-serif; font-weight:700; }
.btn-gold { background:linear-gradient(135deg,var(--gold),var(--gold-dark)); color:#000; font-family:'Montserrat',sans-serif; font-weight:700;
            font-size:.78rem; letter-spacing:1.5px; text-transform:uppercase; padding:9px 20px; border:0; border-radius:3px; }
.btn-gold:hover { filter:brightness(1.1); color:#000; }
.btn-line { background:transparent; color:var(--gold-light); border:1.5px solid var(--gold); font-family:'Montserrat',sans-serif; font-weight:700;
            font-size:.75rem; letter-spacing:1.5px; text-transform:uppercase; padding:8px 16px; border-radius:3px; }
.btn-line:hover { background:rgba(201,168,76,.1); color:var(--gold-light); }
.btn-sm-edit { background:rgba(201,168,76,.12); color:var(--gold-light); border:1px solid var(--border); font-size:.74rem; padding:4px 11px; border-radius:4px; font-weight:600; }
.btn-sm-del  { background:rgba(230,57,70,.1); color:#e87f87; border:1px solid rgba(230,57,70,.3); font-size:.74rem; padding:4px 11px; border-radius:4px; font-weight:600; }
.tabs { display:flex; gap:6px; flex-wrap:wrap; border-bottom:1px solid var(--border); margin-bottom:1.5rem; }
.tabs a { padding:10px 18px; font-family:'Montserrat',sans-serif; font-size:.78rem; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--muted); border-bottom:3px solid transparent; }
.tabs a.on { color:var(--gold-light); border-bottom-color:var(--gold); }
.panel { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.6rem; margin-bottom:1.6rem; position:relative; overflow:hidden; }
.panel::before { content:''; position:absolute; top:0; left:0; width:100%; height:4px; background:linear-gradient(90deg,var(--gold),var(--gold-dark)); }
.ptitle { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:2px; color:var(--gold-light); margin-bottom:1rem; }
.form-control,.form-select { background:#111; border:1px solid rgba(201,168,76,.25); color:var(--white); border-radius:6px; font-size:.88rem; }
.form-control:focus,.form-select:focus { background:#111; color:var(--white); border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,168,76,.1); }
.form-select option { background:#1c1c1c; }
.form-label { font-size:.72rem; letter-spacing:1.5px; text-transform:uppercase; color:var(--gold-light); font-family:'Montserrat',sans-serif; font-weight:600; margin-bottom:4px; }
.stat { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.2rem; }
.stat .n { font-family:'Bebas Neue',sans-serif; font-size:2.2rem; color:var(--gold-light); line-height:1; }
.stat .l { font-size:.7rem; letter-spacing:2px; text-transform:uppercase; color:var(--muted); font-family:'Montserrat',sans-serif; }
.wrap { overflow-x:auto; border:1px solid var(--border); border-radius:10px; }
table.t { width:100%; border-collapse:collapse; font-size:.84rem; }
table.t th { background:rgba(201,168,76,.15); color:var(--gold-light); font-family:'Montserrat',sans-serif; font-size:.7rem; letter-spacing:1.5px; text-transform:uppercase; padding:12px; white-space:nowrap; border-bottom:2px solid var(--gold); }
table.t td { padding:10px 12px; color:var(--muted); border-bottom:1px solid rgba(201,168,76,.08); vertical-align:middle; }
table.t td.nm { color:var(--white); font-weight:600; }
.pill { display:inline-block; font-size:.66rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; padding:3px 10px; border-radius:20px; font-family:'Montserrat',sans-serif; }
.p-Basic{background:rgba(201,168,76,.12);color:var(--gold);border:1px solid rgba(201,168,76,.3)}
.p-Standard{background:rgba(30,100,200,.15);color:#7fb3ff;border:1px solid rgba(30,100,200,.3)}
.p-Premium{background:rgba(160,48,200,.15);color:#d89fff;border:1px solid rgba(160,48,200,.3)}
.p-Annual{background:rgba(230,57,70,.12);color:#e87f87;border:1px solid rgba(230,57,70,.3)}
.p-none{background:#222;color:#777;border:1px solid #333}
.warn { color:#ffb347; font-size:.78rem; }
.alert-x { border-radius:8px; padding:.8rem 1.1rem; margin-bottom:1.3rem; font-size:.87rem; }
.alert-ok  { background:rgba(201,168,76,.08); border:1px solid var(--gold); color:var(--gold-light); }
.alert-bad { background:rgba(230,57,70,.08); border:1px solid var(--red); color:#e87f87; }
.tier-head { font-family:'Bebas Neue',sans-serif; font-size:1.25rem; letter-spacing:2px; color:var(--gold-light); margin:1.2rem 0 .6rem; }
/* attendance */
.att-opt input { display:none; }
.att-opt label { cursor:pointer; padding:5px 14px; border-radius:20px; font-size:.75rem; font-weight:700; font-family:'Montserrat',sans-serif; border:1px solid #444; color:#888; margin:0 4px 0 0; }
.att-opt input[value=Present]:checked + label { background:rgba(46,204,113,.18); color:var(--green); border-color:var(--green); }
.att-opt input[value=Absent]:checked + label  { background:rgba(230,57,70,.18); color:#ff6b78; border-color:var(--red); }
.cP { color:var(--green); font-weight:700; text-align:center; } .cA { color:#ff6b78; font-weight:700; text-align:center; } .cN { color:#444; text-align:center; }
table.g td, table.g th { padding:6px 5px; font-size:.74rem; text-align:center; min-width:26px; }
table.g td.nm, table.g th.nm { text-align:left; min-width:140px; position:sticky; left:0; background:var(--card); }
</style>
</head>
<body>

<nav id="nav">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="brand"><small>Rajkot</small>Fitness Club</div>
      <span class="tag"><i class="bi bi-person-badge-fill me-1"></i>Trainer Panel</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span style="color:var(--gold-light);font-size:.85rem;"><?= h($_SESSION['name']) ?> (<?= h(ucfirst($_SESSION['role'])) ?>)</span>
      <a href="gym.php" class="btn-line">Main Site</a>
      <?php if ($_SESSION['role'] === 'admin'): ?><a href="admin.php" class="btn-line">Admin Panel</a><?php endif; ?>
      <a href="login.php?logout=1" class="btn-gold">Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4">

  <?php if ($msg): ?><div class="alert-x alert-<?= $msg[0] ?>"><?= h($msg[1]) ?></div><?php endif; ?>

  <div class="tabs">
    <a href="?tab=members"    class="<?= $tab==='members'?'on':'' ?>"><i class="bi bi-people-fill me-1"></i>Members</a>
    <a href="?tab=diet"       class="<?= $tab==='diet'?'on':'' ?>"><i class="bi bi-egg-fried me-1"></i>Diet Plans</a>
    <a href="?tab=workout"    class="<?= $tab==='workout'?'on':'' ?>"><i class="bi bi-lightning-charge-fill me-1"></i>Workout Plans</a>
    <a href="?tab=attendance" class="<?= $tab==='attendance'?'on':'' ?>"><i class="bi bi-calendar-check-fill me-1"></i>Attendance</a>
  </div>

<?php /* ===================== MEMBERS ===================== */ if ($tab === 'members'): ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-lg-2"><div class="stat"><div class="n"><?= count($allMembers) ?></div><div class="l">Total Members</div></div></div>
    <?php foreach ($PLANS as $p): ?>
      <div class="col-6 col-md-3 col-lg-2"><div class="stat"><div class="n"><?= $planCount[$p] ?></div><div class="l"><?= $p ?></div></div></div>
    <?php endforeach; ?>
    <div class="col-6 col-md-3 col-lg-2"><div class="stat"><div class="n" style="color:#777"><?= $noPlan ?></div><div class="l">No Plan Yet</div></div></div>
  </div>

  <div class="panel">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-end mb-3">
      <div class="ptitle mb-0">All Members</div>
      <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="tab" value="members">
        <input type="text" name="search" class="form-control" placeholder="Name, email or phone" value="<?= h($search) ?>">
        <button class="btn-gold">Search</button>
        <?php if ($search !== ''): ?><a href="?tab=members" class="btn-line">Reset</a><?php endif; ?>
      </form>
    </div>
    <p style="color:var(--muted);font-size:.82rem;">These are the people who registered with the <b>Join form</b> on the website. Their <b>plan</b> is what they chose in the form. You can add or edit only the <b>fitness level</b> and <b>health notes</b> (injuries or exercises to avoid).</p>
    <div class="wrap">
      <table class="t">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Plan</th><th>Goal</th><th>Level</th><th>Health notes / avoid</th><th></th></tr></thead>
        <tbody>
        <?php if (!$members): ?><tr><td colspan="9" style="text-align:center;padding:2rem;">No members have registered yet.</td></tr><?php endif; ?>
        <?php foreach ($members as $m): $fid = 'mf' . (int)$m['id']; ?>
          <tr>
            <td>
              <form id="<?= $fid ?>" method="POST" class="d-inline">
                <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="save_member"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              </form>
              <?= (int)$m['id'] ?>
            </td>
            <td class="nm"><?= h($m['full_name']) ?></td>
            <td><?= h($m['email']) ?></td>
            <td><?= h($m['phone']) ?></td>
            <td><span class="pill p-<?= $m['plan'] ? h($m['plan']) : 'none' ?>"><?= $m['plan'] ? h($m['plan']) : 'no plan' ?></span></td>
            <td><?= h($m['goal']) ?></td>
            <td style="min-width:140px;">
              <select name="level" form="<?= $fid ?>" class="form-select form-select-sm">
                <?php foreach ($LEVELS as $l): ?><option <?= $m['fitness_level']===$l?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
              </select>
            </td>
            <td style="min-width:200px;"><input type="text" name="notes" form="<?= $fid ?>" maxlength="255" class="form-control form-control-sm" placeholder="e.g. knee pain - no squats" value="<?= h($m['health_notes']) ?>"></td>
            <td><button form="<?= $fid ?>" class="btn-sm-edit"><i class="bi bi-check-lg"></i> Save</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php /* ===================== DIET ===================== */ elseif ($tab === 'diet'): ?>

  <div class="panel">
    <div class="ptitle"><?= $editDiet ? 'Edit Diet Plan' : 'Add Diet Plan' ?></div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="save_diet">
      <input type="hidden" name="id" value="<?= $editDiet ? (int)$editDiet['id'] : 0 ?>">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">For plan *</label>
          <select name="tier" class="form-select"><?php foreach ($PLANS as $p): ?><option <?= ($editDiet['plan_tier'] ?? '')===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Meal *</label>
          <select name="meal" class="form-select"><?php foreach ($MEALS as $x): ?><option <?= ($editDiet['meal_time'] ?? '')===$x?'selected':'' ?>><?= $x ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" maxlength="120" required value="<?= h($editDiet['title'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Calories</label>
          <input type="number" name="calories" min="0" class="form-control" value="<?= h($editDiet['calories'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Diet details *</label>
          <textarea name="details" rows="3" class="form-control" required placeholder="e.g. 2 boiled eggs, 1 banana, 1 glass milk"><?= h($editDiet['details'] ?? '') ?></textarea></div>
        <div class="col-12 d-flex gap-2">
          <button class="btn-gold"><?= $editDiet ? 'Update Diet' : 'Add Diet' ?></button>
          <?php if ($editDiet): ?><a href="?tab=diet" class="btn-line">Cancel</a><?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="ptitle">All Diet Plans</div>
    <?php if (!$diets): ?><p style="color:var(--muted)">No diet plans yet.</p><?php endif; ?>
    <?php $cur = ''; foreach ($diets as $d): if ($cur !== $d['plan_tier']): if ($cur !== '') echo '</tbody></table></div>'; $cur = $d['plan_tier']; ?>
      <div class="tier-head"><span class="pill p-<?= h($cur) ?>"><?= h($cur) ?> plan</span></div>
      <div class="wrap"><table class="t"><thead><tr><th>Meal</th><th>Title</th><th>Details</th><th>Cal</th><th></th></tr></thead><tbody>
    <?php endif; ?>
      <tr>
        <td class="nm"><?= h($d['meal_time']) ?></td><td><?= h($d['title']) ?></td>
        <td style="min-width:240px;white-space:normal;"><?= nl2br(h($d['details'])) ?></td>
        <td><?= h($d['calories']) ?></td>
        <td style="white-space:nowrap;">
          <a class="btn-sm-edit" href="?tab=diet&edit_diet=<?= (int)$d['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this diet?')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_diet"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="btn-sm-del"><i class="bi bi-trash"></i> Del</button></form>
        </td>
      </tr>
    <?php endforeach; if ($cur !== '') echo '</tbody></table></div>'; ?>
  </div>

<?php /* ===================== WORKOUT ===================== */ elseif ($tab === 'workout'): ?>

  <div class="panel">
    <div class="ptitle"><?= $editWo ? 'Edit Workout' : 'Add Workout' ?></div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="save_workout">
      <input type="hidden" name="id" value="<?= $editWo ? (int)$editWo['id'] : 0 ?>">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">For plan *</label>
          <select name="tier" class="form-select"><?php foreach ($PLANS as $p): ?><option <?= ($editWo['plan_tier'] ?? '')===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Fitness level *</label>
          <select name="level" class="form-select"><?php foreach ($LEVELS as $l): ?><option <?= ($editWo['fitness_level'] ?? '')===$l?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Exercise *</label>
          <input type="text" name="exercise" class="form-control" maxlength="120" required placeholder="e.g. Push-ups" value="<?= h($editWo['exercise_name'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Sets *</label>
          <input type="number" name="sets" min="1" class="form-control" value="<?= h($editWo['sets'] ?? 3) ?>"></div>
        <div class="col-md-4"><label class="form-label">Reps / time *</label>
          <input type="text" name="reps" class="form-control" maxlength="60" required placeholder="e.g. 10 push-ups  or  30 seconds" value="<?= h($editWo['reps'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Rest (sec)</label>
          <input type="number" name="rest" min="0" class="form-control" value="<?= h($editWo['rest_seconds'] ?? 60) ?>"></div>
        <div class="col-md-4"><label class="form-label">⚠ Warning</label>
          <input type="text" name="warning" class="form-control" maxlength="255" placeholder="e.g. Skip if you have wrist pain" value="<?= h($editWo['warning'] ?? '') ?>"></div>
        <div class="col-12 d-flex gap-2">
          <button class="btn-gold"><?= $editWo ? 'Update Workout' : 'Add Workout' ?></button>
          <?php if ($editWo): ?><a href="?tab=workout" class="btn-line">Cancel</a><?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="ptitle">All Workout Plans</div>
    <?php if (!$works): ?><p style="color:var(--muted)">No workouts yet.</p><?php endif; ?>
    <?php $cur = ''; foreach ($works as $w): $key = $w['plan_tier'] . ' · ' . $w['fitness_level'];
      if ($cur !== $key): if ($cur !== '') echo '</tbody></table></div>'; $cur = $key; ?>
      <div class="tier-head"><span class="pill p-<?= h($w['plan_tier']) ?>"><?= h($w['plan_tier']) ?></span> <?= h($w['fitness_level']) ?></div>
      <div class="wrap"><table class="t"><thead><tr><th>Exercise</th><th>Sets</th><th>Reps / time</th><th>Rest</th><th>Warning</th><th></th></tr></thead><tbody>
    <?php endif; ?>
      <tr>
        <td class="nm"><?= h($w['exercise_name']) ?></td><td><?= (int)$w['sets'] ?></td><td><?= h($w['reps']) ?></td><td><?= (int)$w['rest_seconds'] ?>s</td>
        <td class="warn" style="min-width:200px;white-space:normal;"><?= $w['warning'] ? '⚠ ' . h($w['warning']) : '' ?></td>
        <td style="white-space:nowrap;">
          <a class="btn-sm-edit" href="?tab=workout&edit_workout=<?= (int)$w['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this workout?')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_workout"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
            <button class="btn-sm-del"><i class="bi bi-trash"></i> Del</button></form>
        </td>
      </tr>
    <?php endforeach; if ($cur !== '') echo '</tbody></table></div>'; ?>
  </div>

<?php /* ===================== ATTENDANCE ===================== */ else: ?>

  <div class="panel">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-end mb-3">
      <div class="ptitle mb-0">Mark Attendance</div>
      <form method="GET" class="d-flex gap-2 align-items-end">
        <input type="hidden" name="tab" value="attendance">
        <div><label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= h($attDate) ?>"></div>
        <button class="btn-line">Load</button>
      </form>
    </div>
    <?php if (!$allMembers): ?>
      <p style="color:var(--muted)">No members yet.</p>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="save_attendance">
      <input type="hidden" name="date" value="<?= h($attDate) ?>">
      <div class="wrap">
        <table class="t">
          <thead><tr><th>ID</th><th>Name</th><th>Plan</th><th>Attendance for <?= h(date('d M Y', strtotime($attDate))) ?></th><th>Check-in</th><th>Check-out</th></tr></thead>
          <tbody>
          <?php foreach ($allMembers as $m): $record = $attMap[$m['id']] ?? []; $cur = $record['status'] ?? 'Absent'; ?>
            <tr>
              <td><?= (int)$m['id'] ?></td>
              <td class="nm"><?= h($m['full_name']) ?></td>
              <td><span class="pill p-<?= $m['plan'] ? h($m['plan']) : 'none' ?>"><?= $m['plan'] ? h($m['plan']) : 'no plan' ?></span></td>
              <td class="att-opt">
                <input type="radio" id="p<?= $m['id'] ?>" name="att[<?= (int)$m['id'] ?>]" value="Present" <?= $cur==='Present'?'checked':'' ?>><label for="p<?= $m['id'] ?>">Present</label>
                <input type="radio" id="a<?= $m['id'] ?>" name="att[<?= (int)$m['id'] ?>]" value="Absent"  <?= $cur==='Absent'?'checked':'' ?>><label for="a<?= $m['id'] ?>">Absent</label>
              </td>
              <td><input type="time" name="check_in[<?= (int)$m['id'] ?>]" class="form-control form-control-sm" value="<?= h($record['check_in'] ?? '') ?>"></td>
              <td><input type="time" name="check_out[<?= (int)$m['id'] ?>]" class="form-control form-control-sm" value="<?= h($record['check_out'] ?? '') ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3 align-items-center flex-wrap">
        <button class="btn-gold"><i class="bi bi-save me-1"></i>Save Attendance</button>
        <button type="button" class="btn-line" onclick="document.querySelectorAll('input[value=Present]').forEach(r=>r.checked=true)">Mark all Present</button>
        <span style="color:var(--muted);font-size:.78rem;">Saving again for the same date updates the earlier record.</span>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-end mb-3">
      <div class="ptitle mb-0">Monthly Record — <?= h(date('F Y', strtotime($first))) ?></div>
      <form method="GET" class="d-flex gap-2 align-items-end">
        <input type="hidden" name="tab" value="attendance">
        <div><label class="form-label">Month</label>
          <input type="month" name="month" class="form-control" max="<?= date('Y-m') ?>" value="<?= h($month) ?>"></div>
        <button class="btn-line">Show</button>
      </form>
    </div>
    <div class="wrap">
      <table class="t g">
        <thead><tr>
          <th class="nm">ID · Name</th>
          <?php for ($d = 1; $d <= $daysInMonth; $d++): ?><th><?= $d ?></th><?php endfor; ?>
          <th>P</th><th>A</th><th>%</th>
        </tr></thead>
        <tbody>
        <?php foreach ($allMembers as $m): $pr = 0; $ab = 0; ?>
          <tr>
            <td class="nm"><?= (int)$m['id'] ?> · <?= h($m['full_name']) ?></td>
            <?php for ($d = 1; $d <= $daysInMonth; $d++): $s = $grid[$m['id']][$d] ?? '';
              if ($s === 'Present') { $pr++; echo '<td class="cP">P</td>'; }
              elseif ($s === 'Absent') { $ab++; echo '<td class="cA">A</td>'; }
              else echo '<td class="cN">·</td>';
            endfor; ?>
            <td class="cP"><?= $pr ?></td><td class="cA"><?= $ab ?></td>
            <td style="color:var(--gold-light);font-weight:700;"><?= ($pr + $ab) ? round($pr * 100 / ($pr + $ab)) . '%' : '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="color:var(--muted);font-size:.76rem;margin:.8rem 0 0;">P = Present, A = Absent, · = not marked. The % counts only the days that were marked.</p>
  </div>

<?php endif; ?>

</div>
<script>setTimeout(()=>document.querySelectorAll('.alert-x').forEach(a=>a.style.display='none'),5000);</script>
</body>
</html>

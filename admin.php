<?php
require 'admin_dashboard.php';
exit;

// ============================================================
// RAJKOT FITNESS CLUB — Admin Panel
// File: admin.php
// ============================================================

// 🔒 ADMIN ONLY - must be logged in with the admin role
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: gym.php'); exit; }

$conn = mysqli_connect("localhost", "root", "", "gym_db");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// ➕ INSERT (prepared statement)
if (isset($_POST['add'])) {
    $stmt = mysqli_prepare($conn,
        "INSERT INTO gym_enquiries (name, email, phone, plan, goal, source, message)
         VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssssss",
        $_POST['name'], $_POST['email'], $_POST['phone'],
        $_POST['plan'], $_POST['goal'], $_POST['source'], $_POST['message']);
    mysqli_stmt_execute($stmt);
    header("Location: admin.php?msg=added"); exit;
}

// ❌ DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM gym_enquiries WHERE id=$id");
    header("Location: admin.php?msg=deleted"); exit;
}

// 🔄 UPDATE (prepared statement)
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $stmt = mysqli_prepare($conn,
        "UPDATE gym_enquiries SET name=?, email=?, phone=?, plan=?, goal=?, source=?, message=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "sssssssi",
        $_POST['name'], $_POST['email'], $_POST['phone'],
        $_POST['plan'], $_POST['goal'], $_POST['source'], $_POST['message'], $id);
    mysqli_stmt_execute($stmt);
    header("Location: admin.php?msg=updated"); exit;
}

// ✏️ FETCH FOR EDIT
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM gym_enquiries WHERE id=$id");
    $editData = mysqli_fetch_assoc($res);
}

// 📊 STATS
$total   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gym_enquiries"))[0];
$basic   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gym_enquiries WHERE plan='Basic'"))[0];
$std     = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gym_enquiries WHERE plan='Standard'"))[0];
$prem    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gym_enquiries WHERE plan='Premium'"))[0];
$annual  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gym_enquiries WHERE plan='Annual'"))[0];
$today   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gym_enquiries WHERE DATE(created_at)=CURDATE()"))[0];

// 🔍 SEARCH & FILTER
$search     = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filterPlan = isset($_GET['plan_filter']) ? mysqli_real_escape_string($conn, $_GET['plan_filter']) : '';
$where = "WHERE 1";
if ($search)     $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
if ($filterPlan) $where .= " AND plan='$filterPlan'";
$result = mysqli_query($conn, "SELECT * FROM gym_enquiries $where ORDER BY id DESC");
$rowCount = mysqli_num_rows($result);

// Plan options helper
$plans = ['Basic','Standard','Premium','Annual'];
$goals = ['Weight Loss','Muscle Gain','Strength','General Fitness','Yoga & Wellness','Body Transformation'];
$sources = ['Instagram','Facebook','Google','Walk-in','Friend','Hoarding'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RFC Admin Panel</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700;900&family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
<style>
/* =========================================================
   ROOT & RESET — mirrors gym.php
========================================================= */
:root {
    --black:      #0a0a0a;
    --dark:       #111111;
    --darker:     #181818;
    --card-bg:    #1c1c1c;
    --gold:       #c9a84c;
    --gold-light: #e8c97a;
    --gold-dark:  #a07830;
    --red-accent: #e63946;
    --white:      #f5f5f5;
    --muted:      #aaaaaa;
    --border:     rgba(201,168,76,0.25);
    --transition: all 0.35s ease;
    --green:      #28a745;
}
* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body { background:var(--black); color:var(--white); font-family:'Montserrat',sans-serif; overflow-x:hidden; }
h1,h2,h3,h4,h5 { font-family:'Bebas Neue',sans-serif; letter-spacing:2px; }
p,li,label,input,textarea,select,td,th { font-family:'Raleway',sans-serif; }
a { text-decoration:none; color:inherit; }
.gold-text { background:linear-gradient(135deg,var(--gold-light),var(--gold-dark)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
::-webkit-scrollbar { width:8px; }
::-webkit-scrollbar-track { background:var(--dark); }
::-webkit-scrollbar-thumb { background:linear-gradient(var(--gold),var(--gold-dark)); border-radius:4px; }

/* =========================================================
   TOP NAV
========================================================= */
#adminNav {
    background:rgba(10,10,10,0.97);
    backdrop-filter:blur(10px);
    border-bottom:1px solid var(--border);
    padding:12px 0;
    position:sticky; top:0; z-index:1030;
    box-shadow:0 4px 30px rgba(201,168,76,0.08);
}
.brand-name { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:3px; line-height:1; }
.brand-name .top { display:block; color:var(--gold-light); font-size:0.95rem; letter-spacing:5px; }
.brand-name .bottom { display:block; color:var(--white); font-size:1.3rem; }
.admin-tag {
    display:inline-block; background:rgba(201,168,76,0.15); border:1px solid var(--gold);
    color:var(--gold-light); font-size:0.7rem; letter-spacing:2px; text-transform:uppercase;
    padding:3px 12px; border-radius:2px; font-family:'Montserrat',sans-serif; font-weight:700;
}
.gym-logo-svg { width:42px; height:42px; flex-shrink:0; }
.nav-back {
    background:linear-gradient(135deg,var(--gold),var(--gold-dark));
    color:var(--black); font-family:'Montserrat',sans-serif; font-weight:700;
    font-size:0.78rem; letter-spacing:1.5px; text-transform:uppercase;
    padding:8px 20px; border:none; border-radius:3px; cursor:pointer; transition:var(--transition);
}
.nav-back:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(201,168,76,0.35); color:var(--black); }

/* =========================================================
   STAT CARDS
========================================================= */
.stat-card {
    background:var(--card-bg); border:1px solid var(--border); border-radius:12px;
    padding:1.5rem 1.2rem; position:relative; overflow:hidden; transition:var(--transition);
}
.stat-card:hover { transform:translateY(-5px); box-shadow:0 16px 40px rgba(201,168,76,0.12); border-color:var(--gold); }
.stat-card::before {
    content:''; position:absolute; top:0; left:0; width:100%; height:3px;
    background:linear-gradient(90deg,var(--gold),var(--gold-dark));
}
.stat-card .num { font-family:'Bebas Neue',sans-serif; font-size:2.8rem; color:var(--gold-light); line-height:1; }
.stat-card .lbl { font-size:0.72rem; letter-spacing:2px; text-transform:uppercase; color:var(--muted); font-family:'Montserrat',sans-serif; }
.stat-card .icon { font-size:2rem; opacity:0.15; position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--gold); }

/* =========================================================
   SECTION WRAPPER
========================================================= */
.section-card {
    background:var(--card-bg); border:1px solid var(--border); border-radius:16px;
    padding:2rem; margin-bottom:2rem; position:relative; overflow:hidden;
}
.section-card::before {
    content:''; position:absolute; top:0; left:0; width:100%; height:4px;
    background:linear-gradient(90deg,var(--gold),var(--gold-dark));
}
.section-title-sm { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:2px; color:var(--gold-light); }
.section-divider { width:50px; height:3px; background:linear-gradient(90deg,var(--gold),var(--gold-dark)); border-radius:2px; margin-bottom:1.5rem; }

/* =========================================================
   FORM CONTROLS
========================================================= */
.form-control, .form-select {
    background:#111; border:1px solid rgba(201,168,76,0.25); color:var(--white);
    border-radius:6px; padding:10px 14px; font-family:'Raleway',sans-serif; font-size:0.88rem;
    transition:var(--transition);
}
.form-control:focus, .form-select:focus {
    background:#111; color:var(--white); border-color:var(--gold);
    box-shadow:0 0 0 3px rgba(201,168,76,0.1);
}
.form-control::placeholder { color:#555; }
.form-select option { background:#1c1c1c; color:var(--white); }
.form-label {
    font-size:0.75rem; letter-spacing:1.5px; text-transform:uppercase;
    color:var(--gold-light); font-family:'Montserrat',sans-serif; font-weight:600; margin-bottom:5px;
}

/* =========================================================
   BUTTONS
========================================================= */
.btn-gold {
    background:linear-gradient(135deg,var(--gold),var(--gold-dark)); color:var(--black);
    font-family:'Montserrat',sans-serif; font-weight:700; font-size:0.8rem;
    letter-spacing:1.5px; text-transform:uppercase; padding:10px 22px;
    border:none; border-radius:3px; cursor:pointer; transition:var(--transition);
}
.btn-gold:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(201,168,76,0.35); color:var(--black); }
.btn-edit {
    background:rgba(201,168,76,0.12); color:var(--gold-light); border:1px solid var(--border);
    font-size:0.75rem; padding:5px 12px; border-radius:4px; transition:var(--transition);
    font-family:'Montserrat',sans-serif; font-weight:600; letter-spacing:1px; white-space:nowrap;
}
.btn-edit:hover { border-color:var(--gold); background:rgba(201,168,76,0.2); color:var(--gold-light); }
.btn-del {
    background:rgba(230,57,70,0.1); color:#e87f87; border:1px solid rgba(230,57,70,0.3);
    font-size:0.75rem; padding:5px 12px; border-radius:4px; transition:var(--transition);
    font-family:'Montserrat',sans-serif; font-weight:600; letter-spacing:1px; white-space:nowrap;
}
.btn-del:hover { background:rgba(230,57,70,0.25); border-color:var(--red-accent); color:#ff6b78; }
.btn-update {
    background:linear-gradient(135deg,#1e8449,#145a32); color:#fff;
    font-family:'Montserrat',sans-serif; font-weight:700; font-size:0.78rem;
    letter-spacing:1.5px; text-transform:uppercase; padding:10px 20px;
    border:none; border-radius:3px; cursor:pointer; transition:var(--transition);
}
.btn-update:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(40,167,69,0.3); }
.btn-outline-gold {
    background:transparent; color:var(--gold-light); border:1.5px solid var(--gold);
    font-size:0.78rem; padding:9px 18px; border-radius:3px; transition:var(--transition);
    font-family:'Montserrat',sans-serif; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; cursor:pointer;
}
.btn-outline-gold:hover { background:rgba(201,168,76,0.1); color:var(--gold-light); }

/* =========================================================
   ALERT / TOAST
========================================================= */
.rfc-alert {
    border-radius:8px; padding:0.9rem 1.2rem; font-size:0.87rem;
    font-family:'Raleway',sans-serif; margin-bottom:1.5rem;
}
.rfc-alert-success { background:rgba(201,168,76,0.08); border:1px solid var(--gold); color:var(--gold-light); }
.rfc-alert-danger  { background:rgba(230,57,70,0.08); border:1px solid var(--red-accent); color:#e87f87; }

/* =========================================================
   TABLE
========================================================= */
.rfc-table-wrap { overflow-x:auto; border-radius:10px; border:1px solid var(--border); }
.rfc-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
.rfc-table thead tr {
    background:linear-gradient(90deg,rgba(201,168,76,0.18),rgba(160,120,48,0.15));
    border-bottom:2px solid var(--gold);
}
.rfc-table thead th {
    color:var(--gold-light); font-family:'Montserrat',sans-serif; font-weight:700;
    font-size:0.72rem; letter-spacing:1.5px; text-transform:uppercase;
    padding:14px 14px; white-space:nowrap;
}
.rfc-table tbody tr { border-bottom:1px solid rgba(201,168,76,0.08); transition:var(--transition); }
.rfc-table tbody tr:hover { background:rgba(201,168,76,0.04); }
.rfc-table tbody td { padding:12px 14px; color:var(--muted); vertical-align:middle; }
.rfc-table tbody td .name-cell { color:var(--white); font-weight:600; font-family:'Montserrat',sans-serif; }

/* Plan badges */
.plan-pill {
    display:inline-block; font-size:0.68rem; font-weight:700; letter-spacing:1px;
    text-transform:uppercase; padding:3px 10px; border-radius:20px; font-family:'Montserrat',sans-serif;
}
.plan-Basic    { background:rgba(201,168,76,0.12); color:var(--gold); border:1px solid rgba(201,168,76,0.3); }
.plan-Standard { background:rgba(30,100,200,0.15); color:#7fb3ff; border:1px solid rgba(30,100,200,0.3); }
.plan-Premium  { background:rgba(160,48,200,0.15); color:#d89fff; border:1px solid rgba(160,48,200,0.3); }
.plan-Annual   { background:rgba(230,57,70,0.12); color:#e87f87; border:1px solid rgba(230,57,70,0.3); }

/* Row number */
.rfc-table tbody td.row-id { color:#444; font-size:0.78rem; }

/* =========================================================
   SEARCH BAR
========================================================= */
.search-wrap {
    background:var(--darker); border:1px solid var(--border); border-radius:8px;
    padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;
}

/* =========================================================
   EDIT CARD HIGHLIGHT
========================================================= */
.edit-section-card { border-color:var(--gold-light); }
.edit-section-card::before { background:linear-gradient(90deg,#e8c97a,#c9a84c); }

/* =========================================================
   FOOTER
========================================================= */
.admin-footer {
    border-top:1px solid var(--border); padding:24px 0; margin-top:3rem;
    text-align:center; color:var(--muted); font-size:0.8rem;
}
.admin-footer span { color:var(--gold); }

/* =========================================================
   RESPONSIVE
========================================================= */
@media (max-width:768px) {
    .stat-card .num { font-size:2rem; }
    .section-card { padding:1.2rem; }
    .search-wrap { flex-direction:column; }
}
</style>
</head>
<body>

<!-- ===================== NAV ===================== -->
<nav id="adminNav">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
      <svg class="gym-logo-svg" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="60" height="60" rx="8" fill="#1c1c1c"/>
        <rect x="5" y="27" width="8" height="6" rx="2" fill="#c9a84c"/>
        <rect x="47" y="27" width="8" height="6" rx="2" fill="#c9a84c"/>
        <rect x="10" y="22" width="6" height="16" rx="2" fill="#e8c97a"/>
        <rect x="44" y="22" width="6" height="16" rx="2" fill="#e8c97a"/>
        <rect x="16" y="18" width="6" height="24" rx="2" fill="#c9a84c"/>
        <rect x="38" y="18" width="6" height="24" rx="2" fill="#c9a84c"/>
        <rect x="22" y="28" width="16" height="4" rx="2" fill="#e8c97a"/>
        <rect x="26" y="14" width="8" height="6" rx="2" fill="#a07830"/>
      </svg>
      <div>
        <div class="brand-name"><span class="top">Rajkot</span><span class="bottom">Fitness Club</span></div>
      </div>
      <div class="admin-tag ms-1"><i class="bi bi-shield-lock-fill me-1"></i>Admin</div>
    </div>
    <a href="gym.php" class="nav-back"><i class="bi bi-arrow-left me-1"></i>Main Site</a>
  </div>
</nav>

<!-- ===================== MAIN ===================== -->
<div class="container py-4">

  <!-- FLASH MESSAGES -->
  <?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg']==='added'):   ?><div class="rfc-alert rfc-alert-success"><i class="bi bi-check-circle-fill me-2"></i>Member enquiry added successfully.</div><?php endif; ?>
    <?php if ($_GET['msg']==='updated'): ?><div class="rfc-alert rfc-alert-success"><i class="bi bi-check-circle-fill me-2"></i>Record updated successfully.</div><?php endif; ?>
    <?php if ($_GET['msg']==='deleted'): ?><div class="rfc-alert rfc-alert-danger"><i class="bi bi-trash-fill me-2"></i>Record deleted.</div><?php endif; ?>
  <?php endif; ?>

  <!-- =========================================================
       STAT CARDS
  ========================================================= -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card">
        <div class="num"><?= $total ?></div>
        <div class="lbl">Total Enquiries</div>
        <i class="bi bi-people-fill icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card">
        <div class="num"><?= $today ?></div>
        <div class="lbl">Today</div>
        <i class="bi bi-calendar-check icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card">
        <div class="num"><?= $basic ?></div>
        <div class="lbl">Basic</div>
        <i class="bi bi-star icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card">
        <div class="num"><?= $std ?></div>
        <div class="lbl">Standard</div>
        <i class="bi bi-star-half icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card">
        <div class="num"><?= $prem ?></div>
        <div class="lbl">Premium</div>
        <i class="bi bi-star-fill icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card" style="border-color:rgba(230,57,70,0.3);">
        <div class="num" style="color:#e87f87;"><?= $annual ?></div>
        <div class="lbl">Annual</div>
        <i class="bi bi-trophy-fill icon" style="color:var(--red-accent);"></i>
      </div>
    </div>
  </div>

  <!-- =========================================================
       EDIT FORM
  ========================================================= -->
  <?php if ($editData): ?>
  <div class="section-card edit-section-card mb-4">
    <h3 class="section-title-sm"><i class="bi bi-pencil-square me-2"></i>Edit Member Record</h3>
    <div class="section-divider"></div>
    <form method="POST">
      <input type="hidden" name="id" value="<?= $editData['id'] ?>">
      <div class="row g-3">
        <div class="col-md-4 col-lg-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editData['name']) ?>" required>
        </div>
        <div class="col-md-4 col-lg-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editData['email']) ?>" required>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editData['phone']) ?>" maxlength="10" required>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Plan</label>
          <select name="plan" class="form-select">
            <?php foreach($plans as $p): ?><option <?= $editData['plan']==$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Goal</label>
          <select name="goal" class="form-select">
            <?php foreach($goals as $g): ?><option <?= $editData['goal']==$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Source</label>
          <select name="source" class="form-select">
            <?php foreach($sources as $s): ?><option <?= $editData['source']==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8 col-lg-4">
          <label class="form-label">Message</label>
          <input type="text" name="message" class="form-control" value="<?= htmlspecialchars($editData['message']) ?>">
        </div>
        <div class="col-12 d-flex gap-2 pt-1">
          <button type="submit" name="update" class="btn-update"><i class="bi bi-check-lg me-1"></i>Update Record</button>
          <a href="admin.php" class="btn-outline-gold"><i class="bi bi-x me-1"></i>Cancel</a>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- =========================================================
       ADD FORM
  ========================================================= -->
  <div class="section-card mb-4">
    <h3 class="section-title-sm"><i class="bi bi-person-plus-fill me-2"></i>Add New Enquiry</h3>
    <div class="section-divider"></div>
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-4 col-lg-3">
          <label class="form-label">Full Name <span style="color:var(--red-accent);">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Arjun Patel" required>
        </div>
        <div class="col-md-4 col-lg-3">
          <label class="form-label">Email <span style="color:var(--red-accent);">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Phone <span style="color:var(--red-accent);">*</span></label>
          <input type="text" name="phone" class="form-control" placeholder="10-digit" maxlength="10" required>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Plan</label>
          <select name="plan" class="form-select">
            <?php foreach($plans as $p): ?><option><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Goal</label>
          <select name="goal" class="form-select">
            <?php foreach($goals as $g): ?><option><?= $g ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 col-lg-2">
          <label class="form-label">Source</label>
          <select name="source" class="form-select">
            <?php foreach($sources as $s): ?><option><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8 col-lg-4">
          <label class="form-label">Message</label>
          <input type="text" name="message" class="form-control" placeholder="Optional note...">
        </div>
        <div class="col-12 pt-1">
          <button type="submit" name="add" class="btn-gold"><i class="bi bi-plus-lg me-1"></i>Add Enquiry</button>
        </div>
      </div>
    </form>
  </div>

  <!-- =========================================================
       SEARCH & FILTER
  ========================================================= -->
  <div class="section-card">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h3 class="section-title-sm"><i class="bi bi-table me-2"></i>All Enquiries</h3>
        <div class="section-divider mb-0"></div>
      </div>
      <span style="font-size:0.78rem; color:var(--muted);">Showing <strong style="color:var(--gold-light);"><?= $rowCount ?></strong> record(s)</span>
    </div>

    <!-- Search bar -->
    <form method="GET" class="search-wrap">
      <div class="flex-grow-1">
        <label class="form-label">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Name, email or phone..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:160px;">
        <label class="form-label">Filter by Plan</label>
        <select name="plan_filter" class="form-select">
          <option value="">All Plans</option>
          <?php foreach($plans as $p): ?>
            <option <?= $filterPlan==$p?'selected':'' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex gap-2 align-items-end">
        <button type="submit" class="btn-gold"><i class="bi bi-search me-1"></i>Search</button>
        <a href="admin.php" class="btn-outline-gold">Reset</a>
      </div>
    </form>

    <!-- TABLE -->
    <div class="rfc-table-wrap">
      <table class="rfc-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Plan</th>
            <th>Goal</th>
            <th>Source</th>
            <th>Message</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr><td colspan="10" style="text-align:center; padding:2rem; color:#555;">No records found.</td></tr>
        <?php else: $i=1; while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td class="row-id"><?= $i++ ?></td>
            <td><span class="name-cell"><?= htmlspecialchars($row['name']) ?></span></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><span class="plan-pill plan-<?= $row['plan'] ?>"><?= $row['plan'] ?></span></td>
            <td><?= htmlspecialchars($row['goal']) ?></td>
            <td><?= htmlspecialchars($row['source']) ?></td>
            <td style="max-width:180px; white-space:normal;"><?= htmlspecialchars($row['message']) ?></td>
            <td style="white-space:nowrap; font-size:0.78rem;"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
            <td style="white-space:nowrap;">
              <a href="admin.php?edit=<?= $row['id'] ?>" class="btn-edit me-1"><i class="bi bi-pencil me-1"></i>Edit</a>
              <a href="admin.php?delete=<?= $row['id'] ?>" class="btn-del" onclick="return confirm('Delete this record?')"><i class="bi bi-trash me-1"></i>Del</a>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /section-card -->

</div><!-- /container -->

<!-- ===================== FOOTER ===================== -->
<footer class="admin-footer">
  <p>&copy; <?= date('Y') ?> <span>Rajkot Fitness Club</span> &bull; Admin Panel &bull; All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Phone — numbers only
document.querySelectorAll('input[name="phone"]').forEach(el => {
    el.addEventListener('input', function(){ this.value = this.value.replace(/[^0-9]/g,'').slice(0,10); });
});
// Auto-dismiss flash alerts
const alerts = document.querySelectorAll('.rfc-alert');
alerts.forEach(a => setTimeout(() => a.style.display='none', 5000));
</script>
</body>
</html>

<?php
/* login.php - ONE file: database connection + auto setup + login + member sign-up + logout */
session_start();

/* ---------- 1. Database connection + auto setup (creates DB, table, demo users) ---------- */
$conn = mysqli_connect("localhost", "root", "");
if (!$conn) { die("Database connection failed. Start MySQL in XAMPP."); }
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS gym_db");
mysqli_select_db($conn, "gym_db");
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(15) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','trainer','member') NOT NULL,
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'plan'")) == 0) {
    mysqli_query($conn, "ALTER TABLE users
        ADD plan ENUM('Basic','Standard','Premium','Annual') NULL,
        ADD membership_registered TINYINT(1) NOT NULL DEFAULT 0");
}
/* ---- Trainer-panel tables (safe to run every time) ---- */
// Members = people who filled the JOIN FORM on the website (table gym_enquiries).
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS gym_enquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100), email VARCHAR(120), phone VARCHAR(15),
  plan VARCHAR(30), goal VARCHAR(60), source VARCHAR(60), message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
// extra columns the trainer can edit: fitness level + health notes
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM gym_enquiries LIKE 'fitness_level'")) == 0) {
    mysqli_query($conn, "ALTER TABLE gym_enquiries
        ADD fitness_level ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
        ADD health_notes VARCHAR(255) NULL");
}
// older version of attendance pointed to login users - remove it so it can be rebuilt for join-form members
if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'")) > 0
    && mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM attendance LIKE 'user_id'")) > 0) {
    mysqli_query($conn, "DROP TABLE attendance");
}
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS diet_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_tier ENUM('Basic','Standard','Premium','Annual') NOT NULL,
  meal_time ENUM('Breakfast','Mid-Morning','Lunch','Snack','Dinner') NOT NULL,
  title VARCHAR(120) NOT NULL,
  details TEXT NOT NULL,
  calories INT NULL,
  created_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS workout_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_tier ENUM('Basic','Standard','Premium','Annual') NOT NULL,
  fitness_level ENUM('Beginner','Intermediate','Advanced') NOT NULL,
  exercise_name VARCHAR(120) NOT NULL,
  sets INT NOT NULL DEFAULT 3,
  reps VARCHAR(60) NOT NULL,
  rest_seconds INT NOT NULL DEFAULT 60,
  warning VARCHAR(255) NULL,
  created_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  attend_date DATE NOT NULL,
  status ENUM('Present','Absent') NOT NULL,
  marked_by INT NULL,
  marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_day (member_id, attend_date),
  KEY idx_day (attend_date)
)");
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM attendance LIKE 'check_in'")) == 0) {
    mysqli_query($conn, "ALTER TABLE attendance ADD check_in TIME NULL, ADD check_out TIME NULL");
}
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name ENUM('Basic','Standard','Premium','Annual') NOT NULL UNIQUE,
  duration_months INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS memberships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  enquiry_id INT NULL,
  package_id INT NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_membership_user (user_id),
  KEY idx_membership_dates (ends_on)
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  membership_id INT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('Dummy Card','Dummy UPI','Cash') NOT NULL,
  transaction_id VARCHAR(60) NOT NULL UNIQUE,
  receipt_no VARCHAR(40) NOT NULL UNIQUE,
  status ENUM('success','failed','refunded') NOT NULL DEFAULT 'success',
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payment_user (user_id),
  KEY idx_payment_date (paid_at)
)");
if (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM packages"))[0] == 0) {
    mysqli_query($conn, "INSERT INTO packages (name, duration_months, price, description) VALUES
      ('Basic', 1, 999, 'Gym floor and cardio access'),
      ('Standard', 1, 1799, 'Full gym access and group classes'),
      ('Premium', 1, 2999, '24/7 access and weekly diet plans'),
      ('Annual', 12, 14999, 'Premium benefits for one year')");
}
// a few sample rows so the pages are not empty - trainer can edit or delete them
if (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM diet_plans"))[0] == 0) {
    mysqli_query($conn, "INSERT INTO diet_plans (plan_tier, meal_time, title, details, calories) VALUES
      ('Basic','Breakfast','Simple Start','2 boiled eggs, 1 banana, 1 glass milk',350),
      ('Standard','Breakfast','Protein Breakfast','3 egg-white omelette, 2 whole-wheat toast, 1 fruit',420),
      ('Premium','Breakfast','High-Protein Breakfast','Oats with whey protein, nuts, 1 fruit, black coffee',480),
      ('Annual','Breakfast','Performance Breakfast','Paneer/egg bhurji, oats, fruit bowl, nuts',520)");
}
if (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM workout_plans"))[0] == 0) {
    mysqli_query($conn, "INSERT INTO workout_plans (plan_tier, fitness_level, exercise_name, sets, reps, rest_seconds, warning) VALUES
      ('Basic','Beginner','Push-ups (knees allowed)',3,'8 reps',60,'Stop if you feel pain in wrists or shoulders. If you cannot do it, skip it.'),
      ('Basic','Beginner','Bodyweight Squats',3,'12 reps',60,'Keep knees behind toes. Skip if you have knee pain.'),
      ('Basic','Beginner','Plank',3,'20 seconds',45,'Skip if you have back pain.'),
      ('Standard','Intermediate','Push-ups',4,'15 reps',60,'Stop if form breaks down.')");
}

// Demo admin + trainer, created only once when the table is empty. CHANGE these passwords.
$cnt = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users"))[0];
if ($cnt == 0) {
    $demo = [
        ['Admin User',   'admin@rfc.com',   '9000000001', 'Admin@123',   'admin'],
        ['Trainer User', 'trainer@rfc.com', '9000000002', 'Trainer@123', 'trainer'],
        ['Member User',  'member@rfc.com',  '9000000003', 'Member@123',  'member'],
    ];
    $st = mysqli_prepare($conn, "INSERT INTO users (full_name,email,phone,password_hash,role) VALUES (?,?,?,?,?)");
    foreach ($demo as $u) {
        $h = password_hash($u[3], PASSWORD_DEFAULT);
        mysqli_stmt_bind_param($st, "sssss", $u[0], $u[1], $u[2], $h, $u[4]);
        mysqli_stmt_execute($st);
    }
}

/* ---------- 2. Logout ---------- */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

/* Already logged in -> go to main website */
if (!empty($_SESSION['user_id'])) { header("Location: gym.php"); exit; }

$error = ''; $success = '';
$mode = (($_GET['mode'] ?? '') === 'register') ? 'register' : 'login';

/* ---------- 3. Handle forms ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (($_POST['action'] ?? '') === 'login') {
        $login = trim($_POST['login'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? '';
        if ($login === '' || $pass === '' || !in_array($role, ['admin','trainer','member'], true)) {
            $error = 'Please fill all fields and select a role.';
        } else {
            // account must match email/phone AND the selected role
            $q = mysqli_prepare($conn, "SELECT id, full_name, password_hash, role FROM users
                                        WHERE (email = ? OR phone = ?) AND role = ? AND status = 'active' LIMIT 1");
            mysqli_stmt_bind_param($q, "sss", $login, $login, $role);
            mysqli_stmt_execute($q);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
            if ($user && password_verify($pass, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['full_name'];
                $_SESSION['role']    = $user['role'];
                header("Location: gym.php");
                exit;
            }
            $error = 'Invalid login details for the selected role.';
        }
    }

    if (($_POST['action'] ?? '') === 'register') {
        $mode  = 'register';
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['confirm'] ?? '';
        if ($name === '' || $email === '' || $phone === '' || $pass === '') {
            $error = 'Please fill all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
            $error = 'Enter a valid 10-digit phone number.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($pass !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            $c = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
            mysqli_stmt_bind_param($c, "ss", $email, $phone);
            mysqli_stmt_execute($c);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($c))) {
                $error = 'This email or phone is already registered.';
            } else {
                $h = password_hash($pass, PASSWORD_DEFAULT);
                // sign-up ALWAYS creates a MEMBER - admin/trainer can never be self-created
                $i = mysqli_prepare($conn, "INSERT INTO users (full_name,email,phone,password_hash,role) VALUES (?,?,?,?, 'member')");
                mysqli_stmt_bind_param($i, "ssss", $name, $email, $phone, $h);
                if (mysqli_stmt_execute($i)) {
                    $success = 'Account created! Now log in as Member.';
                    $mode = 'login'; $_POST = [];
                } else { $error = 'Could not create account. Try again.'; }
            }
        }
    }
}
$v = fn($k) => htmlspecialchars($_POST[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $mode === 'register' ? 'Sign Up' : 'Login' ?> - Rajkot Fitness Club</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Raleway:wght@400;600&display=swap" rel="stylesheet">
<style>
  body { background:#0a0a0a; color:#f5f5f5; font-family:'Raleway',sans-serif; min-height:100vh;
         display:flex; align-items:center; justify-content:center; padding:20px; }
  .box { background:#1c1c1c; border:1px solid rgba(201,168,76,.25); border-radius:14px;
         padding:36px 32px; width:100%; max-width:420px; }
  h2 { font-family:'Bebas Neue',sans-serif; letter-spacing:2px; color:#e8c97a; text-align:center; font-size:2.2rem; }
  .sub { text-align:center; color:#aaa; margin-bottom:24px; }
  .form-control, .form-select { background:#111; border:1px solid #333; color:#fff; }
  .form-control:focus, .form-select:focus { background:#111; color:#fff; border-color:#c9a84c; box-shadow:none; }
  .btn-gold { background:linear-gradient(135deg,#e8c97a,#a07830); color:#000; font-weight:700; border:0; }
  label { font-size:.85rem; color:#ccc; margin-bottom:4px; }
  a { color:#e8c97a; }
</style>
</head>
<body>
<div class="box">
  <h2>Rajkot Fitness Club</h2>
  <p class="sub"><?= $mode === 'register' ? 'Create your member account' : 'Sign in to continue' ?></p>

  <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if ($mode === 'login'): ?>
  <form method="POST" autocomplete="off">
    <input type="hidden" name="action" value="login">
    <div class="mb-3"><label>Login as</label>
      <select name="role" class="form-select" required>
        <option value="">-- Select role --</option>
        <?php foreach (['admin'=>'Admin','trainer'=>'Trainer','member'=>'Member'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= ($_POST['role'] ?? '')===$k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="mb-3"><label>Email or Phone number</label>
      <input type="text" name="login" class="form-control" required value="<?= $v('login') ?>"></div>
    <div class="mb-4"><label>Password</label>
      <input type="password" name="password" class="form-control" required></div>
    <button class="btn btn-gold w-100 py-2">LOGIN</button>
  </form>
  <p class="text-center mt-3 mb-0" style="font-size:.9rem">New member? <a href="login.php?mode=register">Create an account</a></p>

<?php else: ?>
  <form method="POST" autocomplete="off">
    <input type="hidden" name="action" value="register">
    <div class="mb-3"><label>Full name</label>
      <input type="text" name="full_name" class="form-control" required value="<?= $v('full_name') ?>"></div>
    <div class="mb-3"><label>Email</label>
      <input type="email" name="email" class="form-control" required value="<?= $v('email') ?>"></div>
    <div class="mb-3"><label>Phone (10 digits)</label>
      <input type="text" name="phone" class="form-control" maxlength="10" required value="<?= $v('phone') ?>"></div>
    <div class="mb-3"><label>Password</label>
      <input type="password" name="password" class="form-control" required></div>
    <div class="mb-4"><label>Confirm password</label>
      <input type="password" name="confirm" class="form-control" required></div>
    <button class="btn btn-gold w-100 py-2">CREATE ACCOUNT</button>
  </form>
  <p class="text-center mt-3 mb-0" style="font-size:.9rem"><a href="login.php">Back to login</a></p>
<?php endif; ?>
</div>
</body>
</html>

<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: gym.php'); exit; }
$conn = mysqli_connect("localhost", "root", "", "gym_db");
if (!$conn) die("Connection failed.");
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "UPDATE memberships SET status = 'expired' WHERE status = 'active' AND ends_on < CURDATE()");
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];
$plans = ['Basic', 'Standard', 'Premium', 'Annual'];
$tab = in_array($_GET['tab'] ?? '', ['dashboard', 'members', 'packages', 'payments'], true) ? $_GET['tab'] : 'dashboard';
$msg = '';
$error = '';
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function rows($conn, $sql, $types = '', $params = []) {
    $st = mysqli_prepare($conn, $sql);
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    return mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
}
function one($conn, $sql, $types = '', $params = []) { $r = rows($conn, $sql, $types, $params); return $r[0] ?? null; }
function assign_membership($conn, $userId, $packageId, $enquiryId = null) {
    $package = one($conn, "SELECT id, name, duration_months FROM packages WHERE id = ? AND status = 'active'", "i", [$packageId]);
    if (!$package) return false;
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+' . (int)$package['duration_months'] . ' months -1 day'));
    $old = mysqli_prepare($conn, "UPDATE memberships SET status = 'expired' WHERE user_id = ? AND status = 'active'");
    mysqli_stmt_bind_param($old, "i", $userId); mysqli_stmt_execute($old);
    $membership = mysqli_prepare($conn, "INSERT INTO memberships (user_id, enquiry_id, package_id, starts_on, ends_on, status) VALUES (?, ?, ?, ?, ?, 'active')");
    mysqli_stmt_bind_param($membership, "iiiss", $userId, $enquiryId, $package['id'], $start, $end);
    if (!mysqli_stmt_execute($membership)) return false;
    $update = mysqli_prepare($conn, "UPDATE users SET plan = ?, membership_registered = 1 WHERE id = ?");
    mysqli_stmt_bind_param($update, "si", $package['name'], $userId); mysqli_stmt_execute($update);
    return true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('Invalid request. Reload the page.');
    $action = $_POST['action'] ?? '';
    if ($action === 'save_package') {
        $id = (int)$_POST['id']; $duration = max(1, (int)$_POST['duration_months']); $price = max(0, (float)$_POST['price']);
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $st = mysqli_prepare($conn, "UPDATE packages SET duration_months = ?, price = ?, status = ? WHERE id = ?");
        mysqli_stmt_bind_param($st, "idsi", $duration, $price, $status, $id); mysqli_stmt_execute($st); $msg = 'Package updated.';
    } elseif ($action === 'delete_member') {
        $id = (int)$_POST['id'];
        $emailRow = one($conn, "SELECT email FROM users WHERE id = ? AND role = 'member'", "i", [$id]);
        if ($emailRow) {
            $email = $emailRow['email'];
            $deleteAttendance = mysqli_prepare($conn, "DELETE a FROM attendance a JOIN gym_enquiries e ON e.id = a.member_id WHERE e.email = ?");
            mysqli_stmt_bind_param($deleteAttendance, "s", $email); mysqli_stmt_execute($deleteAttendance);
            foreach (['payments' => 'user_id', 'memberships' => 'user_id'] as $table => $column) {
                $delete = mysqli_prepare($conn, "DELETE FROM $table WHERE $column = ?");
                mysqli_stmt_bind_param($delete, "i", $id); mysqli_stmt_execute($delete);
            }
            $deleteEnquiries = mysqli_prepare($conn, "DELETE FROM gym_enquiries WHERE email = ?");
            mysqli_stmt_bind_param($deleteEnquiries, "s", $email); mysqli_stmt_execute($deleteEnquiries);
            $deleteUser = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND role = 'member'");
            mysqli_stmt_bind_param($deleteUser, "i", $id); mysqli_stmt_execute($deleteUser);
            $msg = 'Member account deleted.';
        }
    } elseif ($action === 'save_member') {
        $id = (int)$_POST['id']; $name = trim($_POST['full_name'] ?? ''); $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        $status = ($_POST['status'] ?? '') === 'blocked' ? 'blocked' : 'active'; $packageId = (int)($_POST['package_id'] ?? 0);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{10}$/', $phone)) $error = 'Enter a valid name, email, and 10-digit phone.';
        else {
            $st = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ? AND role = 'member'");
            mysqli_stmt_bind_param($st, "ssssi", $name, $email, $phone, $status, $id);
            if (!mysqli_stmt_execute($st)) $error = 'Could not update the member. Email or phone may already exist.';
            elseif ($packageId > 0 && !assign_membership($conn, $id, $packageId)) $error = 'Member saved, but membership package could not be assigned.';
            else $msg = 'Member profile saved.';
        }
    } elseif ($action === 'add_member') {
        $name = trim($_POST['full_name'] ?? ''); $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{10}$/', $phone) || strlen($password) < 6) $error = 'Enter valid member details and a password of at least 6 characters.';
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = mysqli_prepare($conn, "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'member')");
            mysqli_stmt_bind_param($st, "ssss", $name, $email, $phone, $hash);
            if (mysqli_stmt_execute($st)) $msg = 'Member account created.'; else $error = 'Could not create the member. Email or phone may already exist.';
        }
    }
}
$packages = rows($conn, "SELECT * FROM packages ORDER BY FIELD(name,'Basic','Standard','Premium','Annual')");
$members = rows($conn, "SELECT u.id, u.full_name, u.email, u.phone, u.status, u.plan, m.id membership_id, m.starts_on, m.ends_on, m.status membership_status, p.name package_name FROM users u LEFT JOIN memberships m ON m.id = (SELECT MAX(m2.id) FROM memberships m2 WHERE m2.user_id = u.id) LEFT JOIN packages p ON p.id = m.package_id WHERE u.role = 'member' ORDER BY u.id DESC");
$payments = rows($conn, "SELECT p.*, u.full_name, pkg.name package_name FROM payments p JOIN users u ON u.id = p.user_id LEFT JOIN memberships m ON m.id = p.membership_id LEFT JOIN packages pkg ON pkg.id = m.package_id ORDER BY p.id DESC LIMIT 100");
$stats = [
    'members' => (int)(one($conn, "SELECT COUNT(*) c FROM users WHERE role='member'")['c'] ?? 0),
    'active' => (int)(one($conn, "SELECT COUNT(*) c FROM memberships WHERE status='active'")['c'] ?? 0),
    'expired' => (int)(one($conn, "SELECT COUNT(*) c FROM memberships WHERE status='expired'")['c'] ?? 0),
    'revenue' => (float)(one($conn, "SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status='success'")['total'] ?? 0),
    'today_attendance' => (int)(one($conn, "SELECT COUNT(*) c FROM attendance WHERE attend_date=CURDATE() AND status='Present'")['c'] ?? 0),
];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Dashboard - RFC</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700&family=Raleway:wght@400;600&display=swap" rel="stylesheet"><style>:root{--gold:#c9a84c;--light:#e8c97a;--card:#1c1c1c;--muted:#aaa;--border:rgba(201,168,76,.25)}body{background:#0a0a0a;color:#f5f5f5;font-family:'Raleway',sans-serif}h1,h2,h3{font-family:'Bebas Neue',sans-serif;letter-spacing:2px;color:var(--light)}.panel{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem}.stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.2rem}.stat b{font:2.3rem 'Bebas Neue';color:var(--light)}.stat small{display:block;color:var(--muted);text-transform:uppercase;letter-spacing:1px}.form-control,.form-select{background:#111;border-color:var(--border);color:#fff}.form-control:focus,.form-select:focus{background:#111;color:#fff;border-color:var(--gold);box-shadow:none}.btn-gold{background:linear-gradient(135deg,var(--gold),#a07830);border:0;color:#000;font-weight:700}.nav-tabs .nav-link{color:var(--muted)}.nav-tabs .nav-link.active{background:var(--gold);color:#000}.table{--bs-table-bg:transparent;--bs-table-color:#ccc}.table th{color:var(--light);font-size:.72rem;text-transform:uppercase;letter-spacing:1px;white-space:nowrap}.table td{vertical-align:middle}.muted{color:var(--muted)}</style></head>
<body><nav class="border-bottom" style="border-color:var(--border)!important;background:#111"><div class="container py-3 d-flex justify-content-between align-items-center gap-2 flex-wrap"><h3 class="m-0">RFC Admin Dashboard</h3><div class="d-flex gap-2"><a href="trainer.php" class="btn btn-outline-warning btn-sm">Trainer Tools</a><a href="gym.php" class="btn btn-outline-light btn-sm">Main Site</a><a href="login.php?logout=1" class="btn btn-gold btn-sm">Logout</a></div></div></nav>
<main class="container py-4"><div class="d-flex justify-content-between align-items-center flex-wrap mb-3"><div><h1 class="mb-1">Operations Dashboard</h1><p class="muted mb-0">Members, memberships, payments, and attendance overview</p></div></div><?php if($msg): ?><div class="alert alert-success"><?=h($msg)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
<ul class="nav nav-tabs mb-4"><li class="nav-item"><a class="nav-link <?=$tab==='dashboard'?'active':''?>" href="?tab=dashboard">Dashboard</a></li><li class="nav-item"><a class="nav-link <?=$tab==='members'?'active':''?>" href="?tab=members">Members</a></li><li class="nav-item"><a class="nav-link <?=$tab==='packages'?'active':''?>" href="?tab=packages">Packages</a></li><li class="nav-item"><a class="nav-link <?=$tab==='payments'?'active':''?>" href="?tab=payments">Payment Reports</a></li></ul>
<?php if($tab==='dashboard'): ?><div class="row g-3 mb-4"><?php foreach([['members','Total Members','people'],['active','Active Memberships','check-circle'],['expired','Expired Memberships','calendar-x'],['revenue','Collected Revenue','cash-coin'],['today_attendance','Today Present','calendar-check']] as $s): ?><div class="col-6 col-lg"><div class="stat"><b><?= $s[0]==='revenue'?'&#8377;'.number_format($stats[$s[0]],2):$stats[$s[0]] ?></b><small><i class="bi bi-<?=$s[2]?>"></i> <?=$s[1]?></small></div></div><?php endforeach; ?></div><div class="row g-4"><div class="col-lg-6"><div class="panel"><h2>Membership Status</h2><table class="table"><tr><td>Active</td><td class="text-end"><?=$stats['active']?></td></tr><tr><td>Expired</td><td class="text-end"><?=$stats['expired']?></td></tr><tr><td>Members without active plan</td><td class="text-end"><?=max(0,$stats['members']-$stats['active'])?></td></tr></table></div></div><div class="col-lg-6"><div class="panel"><h2>Recent Payments</h2><?php foreach(array_slice($payments,0,5) as $p): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?=h($p['full_name'])?><small class="d-block muted"><?=h($p['package_name']?:'Payment')?></small></span><strong>&#8377;<?=number_format((float)$p['amount'],2)?></strong></div><?php endforeach; if(!$payments): ?><p class="muted">No payments recorded.</p><?php endif; ?></div></div></div>
<?php elseif($tab==='members'): ?><div class="panel"><h2>Add Member Account</h2><form method="POST" class="row g-3"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="add_member"><div class="col-md-3"><input name="full_name" class="form-control" placeholder="Full name" required></div><div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="Email" required></div><div class="col-md-2"><input name="phone" class="form-control" placeholder="10-digit phone" maxlength="10" required></div><div class="col-md-2"><input name="password" type="password" class="form-control" placeholder="Password" required></div><div class="col-md-2"><button class="btn btn-gold w-100">Add Member</button></div></form></div><div class="panel"><h2>Member Accounts</h2><div class="table-responsive"><table class="table"><thead><tr><th>Member</th><th>Contact</th><th>Account</th><th>Membership</th><th>Dates</th><th>Save</th></tr></thead><tbody><?php foreach($members as $m): ?><tr><form method="POST"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="save_member"><input type="hidden" name="id" value="<?=$m['id']?>"><td><input name="full_name" class="form-control form-control-sm" value="<?=h($m['full_name'])?>"></td><td><input name="email" class="form-control form-control-sm mb-1" value="<?=h($m['email'])?>"><input name="phone" class="form-control form-control-sm" value="<?=h($m['phone'])?>"></td><td><select name="status" class="form-select form-select-sm"><option value="active" <?=$m['status']==='active'?'selected':''?>>Active</option><option value="blocked" <?=$m['status']==='blocked'?'selected':''?>>Blocked</option></select></td><td><select name="package_id" class="form-select form-select-sm"><option value="0">Keep current (<?=h($m['package_name']?:'none')?>)</option><?php foreach($packages as $p): ?><option value="<?=$p['id']?>"><?=h($p['name'])?> - &#8377;<?=number_format($p['price'],2)?></option><?php endforeach; ?></select></td><td class="muted"><?= $m['starts_on'] ? h(date('d M Y',strtotime($m['starts_on']))) . ' - ' . h(date('d M Y',strtotime($m['ends_on']))) : 'No membership' ?></td><td><button class="btn btn-gold btn-sm">Save</button> <button name="action" value="delete_member" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this member account and all linked records?')">Delete</button></td></form></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif($tab==='packages'): ?><div class="panel"><h2>Membership Packages</h2><div class="table-responsive"><table class="table"><thead><tr><th>Package</th><th>Duration (months)</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($packages as $p): ?><tr><form method="POST"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="save_package"><input type="hidden" name="id" value="<?=$p['id']?>"><td><strong><?=h($p['name'])?></strong><small class="d-block muted"><?=h($p['description'])?></small></td><td><input type="number" min="1" name="duration_months" class="form-control form-control-sm" value="<?=$p['duration_months']?>"></td><td><input type="number" min="0" step="0.01" name="price" class="form-control form-control-sm" value="<?=$p['price']?>"></td><td><select name="status" class="form-select form-select-sm"><option value="active" <?=$p['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$p['status']==='inactive'?'selected':''?>>Inactive</option></select></td><td><button class="btn btn-gold btn-sm">Save</button></td></form></tr><?php endforeach; ?></tbody></table></div></div>
<?php else: ?><div class="panel"><h2>Payment Report</h2><div class="table-responsive"><table class="table"><thead><tr><th>Receipt</th><th>Member</th><th>Package</th><th>Amount</th><th>Method</th><th>Transaction</th><th>Paid</th><th></th></tr></thead><tbody><?php foreach($payments as $p): ?><tr><td><?=h($p['receipt_no'])?></td><td><?=h($p['full_name'])?></td><td><?=h($p['package_name']?:'Payment')?></td><td>&#8377;<?=number_format((float)$p['amount'],2)?></td><td><?=h($p['method'])?></td><td><?=h($p['transaction_id'])?></td><td><?=h(date('d M Y',strtotime($p['paid_at'])))?></td><td><a class="btn btn-outline-warning btn-sm" href="receipt.php?id=<?=$p['id']?>">Receipt</a></td></tr><?php endforeach; if(!$payments): ?><tr><td colspan="8" class="muted">No payments recorded.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?></main></body></html>

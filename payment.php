<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['role'] ?? '') !== 'member') { header('Location: trainer.php'); exit; }

$conn = mysqli_connect("localhost", "root", "", "gym_db");
if (!$conn) die("Connection failed.");
mysqli_set_charset($conn, "utf8mb4");
$userId = (int)$_SESSION['user_id'];
$error = '';

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$membershipQuery = mysqli_prepare($conn,
    "SELECT m.id, m.starts_on, m.ends_on, p.name, p.price, p.duration_months
     FROM memberships m JOIN packages p ON p.id = m.package_id
     WHERE m.user_id = ? AND m.status = 'active' AND m.ends_on >= CURDATE()
     ORDER BY m.id DESC LIMIT 1");
mysqli_stmt_bind_param($membershipQuery, "i", $userId);
mysqli_stmt_execute($membershipQuery);
$membership = mysqli_fetch_assoc(mysqli_stmt_get_result($membershipQuery));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? '';
    $allowedMethods = ['Dummy Card', 'Dummy UPI'];
    if (!$membership) {
        $error = 'You need an active membership before making a payment.';
    } elseif (!in_array($method, $allowedMethods, true)) {
        $error = 'Please select a valid dummy payment method.';
    } elseif ($method === 'Dummy Card' && !preg_match('/^[0-9]{16}$/', preg_replace('/\s+/', '', $_POST['card_number'] ?? ''))) {
        $error = 'Enter a 16-digit dummy card number.';
    } elseif ($method === 'Dummy UPI' && !preg_match('/^[A-Za-z0-9._-]{2,50}@[A-Za-z]{2,20}$/', trim($_POST['upi_id'] ?? ''))) {
        $error = 'Enter a valid dummy UPI ID.';
    } else {
        $transactionId = 'DUMMY-' . strtoupper(bin2hex(random_bytes(5)));
        $receiptNo = 'RFC-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $payment = mysqli_prepare($conn,
            "INSERT INTO payments (user_id, membership_id, amount, method, transaction_id, receipt_no, status)
             VALUES (?, ?, ?, ?, ?, ?, 'success')");
        $amount = (float)$membership['price'];
        mysqli_stmt_bind_param($payment, "iidsss", $userId, $membership['id'], $amount, $method, $transactionId, $receiptNo);
        if (mysqli_stmt_execute($payment)) {
            header('Location: receipt.php?id=' . mysqli_insert_id($conn));
            exit;
        }
        $error = 'The dummy payment could not be recorded. Please try again.';
    }
}

$historyQuery = mysqli_prepare($conn,
    "SELECT id, amount, method, transaction_id, receipt_no, paid_at FROM payments WHERE user_id = ? ORDER BY id DESC");
mysqli_stmt_bind_param($historyQuery, "i", $userId);
mysqli_stmt_execute($historyQuery);
$history = mysqli_fetch_all(mysqli_stmt_get_result($historyQuery), MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dummy Payment - Rajkot Fitness Club</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700&family=Raleway:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --gold:#c9a84c; --light:#e8c97a; --card:#1c1c1c; --muted:#aaa; --border:rgba(201,168,76,.25); }
body { background:#0a0a0a; color:#f5f5f5; font-family:'Raleway',sans-serif; }
h1,h2,h3 { font-family:'Bebas Neue',sans-serif; letter-spacing:2px; color:var(--light); }
.panel { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.6rem; margin-bottom:1.5rem; }
.form-control,.form-select { background:#111; border:1px solid var(--border); color:#fff; }
.form-control:focus,.form-select:focus { background:#111; color:#fff; border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,168,76,.1); }
.btn-gold { background:linear-gradient(135deg,var(--gold),#a07830); color:#000; border:0; font-weight:700; letter-spacing:1px; }
.badge-demo { color:#ffcf8a; border:1px solid #b87b32; background:rgba(184,123,50,.12); }
th { color:var(--light); font-size:.75rem; text-transform:uppercase; letter-spacing:1px; } td { color:#ccc; }
</style>
</head>
<body>
<nav class="border-bottom" style="border-color:var(--border)!important;background:#111;">
  <div class="container py-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <h3 class="m-0">Rajkot Fitness Club</h3>
        <div class="d-flex gap-2"><a href="member_plan.php" class="btn btn-outline-warning btn-sm">My Plan</a><a href="login.php?logout=1" class="btn btn-gold btn-sm">Logout</a></div>
  </div>
</nav>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h1 class="mb-1">Fee Payment</h1><span class="badge badge-demo">DEMO PAYMENT MODE - NO REAL CHARGE</span></div></div>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
  <?php if (!$membership): ?><div class="panel"><h3>No Active Membership</h3><p class="text-secondary mb-0">Submit the membership form on the main site before making a payment.</p></div><?php else: ?>
  <div class="row g-4">
    <div class="col-lg-5"><div class="panel h-100"><h3>Payment Summary</h3><dl class="row mb-0"><dt class="col-6 text-secondary">Package</dt><dd class="col-6 text-end"><?= h($membership['name']) ?></dd><dt class="col-6 text-secondary">Amount</dt><dd class="col-6 text-end fs-4" style="color:var(--light)">&#8377;<?= number_format((float)$membership['price'], 2) ?></dd><dt class="col-6 text-secondary">Valid until</dt><dd class="col-6 text-end"><?= h(date('d M Y', strtotime($membership['ends_on']))) ?></dd></dl></div></div>
    <div class="col-lg-7"><div class="panel"><h3>Dummy Checkout</h3><p class="text-secondary">Use any valid-looking test details. No bank or payment gateway is contacted.</p><form method="POST"><label class="form-label">Payment method</label><select name="method" id="method" class="form-select mb-3" required><option value="Dummy Card">Dummy Card</option><option value="Dummy UPI">Dummy UPI</option></select><div id="cardFields"><label class="form-label">Card number</label><input name="card_number" class="form-control mb-3" inputmode="numeric" maxlength="19" placeholder="4111 1111 1111 1111"><div class="row g-3"><div class="col-6"><label class="form-label">Expiry</label><input class="form-control" placeholder="12/30"></div><div class="col-6"><label class="form-label">CVV</label><input class="form-control" placeholder="123" maxlength="3"></div></div></div><div id="upiFields" style="display:none"><label class="form-label">Dummy UPI ID</label><input name="upi_id" class="form-control" placeholder="demo@upi"></div><button class="btn btn-gold w-100 mt-4 py-2">PROCESS DUMMY PAYMENT</button></form></div></div>
  </div>
  <?php endif; ?>
  <div class="panel"><h3>Payment History</h3><div class="table-responsive"><table class="table table-dark align-middle"><thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead><tbody><?php if (!$history): ?><tr><td colspan="5" class="text-secondary">No payments recorded yet.</td></tr><?php endif; ?><?php foreach ($history as $payment): ?><tr><td><?= h($payment['receipt_no']) ?></td><td>&#8377;<?= number_format((float)$payment['amount'], 2) ?></td><td><?= h($payment['method']) ?></td><td><?= h(date('d M Y, h:i A', strtotime($payment['paid_at']))) ?></td><td><a class="btn btn-outline-warning btn-sm" href="receipt.php?id=<?= (int)$payment['id'] ?>">View receipt</a></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<script>const method=document.getElementById('method');const card=document.getElementById('cardFields');const upi=document.getElementById('upiFields');function toggle(){const isUpi=method.value==='Dummy UPI';card.style.display=isUpi?'none':'block';upi.style.display=isUpi?'block':'none';}method.addEventListener('change',toggle);toggle();</script>
</body>
</html>


<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect("localhost", "root", "", "gym_db");
    mysqli_set_charset($conn, "utf8mb4");

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);
        exit("Invalid payment ID.");
    }

    $userId = (int) $_SESSION['user_id'];

    $sql = "SELECT
                p.*,
                u.full_name,
                u.email,
                m.starts_on,
                m.ends_on,
                pkg.name AS package_name
            FROM payments p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN memberships m ON m.id = p.membership_id
            LEFT JOIN packages pkg ON pkg.id = m.package_id
            WHERE p.id = ? AND p.user_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);

    if (!$payment) {
        http_response_code(404);
        exit("Receipt not found.");
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to load receipt. Check your database configuration.");
}

function h($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function pdfText($value) {
    $value = (string) $value;

    // Convert text to a format supported by standard PDF fonts.
    if (function_exists('iconv')) {
        $converted = iconv(
            'UTF-8',
            'Windows-1252//TRANSLIT//IGNORE',
            $value
        );

        if ($converted !== false) {
            $value = $converted;
        }
    }

    // Remove control characters and escape PDF string delimiters.
    $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);

    return str_replace(
        ['\\', '(', ')'],
        ['\\\\', '\\(', '\\)'],
        $value
    );
}

function downloadReceipt($payment) {
    $paidTimestamp = !empty($payment['paid_at'])
        ? strtotime($payment['paid_at'])
        : false;

    $endTimestamp = !empty($payment['ends_on'])
        ? strtotime($payment['ends_on'])
        : false;

    $lines = [
        ['RAJKOT FITNESS CLUB', 20],
        ['DUMMY PAYMENT RECEIPT', 14],
        ['Receipt: ' . ($payment['receipt_no'] ?? 'N/A'), 11],
        ['Transaction: ' . ($payment['transaction_id'] ?? 'N/A'), 11],
        ['Member: ' . ($payment['full_name'] ?? 'N/A'), 11],
        ['Email: ' . ($payment['email'] ?? 'N/A'), 11],
        ['Package: ' . ($payment['package_name'] ?: 'Membership payment'), 11],
        ['Amount: INR ' . number_format((float) $payment['amount'], 2), 11],
        ['Method: ' . ($payment['method'] ?? 'N/A'), 11],
        ['Status: ' . strtoupper($payment['status'] ?? 'UNKNOWN'), 11],
        ['Paid: ' . ($paidTimestamp ? date('d M Y, h:i A', $paidTimestamp) : 'N/A'), 11],
        ['Valid until: ' . ($endTimestamp ? date('d M Y', $endTimestamp) : 'N/A'), 11],
        ['Dummy receipt only. No real payment was processed.', 9]
    ];

    $stream = "BT\n";

    $y = 790;

    foreach ($lines as $index => $item) {
        [$text, $size] = $item;

        if ($index === 1) {
            $y -= 35;
        } elseif ($index > 1) {
            $y -= 27;
        }

        $stream .= "/F1 {$size} Tf\n";
        $stream .= "1 0 0 1 50 {$y} Tm\n";
        $stream .= "(" . pdfText($text) . ") Tj\n";
    }

    $stream .= "ET\n";

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream"
    ];

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];

    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n";
        $pdf .= $object . "\nendobj\n";
    }

    $xrefPosition = strlen($pdf);

    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n";
    $pdf .= "<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF\n";

    // Prevent PHP warnings or HTML from corrupting the PDF response.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $receiptNo = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        (string) ($payment['receipt_no'] ?? 'receipt')
    );

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $receiptNo . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    echo $pdf;
    exit;
}

if (($_GET['format'] ?? '') === 'pdf') {
    downloadReceipt($payment);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Receipt</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #0a0a0a;
            color: #f5f5f5;
        }
        .receipt {
            max-width: 700px;
            margin: 40px auto;
            background: #1c1c1c;
            border: 1px solid #c9a84c;
            padding: 32px;
            border-radius: 12px;
        }
        .gold { color: #e8c97a; }
    </style>
</head>
<body>
<main class="receipt">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="gold">Rajkot Fitness Club</h1>
            <p>Dummy payment receipt</p>
        </div>
        <span class="badge text-bg-success">
            <?= h(strtoupper($payment['status'] ?? 'UNKNOWN')) ?>
        </span>
    </div>

    <hr>

    <dl class="row">
        <dt class="col-sm-5">Receipt number</dt>
        <dd class="col-sm-7"><?= h($payment['receipt_no'] ?? '') ?></dd>

        <dt class="col-sm-5">Transaction ID</dt>
        <dd class="col-sm-7"><?= h($payment['transaction_id'] ?? '') ?></dd>

        <dt class="col-sm-5">Member</dt>
        <dd class="col-sm-7"><?= h($payment['full_name'] ?? '') ?></dd>

        <dt class="col-sm-5">Package</dt>
        <dd class="col-sm-7"><?= h($payment['package_name'] ?: 'Membership payment') ?></dd>

        <dt class="col-sm-5">Amount</dt>
        <dd class="col-sm-7">
            &#8377;<?= number_format((float) $payment['amount'], 2) ?>
        </dd>

        <dt class="col-sm-5">Method</dt>
        <dd class="col-sm-7"><?= h($payment['method'] ?? '') ?></dd>

        <dt class="col-sm-5">Paid on</dt>
        <dd class="col-sm-7">
            <?php
            $paid = !empty($payment['paid_at'])
                ? strtotime($payment['paid_at']) : false;
            echo $paid ? h(date('d M Y, h:i A', $paid)) : 'N/A';
            ?>
        </dd>

        <dt class="col-sm-5">Valid until</dt>
        <dd class="col-sm-7">
            <?php
            $ends = !empty($payment['ends_on'])
                ? strtotime($payment['ends_on']) : false;
            echo $ends ? h(date('d M Y', $ends)) : 'N/A';
            ?>
        </dd>
    </dl>

    <div class="alert alert-warning">
        This is a dummy receipt. No real payment was processed.
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-warning"
           href="receipt.php?id=<?= (int) $id ?>&amp;format=pdf">
            Download PDF
        </a>

        <a class="btn btn-outline-light" href="payment.php">
            Back to payments
        </a>
    </div>
</main>
</body>
</html>
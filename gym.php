<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "gym_db");
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
?>
<?php
// ============================================================
// RAJKOT FITNESS CLUB - Gym Management Website
// File: gym_management.php
// Usage: Place in htdocs folder of XAMPP and open:
//        http://localhost/gym_management.php
// ============================================================

$form_success = '';
$form_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_form'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $plan    = trim($_POST['plan'] ?? '');
    $goal    = trim($_POST['goal'] ?? '');
    $source  = trim($_POST['source'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($phone) || empty($plan)) {
        $form_error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $form_error = 'Please enter a valid 10-digit phone number.';
    } elseif (!in_array($plan, ['Basic', 'Standard', 'Premium', 'Annual'], true)) {
        $form_error = 'Please select a valid membership plan.';
    } elseif (($_SESSION['role'] ?? '') !== 'member') {
        $form_error = 'Only member accounts can submit a membership registration.';
    } else {
        $package = mysqli_prepare($conn, "SELECT id, duration_months FROM packages WHERE name = ? AND status = 'active' LIMIT 1");
        mysqli_stmt_bind_param($package, "s", $plan);
        mysqli_stmt_execute($package);
        $packageRow = mysqli_fetch_assoc(mysqli_stmt_get_result($package));
        $userId = (int)$_SESSION['user_id'];

        if (!$packageRow) {
            $form_error = 'The selected membership package is unavailable.';
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO gym_enquiries (name, email, phone, plan, goal, source, message)
                 VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssssss", $name, $email, $phone, $plan, $goal, $source, $message);
            if (mysqli_stmt_execute($stmt)) {
                $enquiryId = mysqli_insert_id($conn);
                $startDate = date('Y-m-d');
                $endDate = date('Y-m-d', strtotime('+' . (int)$packageRow['duration_months'] . ' months -1 day'));
                $member = mysqli_prepare($conn,
                    "UPDATE users SET plan = ?, membership_registered = 1 WHERE id = ? AND role = 'member'");
                mysqli_stmt_bind_param($member, "si", $plan, $userId);
                mysqli_stmt_execute($member);

                $old = mysqli_prepare($conn, "UPDATE memberships SET status = 'expired' WHERE user_id = ? AND status = 'active'");
                mysqli_stmt_bind_param($old, "i", $userId);
                mysqli_stmt_execute($old);
                $membership = mysqli_prepare($conn,
                    "INSERT INTO memberships (user_id, enquiry_id, package_id, starts_on, ends_on, status)
                     VALUES (?, ?, ?, ?, ?, 'active')");
                mysqli_stmt_bind_param($membership, "iiiss", $userId, $enquiryId, $packageRow['id'], $startDate, $endDate);
                if (mysqli_stmt_execute($membership)) {
                    $form_success = "Welcome, $name! Your membership is active until " . date('d M Y', strtotime($endDate)) . ".";
                } else {
                    $form_error = 'The membership could not be activated. Please try again.';
                }
            } else {
                $form_error = 'We could not save your registration. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFC</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700;900&family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <style>
        /* =========================================================
           ROOT VARIABLES & RESET
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            background-color: var(--black);
            color: var(--white);
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
        }

        p, li, label, input, textarea, select {
            font-family: 'Raleway', sans-serif;
        }

        a { text-decoration: none; color: inherit; }

        /* Gold gradient text utility */
        .gold-text {
            background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Section padding */
        .section-pad { padding: 90px 0; }

        /* Section title */
        .section-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            margin-bottom: 0.3rem;
        }

        .section-divider {
            width: 70px;
            height: 4px;
            background: linear-gradient(90deg, var(--gold), var(--gold-dark));
            border-radius: 2px;
            margin: 0 auto 2.5rem;
        }

        /* =========================================================
           CUSTOM SCROLLBAR
        ========================================================= */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--dark); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--gold), var(--gold-dark));
            border-radius: 4px;
        }

        /* =========================================================
           PRELOADER
        ========================================================= */
        #preloader {
            position: fixed; inset: 0;
            background: var(--black);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999;
            transition: opacity 0.6s ease;
        }

        #preloader .logo-text {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            letter-spacing: 6px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: pulse 1s infinite alternate;
        }

        @keyframes pulse { from { opacity: 0.4; } to { opacity: 1; } }

        /* =========================================================
           NAVBAR
        ========================================================= */
        #mainNav {
            background: rgba(10,10,10,0.97);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 14px 0;
            transition: var(--transition);
            position: sticky;
            top: 0;
        }
            z-index: 1030;

        #mainNav.scrolled {
            padding: 8px 0;
            box-shadow: 0 4px 30px rgba(201,168,76,0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .gym-logo-svg {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
        }

        .brand-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.7rem;
            letter-spacing: 3px;
            line-height: 1;
        }

        .brand-name span { display: block; }
        .brand-name .top { color: var(--gold-light); font-size: 1.1rem; letter-spacing: 5px; }
        .brand-name .bottom { color: var(--white); font-size: 1.5rem; }

        .nav-link {
            color: var(--white) !important;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 8px 14px !important;
            position: relative;
            transition: var(--transition);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0; left: 50%;
            width: 0; height: 2px;
            background: var(--gold);
            transform: translateX(-50%);
            transition: width 0.3s ease;
        }

        .nav-link:hover { color: var(--gold-light) !important; }
        .nav-link:hover::after { width: 70%; }

        .nav-cta {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black) !important;
            border-radius: 4px;
            padding: 8px 22px !important;
            font-weight: 700;
        }

        .nav-cta:hover {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: var(--black) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(201,168,76,0.4);
        }

        .nav-cta::after { display: none; }

        .navbar-toggler {
            border-color: var(--gold);
            color: var(--gold);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(201,168,76,1)' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }


        /* =========================================================
           HERO SECTION
        ========================================================= */
        #hero {
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1000 50%, #0a0a0a 100%);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        #hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(201,168,76,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(201,168,76,0.05) 0%, transparent 50%);
        }

        .hero-bg-text {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(8rem, 20vw, 22rem);
            color: rgba(201,168,76,0.04);
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
            letter-spacing: 10px;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(201,168,76,0.15);
            border: 1px solid var(--gold);
            color: var(--gold-light);
            font-size: 0.75rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            padding: 6px 18px;
            border-radius: 2px;
            margin-bottom: 1.2rem;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .hero-title {
            font-size: clamp(3rem, 8vw, 7rem);
            line-height: 1;
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-family: 'Raleway', sans-serif;
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: var(--muted);
            max-width: 540px;
            margin-bottom: 2.5rem;
            line-height: 1.7;
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black);
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 14px 36px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-block;
        }

        .btn-gold:hover {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(201,168,76,0.4);
            color: var(--black);
        }

        .btn-outline-gold {
            background: transparent;
            color: var(--gold-light);
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 13px 34px;
            border: 2px solid var(--gold);
            border-radius: 3px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-inline-block;
        }

        .btn-outline-gold:hover {
            background: rgba(201,168,76,0.1);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(201,168,76,0.2);
            color: var(--gold-light);
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 3.5rem;
        }

        .hero-stat .num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.8rem;
            color: var(--gold-light);
            line-height: 1;
        }

        .hero-stat .label {
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            font-family: 'Montserrat', sans-serif;
        }

        .hero-image-wrap {
            position: relative;
            text-align: center;
        }

        .hero-dumbbell-icon {
            font-size: 18rem;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.7;
            animation: float 4s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(-5deg); }
            50%       { transform: translateY(-20px) rotate(5deg); }
        }

        /* =========================================================
           ABOUT SECTION
        ========================================================= */
        #about {
            background: var(--darker);
        }

        .about-feature {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 1.6rem;
        }

        .about-icon {
            width: 50px; height: 50px;
            background: rgba(201,168,76,0.1);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: 1.4rem;
            color: var(--gold);
        }

        .about-feature h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 1px;
            color: var(--gold-light);
            margin-bottom: 4px;
        }

        .about-feature p {
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.6;
        }

        .about-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .about-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--gold), var(--gold-dark));
        }

        /* =========================================================
           EQUIPMENT SECTION
        ========================================================= */
        #equipment {
            background: var(--black);
        }

        .equip-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem 1.5rem;
            text-align: center;
            transition: var(--transition);
            cursor: default;
            height: 100%;
        }

        .equip-card:hover {
            border-color: var(--gold);
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(201,168,76,0.15);
            background: #222;
        }

        .equip-icon {
            font-size: 3.5rem;
            background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            display: block;
        }

        .equip-card h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 1px;
            color: var(--white);
            margin-bottom: 0.6rem;
        }

        .equip-card p {
            font-size: 0.83rem;
            color: var(--muted);
            line-height: 1.6;
        }

        .equip-tag {
            display: inline-block;
            background: rgba(201,168,76,0.1);
            color: var(--gold);
            font-size: 0.7rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 3px;
            margin-top: 0.8rem;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        /* =========================================================
           MEMBERSHIP PLANS SECTION
        ========================================================= */
        #plans {
            background: var(--darker);
        }

        .plan-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            position: relative;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .plan-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(201,168,76,0.12);
        }

        .plan-card.featured {
            border-color: var(--gold);
            background: linear-gradient(160deg, #1e1a00 0%, #1c1c1c 100%);
            box-shadow: 0 0 40px rgba(201,168,76,0.15);
        }

        .plan-badge {
            position: absolute;
            top: -14px; left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(90deg, var(--gold), var(--gold-dark));
            color: var(--black);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 5px 18px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            white-space: nowrap;
        }

        .plan-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            letter-spacing: 3px;
            color: var(--gold-light);
            margin-bottom: 0.3rem;
        }

        .plan-price {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3.5rem;
            color: var(--white);
            line-height: 1;
        }

        .plan-price sup { font-size: 1.5rem; vertical-align: top; margin-top: 0.5rem; }
        .plan-price sub { font-size: 1rem; color: var(--muted); }

        .plan-divider {
            height: 1px;
            background: var(--border);
            margin: 1.5rem 0;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            flex: 1;
        }

        .plan-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.87rem;
            color: var(--muted);
            margin-bottom: 0.75rem;
        }

        .plan-features li i { color: var(--gold); font-size: 1rem; }
        .plan-features li.disabled { text-decoration: line-through; opacity: 0.4; }
        .plan-features li.disabled i { color: var(--muted); }

        /* =========================================================
           TRAINERS SECTION
        ========================================================= */
        #trainers {
            background: var(--black);
        }

        .trainer-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: var(--transition);
            text-align: center;
        }

        .trainer-card:hover {
            border-color: var(--gold);
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(201,168,76,0.12);
        }

        .trainer-avatar {
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6rem;
            background: linear-gradient(135deg, #1a1400 0%, #111 100%);
        }

        .trainer-info { padding: 1.5rem 1.2rem; }

        .trainer-info h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--white);
            margin-bottom: 4px;
        }

        .trainer-role {
            font-size: 0.8rem;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .trainer-bio {
            font-size: 0.83rem;
            color: var(--muted);
            margin: 0.8rem 0;
            line-height: 1.6;
        }

        .trainer-social a {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 34px; height: 34px;
            border: 1px solid var(--border);
            border-radius: 50%;
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0 3px;
            transition: var(--transition);
        }

        .trainer-social a:hover {
            border-color: var(--gold);
            color: var(--gold);
            background: rgba(201,168,76,0.1);
        }

        /* =========================================================
           REVIEWS SECTION
        ========================================================= */
        #reviews {
            background: var(--darker);
        }

        .review-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            transition: var(--transition);
            height: 100%;
            position: relative;
        }

        .review-card:hover {
            border-color: var(--gold);
            box-shadow: 0 10px 40px rgba(201,168,76,0.1);
        }

        .review-quote {
            font-size: 4rem;
            font-family: 'Bebas Neue', sans-serif;
            color: var(--gold);
            line-height: 0.5;
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        .review-text {
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .review-stars { color: var(--gold); font-size: 1rem; margin-bottom: 1rem; }

        .reviewer-name {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--white);
        }

        .reviewer-city {
            font-size: 0.78rem;
            color: var(--gold);
            letter-spacing: 1px;
        }

        /* =========================================================
           JOIN FORM SECTION
        ========================================================= */
        #join {
            background: var(--black);
        }

        .form-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .form-wrap::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--gold), var(--gold-dark));
        }

        .form-control, .form-select {
            background: #111;
            border: 1px solid rgba(201,168,76,0.25);
            color: var(--white);
            border-radius: 6px;
            padding: 12px 16px;
            font-family: 'Raleway', sans-serif;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            background: #111;
            color: var(--white);
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
        }

        .form-control::placeholder { color: #555; }

        .form-select option { background: #1c1c1c; color: var(--white); }

        .form-label {
            font-size: 0.82rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold-light);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-info {
            background: rgba(201,168,76,0.05);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2rem;
        }

        .form-info h4 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            letter-spacing: 2px;
            color: var(--gold-light);
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            margin-bottom: 1.2rem;
        }

        .info-item i {
            font-size: 1.2rem;
            color: var(--gold);
            margin-top: 2px;
        }

        .info-item p {
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.5;
            margin: 0;
        }

        .info-item span {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--white);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            margin-bottom: 2px;
        }

        /* =========================================================
           GALLERY STRIP
        ========================================================= */
        #gallery-strip {
            background: var(--black);
            padding: 0;
            overflow: hidden;
        }

        .gallery-ticker {
            display: flex;
            animation: ticker 20s linear infinite;
            width: max-content;
        }

        .gallery-ticker span {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.1rem;
            letter-spacing: 4px;
            color: var(--gold);
            padding: 14px 30px;
            border-right: 1px solid var(--border);
            white-space: nowrap;
        }

        @keyframes ticker {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* =========================================================
           FOOTER
        ========================================================= */
        footer {
            background: var(--dark);
            border-top: 1px solid var(--border);
            padding: 70px 0 30px;
        }

        .footer-brand .brand-name { font-size: 1.4rem; }

        .footer-tagline {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 0.8rem;
            line-height: 1.6;
            max-width: 280px;
        }

        .footer-heading {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 3px;
            color: var(--gold-light);
            margin-bottom: 1.2rem;
        }

        .footer-links { list-style: none; padding: 0; }

        .footer-links li {
            margin-bottom: 0.6rem;
        }

        .footer-links a {
            font-size: 0.88rem;
            color: var(--muted);
            transition: var(--transition);
        }

        .footer-links a:hover { color: var(--gold-light); padding-left: 6px; }

        .footer-contact-item {
            display: flex;
            gap: 12px;
            margin-bottom: 0.9rem;
            align-items: flex-start;
        }

        .footer-contact-item i {
            color: var(--gold);
            font-size: 1rem;
            margin-top: 2px;
        }

        .footer-contact-item span {
            font-size: 0.87rem;
            color: var(--muted);
        }

        .footer-social a {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 40px; height: 40px;
            border: 1px solid var(--border);
            border-radius: 50%;
            color: var(--muted);
            font-size: 1rem;
            margin-right: 8px;
            transition: var(--transition);
        }

        .footer-social a:hover {
            border-color: var(--gold);
            color: var(--gold);
            background: rgba(201,168,76,0.1);
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid var(--border);
            padding-top: 25px;
            margin-top: 50px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .footer-bottom p {
            font-size: 0.82rem;
            color: var(--muted);
            margin: 0;
        }

        /* =========================================================
           BACK TO TOP
        ========================================================= */
        #backToTop {
            position: fixed;
            bottom: 30px; right: 30px;
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black);
            border: none;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            display: none;
            align-items: center; justify-content: center;
            z-index: 999;
            box-shadow: 0 4px 20px rgba(201,168,76,0.4);
            transition: var(--transition);
        }

        #backToTop:hover { transform: translateY(-4px); }

        /* =========================================================
           RESPONSIVE
        ========================================================= */
        @media (max-width: 768px) {
            .hero-dumbbell-icon { font-size: 10rem; }
            .form-wrap { padding: 2rem 1.2rem; }
            .section-pad { padding: 60px 0; }
            .hero-stats { gap: 25px; }
        }
    </style>
</head>
<body>

<!-- ===================== PRELOADER ===================== -->
<div id="preloader">
    <div class="logo-text">RFC</div>
</div>

<!-- ===================== NAVBAR ===================== -->
<nav class="navbar navbar-expand-lg" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="#hero">
            <!-- SVG Gym Logo -->
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
            <div class="brand-name">
                <span class="top">Rajkot</span>
                <span class="bottom">Fitness Club</span>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#equipment">Equipment</a></li>
                <li class="nav-item"><a class="nav-link" href="#plans">Plans</a></li>
                <li class="nav-item"><a class="nav-link" href="#trainers">Trainers</a></li>
                <li class="nav-item"><a class="nav-link" href="#reviews">Reviews</a></li>
                <li class="nav-item ms-lg-2">
                    <a class="nav-link nav-cta" href="#join">Join Now</a>
                </li>
                <li class="nav-item ms-lg-2">
                    <span class="nav-link" style="color:#e8c97a"><?= htmlspecialchars($_SESSION['name']) ?> (<?= ucfirst($_SESSION['role']) ?>)</span>
                </li>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link nav-cta" href="admin.php">Admin Panel</a>
                </li>
                <?php endif; ?>
                <?php if (in_array($_SESSION['role'] ?? '', ['trainer','admin'], true)): ?>
                <li class="nav-item">
                    <a class="nav-link nav-cta" href="trainer.php">Trainer Panel</a>
                </li>
                <?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
                <li class="nav-item">
                    <a class="nav-link nav-cta" href="member_plan.php">My Plan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-cta" href="payment.php">Payments</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="login.php?logout=1">Logout</a>
                </li>
                
            </ul>
        </div>
    </div>
</nav>

<!-- ===================== PAGE 1: HERO ===================== -->
<section id="hero">
    <div class="hero-bg-text">POWER</div>
    <div class="container position-relative" style="z-index:1">
        <div class="row align-items-center min-vh-100 py-5">
            <div class="col-lg-7 col-md-8">
                <div class="hero-badge animate__animated animate__fadeInDown">
                    <i class="bi bi-geo-alt-fill me-1"></i> Rajkot, Gujarat
                </div>
                <h1 class="hero-title animate__animated animate__fadeInLeft">
                    <span class="gold-text">FORGE</span><br>YOUR<br>BODY.
                </h1>
                <p class="hero-subtitle animate__animated animate__fadeInLeft animate__delay-1s">
                    Rajkot's most premium fitness destination. State-of-the-art equipment, expert trainers, and a community that pushes you beyond your limits.
                </p>
                <div class="d-flex flex-wrap gap-3 animate__animated animate__fadeInUp animate__delay-1s">
                    <a href="#join" class="btn-gold">Start Your Journey</a>
                    <a href="#plans" class="btn-outline-gold">View Plans</a>
                </div>
                <div class="hero-stats animate__animated animate__fadeInUp animate__delay-2s">
                    <div class="hero-stat">
                        <div class="num" data-count="1500">0</div>
                        <div class="label">Members</div>
                    </div>
                    <div class="hero-stat">
                        <div class="num" data-count="15">0</div>
                        <div class="label">Expert Trainers</div>
                    </div>
                    <div class="hero-stat">
                        <div class="num" data-count="200">0</div>
                        <div class="label">Equipments</div>
                    </div>
                    <div class="hero-stat">
                        <div class="num" data-count="8">0</div>
                        <div class="label">Years Strong</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-flex justify-content-center">
                <div class="hero-image-wrap">
                    <i class="bi bi-trophy-fill hero-dumbbell-icon"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== GALLERY TICKER ===================== -->
<div id="gallery-strip" style="background:#111; padding:0; border-top:1px solid rgba(201,168,76,0.15); border-bottom:1px solid rgba(201,168,76,0.15);">
    <div class="gallery-ticker" id="ticker">
        <span>Strength Training</span><span>Cardio Zone</span><span>Yoga & Meditation</span>
        <span>CrossFit</span><span>Boxing Ring</span><span>Swimming Pool</span>
        <span>Sauna</span><span>Personal Training</span><span>Nutrition Guidance</span>
        <span>Zumba</span><span>Diet Plans</span><span>Body Transformation</span>
        <span>Strength Training</span><span>Cardio Zone</span><span>Yoga & Meditation</span>
        <span>CrossFit</span><span>Boxing Ring</span><span>Swimming Pool</span>
        <span>Sauna</span><span>Personal Training</span><span>Nutrition Guidance</span>
        <span>Zumba</span><span>Diet Plans</span><span>Body Transformation</span>
    </div>
</div>

<!-- ===================== PAGE 2: ABOUT ===================== -->
<section id="about" class="section-pad">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <p class="hero-badge mb-2">About Us</p>
                <h2 class="section-title gold-text">RAJKOT'S<br>FINEST GYM</h2>
                <div class="section-divider" style="margin:0 0 1.5rem;"></div>
                <p style="color:var(--muted); font-size:0.93rem; line-height:1.8; margin-bottom:2rem;">
                    Established in 2016, Rajkot Fitness Club has been the benchmark for premium fitness in Saurashtra. Located in the heart of Rajkot, we offer world-class facilities designed to transform lives.
                </p>
                <div class="about-feature">
                    <div class="about-icon"><i class="bi bi-clock"></i></div>
                    <div>
                        <h5>24/7 Access</h5>
                        <p>Train whenever your schedule allows. Our doors never close for our premium members.</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="about-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <h5>Certified Trainers</h5>
                        <p>All coaches hold national and international certifications for your safety.</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="about-icon"><i class="bi bi-wifi"></i></div>
                    <div>
                        <h5>Smart Gym Tech</h5>
                        <p>Digital locker rooms, app-based class booking, and real-time progress tracking.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-4">
                    <div class="col-sm-6">
                        <div class="about-card h-100">
                            <div style="font-size:2.5rem; color:var(--gold); margin-bottom:1rem;"><i class="bi bi-person-heart"></i></div>
                            <h3 style="font-family:'Montserrat',sans-serif; font-weight:700; font-size:1rem; color:var(--white); margin-bottom:0.5rem;">Community First</h3>
                            <p style="font-size:0.85rem; color:var(--muted); line-height:1.7;">Join a 1500+ strong community of fitness enthusiasts from Rajkot and Saurashtra who motivate each other daily.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="about-card h-100" style="margin-top:30px;">
                            <div style="font-size:2.5rem; color:var(--gold); margin-bottom:1rem;"><i class="bi bi-bar-chart-line"></i></div>
                            <h3 style="font-family:'Montserrat',sans-serif; font-weight:700; font-size:1rem; color:var(--white); margin-bottom:0.5rem;">Proven Results</h3>
                            <p style="font-size:0.85rem; color:var(--muted); line-height:1.7;">Over 800 documented body transformations with our structured programs, diet counseling, and trainer support.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="about-card h-100" style="margin-top:-20px;">
                            <div style="font-size:2.5rem; color:var(--gold); margin-bottom:1rem;"><i class="bi bi-award"></i></div>
                            <h3 style="font-family:'Montserrat',sans-serif; font-weight:700; font-size:1rem; color:var(--white); margin-bottom:0.5rem;">Award Winning</h3>
                            <p style="font-size:0.85rem; color:var(--muted); line-height:1.7;">Awarded Best Gym in Rajkot for 3 consecutive years by Gujarat Fitness Association.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="about-card h-100">
                            <div style="font-size:2.5rem; color:var(--gold); margin-bottom:1rem;"><i class="bi bi-camera-video"></i></div>
                            <h3 style="font-family:'Montserrat',sans-serif; font-weight:700; font-size:1rem; color:var(--white); margin-bottom:0.5rem;">HD Surveillance</h3>
                            <p style="font-size:0.85rem; color:var(--muted); line-height:1.7;">100% CCTV covered premises with separate changing rooms and lockers for your complete safety.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== PAGE 3: EQUIPMENT ===================== -->
<section id="equipment" class="section-pad">
    <div class="container">
        <div class="text-center mb-5">
            <p class="hero-badge">World-Class</p>
            <h2 class="section-title gold-text">OUR EQUIPMENT</h2>
            <div class="section-divider"></div>
            <p style="color:var(--muted); max-width:560px; margin:0 auto; font-size:0.93rem; line-height:1.7;">
                Equipped with 200+ machines from global brands like Life Fitness, Technogym, and Hammer Strength.
            </p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-lightning-charge"></i></span>
                    <h4>Power Racks</h4>
                    <p>Olympic power racks with safety spotter arms, pull-up bars and J-hooks for heavy compound lifts.</p>
                    <div class="equip-tag">Strength Zone</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-heart-pulse"></i></span>
                    <h4>Cardio Machines</h4>
                    <p>50+ treadmills, ellipticals, stair climbers & rowing machines with HD screens and heart-rate tracking.</p>
                    <div class="equip-tag">Cardio Zone</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-activity"></i></span>
                    <h4>Free Weights</h4>
                    <p>Dumbbells ranging from 2kg to 100kg, barbells, EZ-curl bars, and hex bars for versatile training.</p>
                    <div class="equip-tag">Free Weights</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-boxes"></i></span>
                    <h4>Cable Machines</h4>
                    <p>Dual-pulley functional trainers and cable crossovers for isolation and compound cable work.</p>
                    <div class="equip-tag">Cables</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-person-arms-up"></i></span>
                    <h4>Yoga Studio</h4>
                    <p>Dedicated 2000 sq ft yoga and meditation studio with premium mats, blocks, and straps.</p>
                    <div class="equip-tag">Mind & Body</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-fire"></i></span>
                    <h4>CrossFit Rig</h4>
                    <p>Full CrossFit rig with assault bikes, battle ropes, kettlebells, plyo boxes, and climbing ropes.</p>
                    <div class="equip-tag">Functional</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-trophy"></i></span>
                    <h4>Boxing Ring</h4>
                    <p>Professional 20x20 ft boxing ring with heavy bags, speed bags, and sparring gear available.</p>
                    <div class="equip-tag">Boxing</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="equip-card">
                    <span class="equip-icon"><i class="bi bi-droplet"></i></span>
                    <h4>Recovery Zone</h4>
                    <p>Steam sauna, ice bath, massage chairs, and foam rolling area for optimal post-workout recovery.</p>
                    <div class="equip-tag">Recovery</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== PAGE 4: PLANS ===================== -->
<section id="plans" class="section-pad">
    <div class="container">
        <div class="text-center mb-5">
            <p class="hero-badge">Membership</p>
            <h2 class="section-title gold-text">CHOOSE YOUR PLAN</h2>
            <div class="section-divider"></div>
            <p style="color:var(--muted); max-width:500px; margin:0 auto; font-size:0.93rem; line-height:1.7;">
                Flexible membership plans designed to suit every fitness goal and budget.
            </p>
        </div>
        <div class="row g-4 justify-content-center">
            <!-- Basic -->
            <div class="col-lg-3 col-md-6">
                <div class="plan-card">
                    <div class="plan-name">Basic</div>
                    <div class="plan-price"><sup>&#8377;</sup>999<sub>/mo</sub></div>
                    <div class="plan-divider"></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> Gym Floor Access</li>
                        <li><i class="bi bi-check-circle-fill"></i> Cardio Equipment</li>
                        <li><i class="bi bi-check-circle-fill"></i> Changing Rooms</li>
                        <li><i class="bi bi-check-circle-fill"></i> 1 Guest Pass/mo</li>
                        <li class="disabled"><i class="bi bi-x-circle"></i> Group Classes</li>
                        <li class="disabled"><i class="bi bi-x-circle"></i> Personal Trainer</li>
                        <li class="disabled"><i class="bi bi-x-circle"></i> Diet Consultation</li>
                    </ul>
                    <a href="#join" class="btn-outline-gold d-block text-center mt-4" style="width:100%;">Get Started</a>
                </div>
            </div>
            <!-- Standard -->
            <div class="col-lg-3 col-md-6">
                <div class="plan-card featured">
                    <div class="plan-badge">Most Popular</div>
                    <div class="plan-name">Standard</div>
                    <div class="plan-price"><sup>&#8377;</sup>1,799<sub>/mo</sub></div>
                    <div class="plan-divider"></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> Full Gym Access</li>
                        <li><i class="bi bi-check-circle-fill"></i> All Cardio Equipment</li>
                        <li><i class="bi bi-check-circle-fill"></i> Unlimited Classes</li>
                        <li><i class="bi bi-check-circle-fill"></i> 3 Guest Passes/mo</li>
                        <li><i class="bi bi-check-circle-fill"></i> 2 PT Sessions/mo</li>
                        <li><i class="bi bi-check-circle-fill"></i> Diet Consultation</li>
                        <li class="disabled"><i class="bi bi-x-circle"></i> Priority Booking</li>
                    </ul>
                    <a href="#join" class="btn-gold d-block text-center mt-4" style="width:100%;">Get Started</a>
                </div>
            </div>
            <!-- Premium -->
            <div class="col-lg-3 col-md-6">
                <div class="plan-card">
                    <div class="plan-name">Premium</div>
                    <div class="plan-price"><sup>&#8377;</sup>2,999<sub>/mo</sub></div>
                    <div class="plan-divider"></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> 24/7 Gym Access</li>
                        <li><i class="bi bi-check-circle-fill"></i> All Equipment</li>
                        <li><i class="bi bi-check-circle-fill"></i> Unlimited Classes</li>
                        <li><i class="bi bi-check-circle-fill"></i> Unlimited Guests</li>
                        <li><i class="bi bi-check-circle-fill"></i> 8 PT Sessions/mo</li>
                        <li><i class="bi bi-check-circle-fill"></i> Weekly Diet Plans</li>
                        <li><i class="bi bi-check-circle-fill"></i> Priority Booking</li>
                    </ul>
                    <a href="#join" class="btn-outline-gold d-block text-center mt-4" style="width:100%;">Get Started</a>
                </div>
            </div>
            <!-- Annual -->
            <div class="col-lg-3 col-md-6">
                <div class="plan-card" style="border-color:var(--red-accent);">
                    <div class="plan-badge" style="background:var(--red-accent);">Best Value</div>
                    <div class="plan-name" style="color:#e87f87;">Annual</div>
                    <div class="plan-price"><sup>&#8377;</sup>14,999<sub>/yr</sub></div>
                    <div class="plan-divider"></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> Everything in Premium</li>
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> 2 Months Free</li>
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> Free Gym Kit</li>
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> Locker Included</li>
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> Priority Access</li>
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> Monthly Assessment</li>
                        <li><i class="bi bi-check-circle-fill" style="color:var(--red-accent);"></i> Free Supplements</li>
                    </ul>
                    <a href="#join" class="btn-gold d-block text-center mt-4" style="width:100%; background:linear-gradient(135deg, var(--red-accent), #a0000e);">Get Started</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== PAGE 5: TRAINERS ===================== -->
<section id="trainers" class="section-pad">
    <div class="container">
        <div class="text-center mb-5">
            <p class="hero-badge">Meet The Team</p>
            <h2 class="section-title gold-text">EXPERT TRAINERS</h2>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="trainer-card">
                    <div class="trainer-avatar">&#129304;</div>
                    <div class="trainer-info">
                        <h4>Arjun Patel</h4>
                        <div class="trainer-role">Head Coach</div>
                        <p class="trainer-bio">12 years of experience. NSCA-CSCS certified. Specialises in hypertrophy and powerlifting programs.</p>
                        <div class="trainer-social">
                            <a href="#"><i class="bi bi-instagram"></i></a>
                            <a href="#"><i class="bi bi-facebook"></i></a>
                            <a href="#"><i class="bi bi-youtube"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="trainer-card">
                    <div class="trainer-avatar">&#129302;</div>
                    <div class="trainer-info">
                        <h4>Priya Sharma</h4>
                        <div class="trainer-role">Yoga & Wellness</div>
                        <p class="trainer-bio">RYT-500 certified yoga instructor with expertise in Hatha, Vinyasa, and therapeutic yoga.</p>
                        <div class="trainer-social">
                            <a href="#"><i class="bi bi-instagram"></i></a>
                            <a href="#"><i class="bi bi-facebook"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="trainer-card">
                    <div class="trainer-avatar">&#129303;</div>
                    <div class="trainer-info">
                        <h4>Ravi Maheta</h4>
                        <div class="trainer-role">CrossFit Coach</div>
                        <p class="trainer-bio">CF-L3 certified. Former state-level athlete. Expert in functional fitness and Olympic lifting.</p>
                        <div class="trainer-social">
                            <a href="#"><i class="bi bi-instagram"></i></a>
                            <a href="#"><i class="bi bi-twitter-x"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="trainer-card">
                    <div class="trainer-avatar">&#129305;</div>
                    <div class="trainer-info">
                        <h4>Neha Desai</h4>
                        <div class="trainer-role">Nutritionist</div>
                        <p class="trainer-bio">MSc in Sports Nutrition. Creates personalised macro-based meal plans for every body type and goal.</p>
                        <div class="trainer-social">
                            <a href="#"><i class="bi bi-instagram"></i></a>
                            <a href="#"><i class="bi bi-linkedin"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== PAGE 6a: REVIEWS ===================== -->
<section id="reviews" class="section-pad">
    <div class="container">
        <div class="text-center mb-5">
            <p class="hero-badge">Testimonials</p>
            <h2 class="section-title gold-text">WHAT MEMBERS SAY</h2>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="review-card">
                    <div class="review-quote">&ldquo;</div>
                    <div class="review-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="review-text">"Best gym in Rajkot, no doubt! The equipment is top-notch and the trainers actually care about your progress. Lost 18kg in 5 months with Arjun sir's guidance."</p>
                    <div class="reviewer-name">Makvana Aryan</div>
                    <div class="reviewer-city"><i class="bi bi-geo-alt-fill me-1"></i>Rajkot, Gujarat</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="review-card">
                    <div class="review-quote">&ldquo;</div>
                    <div class="review-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="review-text">"Priya ma'am's yoga sessions are life-changing. The calm studio, the scent, the music – everything is perfect. I feel 10 years younger after joining RFC."</p>
                    <div class="reviewer-name">kashish makvana</div>
                    <div class="reviewer-city"><i class="bi bi-geo-alt-fill me-1"></i>Rajkot, Gujarat</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="review-card">
                    <div class="review-quote">&ldquo;</div>
                    <div class="review-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                    </div>
                    <p class="review-text">"The CrossFit classes with Ravi bhai are insane! Pushed me beyond what I thought was possible. Great community and amazing energy every single morning."</p>
                    <div class="reviewer-name">astha makvana</div>
                    <div class="reviewer-city"><i class="bi bi-geo-alt-fill me-1"></i>Rajkot, Gujarat</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="review-card">
                    <div class="review-quote">&ldquo;</div>
                    <div class="review-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="review-text">"Neha ma'am's nutrition plan combined with my gym routine gave me visible abs in 3 months. The diet advice is scientific and not just generic bro-science."</p>
                    <div class="reviewer-name">adhya shah</div>
                    <div class="reviewer-city"><i class="bi bi-geo-alt-fill me-1"></i>Morbi, Gujarat</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="review-card">
                    <div class="review-quote">&ldquo;</div>
                    <div class="review-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="review-text">"As a working woman, I was worried about safety and timings. RFC has 24/7 access, HD cameras, and a super clean changing room. Highly recommend for women!"</p>
                    <div class="reviewer-name">pinal darji</div>
                    <div class="reviewer-city"><i class="bi bi-geo-alt-fill me-1"></i>Rajkot, Gujarat</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="review-card">
                    <div class="review-quote">&ldquo;</div>
                    <div class="review-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="review-text">"Joining RFC was the best decision of 2024. The Premium plan value is unbeatable. Free supplements, locker, and monthly assessments – totally worth every rupee."</p>
                    <div class="reviewer-name">vyom ambani</div>
                    <div class="reviewer-city"><i class="bi bi-geo-alt-fill me-1"></i>Junagadh, Gujarat</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== PAGE 6b: JOIN FORM ===================== -->
<section id="join" class="section-pad">
    <div class="container">
        <div class="text-center mb-5">
            <p class="hero-badge">Start Today</p>
            <h2 class="section-title gold-text">JOIN RFC NOW</h2>
            <div class="section-divider"></div>
        </div>
        <div class="row g-5">
            <div class="col-lg-7">
                <div class="form-wrap">
                    <h3 style="font-family:'Bebas Neue',sans-serif; font-size:1.8rem; letter-spacing:2px; color:var(--gold-light); margin-bottom:0.3rem;">Membership Enquiry</h3>
                    <p style="font-size:0.85rem; color:var(--muted); margin-bottom:2rem;">Fill the form and our team will contact you within 24 hours.</p>

                    <?php if ($form_success): ?>
                    <div class="alert" style="background:rgba(201,168,76,0.1); border:1px solid var(--gold); color:var(--gold-light); border-radius:8px; padding:1rem 1.2rem; margin-bottom:1.5rem; font-size:0.9rem;">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo $form_success; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($form_error): ?>
                    <div class="alert" style="background:rgba(230,57,70,0.1); border:1px solid var(--red-accent); color:#e87f87; border-radius:8px; padding:1rem 1.2rem; margin-bottom:1.5rem; font-size:0.9rem;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $form_error; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="#join" novalidate>
                        <input type="hidden" name="join_form" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span style="color:var(--red-accent);">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Arjun Patel"
                                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span style="color:var(--red-accent);">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="your@email.com"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span style="color:var(--red-accent);">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number"
                                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" maxlength="10" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Membership Plan <span style="color:var(--red-accent);">*</span></label>
                                <select name="plan" class="form-select" required>
                                    <option value="">-- Select Plan --</option>
                                    <option value="Basic" <?php echo (($_POST['plan'] ?? '') === 'Basic') ? 'selected' : ''; ?>>Basic – &#8377;999/mo</option>
                                    <option value="Standard" <?php echo (($_POST['plan'] ?? '') === 'Standard') ? 'selected' : ''; ?>>Standard – &#8377;1,799/mo</option>
                                    <option value="Premium" <?php echo (($_POST['plan'] ?? '') === 'Premium') ? 'selected' : ''; ?>>Premium – &#8377;2,999/mo</option>
                                    <option value="Annual" <?php echo (($_POST['plan'] ?? '') === 'Annual') ? 'selected' : ''; ?>>Annual – &#8377;14,999/yr</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fitness Goal</label>
                                <select name="goal" class="form-select">
                                    <option value="">-- Select Goal --</option>
                                    <option value="Weight Loss">Weight Loss</option>
                                    <option value="Muscle Gain">Muscle Gain</option>
                                    <option value="Strength">Strength Training</option>
                                    <option value="General Fitness">General Fitness</option>
                                    <option value="Yoga & Wellness">Yoga & Wellness</option>
                                    <option value="Body Transformation">Body Transformation</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">How did you hear about us?</label>
                                <select name="source" class="form-select">
                                    <option value="">-- Select Source --</option>
                                    <option value="Instagram">Instagram</option>
                                    <option value="Facebook">Facebook</option>
                                    <option value="Friend">Friend/Family</option>
                                    <option value="Hoarding">Hoarding/Banner</option>
                                    <option value="Google">Google Search</option>
                                    <option value="Walk-in">Walk-in</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message / Special Requirements</label>
                                <textarea name="message" class="form-control" rows="4"
                                    placeholder="Any special health conditions, preferred timing, or questions..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="form-check" style="display:flex; gap:10px; align-items:flex-start;">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required
                                        style="margin-top:4px; border-color:var(--gold); background:transparent;">
                                    <label class="form-check-label" for="terms" style="font-size:0.83rem; color:var(--muted);">
                                        I agree to the <a href="#" style="color:var(--gold);">Terms & Conditions</a> and <a href="#" style="color:var(--gold);">Privacy Policy</a> of Rajkot Fitness Club.
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <button type="submit" class="btn-gold" style="width:100%; text-align:center; padding:16px;">
                                    <i class="bi bi-send-fill me-2"></i>Submit Enquiry
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            $conn = mysqli_connect("localhost", "root", "", "gym_db");

            if (!$conn) {
                     die("Connection failed: " . mysqli_connect_error());
            }
            ?>
            <div class="col-lg-5">
                <div class="form-info">
                    <h4>GET IN TOUCH</h4>
                    <div class="info-item">
                        <i class="bi bi-geo-alt-fill"></i>
                        <p><span>Address</span>RFC Tower, Kalawad Road, Near Aji Dam Circle, Rajkot – 360001, Gujarat, India</p>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-telephone-fill"></i>
                        <p><span>Phone</span>+91 98765 43210 &nbsp;|&nbsp; +91 98765 43211</p>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-envelope-fill"></i>
                        <p><span>Email</span>info@rajkotfitnessclub.in</p>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-clock-fill"></i>
                        <p><span>Hours</span>Mon–Fri: 5:00 AM – 11:00 PM<br>Sat–Sun: 6:00 AM – 10:00 PM<br><span style="color:var(--gold); font-size:0.75rem;">24/7 for Premium & Annual members</span></p>
                    </div>
                </div>

                <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:10px; padding:1.5rem; margin-top:1.5rem;">
                    <h4 style="font-family:'Bebas Neue'; font-size:1.4rem; letter-spacing:2px; color:var(--gold-light); margin-bottom:1rem;">FOLLOW US</h4>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <a href="#" style="display:flex; align-items:center; gap:8px; background:rgba(201,168,76,0.08); border:1px solid var(--border); border-radius:8px; padding:10px 16px; color:var(--muted); font-size:0.85rem; transition:var(--transition);" onmouseover="this.style.borderColor='var(--gold)'; this.style.color='var(--gold-light)';" onmouseout="this.style.borderColor='rgba(201,168,76,0.25)'; this.style.color='var(--muted)';">
                            <i class="bi bi-instagram" style="font-size:1.1rem;"></i> Instagram
                        </a>
                        <a href="#" style="display:flex; align-items:center; gap:8px; background:rgba(201,168,76,0.08); border:1px solid var(--border); border-radius:8px; padding:10px 16px; color:var(--muted); font-size:0.85rem; transition:var(--transition);" onmouseover="this.style.borderColor='var(--gold)'; this.style.color='var(--gold-light)';" onmouseout="this.style.borderColor='rgba(201,168,76,0.25)'; this.style.color='var(--muted)';">
                            <i class="bi bi-facebook" style="font-size:1.1rem;"></i> Facebook
                        </a>
                        <a href="#" style="display:flex; align-items:center; gap:8px; background:rgba(201,168,76,0.08); border:1px solid var(--border); border-radius:8px; padding:10px 16px; color:var(--muted); font-size:0.85rem; transition:var(--transition);" onmouseover="this.style.borderColor='var(--gold)'; this.style.color='var(--gold-light)';" onmouseout="this.style.borderColor='rgba(201,168,76,0.25)'; this.style.color='var(--muted)';">
                            <i class="bi bi-youtube" style="font-size:1.1rem;"></i> YouTube
                        </a>
                    </div>
                </div>

                <!-- Google Map Embed Placeholder -->
                <div style="border:1px solid var(--border); border-radius:10px; overflow:hidden; margin-top:1.5rem; height:200px; background:#111; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px;">
                    <i class="bi bi-map" style="font-size:2.5rem; color:var(--gold); opacity:0.5;"></i>
                    <p style="font-size:0.8rem; color:var(--muted); margin:0;">Kalawad Road, Rajkot, Gujarat</p>
                    <a href="https://maps.google.com/?q=Rajkot,Gujarat" target="_blank" class="btn-outline-gold" style="padding:8px 20px; font-size:0.75rem;">Open in Google Maps</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                    <a class="navbar-brand" href="#hero" style="padding:0;">
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
                        <div class="brand-name">
                            <span class="top">Rajkot</span>
                            <span class="bottom">Fitness Club</span>
                        </div>
                    </a>
                </div>
                <p class="footer-tagline">Rajkot's most premium fitness destination since 2016. Transforming lives through science-backed training and elite coaching.</p>
                <div class="footer-social mt-3">
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-youtube"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="#about"><i class="bi bi-chevron-right me-1"></i>About Us</a></li>
                    <li><a href="#equipment"><i class="bi bi-chevron-right me-1"></i>Equipment</a></li>
                    <li><a href="#plans"><i class="bi bi-chevron-right me-1"></i>Plans</a></li>
                    <li><a href="#trainers"><i class="bi bi-chevron-right me-1"></i>Trainers</a></li>
                    <li><a href="#reviews"><i class="bi bi-chevron-right me-1"></i>Reviews</a></li>
                    <li><a href="#join"><i class="bi bi-chevron-right me-1"></i>Join Now</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h4 class="footer-heading">Services</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="bi bi-chevron-right me-1"></i>Personal Training</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right me-1"></i>Group Classes</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right me-1"></i>Yoga & Meditation</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right me-1"></i>Diet & Nutrition</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right me-1"></i>Body Transformation</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right me-1"></i>Corporate Wellness</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h4 class="footer-heading">Contact</h4>
                <div class="footer-contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>RFC Tower, Kalawad Road, Rajkot – 360001, Gujarat</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span>+91 98765 43210</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <span>info@rajkotfitnessclub.in</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-clock-fill"></i>
                    <span>Mon–Sun: 5 AM – 11 PM</span>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Rajkot Fitness Club. All rights reserved. Rajkot, Gujarat, India.</p>
            <p style="color:var(--gold); font-size:0.8rem;">Designed for XAMPP &bull; gym_management.php</p>
        </div>
    </div>
</footer>

<!-- Back to top -->
<button id="backToTop" title="Back to Top"><i class="bi bi-arrow-up"></i></button>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// =====================================================================
// PRELOADER
// =====================================================================
window.addEventListener('load', function () {
    const preloader = document.getElementById('preloader');
    setTimeout(function () {
        preloader.style.opacity = '0';
        setTimeout(function () { preloader.style.display = 'none'; }, 600);
    }, 1000);
});

// =====================================================================
// NAVBAR SCROLL EFFECT
// =====================================================================
window.addEventListener('scroll', function () {
    const nav = document.getElementById('mainNav');
    if (window.scrollY > 60) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }

    // Back to top button
    const btn = document.getElementById('backToTop');
    if (window.scrollY > 400) {
        btn.style.display = 'flex';
    } else {
        btn.style.display = 'none';
    }
});

// =====================================================================
// BACK TO TOP
// =====================================================================
document.getElementById('backToTop').addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// =====================================================================
// ANIMATED COUNTER
// =====================================================================
function animateCounter(el, target, duration) {
    let start = 0;
    const step = target / (duration / 16);
    const timer = setInterval(function () {
        start += step;
        if (start >= target) {
            el.textContent = target.toLocaleString('en-IN') + '+';
            clearInterval(timer);
        } else {
            el.textContent = Math.floor(start).toLocaleString('en-IN') + '+';
        }
    }, 16);
}

const counterEls = document.querySelectorAll('.num[data-count]');
let countersStarted = false;

const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
        if (entry.isIntersecting && !countersStarted) {
            countersStarted = true;
            counterEls.forEach(function (el) {
                animateCounter(el, parseInt(el.dataset.count), 2000);
            });
        }
    });
}, { threshold: 0.5 });

if (counterEls.length > 0) observer.observe(counterEls[0]);

// =====================================================================
// SCROLL REVEAL – fade-in sections on scroll
// =====================================================================
const revealElements = document.querySelectorAll(
    '.equip-card, .plan-card, .trainer-card, .review-card, .about-card, .about-feature'
);

const revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

revealElements.forEach(function (el) {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    revealObserver.observe(el);
});

// =====================================================================
// ACTIVE NAV LINK ON SCROLL
// =====================================================================
const sections = document.querySelectorAll('section[id]');
const navLinks = document.querySelectorAll('.nav-link');

window.addEventListener('scroll', function () {
    let current = '';
    sections.forEach(function (sec) {
        if (window.scrollY >= sec.offsetTop - 100) {
            current = sec.getAttribute('id');
        }
    });
    navLinks.forEach(function (link) {
        link.style.color = '';
        if (link.getAttribute('href') === '#' + current) {
            link.style.color = 'var(--gold-light)';
        }
    });
});

// =====================================================================
// FORM VALIDATION ENHANCEMENT (client-side)
// =====================================================================
const form = document.querySelector('form[method="POST"]');
if (form) {
    form.addEventListener('submit', function (e) {
        const terms = document.getElementById('terms');
        if (terms && !terms.checked) {
            e.preventDefault();
            alert('Please agree to the Terms & Conditions to proceed.');
        }
    });

    // Phone – only numbers
    const phoneInput = form.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }
}
</script>
</body>
</html>

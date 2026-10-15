<?php
// admin_guard.php - paste these lines at the VERY TOP of your admin panel file (before any HTML)
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }          // not logged in
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: gym.php'); exit; }  // logged in but not admin

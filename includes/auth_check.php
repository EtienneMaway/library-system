<?php

// Put this at the very top of every protected page, before any output.
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    flash('error', 'Please log in to continue.');
    redirect('auth/login.php');
}

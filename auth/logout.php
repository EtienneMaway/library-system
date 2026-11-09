<?php
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];
session_regenerate_id(true);
flash('success', 'You have logged out.');
redirect('auth/login.php');

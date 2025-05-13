<?php
// Use an absolute path to the constants.php file
require_once __DIR__ . '/../../config/constants.php';  // Adjusting path correctly to point to config/constants.php
require_once __DIR__ . '/../../config/database.php';

session_start();
session_unset();
session_destroy();

// Ensure to use the correct relative path to your login page
header("Location: " . BASE_URL . "modules/auth/login.php"); // Use BASE_URL or the correct relative path to login.php
exit();

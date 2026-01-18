<?php
/**
 * SMTP Configuration
 * Store email credentials securely
 * 
 * IMPORTANT: Add this file to .gitignore to prevent credentials from being committed
 */

// SMTP Server Settings
define('SMTP_HOST', 'ssl://smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'helpemployeerecruitmentsystem@gmail.com');
define('SMTP_PASSWORD', 'jlel ebvj qurg gpif'); // Gmail App Password
define('SMTP_FROM_NAME', 'Employee Recruitment System');

// Email Settings
define('SMTP_TIMEOUT', 30);
define('SMTP_DEBUG', false); // Set to true for debugging
?>

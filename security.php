<?php

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/*
  Get CSRF Token Input Field
  Returns HTML input field with CSRF token
 */
function csrf_token_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/*
  Regenerate Session ID
  Regenerates session ID to prevent session fixation attacks
 */
function regenerate_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Validate File Upload
 * Validates uploaded file for type, size, and security
 * 
 * @param array $file - $_FILES array element
 * @param array $allowed_types - Allowed MIME types
 * @param int $max_size - Maximum file size in bytes
 * @return array - ['success' => bool, 'error' => string]
 */
function validate_file_upload($file, $allowed_types = ['application/pdf'], $max_size = 5242880) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return ['success' => false, 'error' => $error_messages[$file['error']] ?? 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $max_mb = round($max_size / 1048576, 2);
        return ['success' => false, 'error' => "File size exceeds maximum allowed size of {$max_mb}MB"];
    }
    
    // Check MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only PDF files are allowed'];
    }
    
    // Additional security: Check file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf'];
    
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file extension'];
    }
    
    // Check for double extensions (security risk)
    if (substr_count($file['name'], '.') > 1) {
        return ['success' => false, 'error' => 'File name contains multiple extensions'];
    }
    
    return ['success' => true, 'error' => ''];
}

/**
 * Sanitize Input
 * Cleans user input to prevent XSS
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate Email
 * Validates email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Set Security Headers
 * Sets HTTP security headers
 */
function set_security_headers() {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

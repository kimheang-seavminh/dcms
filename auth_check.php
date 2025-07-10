<?php
// auth_check.php

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Start the session. This MUST be the very first executable line.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Connection (replace with your actual credentials) ---
$servername = "localhost";
$username = "root"; // Your MySQL username
$password = "";     // Your MySQL password
$dbname = "dms_db"; // The database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Language Selection Logic ---
// Check if a language is requested via GET parameter
if (isset($_GET['lang'])) {
    $requested_lang = $_GET['lang'];
    // Validate the requested language
    if (in_array($requested_lang, ['en', 'kh'])) { // Use 'kh' to match the filename 'kh.php'
        $_SESSION['lang'] = $requested_lang;
    }
}

// Set default language if not set in session
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // Default to English
}

// Include the selected language file using __DIR__ for robustness
$lang_file = __DIR__ . '/lang/lang/' . $_SESSION['lang'] . '.php'; // Corrected path
if (file_exists($lang_file)) {
    include_once $lang_file;
} else {
    // Fallback to English if the selected language file is missing
    include_once __DIR__ . '/lang/en.php';
    $_SESSION['lang'] = 'en'; // Reset session lang to default
}

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    // If user is not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// Fetch user details from session
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? 'Guest'; // Fallback for email

// Fetch user's role name from session or database if not set
$user_role_name = $_SESSION['user_role_name'] ?? 'Guest';

// Function to check specific permissions
function check_permission($conn, $user_id, $permission_name) {
    // If user is Super Admin, they have all permissions
    if (isset($_SESSION['user_role_name']) && $_SESSION['user_role_name'] === 'Super Admin') {
        return true;
    }

    // Otherwise, check database for specific permission
    $sql = "SELECT COUNT(rp.permission_id)
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.name = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Error preparing permission check: " . $conn->error);
        return false;
    }
    $stmt->bind_param("is", $user_id, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return $row[0] > 0;
}

// ob_end_flush(); // Removed from here, better to have it at the end of each page.
?>

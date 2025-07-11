
<?php
// login.php - PHP Backend for Login

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Start the session. This MUST be the very first executable line.
//session_start();

// --- Language Selection Logic (for public pages like login) ---
// This ensures the login page respects language selection even before login
if (isset($_GET['lang'])) {
    $requested_lang = $_GET['lang'];
    if (in_array($requested_lang, ['en', 'kh'])) { // Use 'kh' to match the filename 'kh.php'
        $_SESSION['lang'] = $requested_lang;
    }
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // Default to English
}
$lang_file = 'lang/lang/' . $_SESSION['lang'] . '.php'; // Corrected path
if (file_exists($lang_file)) {
    include_once $lang_file;
} 
// --- Database Connection ---
$servername = "192.168.20.13:32163";
$username = "root"; // Your MySQL username
$password = "382096a3207028829496cb77202a76d1e76549e0";     // Your MySQL password
$dbname = "dms_db"; // The database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_type = '';

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Plain text password from form

    // Basic validation (server-side)
    if (empty($email) || empty($password)) {
        $message = $lang['please_fill_all_fields'];
        $message_type = 'error';
    } else {
        // Prepare SQL statement to prevent SQL injection
        // IMPORTANT: Fetch the hashed password from the database
        $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verify the submitted password against the hashed password from the database
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];

                // Fetch user's role name and store in session
                $role_stmt = $conn->prepare("SELECT r.name FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = ?");
                $role_stmt->bind_param("i", $user['id']);
                $role_stmt->execute();
                $role_result = $role_stmt->get_result();
                if ($role_row = $role_result->fetch_assoc()) {
                    $_SESSION['user_role_name'] = $role_row['name'];
                } else {
                    $_SESSION['user_role_name'] = 'Guest'; // Default role if none found
                }
                $role_stmt->close();

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Invalid password
                $message = $lang['invalid_credentials'];
                $message_type = 'error';
            }
        } else {
            // User not found
            $message = $lang['invalid_credentials'];
            $message_type = 'error';
        }

        $stmt->close();
    }
}

$conn->close();
ob_end_flush(); // End output buffering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['dms_title'] . ' - ' . $lang['login']; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: <?php echo (isset($_SESSION['lang']) && $_SESSION['lang'] === 'kh') ? "'Battambang', sans-serif" : "'Inter', sans-serif"; ?>;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6"><?php echo $lang['login_to_dms']; ?></h2>

        <?php if (!empty($message)): ?>
            <div class="p-3 mb-4 rounded-md text-sm <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700"><?php echo $lang['email']; ?></label>
                <input type="email" id="email" name="email" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="your@example.com">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700"><?php echo $lang['password']; ?></label>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="********">
            </div>
            <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <?php echo $lang['sign_in']; ?>
            </button>
        </form>
    </div>
</body>
</html>

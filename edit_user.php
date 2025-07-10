<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debugging: Dump POST data to see if it reaches the script
// If you see this output, the POST request is hitting the script.
// If you don't see this, the 404 is happening before PHP execution.
// var_dump($_POST);

// Include the central authentication and permission check file
include 'auth_check.php'; // This handles session_start() and redirects if not logged in

// Check if the user has permission to manage users (e.g., Super Admin)
if ($user_role_name !== 'Super Admin') {
    header("Location: dashboard.php?message=Access Denied: You do not have permission to edit users.&type=error");
    exit();
}

// --- Database Connection (from auth_check.php, already open) ---

// Get user ID from GET for initial load, or from POST if form was submitted
$user_to_edit_id = $_GET['id'] ?? $_POST['user_id_hidden'] ?? null; // Added $_POST['user_id_hidden']

$user_data = null;
$message = '';
$message_type = '';

// Fetch user details for pre-filling the form
if ($user_to_edit_id) {
    $stmt = $conn->prepare("SELECT id, email, first_name, last_name, mobile_number, role_id FROM users WHERE id = ?");
    if ($stmt === false) {
        $message = "Database error: Could not prepare user fetch query. Error: " . $conn->error;
        $message_type = 'error';
        $user_to_edit_id = null; // Invalidate ID if query prepare fails
    } else {
        $stmt->bind_param("i", $user_to_edit_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        } else {
            $message = "User not found.";
            $message_type = 'error';
            $user_to_edit_id = null; // Invalidate ID if not found
        }
        $stmt->close();
    }
} else {
    $message = "No user ID provided for editing.";
    $message_type = 'error';
}

// Handle form submission for updating a user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user']) && $user_to_edit_id) {
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $role_id = $_POST['role_id'] ?? null;

    // Basic validation
    if (empty($email) || empty($role_id)) {
        $message = "Email and Role are required fields.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = 'error';
    } else {
        // Check if email already exists for another user (excluding the current user being edited)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        if ($check_stmt === false) {
            $message = "Database error: Could not prepare email existence check query. Error: " . $conn->error;
            $message_type = 'error';
        } else {
            $check_stmt->bind_param("si", $email, $user_to_edit_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "User with this email already exists for another account.";
                $message_type = 'error';
            } else {
                // Update user in the database
                $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, mobile_number = ?, role_id = ? WHERE id = ?");
                if ($stmt === false) {
                    $message = "Database error: Could not prepare user update query. Please check your 'users' table column names. Error: " . $conn->error;
                    $message_type = 'error';
                } else {
                    $stmt->bind_param("ssssii", $email, $first_name, $last_name, $mobile_number, $role_id, $user_to_edit_id);

                    if ($stmt->execute()) {
                        $message = "User '" . htmlspecialchars($email) . "' updated successfully.";
                        $message_type = 'success';
                        // Redirect to users.php after successful update
                        header("Location: users.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
                        exit(); // Important to exit after header redirect
                    } else {
                        $message = "Error updating user: " . $stmt->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
}

// Fetch all roles for the dropdown (needed for initial load and after update)
$roles = [];
$roles_result = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
if ($roles_result === false) {
    error_log("Error fetching roles for dropdown: " . $conn->error);
} else {
    if ($roles_result->num_rows > 0) {
        while ($row = $roles_result->fetch_assoc()) {
            $roles[] = $row;
        }
    }
}

// Close connection (from auth_check.php)
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Edit User</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <div class="w-70 bg-white shadow-lg flex-shrink-0 overflow-y-auto">
        <div class="p-5 border-b border-gray-200">
            <h1 class="text-3xl font-extrabold text-indigo-700">DMS</h1>
        </div>
        <nav class="mt-4 bg-gray-50">
            <a href="dashboard.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-500 rounded-lg mx-2 transition duration-150 ease-in-out bg-indigo-100 text-indigo-700 border-b">
                <i class="fas fa-tachometer-alt mr-2"></i> <?php echo $lang['dashboard']; ?>
            </a>
            <a href="assigned_documents.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-file-alt mr-2"></i> <?php echo $lang['assigned_documents']; ?>
            </a>
            <a href="all_documents.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-folder-open mr-2"></i> <?php echo $lang['all_documents']; ?>
            </a>
            <a href="document_categories.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-tags mr-2"></i> <?php echo $lang['document_categories']; ?>
            </a>
            <a href="documents_audit_trail.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-history mr-2"></i> <?php echo $lang['documents_audit_trail']; ?>
            </a>
            <a href="roles.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-user-tag mr-2"></i> <?php echo $lang['roles']; ?>
            </a>
            <a href="users.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-users mr-2"></i> <?php echo $lang['users']; ?>
            </a>
            <a href="role_user.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-user-cog mr-2"></i> <?php echo $lang['role_user']; ?>
            </a>
            <a href="reminder.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-bell mr-2"></i> <?php echo $lang['reminder']; ?>
            </a>
            <a href="#" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-sign-in-alt mr-2"></i> <?php echo $lang['login_audits']; ?>
            </a>
            <a href="#" class="flex items-center py-3 px-6 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg mx-2 transition duration-150 ease-in-out border-b">
                <i class="fas fa-envelope mr-2"></i> <?php echo $lang['smtp_setting']; ?>
            </a>
            <a href="/dcms/logout.php" class="flex items-center py-3 px-6 text-gray-700 hover:bg-red-50 hover:text-red-700 rounded-lg mx-2 transition duration-150 ease-in-out mt-4 border-b" id="logout-link">
                <i class="fas fa-sign-out-alt mr-2"></i> <?php echo $lang['logout']; ?>
            </a>
            <!-- Language Switcher in Sidebar -->
            <div class="px-6 py-3">
                <form method="post" id="lang-form">
            <!-- <label for="lang-select" class="block text-sm font-medium text-gray-700 mb-1">Language</label> -->
                    <select name="lang" id="lang-select" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" onchange="document.getElementById('lang-form').submit()">
                        <option value="en" <?php echo ($_SESSION['lang'] === 'en') ? 'selected' : ''; ?>>English</option>
                        <option value="kh" <?php echo ($_SESSION['lang'] === 'kh') ? 'selected' : ''; ?>>ខ្មែរ</option>
                    </select>
                </form>
            </div>
        </nav>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Header -->
        <header class="flex items-center justify-between p-6 bg-white shadow-md">
            <div class="flex items-center">
                <button class="text-gray-500 hover:text-gray-700 focus:outline-none focus:text-gray-700 mr-4 md:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-2xl font-semibold text-gray-800">Edit User</h1>
            </div>
            <div class="flex items-center space-x-4">
                <button class="text-gray-500 hover:text-gray-700 focus:outline-none focus:text-gray-700">
                    <i class="fas fa-bell text-xl"></i>
                </button>
                <button class="text-gray-500 hover:text-gray-700 focus:outline-none focus:text-gray-700">
                    <i class="fas fa-flag text-xl"></i>
                </button>
                <div class="relative">
                    <button class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none focus:text-gray-900">
                        <img class="h-8 w-8 rounded-full object-cover" src="https://placehold.co/150x150/cccccc/ffffff?text=User" alt="User Avatar">
                        <span class="hidden md:inline-block font-medium"><?php echo htmlspecialchars($user_email); ?></span>
                    </button>
                    <!-- Dropdown for user profile (optional, can be added with JS) -->
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="bg-white rounded-xl shadow-md p-6 max-w-2xl mx-auto">
                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($user_data): ?>
                    <form action="edit_user.php" method="POST" class="space-y-6"> <!-- Simplified action -->
                        <input type="hidden" name="update_user" value="1">
                        <input type="hidden" name="user_id_hidden" value="<?php echo htmlspecialchars($user_to_edit_id); ?>"> <!-- Hidden ID for POST -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>">
                        </div>
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" id="first_name" name="first_name"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="<?php echo htmlspecialchars($user_data['first_name']); ?>">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" id="last_name" name="last_name"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="<?php echo htmlspecialchars($user_data['last_name']); ?>">
                        </div>
                        <div>
                            <label for="mobile_number" class="block text-sm font-medium text-gray-700">Mobile Number</label>
                            <input type="text" id="mobile_number" name="mobile_number"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="<?php echo htmlspecialchars($user_data['mobile_number']); ?>">
                        </div>
                        <div>
                            <label for="role_id" class="block text-sm font-medium text-gray-700">Assign Role <span class="text-red-500">*</span></label>
                            <select id="role_id" name="role_id" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['id']); ?>"
                                        <?php echo ($user_data['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 transition duration-150 ease-in-out">
                                <i class="fas fa-save mr-2"></i> Save Changes
                            </button>
                            <button type="button" onclick="window.history.back()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition duration-150 ease-in-out">
                                Cancel
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logout functionality
            document.getElementById('logout-link').addEventListener('click', function(event) {
                event.preventDefault();
                window.location.href = window.location.origin + '/logout.php';
            });
        });
    </script>
</body>
</html>

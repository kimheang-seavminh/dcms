<?php
// Include the central authentication and permission check file
include 'auth_check.php'; // This handles session_start() and redirects if not logged in
if (isset($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
// Check if the user has permission to manage users (e.g., Super Admin)
if ($user_role_name !== 'Super Admin') {
    header("Location: dashboard.php?message=Access Denied: You do not have permission to manage users.&type=error");
    exit();
}

// --- Database Connection (from auth_check.php, already open) ---

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

// Fetch all roles for the dropdowns in Add/Edit User modals
$all_roles = [];
$roles_result = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
if ($roles_result) {
    while ($row = $roles_result->fetch_assoc()) {
        $all_roles[] = $row;
    }
} else {
    error_log("Error fetching roles: " . $conn->error);
}

// Handle Add/Edit/Delete User
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role_id = $_POST['role_id'] ?? null;

        if (empty($email) || empty($password) || empty($confirm_password) || empty($role_id)) {
            $message = "All fields are required to add a user.";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
            $message_type = 'error';
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
            $message_type = 'error';
        } else {
            // Hash the password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();
            try {
                // Check if email already exists
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    throw new Exception("User with this email already exists.");
                }
                $check_stmt->close();

                $stmt = $conn->prepare("INSERT INTO users (email, password, role_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $email, $hashed_password, $role_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error adding user: " . $stmt->error);
                }
                $stmt->close();
                $conn->commit();
                $message = "User '" . htmlspecialchars($email) . "' added successfully.";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error adding user: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($action === 'edit_user') {
        $user_id_to_edit = $_POST['user_id'] ?? null;
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';
        $role_id = $_POST['role_id'] ?? null;

        if (empty($user_id_to_edit) || empty($email) || empty($role_id)) {
            $message = "User ID, Email, and Role are required for update.";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
            $message_type = 'error';
        } elseif (!empty($new_password) && $new_password !== $confirm_new_password) {
            $message = "New passwords do not match.";
            $message_type = 'error';
        } else {
            $conn->begin_transaction();
            try {
                // Check if email already exists for another user
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->bind_param("si", $email, $user_id_to_edit);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    throw new Exception("User with this email already exists.");
                }
                $check_stmt->close();

                $sql = "UPDATE users SET email = ?, role_id = ?";
                $params = [$email, $role_id];
                $types = "si";

                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql .= ", password = ?";
                    $params[] = $hashed_password;
                    $types .= "s";
                }
                $sql .= " WHERE id = ?";
                $params[] = $user_id_to_edit;
                $types .= "i";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);

                if (!$stmt->execute()) {
                    throw new Exception("Error updating user: " . $stmt->error);
                }
                $stmt->close();
                $conn->commit();
                $message = "User '" . htmlspecialchars($email) . "' updated successfully.";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error updating user: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($action === 'delete_user') {
        $user_id_to_delete = $_POST['user_id'] ?? null;

        if (empty($user_id_to_delete)) {
            $message = "Invalid user ID for deletion.";
            $message_type = 'error';
        } elseif ($user_id_to_delete == $_SESSION['user_id']) {
            $message = "Cannot delete your own account.";
            $message_type = 'error';
        } else {
            $conn->begin_transaction();
            try {
                // Optional: Check if user has any linked documents or other data before deleting
                // For now, we'll just delete. If there are foreign key constraints, MySQL will prevent deletion.
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id_to_delete);
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting user: " . $stmt->error);
                }
                $stmt->close();
                $conn->commit();
                $message = "User deleted successfully.";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error deleting user: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    // Redirect to clear POST data and show message
    header("Location: users.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}
// Fetch all users for display
$all_users = [];
$sql = "SELECT u.id, u.email, u.role_id, r.name as role_name, u.created_at
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.email ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_users[] = $row;
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
    <title>DMS - Users</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts for Inter (English) and Battambang (Khmer) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* Custom styles for fonts */
        body {
        font-family: <?php echo (isset($_SESSION['lang']) && $_SESSION['lang'] === 'kh') ? "'Battambang', sans-serif" : "'Regular', sans-serif"; ?>;
        background-color: #e0f2fe; /* Tailwind's sky-100 */
        }
        /* Style for the action dropdown */
        .action-dropdown {
            position: absolute;
            right: 0;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
            min-width: 120px;
        }
        .action-dropdown a, .action-dropdown button {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            text-align: left;
            font-size: 0.875rem;
            color: #4b5563; /* gray-700 */
            transition: background-color 150ms ease-in-out;
        }
        .action-dropdown a:hover, .action-dropdown button:hover {
            background-color: #e5e7eb; /* gray-100 */
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 90%;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-modal-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280; /* gray-500 */
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['users']; ?></h1>
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
            <div class="bg-white rounded-xl shadow-md p-6">
                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="flex justify-end mb-4">
                    <button id="add-user-btn" class="px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 transition duration-150 ease-in-out flex items-center">
                        <i class="fas fa-plus mr-2"></i> <?php echo $lang['add_user']; ?>
                    </button>
                </div>

                <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['action']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['email']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['role']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['created_at']; ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($all_users)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <button class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-150 ease-in-out edit-user-btn"
                                                    data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                    data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-user-role-id="<?php echo htmlspecialchars($user['role_id'] ?? ''); ?>">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline-block">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['role_name'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('addUserModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4"><?php echo $lang['add_user']; ?></h3>
            <form action="users.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_user">
                <div>
                    <label for="add_email" class="block text-sm font-medium text-gray-700"><?php echo $lang['email']; ?> <span class="text-red-500">*</span></label>
                    <input type="email" id="add_email" name="email" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="add_password" class="block text-sm font-medium text-gray-700"><?php echo $lang['password']; ?> <span class="text-red-500">*</span></label>
                    <input type="password" id="add_password" name="password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="add_confirm_password" class="block text-sm font-medium text-gray-700"><?php echo $lang['confirm_password']; ?> <span class="text-red-500">*</span></label>
                    <input type="password" id="add_confirm_password" name="confirm_password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="add_role_id" class="block text-sm font-medium text-gray-700"><?php echo $lang['role']; ?> <span class="text-red-500">*</span></label>
                    <select id="add_role_id" name="role_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Select Role</option>
                        <?php foreach ($all_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"><?php echo $lang['add_user']; ?></button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('addUserModal').classList.add('hidden');"><?php echo $lang['cancel']; ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('editUserModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit User</h3>
            <form action="users.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="edit_email" name="email" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_new_password" class="block text-sm font-medium text-gray-700">New Password (optional)</label>
                    <input type="password" id="edit_new_password" name="new_password"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_confirm_new_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input type="password" id="edit_confirm_new_password" name="confirm_new_password"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_role_id" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                    <select id="edit_role_id" name="role_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Select Role</option>
                        <?php foreach ($all_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save Changes</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('editUserModal').classList.add('hidden');">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logout functionality
            document.getElementById('logout-link').addEventListener('click', function(event) {
                event.preventDefault();
                window.location.href = window.location.origin + '/logout.php';
            });

            // Add User Modal Logic
            const addUserBtn = document.getElementById('add-user-btn');
            const addUserModal = document.getElementById('addUserModal');

            if (addUserBtn) {
                addUserBtn.addEventListener('click', function() {
                    addUserModal.classList.remove('hidden');
                });
            }

            // Edit User Modal Logic
            const editUserModal = document.getElementById('editUserModal');
            const editUserIdInput = document.getElementById('edit_user_id');
            const editUserEmailInput = document.getElementById('edit_email');
            const editUserRoleIdSelect = document.getElementById('edit_role_id');
            const editNewPasswordInput = document.getElementById('edit_new_password');
            const editConfirmNewPasswordInput = document.getElementById('edit_confirm_new_password');
            const editUserButtons = document.querySelectorAll('.edit-user-btn');

            editUserButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const userEmail = this.dataset.userEmail;
                    const userRoleId = this.dataset.userRoleId; // This will be the ID

                    editUserIdInput.value = userId;
                    editUserEmailInput.value = userEmail;
                    editUserRoleIdSelect.value = userRoleId; // Set the selected option by value
                    editNewPasswordInput.value = ''; // Clear password fields
                    editConfirmNewPasswordInput.value = '';

                    editUserModal.classList.remove('hidden');
                });
            });

            // Close modals if clicking outside or via close button
            const closeModalButtons = document.querySelectorAll('.close-modal-btn');
            closeModalButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.modal-overlay').classList.add('hidden');
                });
            });

            window.addEventListener('click', function(event) {
                if (event.target == addUserModal) {
                    addUserModal.classList.add('hidden');
                }
                if (event.target == editUserModal) {
                    editUserModal.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>

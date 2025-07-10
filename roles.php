<?php
// Include the central authentication and permission check file
include 'auth_check.php'; // This handles session_start() and redirects if not logged in
    if (isset($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
// Check if the user has permission to manage roles (e.g., Super Admin)
if ($user_role_name !== 'Super Admin') {
    header("Location: dashboard.php?message=Access Denied: You do not have permission to manage roles.&type=error");
    exit();
}

// --- Database Connection (from auth_check.php, already open) ---

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

// Fetch all available permissions, grouped by category (still needed if you re-introduce permission display on this page, but not for modals anymore)
$all_permissions = []; // This variable is no longer strictly needed in roles.php itself, but kept for consistency if needed elsewhere.
$permissions_result = $conn->query("SELECT id, name, description, category FROM permissions ORDER BY category, name ASC");
if ($permissions_result) {
    while ($row = $permissions_result->fetch_assoc()) {
        $all_permissions[$row['category']][] = $row;
    }
} else {
    error_log("Error fetching permissions: " . $conn->error);
}


// Handle Add/Edit/Delete Role (Add Role logic is now primarily in add_role_page.php, Edit logic in edit_role_page.php)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Only delete logic remains here
    $role_name = trim($_POST['role_name'] ?? ''); // Not used for delete, but kept for context if needed
    $description = trim($_POST['description'] ?? ''); // Not used for delete, but kept for context if needed
    $selected_permissions = $_POST['permissions'] ?? []; // Not used for delete, but kept for context if needed

    if (isset($_POST['delete_role'])) {
        $role_id = $_POST['role_id'] ?? null;
        if ($role_id) {
            $conn->begin_transaction(); // Start transaction

            try {
                // Check if any users are assigned to this role before deleting
                $check_users_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
                $check_users_stmt->bind_param("i", $role_id);
                $check_users_stmt->execute();
                $check_users_result = $check_users_stmt->get_result()->fetch_row()[0];
                $check_users_stmt->close();

                if ($check_users_result > 0) {
                    throw new Exception("Cannot delete role: " . $check_users_result . " user(s) are still assigned to this role. Please reassign them first.");
                }

                // Delete associated permissions first (due to CASCADE, this might not be strictly needed if FK is set up correctly, but explicit is safer)
                $delete_perm_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                if ($delete_perm_stmt === false) {
                    throw new Exception("Error preparing permission delete: " . $conn->error);
                }
                $delete_perm_stmt->bind_param("i", $role_id);
                if (!$delete_perm_stmt->execute()) {
                    throw new Exception("Error deleting associated permissions: " . $delete_perm_stmt->error);
                }
                $delete_perm_stmt->close();

                // Now delete the role
                $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                if ($stmt === false) {
                    throw new Exception("Error preparing role delete: " . $conn->error);
                }
                $stmt->bind_param("i", $role_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting role: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit(); // Commit transaction
                $message = "Role deleted successfully.";
                $message_type = 'success';

            } catch (Exception $e) {
                $conn->rollback(); // Rollback transaction on error
                $message = "Error deleting role: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Invalid role ID for deletion.";
            $message_type = 'error';
        }
    }
    // Redirect to clear POST data and show message
    header("Location: roles.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}


// Fetch all roles
$all_roles = [];
$sql = "SELECT id, name, description FROM roles ORDER BY name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Fetch permissions for each role (still needed for display, but not for passing to edit modal directly)
        $role_permissions = [];
        $perm_stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        if ($perm_stmt) {
            $perm_stmt->bind_param("i", $row['id']);
            $perm_stmt->execute();
            $perm_result = $perm_stmt->get_result();
            while ($perm_row = $perm_result->fetch_assoc()) {
                $role_permissions[] = $perm_row['permission_id'];
            }
            $perm_stmt->close();
        }
        $row['permissions'] = $role_permissions; // This data is now only for potential display on roles.php, not passed to edit modal.
        $all_roles[] = $row;
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
    <title>DMS - Roles</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: 'Battambang', sans-serif;
            background-color: #f9fafb; /* gray-50 */
            background-color: #f0f2f5;
        }
        /* Style for the action dropdown (not used on this page anymore for edit/add) */
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
        
        /* Modal styles are removed as modals are no longer used for add/edit roles */
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['roles']; ?></h1>
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
                    <a href="add_role_page.php" id="add-role-btn" class="px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 transition duration-150 ease-in-out flex items-center">
                        <i class="fas fa-plus mr-2"></i> <?php echo $lang['add_new_role']; ?>
                    </a>
                </div>

                <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['action']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['role_name']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['description']; ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($all_roles)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No roles found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_roles as $role): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <a href="edit_role_page.php?id=<?php echo htmlspecialchars($role['id']); ?>" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-150 ease-in-out">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <form action="roles.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this role? This will also affect users assigned to it.');" class="inline-block">
                                                <input type="hidden" name="delete_role" value="1">
                                                <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role['id']); ?>">
                                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($role['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($role['description']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
            // Removed JavaScript for addRoleModal and editRoleModal as they are now separate pages.
        });
    </script>
</body>
</html>

<?php
// Include the central authentication and permission check file
include 'auth_check.php'; // This handles session_start() and redirects if not logged in

// Check if the user has permission to manage roles (e.g., Super Admin)
if ($user_role_name !== 'Super Admin') {
    header("Location: dashboard.php?message=Access Denied: You do not have permission to edit roles.&type=error");
    exit();
}

// --- Database Connection (from auth_check.php, already open) ---

$message = '';
$message_type = '';
$role_data = null; // To store the role's current data
$current_role_permissions = []; // To store IDs of permissions already assigned to this role

// Get role ID from URL
$role_id = $_GET['id'] ?? null;

if (!$role_id || !is_numeric($role_id)) {
    $message = "Invalid role ID provided.";
    $message_type = 'error';
    // Redirect back to roles.php if no valid ID
    header("Location: roles.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

// Fetch all available permissions, grouped by category
$all_permissions = [];
$permissions_result = $conn->query("SELECT id, name, description, category FROM permissions ORDER BY category, name ASC");
if ($permissions_result) {
    while ($row = $permissions_result->fetch_assoc()) {
        $all_permissions[$row['category']][] = $row;
    }
} else {
    error_log("Error fetching all permissions: " . $conn->error);
}

// Handle form submission for updating a role
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_role'])) {
    $role_name = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selected_permissions = $_POST['permissions'] ?? []; // Array of permission IDs

    if (!empty($role_name)) {
        $conn->begin_transaction(); // Start transaction for atomicity

        try {
            // Check if role name already exists for another role
            $check_stmt = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
            $check_stmt->bind_param("si", $role_name, $role_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                throw new Exception("Role with this name already exists for another role.");
            }
            $check_stmt->close();

            // Update role details
            $stmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $role_name, $description, $role_id);
            if (!$stmt->execute()) {
                throw new Exception("Error updating role details: " . $stmt->error);
            }
            $stmt->close();

            // Delete existing permissions for this role
            $delete_perm_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            if ($delete_perm_stmt === false) {
                throw new Exception("Error preparing permission delete: " . $conn->error);
            }
            $delete_perm_stmt->bind_param("i", $role_id);
            if (!$delete_perm_stmt->execute()) {
                throw new Exception("Error deleting existing permissions: " . $delete_perm_stmt->error);
            }
            $delete_perm_stmt->close();

            // Insert new selected permissions
            if (!empty($selected_permissions)) {
                $permission_insert_sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $permission_stmt = $conn->prepare($permission_insert_sql);
                if ($permission_stmt === false) {
                    throw new Exception("Error preparing permission insert: " . $conn->error);
                }
                foreach ($selected_permissions as $perm_id) {
                    $permission_stmt->bind_param("ii", $role_id, $perm_id);
                    if (!$permission_stmt->execute()) {
                        throw new Exception("Error assigning permission " . $perm_id . ": " . $permission_stmt->error);
                    }
                }
                $permission_stmt->close();
            }

            $conn->commit(); // Commit transaction
            $message = "Role '" . htmlspecialchars($role_name) . "' updated successfully with permissions.";
            $message_type = 'success';
            // Redirect back to roles.php after successful update
            header("Location: roles.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
            exit();

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaction on error
            $message = "Error updating role: " . $e->getMessage();
            $message_type = 'error';
        }
    } else { // Added this else block
        $message = "Role Name is required.";
        $message_type = 'error';
    }
}

// Fetch role data and its current permissions for initial display
$stmt_role = $conn->prepare("SELECT id, name, description FROM roles WHERE id = ?");
$stmt_role->bind_param("i", $role_id);
$stmt_role->execute();
$result_role = $stmt_role->get_result();
if ($result_role->num_rows > 0) {
    $role_data = $result_role->fetch_assoc();
} else {
    $message = "Role not found for editing.";
    $message_type = 'error';
    $role_id = null; // Invalidate ID if role not found
}
$stmt_role->close();

if ($role_data) {
    $stmt_perms = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt_perms->bind_param("i", $role_id);
    $stmt_perms->execute();
    $result_perms = $stmt_perms->get_result();
    while ($row = $result_perms->fetch_assoc()) {
        $current_role_permissions[] = $row['permission_id'];
    }
    $stmt_perms->close();
}


// Close connection (from auth_check.php)
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Edit Role</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* Custom styles for fonts */
        body {
        font-family: <?php echo (isset($_SESSION['lang']) && $_SESSION['lang'] === 'kh') ? "'Battambang', sans-serif" : "'Inter', sans-serif"; ?>;
        background-color: #e0f2fe; /* Tailwind's sky-100 */
        }
        .permission-category-header {
            background-color: #f9fafb; /* Light gray for category headers */
            padding: 0.75rem 1rem;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            color: #374151; /* Darker gray text */
            border-left: 4px solid #4f46e5; /* Indigo border */
        }
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 0.5rem;
            padding: 0.5rem 0;
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
                <h1 class="text-2xl font-semibold text-gray-800">Edit Role</h1>
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
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Edit Role Details</h2>

                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($role_data): ?>
                <form action="edit_role_page.php?id=<?php echo htmlspecialchars($role_id); ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="update_role" value="1">
                    <div>
                        <label for="role_name" class="block text-sm font-medium text-gray-700">Role Name <span class="text-red-500">*</span></label>
                        <input type="text" id="role_name" name="role_name" required
                               value="<?php echo htmlspecialchars($role_data['name']); ?>"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo htmlspecialchars($role_data['description']); ?></textarea>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-800 mb-3">Assign Permissions</h4>
                        <div class="flex items-center mb-3">
                            <input type="checkbox" id="select-all-edit" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <label for="select-all-edit" class="ml-2 block text-sm text-gray-900 font-medium">Select All</label>
                        </div>

                        <?php foreach ($all_permissions as $category => $permissions): ?>
                            <div class="permission-category-header"><?php echo htmlspecialchars($category); ?></div>
                            <div class="permission-grid">
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="edit-perm-<?php echo htmlspecialchars($permission['id']); ?>"
                                               name="permissions[]" value="<?php echo htmlspecialchars($permission['id']); ?>"
                                               class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 edit-permission-checkbox"
                                               <?php echo in_array($permission['id'], $current_role_permissions) ? 'checked' : ''; ?>>
                                        <label for="edit-perm-<?php echo htmlspecialchars($permission['id']); ?>" class="ml-2 block text-sm text-gray-900">
                                            <?php echo htmlspecialchars($permission['description'] ?: $permission['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save Changes</button>
                        <button type="button" onclick="window.history.back();" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancel</button>
                    </div>
                </form>
                <?php else: ?>
                    <p class="text-center text-red-600">Could not load role for editing. <?php echo htmlspecialchars($message); ?></p>
                    <div class="flex justify-center mt-6">
                        <button type="button" onclick="window.location.href='roles.php';" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Back to Roles</button>
                    </div>
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

            // Select All for Edit Role Page
            const editPermissionCheckboxes = document.querySelectorAll('.edit-permission-checkbox');
            const selectAllEditCheckbox = document.getElementById('select-all-edit');

            if (selectAllEditCheckbox) {
                selectAllEditCheckbox.addEventListener('change', function() {
                    editPermissionCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            // Function to update Select All checkbox state
            function updateSelectAllCheckbox(checkboxes, selectAllCheckbox) {
                if (!checkboxes.length) {
                    selectAllCheckbox.checked = false;
                    return;
                }
                const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
                selectAllCheckbox.checked = allChecked;
            }

            // Listen for individual checkbox changes to update select-all
            editPermissionCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => updateSelectAllCheckbox(editPermissionCheckboxes, selectAllEditCheckbox));
            });

            // Initial update of select-all checkbox when page loads
            updateSelectAllCheckbox(editPermissionCheckboxes, selectAllEditCheckbox);
        });
    </script>
</body>
</html>

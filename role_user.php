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
    header("Location: dashboard.php?message=Access Denied: You do not have permission to manage user roles.&type=error");
    exit();
}

// --- Database Connection (from auth_check.php, already open) ---

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

// Fetch all roles for the dropdown
$all_roles = [];
$roles_result = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
if ($roles_result) {
    while ($row = $roles_result->fetch_assoc()) {
        $all_roles[] = $row;
    }
} else {
    error_log("Error fetching roles: " . $conn->error);
}

// Get selected role ID from GET or POST
$selected_role_id = $_REQUEST['role_id'] ?? null;
if ($selected_role_id === '') { // Handle case where "Select Role" option is chosen
    $selected_role_id = null;
}

$all_users_not_in_role = [];
$users_in_selected_role = [];

// Handle AJAX request for updating user role
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_user_role') {
    $user_id = $_POST['user_id'] ?? null;
    $new_role_id = $_POST['new_role_id'] ?? null; // Can be 'null' string or actual ID

    // Convert 'null' string to actual NULL for database
    if ($new_role_id === 'null') {
        $new_role_id = null;
    }

    if ($user_id && (is_numeric($new_role_id) || $new_role_id === null)) {
        $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
        // For NULLable columns, passing NULL directly with 'i' type in bind_param usually works.
        // If it causes issues, you might need to adjust based on your MySQLi version or use a different approach.
        if ($new_role_id === null) {
             // For setting a column to NULL, you can use 'i' and pass NULL, or prepare a different query.
             // This method is generally robust.
            $stmt->bind_param("ii", $new_role_id, $user_id);
        } else {
            $stmt->bind_param("ii", $new_role_id, $user_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'User role updated.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update user role: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters for user role update.']);
    }
    $conn->close();
    exit(); // Important: Stop script execution after AJAX response
}

// Fetch users based on the selected role for initial page load or after a role selection
if ($selected_role_id) {
    // Users in the selected role
    $stmt_selected = $conn->prepare("SELECT id, email FROM users WHERE role_id = ? ORDER BY email ASC");
    $stmt_selected->bind_param("i", $selected_role_id);
    $stmt_selected->execute();
    $result_selected = $stmt_selected->get_result();
    while ($row = $result_selected->fetch_assoc()) {
        $users_in_selected_role[] = $row;
    }
    $stmt_selected->close();

    // All users NOT in the selected role (including those with NULL role_id)
    // This query selects users whose role_id is NULL OR whose role_id is not the selected one.
    $stmt_not_selected = $conn->prepare("SELECT id, email FROM users WHERE role_id IS NULL OR role_id != ? ORDER BY email ASC");
    $stmt_not_selected->bind_param("i", $selected_role_id);
    $stmt_not_selected->execute();
    $result_not_selected = $stmt_not_selected->get_result();
    while ($row = $result_not_selected->fetch_assoc()) {
        $all_users_not_in_role[] = $row;
    }
    $stmt_not_selected->close();

} else {
    // If no role is selected in the dropdown, all users are initially shown in the "All Users" list
    $stmt_all_users = $conn->query("SELECT id, email FROM users ORDER BY email ASC");
    if ($stmt_all_users) {
        while ($row = $stmt_all_users->fetch_assoc()) {
            $all_users_not_in_role[] = $row;
        }
    }
    // users_in_selected_role remains empty as no specific role is chosen
}

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Role User Management</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: 'Battambang', sans-serif;
            background-color: #f0f2f5;
        }
        .user-list-container {
            min-height: 300px; /* Ensure containers have a visible height */
            border: 1px solid #e2e8f0; /* gray-200 */
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: #fff;
            overflow-y: auto; /* Enable scrolling if many users */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); /* Subtle shadow */
        }
        .user-item {
            background-color: #f0f4f8; /* Light blue-gray for items */
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem;
            cursor: grab;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #374151; /* Darker text */
        }
        .user-item:last-child {
            margin-bottom: 0; /* No margin for the last item */
        }
        .user-item:hover {
            background-color: #e2e8f0; /* gray-200 */
        }
        .user-item:active {
            cursor: grabbing;
            transform: scale(0.98);
        }
        .user-item.dragging {
            opacity: 0.5;
            border: 2px dashed #6366f1; /* Indigo dashed border */
        }
        .drop-target {
            border: 2px dashed #6366f1; /* Indigo border for drop target */
            background-color: #e0e7ff; /* Light indigo background */
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['role_user_management']; ?></h1>
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

                <div class="mb-6">
                    <label for="select-role" class="block text-sm font-medium text-gray-700 mb-2"><?php echo $lang['select_role']; ?></label>
                    <select id="select-role" name="role_id" onchange="window.location.href='role_user.php?role_id=' + this.value"
                            class="block w-full md:w-1/2 lg:w-1/3 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Select Role</option>
                        <?php foreach ($all_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['id']); ?>"
                                <?php echo ($selected_role_id == $role['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <p class="text-sm text-gray-600 mb-4"><?php echo $lang['note_drag_users']; ?></p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- All Users List -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3"><?php echo $lang['all_users']; ?></h3>
                        <div id="all-users-list" class="user-list-container" ondrop="drop(event, 'null')" ondragover="allowDrop(event)">
                            <?php if (empty($all_users_not_in_role)): ?>
                                <p class="text-gray-500 text-center py-4">No users found outside this role.</p>
                            <?php else: ?>
                                <?php foreach ($all_users_not_in_role as $user): ?>
                                    <div class="user-item" draggable="true"
                                         data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
                                         data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                         ondragstart="drag(event)">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Role Users List -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3"><?php echo $lang['role_users']; ?></h3>
                        <div id="role-users-list" class="user-list-container" ondrop="drop(event, '<?php echo htmlspecialchars($selected_role_id ?? 'null'); ?>')" ondragover="allowDrop(event)">
                            <?php if (empty($users_in_selected_role)): ?>
                                <p class="text-gray-500 text-center py-4"><?php echo $lang['no_users_in_role']; ?></p>
                            <?php else: ?>
                                <?php foreach ($users_in_selected_role as $user): ?>
                                    <div class="user-item" draggable="true"
                                         data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
                                         data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                         ondragstart="drag(event)">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let draggedItem = null;

        function allowDrop(event) {
            event.preventDefault(); // Allow dropping
            // Add visual feedback to the drop target
            event.target.closest('.user-list-container').classList.add('drop-target');
        }

        function drag(event) {
            draggedItem = event.target;
            event.dataTransfer.setData("text/plain", event.target.dataset.userId);
            event.dataTransfer.effectAllowed = "move";
            event.target.classList.add('dragging');
        }

        function drop(event, newRoleId) {
            event.preventDefault();
            const data = event.dataTransfer.getData("text/plain");
            const userId = data;
            const targetList = event.target.closest('.user-list-container');

            if (targetList && draggedItem) {
                // Remove visual feedback
                targetList.classList.remove('drop-target');
                draggedItem.classList.remove('dragging');

                // Check if the item is being dropped into the same list it originated from
                if (draggedItem.parentNode === targetList) {
                    return; // Do nothing if dropping into the same list
                }

                // Append the dragged item to the new list
                targetList.appendChild(draggedItem);

                // Send AJAX request to update the user's role in the database
                updateUserRole(userId, newRoleId);
            }
        }

        // Remove drop-target class when drag leaves the area
        document.querySelectorAll('.user-list-container').forEach(container => {
            container.addEventListener('dragleave', function(event) {
                event.target.closest('.user-list-container').classList.remove('drop-target');
            });
            container.addEventListener('dragend', function(event) {
                // This event fires on the draggable element after the drag operation has ended
                // Remove the dragging class from the dragged item
                if (draggedItem) {
                    draggedItem.classList.remove('dragging');
                }
                // Also remove drop-target from all containers in case drag ended outside a target
                document.querySelectorAll('.user-list-container').forEach(c => c.classList.remove('drop-target'));
            });
        });


        async function updateUserRole(userId, newRoleId) {
            const formData = new FormData();
            formData.append('action', 'update_user_role');
            formData.append('user_id', userId);
            formData.append('new_role_id', newRoleId); // 'null' string will be converted to NULL in PHP

            try {
                const response = await fetch('role_user.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    // Optionally, show a success message or re-fetch lists
                    console.log(result.message);
                    // For simplicity, we just moved the element visually.
                    // A full re-render or more sophisticated DOM manipulation might be needed
                    // for complex scenarios, but for simple drag-and-drop, this is often enough.
                    // However, if the page is reloaded due to role selection, this visual change
                    // will be reset, which is fine.
                } else {
                    console.error(result.message);
                    // Revert the visual change if update failed (optional, more complex)
                    alert('Error updating user role: ' + result.message);
                    // Consider reloading the page to reflect true state if error occurs
                    // window.location.reload();
                }
            } catch (error) {
                console.error('Network error:', error);
                alert('Network error during role update.');
                // window.location.reload();
            }
        }

        // Logout functionality
        document.getElementById('logout-link').addEventListener('click', function(event) {
            event.preventDefault();
            window.location.href = window.location.origin + '/logout.php';
        });
    </script>
</body>
</html>

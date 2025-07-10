<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Share Document</title>
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
    <?php
    // Start session and check if user is logged in
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.html"); // Redirect to login page if not logged in
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $user_email = $_SESSION['user_email'];

    // --- Database Connection (replace with your actual credentials) ---
    $servername = "localhost";
    $username = "root"; // Your MySQL username
    $password = "";     // Your MySQL password
    $dbname = "dms_db"; // The database name

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    $document_id = $_GET['document_id'] ?? null;
    $document_name = '';
    $document_description = '';
    $message = '';
    $message_type = '';

    // Fetch document details
    if ($document_id) {
        $stmt = $conn->prepare("SELECT name, description FROM documents WHERE id = ?");
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $doc_data = $result->fetch_assoc();
            $document_name = $doc_data['name'];
            $document_description = $doc_data['description'];
        } else {
            $message = "Document not found.";
            $message_type = 'error';
            $document_id = null; // Invalidate document_id if not found
        }
        $stmt->close();
    } else {
        $message = "No document ID provided.";
        $message_type = 'error';
    }

    // Handle form submissions for sharing
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $document_id) {
        // Add User Share
        if (isset($_POST['add_user_share'])) {
            $target_user_id = $_POST['target_user_id'] ?? null;
            $share_start_date = $_POST['share_start_date'] ?? null;
            $share_end_date = $_POST['share_end_date'] ?? null;
            $allow_download = isset($_POST['allow_download']) ? 1 : 0;

            if ($target_user_id) {
                // Check for existing share to prevent duplicates
                $check_stmt = $conn->prepare("SELECT id FROM document_shares WHERE document_id = ? AND user_id = ?");
                $check_stmt->bind_param("ii", $document_id, $target_user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $message = "Document already shared with this user.";
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO document_shares (document_id, user_id, start_date, end_date, allow_download) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissi", $document_id, $target_user_id, $share_start_date, $share_end_date, $allow_download);
                    if ($stmt->execute()) {
                        $message = "Document shared with user successfully.";
                        $message_type = 'success';
                    } else {
                        $message = "Error sharing document with user: " . $stmt->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            } else {
                $message = "Please select a user to share with.";
                $message_type = 'error';
            }
        }
        // Add Role Share
        elseif (isset($_POST['add_role_share'])) {
            $target_role_id = $_POST['target_role_id'] ?? null;
            $share_start_date = $_POST['share_start_date'] ?? null;
            $share_end_date = $_POST['share_end_date'] ?? null;
            $allow_download = isset($_POST['allow_download']) ? 1 : 0;

            if ($target_role_id) {
                // Check for existing share to prevent duplicates
                $check_stmt = $conn->prepare("SELECT id FROM document_shares WHERE document_id = ? AND role_id = ?");
                $check_stmt->bind_param("ii", $document_id, $target_role_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $message = "Document already shared with this role.";
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO document_shares (document_id, role_id, start_date, end_date, allow_download) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissi", $document_id, $target_role_id, $share_start_date, $share_end_date, $allow_download);
                    if ($stmt->execute()) {
                        $message = "Document shared with role successfully.";
                        $message_type = 'success';
                    } else {
                        $message = "Error sharing document with role: " . $stmt->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            } else {
                $message = "Please select a role to share with.";
                $message_type = 'error';
            }
        }
        // Delete Share
        elseif (isset($_POST['delete_share'])) {
            $share_id = $_POST['share_id'] ?? null;
            if ($share_id) {
                $stmt = $conn->prepare("DELETE FROM document_shares WHERE id = ?");
                $stmt->bind_param("i", $share_id);
                if ($stmt->execute()) {
                    $message = "Share deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting share: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $message = "Invalid share ID for deletion.";
                $message_type = 'error';
            }
        }
        // Update Share (Not explicitly requested in image, but good to have)
        elseif (isset($_POST['update_share'])) {
            $share_id = $_POST['share_id'] ?? null;
            $share_start_date = $_POST['share_start_date_edit'] ?? null;
            $share_end_date = $_POST['share_end_date_edit'] ?? null;
            $allow_download = isset($_POST['allow_download_edit']) ? 1 : 0;

            if ($share_id) {
                $stmt = $conn->prepare("UPDATE document_shares SET start_date = ?, end_date = ?, allow_download = ? WHERE id = ?");
                $stmt->bind_param("ssii", $share_start_date, $share_end_date, $allow_download, $share_id);
                if ($stmt->execute()) {
                    $message = "Share updated successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error updating share: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $message = "Invalid share ID for update.";
                $message_type = 'error';
            }
        }
    }

    // Fetch existing shares for the document
    $current_shares = [];
    if ($document_id) {
        $sql_shares = "
            SELECT 
                ds.id,
                ds.start_date,
                ds.end_date,
                ds.allow_download,
                u.email as user_email,
                r.name as role_name
            FROM document_shares ds
            LEFT JOIN users u ON ds.user_id = u.id
            LEFT JOIN roles r ON ds.role_id = r.id
            WHERE ds.document_id = ?
            ORDER BY ds.created_at DESC
        ";
        $stmt_shares = $conn->prepare($sql_shares);
        $stmt_shares->bind_param("i", $document_id);
        $stmt_shares->execute();
        $result_shares = $stmt_shares->get_result();
        if ($result_shares->num_rows > 0) {
            while ($row = $result_shares->fetch_assoc()) {
                $current_shares[] = $row;
            }
        }
        $stmt_shares->close();
    }

    // Fetch all users for the "Assign User" dropdown
    $all_users = [];
    $users_result = $conn->query("SELECT id, email FROM users ORDER BY email ASC");
    if ($users_result && $users_result->num_rows > 0) {
        while ($row = $users_result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }

    // Fetch all roles for the "Assign Role" dropdown
    $all_roles = [];
    $roles_result = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
    if ($roles_result && $roles_result->num_rows > 0) {
        while ($row = $roles_result->fetch_assoc()) {
            $all_roles[] = $row;
        }
    }

    $conn->close();
    ?>

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
                <h1 class="text-2xl font-semibold text-gray-800">Share Document</h1>
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
            <div class="bg-white rounded-xl shadow-md p-6 max-w-4xl mx-auto">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Document Sharing for:</h2>
                <?php if ($document_id): ?>
                    <div class="mb-6 flex flex-col md:flex-row md:space-x-8">
                        <p class="text-lg font-medium text-gray-700">Document Name: <span class="font-normal"><?php echo htmlspecialchars($document_name); ?></span></p>
                        <p class="text-lg font-medium text-gray-700">Description: <span class="font-normal"><?php echo htmlspecialchars($document_description); ?></span></p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex space-x-4 mb-6">
                        <button id="assign-user-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> Assign Users
                        </button>
                        <button id="assign-role-btn" class="px-4 py-2 bg-purple-600 text-white rounded-md shadow-sm hover:bg-purple-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-users-cog mr-2"></i> Assign Roles
                        </button>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Shares</h3>
                    <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Allow Download</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($current_shares)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No shares found for this document.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($current_shares as $share): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center space-x-2">
                                                <button class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-150 ease-in-out edit-share-btn"
                                                        data-share-id="<?php echo htmlspecialchars($share['id']); ?>"
                                                        data-start-date="<?php echo htmlspecialchars($share['start_date']); ?>"
                                                        data-end-date="<?php echo htmlspecialchars($share['end_date']); ?>"
                                                        data-allow-download="<?php echo htmlspecialchars($share['allow_download']); ?>">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </button>
                                                <form action="share_document.php?document_id=<?php echo htmlspecialchars($document_id); ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this share?');" class="inline-block">
                                                    <input type="hidden" name="delete_share" value="1">
                                                    <input type="hidden" name="share_id" value="<?php echo htmlspecialchars($share['id']); ?>">
                                                    <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out">
                                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $share['user_email'] ? 'User' : 'Role'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($share['user_email'] ?: $share['role_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($share['start_date'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($share['end_date'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $share['allow_download'] ? '<i class="fas fa-check-circle text-green-500"></i> Yes' : '<i class="fas fa-times-circle text-red-500"></i> No'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-red-600 text-center text-lg"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Assign User Modal -->
    <div id="assignUserModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('assignUserModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Assign User Permission</h3>
            <form action="share_document.php?document_id=<?php echo htmlspecialchars($document_id); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="add_user_share" value="1">
                <div>
                    <label for="target_user_id" class="block text-sm font-medium text-gray-700">Select User</label>
                    <select id="target_user_id" name="target_user_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Select a User --</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="share_start_date_user" class="block text-sm font-medium text-gray-700">Start Date (Optional)</label>
                    <input type="date" id="share_start_date_user" name="share_start_date"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="share_end_date_user" class="block text-sm font-medium text-gray-700">End Date (Optional)</label>
                    <input type="date" id="share_end_date_user" name="share_end_date"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-center">
                    <input id="allow_download_user" name="allow_download" type="checkbox" checked
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="allow_download_user" class="ml-2 block text-sm text-gray-900">Allow Download</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('assignUserModal').classList.add('hidden');">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Role Modal -->
    <div id="assignRoleModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('assignRoleModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Assign Role Permission</h3>
            <form action="share_document.php?document_id=<?php echo htmlspecialchars($document_id); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="add_role_share" value="1">
                <div>
                    <label for="target_role_id" class="block text-sm font-medium text-gray-700">Select Role</label>
                    <select id="target_role_id" name="target_role_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Select a Role --</option>
                        <?php foreach ($all_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="share_start_date_role" class="block text-sm font-medium text-gray-700">Start Date (Optional)</label>
                    <input type="date" id="share_start_date_role" name="share_start_date"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="share_end_date_role" class="block text-sm font-medium text-gray-700">End Date (Optional)</label>
                    <input type="date" id="share_end_date_role" name="share_end_date"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-center">
                    <input id="allow_download_role" name="allow_download" type="checkbox" checked
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="allow_download_role" class="ml-2 block text-sm text-gray-900">Allow Download</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('assignRoleModal').classList.add('hidden');">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Share Modal -->
    <div id="editShareModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('editShareModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Share Permission</h3>
            <form action="share_document.php?document_id=<?php echo htmlspecialchars($document_id); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="update_share" value="1">
                <input type="hidden" name="share_id" id="edit_share_id">
                <div>
                    <label for="share_start_date_edit" class="block text-sm font-medium text-gray-700">Start Date (Optional)</label>
                    <input type="date" id="share_start_date_edit" name="share_start_date_edit"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="share_end_date_edit" class="block text-sm font-medium text-gray-700">End Date (Optional)</label>
                    <input type="date" id="share_end_date_edit" name="share_end_date_edit"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-center">
                    <input id="allow_download_edit" name="allow_download_edit" type="checkbox"
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="allow_download_edit" class="ml-2 block text-sm text-gray-900">Allow Download</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save Changes</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('editShareModal').classList.add('hidden');">Cancel</button>
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

            // Get modal elements
            const assignUserModal = document.getElementById('assignUserModal');
            const assignRoleModal = document.getElementById('assignRoleModal');
            const editShareModal = document.getElementById('editShareModal');

            // Get buttons to open modals
            const assignUserBtn = document.getElementById('assign-user-btn');
            const assignRoleBtn = document.getElementById('assign-role-btn');
            const editShareBtns = document.querySelectorAll('.edit-share-btn');

            // Get edit modal inputs
            const editShareIdInput = document.getElementById('edit_share_id');
            const editShareStartDateInput = document.getElementById('share_start_date_edit');
            const editShareEndDateInput = document.getElementById('share_end_date_edit');
            const editAllowDownloadCheckbox = document.getElementById('allow_download_edit');

            // Event listeners to open modals
            if (assignUserBtn) {
                assignUserBtn.addEventListener('click', () => {
                    assignUserModal.classList.remove('hidden');
                });
            }
            if (assignRoleBtn) {
                assignRoleBtn.addEventListener('click', () => {
                    assignRoleModal.classList.remove('hidden');
                });
            }

            editShareBtns.forEach(button => {
                button.addEventListener('click', function() {
                    const shareId = this.dataset.shareId;
                    const startDate = this.dataset.startDate;
                    const endDate = this.dataset.endDate;
                    const allowDownload = this.dataset.allowDownload === '1'; // Convert to boolean

                    editShareIdInput.value = shareId;
                    editShareStartDateInput.value = startDate;
                    editShareEndDateInput.value = endDate;
                    editAllowDownloadCheckbox.checked = allowDownload;

                    editShareModal.classList.remove('hidden');
                });
            });

            // Close modals if clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == assignUserModal) {
                    assignUserModal.classList.add('hidden');
                }
                if (event.target == assignRoleModal) {
                    assignRoleModal.classList.add('hidden');
                }
                if (event.target == editShareModal) {
                    editShareModal.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>

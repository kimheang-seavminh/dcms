<?php
// Include the central authentication and permission check file
include 'auth_check.php'; // This handles session_start() and redirects if not logged in

// Check if the user has permission to view audit trail (e.g., Super Admin or specific permission)
// For demonstration, let's allow Super Admin. You might want a specific 'view_document_audit_trail' permission.
if ($user_role_name !== 'Super Admin') {
    header("Location: dashboard.php?message=Access Denied: You do not have permission to view the audit trail.&type=error");
    exit();
}

// --- Database Connection (from auth_check.php, already open) ---
    if (isset($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

// --- Pagination and Filtering Logic ---
$records_per_page = 10; // Default items per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$search_name = $_GET['search_name'] ?? '';
$filter_category_id = $_GET['filter_category_id'] ?? '';
$filter_user_id = $_GET['filter_user_id'] ?? '';

// Fetch all categories for filter dropdown
$all_categories = [];
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $all_categories[] = $row;
    }
} else {
    error_log("Error fetching categories: " . $conn->error);
}

// Fetch all users for filter dropdown
$all_users_for_filter = [];
$users_filter_result = $conn->query("SELECT id, email FROM users ORDER BY email ASC");
if ($users_filter_result) {
    while ($row = $users_filter_result->fetch_assoc()) {
        $all_users_for_filter[] = $row;
    }
} else {
    error_log("Error fetching users for filter: " . $conn->error);
}

// Base SQL query for audit trail
$sql = "SELECT id, action_date, document_name, category_name, operation, performed_by_user_email, to_whom_user_email, to_whom_role_name
        FROM document_audit_trail
        WHERE 1=1"; // Start with a true condition to easily append AND clauses

$count_sql = "SELECT COUNT(id) AS total_records FROM document_audit_trail WHERE 1=1";

$params = [];
$types = "";

// Apply filters
if (!empty($search_name)) {
    $sql .= " AND document_name LIKE ?";
    $count_sql .= " AND document_name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $types .= "s";
}
if (!empty($filter_category_id)) {
    $sql .= " AND category_id = ?";
    $count_sql .= " AND category_id = ?";
    $params[] = $filter_category_id;
    $types .= "i";
}
if (!empty($filter_user_id)) {
    $sql .= " AND performed_by_user_id = ?";
    $count_sql .= " AND performed_by_user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
}

$sql .= " ORDER BY action_date DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $records_per_page;
$params[] = $offset;

// Prepare and execute count query
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    error_log("Error preparing count query: " . $conn->error);
    $total_records = 0;
} else {
    if (!empty($params) && strlen($types) > 2) { // Exclude the last two 'ii' for limit/offset from count query
        $count_params = array_slice($params, 0, -2);
        $count_types = substr($types, 0, -2);
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $total_records = $count_result['total_records'];
    $count_stmt->close();
}

$total_pages = ceil($total_records / $records_per_page);

// Prepare and execute main query
$audit_trail_entries = [];
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Error preparing audit trail query: " . $conn->error);
} else {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $audit_trail_entries[] = $row;
    }
    $stmt->close();
}

// Close connection (from auth_check.php)
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - <?php echo $lang['documents_audit_trail']; ?></title>
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
        /* Basic table styling for readability */
        th, td {
            padding: 0.75rem 1.5rem; /* Increased padding */
            text-align: left;
            border-bottom: 1px solid #e5e7eb; /* Light gray border */
        }
        th {
            background-color: #f9fafb; /* Lighter header background */
            font-weight: 600;
            color: #4b5563; /* Darker text for headers */
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb; /* Zebra striping */
        }
        tbody tr:hover {
            background-color: #f3f4f6; /* Hover effect */
        }
        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            background-color: #e5e7eb; /* gray-200 */
            color: #4b5563; /* gray-700 */
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }
        .pagination-link:hover:not(.active) {
            background-color: #d1d5db; /* gray-300 */
        }
        .pagination-link.active {
            background-color: #4f46e5; /* indigo-600 */
            color: white;
        }
        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['documents_audit_trail']; ?></h1>
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

                <!-- Filters Section -->
                <form method="GET" action="documents_audit_trail.php" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="search_name" class="block text-1sm font-medium text-gray-700"><?php echo $lang['search by name']; ?></label>
                        <input type="text" id="search_name" name="search_name"
                               class="mt-1 block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Search by name">
                    </div>
                    <div>
                        <label for="filter_category_id" class="block text-1sm font-medium text-gray-700"><?php echo $lang['select_category']?></label>
                        <select id="filter_category_id" name="filter_category_id"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Select Category</option>
                            <?php foreach ($all_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>"
                                    <?php echo ($filter_category_id == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="filter_user_id" class="block text-1sm font-medium text-gray-700"><?php echo $lang['select user']; ?></label>
                        <select id="filter_user_id" name="filter_user_id"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Select User</option>
                            <?php foreach ($all_users_for_filter as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>"
                                    <?php echo ($filter_user_id == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-filter mr-2"></i> <?php echo $lang['apply_filters']; ?>
                        </button>
                    </div>
                </form>

                <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-base font-medium text-gray-900 tracking-widexs"><?php echo $lang['action_date']; ?></th>
                                <th scope="col" class="px-6 py-3 text-base"><?php echo $lang['name']; ?></th>
                                <th scope="col" class="px-6 py-3 text-base"><?php echo $lang['category_name']; ?></th>
                                <th scope="col" class="px-6 py-3 text-base"><?php echo $lang['operation']; ?></th>
                                <th scope="col" class="px-6 py-3 text-base">By Whom</th>
                                <th scope="col" class="px-6 py-3 text-base">To Whom User</th>
                                <th scope="col" class="px-6 py-3 text-base">To Whom Role</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($audit_trail_entries)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 whitespace-nowrap text-1sm text-gray-500 text-center"><?php echo $lang['no_audit_entries']; ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($audit_trail_entries as $entry): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($entry['action_date']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($entry['document_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($entry['category_name'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($entry['operation']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($entry['performed_by_user_email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($entry['to_whom_user_email'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($entry['to_whom_role_name'] ?: 'N/A'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-between mt-6">
                    <div class="text-sm text-gray-700">
                        Items per page: 10
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-700">
                            <?php
                            $start_record = $total_records > 0 ? ($offset + 1) : 0;
                            $end_record = min($offset + $records_per_page, $total_records);
                            echo "{$start_record} - {$end_record} of {$total_records}";
                            ?>
                        </span>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <a href="?page=<?php echo max(1, $current_page - 1); ?>&search_name=<?php echo urlencode($search_name); ?>&filter_category_id=<?php echo urlencode($filter_category_id); ?>&filter_user_id=<?php echo urlencode($filter_user_id); ?>"
                               class="pagination-link rounded-l-md <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search_name=<?php echo urlencode($search_name); ?>&filter_category_id=<?php echo urlencode($filter_category_id); ?>&filter_user_id=<?php echo urlencode($filter_user_id); ?>"
                                   class="pagination-link <?php echo ($i === $current_page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&search_name=<?php echo urlencode($search_name); ?>&filter_category_id=<?php echo urlencode($filter_category_id); ?>&filter_user_id=<?php echo urlencode($filter_user_id); ?>"
                               class="pagination-link rounded-r-md <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    </div>
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
        });
    </script>
</body>
</html>

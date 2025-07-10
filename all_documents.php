<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - All Documents</title>
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
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    <?php
    include 'auth_check.php';
    if (isset($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    // Check if a session has already been started to prevent "session_start(): A session had already been started" warning
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.html"); // Redirect to login page if not logged in
        exit();
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
        die("Database connection failed: " . $conn->connect_error);
    }

    // --- Role-based Access Control (Placeholder) ---
    $user_email = $_SESSION['user_email'];
    $is_admin = ($user_email === 'admin@example.com');

    // If you want to restrict 'All Documents' to admin only:
    // if (!$is_admin) {
    //     header("Location: dashboard.php");
    //     exit();
    // }

    // Handle messages from redirects (e.g., after delete)
    $message = $_GET['message'] ?? '';
    $message_type = $_GET['type'] ?? '';

    // Fetch ALL documents from the database
    $all_documents = [];
    $sql = "SELECT d.id, d.name, c.name as category_name, d.created_at, d.expired_date, u.email as created_by
            FROM documents d
            JOIN categories c ON d.category_id = c.id
            JOIN users u ON d.created_by_user_id = u.id
            ORDER BY d.created_at DESC";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_documents[] = $row;
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['all_documents'] ?></h1>
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
                <!-- Message Display -->
                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Top section: Search, Filter, and Buttons -->
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 space-y-4 md:space-y-0 md:space-x-4">
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search-name" class="block text-xl font-medium text-gray-700 mb-1"><?php echo $lang['search by name']; ?></label>
                            <input type="text" id="search-name" placeholder="Search by name or description"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="search-meta" class="block text-xl font-medium text-gray-700 mb-1"><?php echo $lang['search by meta tags']; ?></label>
                            <input type="text" id="search-meta" placeholder="Search by meta tags"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="select-category" class="block text-xl font-medium text-gray-700 mb-1"><?php echo $lang['select_category']; ?></label>
                            <select id="select-category"
                                    class="block w-full px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Select Category</option>
                                <option value="hr-policies">HR Policies</option>
                                <option value="story-book">Story Book</option>
                                <option value="cpu">CPU</option>
                                <option value="mouse">Mouse</option>
                                <!-- More categories from your database -->
                            </select>
                        </div>
                        <div>
                            <label for="created-date" class="block text-xl font-medium text-gray-700 mb-1"><?php echo $lang['created date']; ?></label>
                            <input type="date" id="created-date"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <a href="add_document.php" class="px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-plus mr-3"></i> <?php echo $lang['add Document'];?>
                        </a>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out rounded">
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['action']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['name']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['document_category'];?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['created date'];?><i class="fas fa-sort-down ml-1"></i></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['created by']; ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($all_documents)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No documents found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_documents as $document): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <input type="checkbox" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 relative">
                                        <button class="text-gray-500 hover:text-gray-700 focus:outline-none action-button" data-document-id="<?php echo $document['id']; ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div id="dropdown-<?php echo $document['id']; ?>" class="action-dropdown hidden mt-2 origin-top-right right-0 absolute">
                                            <a href="view_document.php?id=<?php echo htmlspecialchars($document['id']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View</a>
                                            <a href="edit_document.php?id=<?php echo htmlspecialchars($document['id']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                            <a href="share_document.php?document_id=<?php echo htmlspecialchars($document['id']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Share</a>
                                            <form action="delete_document.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                                                <input type="hidden" name="document_id" value="<?php echo htmlspecialchars($document['id']); ?>">
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($document['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($document['category_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($document['created_at']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($document['created_by']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex justify-end items-center mt-6 text-sm text-gray-600">
                    <span class="mr-2">Items per page:</span>
                    <select class="px-3 py-1 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                    <span class="ml-4 mr-4">1 - 7 of 7</span>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 border border-gray-300 rounded-md shadow-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="px-3 py-1 border border-gray-300 rounded-md shadow-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // JavaScript for handling action dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('.action-button');

            actionButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.stopPropagation(); // Prevent click from closing immediately
                    const documentId = this.dataset.documentId;
                    const dropdown = document.getElementById(`dropdown-${documentId}`);

                    // Close all other open dropdowns
                    document.querySelectorAll('.action-dropdown').forEach(openDropdown => {
                        if (openDropdown !== dropdown) {
                            openDropdown.classList.add('hidden');
                        }
                    });

                    // Toggle the clicked dropdown
                    dropdown.classList.toggle('hidden');
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                document.querySelectorAll('.action-dropdown').forEach(dropdown => {
                    if (!dropdown.contains(event.target) && !event.target.closest('.action-button')) {
                        dropdown.classList.add('hidden');
                    }
                });
            });

            // Logout functionality
            document.getElementById('logout-link').addEventListener('click', function(event) {
                event.preventDefault();
                window.location.href = window.location.origin + '/logout.php';
            });
        });
    </script>
</body>
</html>

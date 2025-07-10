<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - View Document</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: <?php echo (isset($_SESSION['lang']) && $_SESSION['lang'] === 'kh') ? "'Battambang', sans-serif" : "'Regular', sans-serif"; ?>;
            background-color: #f0f2f5;
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
    $user_email = $_SESSION['user_email']; // For displaying in header

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

    $document = null;
    $document_id = $_GET['id'] ?? null;
    $message = '';
    $message_type = '';

    if ($document_id) {
        $stmt = $conn->prepare("SELECT d.id, d.name, d.description, c.name as category_name, d.file_path, d.meta_tags, d.created_at, d.expired_date, u.email as created_by
                                FROM documents d
                                JOIN categories c ON d.category_id = c.id
                                JOIN users u ON d.created_by_user_id = u.id
                                WHERE d.id = ?");
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $document = $result->fetch_assoc();
        } else {
            $message = "Document not found.";
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "No document ID provided.";
        $message_type = 'error';
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
                <h1 class="text-2xl font-semibold text-gray-800">View Document</h1>
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
            <div class="bg-white rounded-xl shadow-md p-6 max-w-3xl mx-auto">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Document Details</h2>

                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($document): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">
                        <div>
                            <p class="font-medium">Name:</p>
                            <p class="mb-2"><?php echo htmlspecialchars($document['name']); ?></p>

                            <p class="font-medium">Category:</p>
                            <p class="mb-2"><?php echo htmlspecialchars($document['category_name']); ?></p>

                            <p class="font-medium">Description:</p>
                            <p class="mb-2"><?php echo htmlspecialchars($document['description'] ?: 'N/A'); ?></p>

                            <p class="font-medium">Meta Tags:</p>
                            <p class="mb-2">
                                <?php
                                $meta_tags = explode(',', $document['meta_tags']);
                                if (!empty($meta_tags) && !empty($meta_tags[0])) {
                                    foreach ($meta_tags as $tag) {
                                        echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 mr-2 mb-1">' . htmlspecialchars(trim($tag)) . '</span>';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <p class="font-medium">Created By:</p>
                            <p class="mb-2"><?php echo htmlspecialchars($document['created_by']); ?></p>

                            <p class="font-medium">Created Date:</p>
                            <p class="mb-2"><?php echo htmlspecialchars($document['created_at']); ?></p>

                            <p class="font-medium">Expired Date:</p>
                            <p class="mb-2">
                                <?php
                                $expired_date_display = htmlspecialchars($document['expired_date'] ?: 'N/A');
                                if (!empty($document['expired_date']) && strtotime($document['expired_date']) < time()) {
                                    echo '<span class="text-red-500 font-semibold">' . $expired_date_display . ' (Expired)</span>';
                                } else {
                                    echo $expired_date_display;
                                }
                                ?>
                            </p>

                            <p class="font-medium">Document File:</p>
                            <p class="mb-2">
                                <?php if (!empty($document['file_path']) && file_exists($document['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="text-indigo-600 hover:underline flex items-center">
                                        <i class="fas fa-file-download mr-2"></i> Download File
                                    </a>
                                <?php else: ?>
                                    File not available.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <a href="edit_document.php?id=<?php echo htmlspecialchars($document['id']); ?>"
                           class="px-5 py-2 bg-blue-600 text-white rounded-md shadow-sm hover:bg-blue-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-edit mr-2"></i> Edit Document
                        </a>
                        <form action="delete_document.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');" class="inline-block">
                            <input type="hidden" name="document_id" value="<?php echo htmlspecialchars($document['id']); ?>">
                            <button type="submit"
                                    class="px-5 py-2 bg-red-600 text-white rounded-md shadow-sm hover:bg-red-700 transition duration-150 ease-in-out flex items-center">
                                <i class="fas fa-trash-alt mr-2"></i> Delete Document
                            </button>
                        </form>
                        <button type="button" onclick="window.history.back();"
                                class="px-5 py-2 bg-gray-300 text-gray-800 rounded-md shadow-sm hover:bg-gray-400 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Logout functionality
        document.getElementById('logout-link').addEventListener('click', function(event) {
            event.preventDefault();
            window.location.href = window.location.origin + '/logout.php';
        });
    </script>
</body>
</html>

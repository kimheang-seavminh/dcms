<!DOCTYPE html>
<html lang="<?php session_start(); echo $_SESSION['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Add Document</title>
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
     include 'auth_check.php';
    if (isset($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    // Start session and check if user is logged in
    //session_start();
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

    $categories = [];
    $category_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($category_result && $category_result->num_rows > 0) {
        while ($row = $category_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    $message = '';
    $message_type = ''; // 'success' or 'error'

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $doc_name = $_POST['name'] ?? '';
        $doc_category_id = $_POST['category'] ?? '';
        $doc_description = $_POST['description'] ?? '';
        $doc_meta_tags = $_POST['meta_tags'] ?? ''; // This will be a comma-separated string

        // Basic validation
        if (empty($doc_name) || empty($doc_category_id) || !isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Please fill in all required fields and upload a document.';
            $message_type = 'error';
        } else {
            $target_dir = "uploads/"; // Directory where files will be stored
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
            }

            $file_name = basename($_FILES["document_file"]["name"]);
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_file_name = uniqid() . '.' . $file_extension; // Generate a unique name
            $target_file = $target_dir . $unique_file_name;
            $uploadOk = 1;

            // Check if file already exists (unlikely with uniqid)
            if (file_exists($target_file)) {
                $message = "Sorry, file already exists.";
                $message_type = 'error';
                $uploadOk = 0;
            }

            // Check file size (e.g., 5MB limit)
            if ($_FILES["document_file"]["size"] > 5000000) {
                $message = "Sorry, your file is too large (max 5MB).";
                $message_type = 'error';
                $uploadOk = 0;
            }

            // Allow certain file formats (customize as needed)
            $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png', 'xlsx', 'pptx'];
            if (!in_array($file_extension, $allowed_extensions)) {
                $message = "Sorry, only PDF, DOC, DOCX, TXT, JPG, PNG, XLSX, PPTX files are allowed.";
                $message_type = 'error';
                $uploadOk = 0;
            }

            // If all checks pass, try to upload file
            if ($uploadOk == 1) {
                if (move_uploaded_file($_FILES["document_file"]["tmp_name"], $target_file)) {
                    // File uploaded successfully, now save metadata to database
                    $stmt = $conn->prepare("INSERT INTO documents (name, description, category_id, file_path, meta_tags, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssissi", $doc_name, $doc_description, $doc_category_id, $target_file, $doc_meta_tags, $user_id);

                    if ($stmt->execute()) {
                        $message = "The document " . htmlspecialchars($file_name) . " has been uploaded and metadata saved.";
                        $message_type = 'success';
                    } else {
                        $message = "Error saving document metadata: " . $stmt->error;
                        $message_type = 'error';
                        // Delete uploaded file if database save fails
                        unlink($target_file);
                    }
                    $stmt->close();
                } else {
                    $message = "Sorry, there was an error uploading your file.";
                    $message_type = 'error';
                }
            }
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['add Document'] ?></h1>
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
                <h2 class="text-xl font-semibold text-gray-800 mb-6"><?php echo $lang['add_new_document'] ?></h2>

                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form action="add_document.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700"><?php echo $lang['name'] ?></label>
                        <input type="text" id="name" name="name" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700"><?php echo $lang['category'] ?></label>
                        <select id="category" name="category" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700"><?php echo $lang['description'] ?></label>
                        <textarea id="description" name="description" rows="3"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                    </div>

                    <div>
                        <label for="document_file" class="block text-sm font-medium text-gray-700"><?php echo $lang['document_upload']?></label>
                        <div class="mt-1 flex items-center">
                            <input type="file" id="document_file" name="document_file" required
                                   class="block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-indigo-50 file:text-indigo-700
                                          hover:file:bg-indigo-100">
                        </div>
                    </div>

                    <div>
                        <label for="meta_tags" class="block text-sm font-medium text-gray-700">Meta Tags</label>
                        <div class="mt-1 flex items-center space-x-2">
                            <input type="text" id="meta_tags_input" placeholder="Add a tag"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <button type="button" id="add-tag-btn"
                                    class="p-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-150 ease-in-out">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div id="meta_tags_display" class="mt-2 flex flex-wrap gap-2">
                            <!-- Tags will be added here by JavaScript -->
                        </div>
                        <input type="hidden" name="meta_tags" id="meta_tags_hidden">
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="submit"
                                class="px-5 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-save mr-2"></i> <?php echo $lang['save'] ?>
                        </button>
                        <button type="button" onclick="window.history.back();"
                                class="px-5 py-2 bg-red-600 text-white rounded-md shadow-sm hover:bg-red-700 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-times mr-2"></i> <?php echo $lang['cancel'] ?>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // JavaScript for handling meta tags input
        document.addEventListener('DOMContentLoaded', function() {
            const metaTagsInput = document.getElementById('meta_tags_input');
            const addTagBtn = document.getElementById('add-tag-btn');
            const metaTagsDisplay = document.getElementById('meta_tags_display');
            const metaTagsHidden = document.getElementById('meta_tags_hidden');

            let tags = [];

            function renderTags() {
                metaTagsDisplay.innerHTML = '';
                tags.forEach((tag, index) => {
                    const tagSpan = document.createElement('span');
                    tagSpan.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800';
                    tagSpan.innerHTML = `
                        ${tag}
                        <button type="button" class="flex-shrink-0 ml-1.5 h-3 w-3 rounded-full inline-flex items-center justify-center text-indigo-400 hover:bg-indigo-200 hover:text-indigo-500 focus:outline-none focus:bg-indigo-500 focus:text-white" data-index="${index}">
                            <span class="sr-only">Remove tag</span>
                            <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" />
                            </svg>
                        </button>
                    `;
                    metaTagsDisplay.appendChild(tagSpan);
                });
                metaTagsHidden.value = tags.join(','); // Update hidden input
            }

            function addTag() {
                const tagText = metaTagsInput.value.trim();
                if (tagText && !tags.includes(tagText)) {
                    tags.push(tagText);
                    metaTagsInput.value = '';
                    renderTags();
                }
            }

            addTagBtn.addEventListener('click', addTag);
            metaTagsInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission
                    addTag();
                }
            });

            metaTagsDisplay.addEventListener('click', function(e) {
                if (e.target.closest('button')) {
                    const index = parseInt(e.target.closest('button').dataset.index);
                    tags.splice(index, 1);
                    renderTags();
                }
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

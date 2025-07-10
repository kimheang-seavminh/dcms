<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Document Categories</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts for Inter (English) and Battambang (Khmer) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: 'Battambang', sans-serif;
            background-color: #f9fafb; /* gray-50 */
            background-color: #f0f2f5;
        }
        /* Style for the action dropdown (if needed for future actions) */
        .action-dropdown {
            position: absolute;
            right: 0;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
            min-width: 120px;
        }
        .action-dropdown button {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            text-align: left;
            font-size: 0.875rem;
            color: #4b5563; /* gray-700 */
            transition: background-color 150ms ease-in-out;
        }
        .action-dropdown button:hover {
            background-color: #e5e7eb; /* gray-100 */
        }
        .child-category-row {
            background-color: #f9fafb; /* Lighter background for child rows */
        }
        .child-category-row td:first-child {
            padding-left: 3rem; /* Indent child categories */
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

    $message = '';
    $message_type = ''; // 'success' or 'error'

    // Handle Add Category Form Submission (for both parent and child)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name'] ?? '');
        $parent_id = $_POST['parent_id'] ?? null; // Will be null for top-level categories
        // Convert empty string parent_id to null
        if ($parent_id === '') {
            $parent_id = null;
        }

        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
            $stmt->bind_param("si", $category_name, $parent_id);
            if ($stmt->execute()) {
                $message = "Category '" . htmlspecialchars($category_name) . "' added successfully.";
                $message_type = 'success';
            } else {
                if ($conn->errno == 1062) {
                    $message = "Category '" . htmlspecialchars($category_name) . "' already exists.";
                } else {
                    $message = "Error adding category: " . $stmt->error;
                }
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Category name cannot be empty.";
            $message_type = 'error';
        }
    }

    // Handle Edit Category Form Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'] ?? null;
        $new_category_name = trim($_POST['new_category_name'] ?? '');

        if ($category_id && !empty($new_category_name)) {
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $new_category_name, $category_id);
            if ($stmt->execute()) {
                $message = $lang['category_name'] . " '" . htmlspecialchars($new_category_name) . "' ". $lang['updated successfully'] . ".";
                $message_type = 'success';
            } else {
                if ($conn->errno == 1062) {
                    $message = "Category '" . htmlspecialchars($new_category_name) . "' already exists.";
                } else {
                    $message = "Error updating category: " . $stmt->error;
                }
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Invalid category ID or name for edit.";
            $message_type = 'error';
        }
    }

    // Handle Delete Category
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'] ?? null;

        if ($category_id) {
            // Check for documents linked to this category or its children
            $check_documents_stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE category_id = ?");
            $check_documents_stmt->bind_param("i", $category_id);
            $check_documents_stmt->execute();
            $document_count = $check_documents_stmt->get_result()->fetch_row()[0];
            $check_documents_stmt->close();

            // Check for child categories
            $check_children_stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
            $check_children_stmt->bind_param("i", $category_id);
            $check_children_stmt->execute();
            $children_count = $check_children_stmt->get_result()->fetch_row()[0];
            $check_children_stmt->close();


            if ($document_count > 0) {
                $message = "Cannot delete category as " . $document_count . " document(s) are linked to it. Please reassign documents first.";
                $message_type = 'error';
            } elseif ($children_count > 0) {
                $message = "Cannot delete category as it has " . $children_count . " child categories. Please delete child categories first.";
                $message_type = 'error';
            }
            else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $category_id);
                if ($stmt->execute()) {
                    $message = "Category deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting category: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        } else {
            $message = "Invalid category ID for deletion.";
            $message_type = 'error';
        }
    }


    // Fetch all categories and organize them into a hierarchy
    $all_categories = [];
    $category_result = $conn->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC");
    if ($category_result && $category_result->num_rows > 0) {
        while ($row = $category_result->fetch_assoc()) {
            $all_categories[$row['id']] = $row;
            $all_categories[$row['id']]['children'] = [];
        }
    }

    $parent_categories = [];
    foreach ($all_categories as $id => $category) {
        if ($category['parent_id'] === null) {
            $parent_categories[] = &$all_categories[$id];
        } else {
            if (isset($all_categories[$category['parent_id']])) {
                $all_categories[$category['parent_id']]['children'][] = &$all_categories[$id];
            }
        }
    }

    // Function to render categories recursively
    function renderCategories($categories_array, $is_child = false) {
        foreach ($categories_array as $category) {
            $row_class = $is_child ? 'child-category-row' : '';
            echo '<tr class="' . $row_class . '">';
            echo '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">';
            echo '<div class="flex items-center space-x-2">';
            if (!$is_child && !empty($category['children'])) {
                echo '<button class="text-gray-500 hover:text-gray-700 toggle-children-btn" data-category-id="' . htmlspecialchars($category['id']) . '">';
                echo '<i class="fas fa-chevron-right"></i>';
                echo '</button>';
            } else {
                echo '<span class="w-5"></span>'; // Spacer for alignment
            }
            echo '<button class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-150 ease-in-out edit-category-btn"';
            echo ' data-id="' . htmlspecialchars($category['id']) . '"';
            echo ' data-name="' . htmlspecialchars($category['name']) . '"';
            echo ' data-parent-id="' . htmlspecialchars($category['parent_id'] ?? '') . '">';
            echo '<i class="fas fa-edit mr-1"></i> Edit';
            echo '</button>';
            echo '<form action="document_categories.php" method="POST" onsubmit="return confirm(\'Are you sure you want to delete this category? This action cannot be undone and may affect linked documents/child categories.\');" class="inline-block">';
            echo '<input type="hidden" name="delete_category" value="1">';
            echo '<input type="hidden" name="category_id" value="' . htmlspecialchars($category['id']);
            echo '">';
            echo '<button type="submit" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out">';
            echo '<i class="fas fa-trash-alt mr-1"></i> Delete';
            echo '</button>';
            echo '</form>';
            // Only show 'Add Child Category' button for parent categories in the main list, not for children themselves
            if (!$is_child) {
                echo '<button class="px-3 py-1 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-150 ease-in-out add-child-category-btn" data-parent-id="' . htmlspecialchars($category['id']) . '">';
                echo '<i class="fas fa-plus mr-1"></i> Add Child';
                echo '</button>';
            }
            echo '</div>';
            echo '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($category['name']) . '</td>';
            echo '</tr>';

            if (!empty($category['children'])) {
                echo '<tr class="hidden child-rows-' . htmlspecialchars($category['id']) . '">';
                echo '<td colspan="2" class="p-0">';
                echo '<div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200 ml-8 my-2">';
                echo '<table class="min-w-full divide-y divide-gray-200">';
                echo '<thead class="bg-gray-50">';
                echo '<tr>';
                echo '<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>';
                echo '<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody class="bg-white divide-y divide-gray-200">';
                renderCategories($category['children'], true); // Recursively render children
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['document_categories']; ?></h1>
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
                <!-- Add Document Category Button -->
                <div class="flex justify-end mb-6">
                    <button id="add-category-btn" class="px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 transition duration-150 ease-in-out flex items-center">
                        <i class="fas fa-plus mr-2"></i> <?php echo $lang['add_new_category']; ?>
                    </button>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Categories Table -->
                <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200 mb-8">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['action']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['name']?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($parent_categories)): ?>
                                <tr>
                                    <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?php echo $lang['no_categories_found']; ?></td>
                                </tr>
                            <?php else: ?>
                                <?php renderCategories($parent_categories); ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Child Categories Section (No longer a separate table, integrated above) -->
            </div>
        </main>
    </div>

    <!-- Modals for Add/Edit Category -->
    <div id="add-category-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden flex items-center justify-center">
        <div class="relative p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="add-modal-title"><?php echo $lang['add_new_category']; ?></h3>
            <form action="document_categories.php" method="POST" class="space-y-4">
                <input type="hidden" name="add_category" value="1">
                <input type="hidden" name="parent_id" id="add_category_parent_id">
                <div>
                    <label for="new_category_name_add" class="block text-sm font-medium text-gray-700"><?php echo $lang['category_name']; ?></label>
                    <input type="text" id="new_category_name_add" name="category_name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('add-category-modal').classList.add('hidden');">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-category-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden flex items-center justify-center">
        <div class="relative p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4"><?php echo $lang['edit Category']; ?></h3>
            <form action="document_categories.php" method="POST" class="space-y-4">
                <input type="hidden" name="edit_category" value="1">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div>
                    <label for="new_category_name_edit" class="block text-sm font-medium text-gray-700"><?php echo $lang['category_name']; ?></label>
                    <input type="text" id="new_category_name_edit" name="new_category_name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"><?php echo $lang['save']; ?></button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('edit-category-modal').classList.add('hidden');"><?php echo $lang['cancel'];?></button>
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

            // Add Category Modal Logic
            const addCategoryBtn = document.getElementById('add-category-btn');
            const addCategoryModal = document.getElementById('add-category-modal');
            const addCategoryParentIdInput = document.getElementById('add_category_parent_id');
            const addModalTitle = document.getElementById('add-modal-title');

            addCategoryBtn.addEventListener('click', function() {
                addCategoryParentIdInput.value = ''; // Reset parent ID for top-level
                addModalTitle.textContent = <?php echo json_encode($lang['add_new_category']); ?>;
                addCategoryModal.classList.remove('hidden');
            });

            // Add Child Category Button Logic
            document.querySelectorAll('.add-child-category-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const parentId = this.dataset.parentId;
                    addCategoryParentIdInput.value = parentId;
                    addModalTitle.textContent = 'Add New Child Category';
                    addCategoryModal.classList.remove('hidden');
                });
            });


            // Edit Category Modal Logic
            const editCategoryModal = document.getElementById('edit-category-modal');
            const editCategoryNameInput = document.getElementById('new_category_name_edit');
            const editCategoryIdInput = document.getElementById('edit_category_id');
            const editButtons = document.querySelectorAll('.edit-category-btn');

            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const categoryId = this.dataset.id;
                    const categoryName = this.dataset.name;
                    editCategoryIdInput.value = categoryId;
                    editCategoryNameInput.value = categoryName;
                    editCategoryModal.classList.remove('hidden');
                });
            });

            // Toggle Child Categories Visibility
            document.querySelectorAll('.toggle-children-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const categoryId = this.dataset.categoryId;
                    const childRows = document.querySelectorAll(`.child-rows-${categoryId}`);
                    childRows.forEach(row => {
                        row.classList.toggle('hidden');
                    });
                    this.querySelector('i').classList.toggle('fa-chevron-right');
                    this.querySelector('i').classList.toggle('fa-chevron-down');
                });
            });

            // Close modals if clicking outside (optional, but good UX)
            window.addEventListener('click', function(event) {
                if (event.target == addCategoryModal) {
                    addCategoryModal.classList.add('hidden');
                }
                if (event.target == editCategoryModal) {
                    editCategoryModal.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - Reminders</title>
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
    <?php
    // Include the central authentication and permission check file
    include 'auth_check.php'; // This handles session_start() and redirects if not logged in
    if (isset($_POST['lang'])) {
            $_SESSION['lang'] = $_POST['lang'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    // --- Database Connection (from auth_check.php, already open) ---

    $message = $_GET['message'] ?? '';
    $message_type = $_GET['type'] ?? '';

    // Fetch all documents for the dropdown in Add/Edit Reminder modals
    $all_documents = [];
    $documents_result = $conn->query("SELECT id, name FROM documents ORDER BY name ASC");
    if ($documents_result) {
        while ($row = $documents_result->fetch_assoc()) {
            $all_documents[] = $row;
        }
    } else {
        error_log("Error fetching documents: " . $conn->error);
    }

    // Handle Add/Edit/Delete Reminder
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_reminder') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $reminder_date = $_POST['reminder_date'] ?? '';
            $document_id = $_POST['document_id'] ?? null;
            if ($document_id === '') { $document_id = null; } // Convert empty string to NULL

            if (empty($title) || empty($reminder_date)) {
                $message = "Title and Reminder Date are required to add a reminder.";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO reminders (user_id, document_id, reminder_date, title, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iissi", $_SESSION['user_id'], $document_id, $reminder_date, $title, $description);
                if (!$stmt->execute()) {
                    $message = "Error adding reminder: " . $stmt->error;
                    $message_type = 'error';
                } else {
                    $message = "Reminder '" . htmlspecialchars($title) . "' added successfully.";
                    $message_type = 'success';
                }
                $stmt->close();
            }
        } elseif ($action === 'edit_reminder') {
            $reminder_id_to_edit = $_POST['reminder_id'] ?? null;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $reminder_date = $_POST['reminder_date'] ?? '';
            $document_id = $_POST['document_id'] ?? null;
            if ($document_id === '') { $document_id = null; } // Convert empty string to NULL

            if (empty($reminder_id_to_edit) || empty($title) || empty($reminder_date)) {
                $message = "Reminder ID, Title, and Reminder Date are required for update.";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE reminders SET document_id = ?, reminder_date = ?, title = ?, description = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("issisi", $document_id, $reminder_date, $title, $description, $reminder_id_to_edit, $_SESSION['user_id']);
                if (!$stmt->execute()) {
                    $message = "Error updating reminder: " . $stmt->error;
                    $message_type = 'error';
                } else {
                    $message = "Reminder '" . htmlspecialchars($title) . "' updated successfully.";
                    $message_type = 'success';
                }
                $stmt->close();
            }
        } elseif ($action === 'delete_reminder') {
            $reminder_id_to_delete = $_POST['reminder_id'] ?? null;

            if (empty($reminder_id_to_delete)) {
                $message = "Invalid reminder ID for deletion.";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM reminders WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $reminder_id_to_delete, $_SESSION['user_id']);
                if (!$stmt->execute()) {
                    $message = "Error deleting reminder: " . $stmt->error;
                    $message_type = 'error';
                } else {
                    $message = "Reminder deleted successfully.";
                    $message_type = 'success';
                }
                $stmt->close();
            }
        }
        // Redirect to clear POST data and show message
        header("Location: reminder.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit();
    }

    // Fetch all reminders for the logged-in user
    $all_reminders = [];
    $sql = "SELECT r.id, r.title, r.description, r.reminder_date, d.name as document_name, r.document_id
            FROM reminders r
            LEFT JOIN documents d ON r.document_id = d.id
            WHERE r.user_id = ?
            ORDER BY r.reminder_date ASC, r.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_reminders[] = $row;
        }
    }
    $stmt->close();

    // Close connection
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['reminder']; ?></h1>
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
                    <button id="add-reminder-btn" class="px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 transition duration-150 ease-in-out flex items-center">
                        <i class="fas fa-plus mr-2"></i> <?php echo $lang['add_reminder']; ?>
                    </button>
                </div>

                <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['action']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['title']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['description']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['reminder_date']; ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['document_linked']; ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($all_reminders)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No reminders found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_reminders as $reminder): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <button class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-150 ease-in-out edit-reminder-btn"
                                                    data-reminder-id="<?php echo htmlspecialchars($reminder['id']); ?>"
                                                    data-title="<?php echo htmlspecialchars($reminder['title']); ?>"
                                                    data-description="<?php echo htmlspecialchars($reminder['description']); ?>"
                                                    data-reminder-date="<?php echo htmlspecialchars($reminder['reminder_date']); ?>"
                                                    data-document-id="<?php echo htmlspecialchars($reminder['document_id'] ?? ''); ?>">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <form action="reminder.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this reminder?');" class="inline-block">
                                                <input type="hidden" name="action" value="delete_reminder">
                                                <input type="hidden" name="reminder_id" value="<?php echo htmlspecialchars($reminder['id']); ?>">
                                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($reminder['title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($reminder['description'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($reminder['reminder_date']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($reminder['document_id']): ?>
                                            <a href="view_document.php?id=<?php echo htmlspecialchars($reminder['document_id']); ?>" class="text-indigo-600 hover:underline">
                                                <?php echo htmlspecialchars($reminder['document_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Reminder Modal -->
    <div id="addReminderModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('addReminderModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4"><?php echo $lang['add_reminder']; ?></h3>
            <form action="reminder.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_reminder">
                <div>
                    <label for="add_title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" id="add_title" name="title" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="add_description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="add_description" name="description" rows="3"
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                </div>
                <div>
                    <label for="add_reminder_date" class="block text-sm font-medium text-gray-700">Reminder Date <span class="text-red-500">*</span></label>
                    <input type="date" id="add_reminder_date" name="reminder_date" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="add_document_id" class="block text-sm font-medium text-gray-700">Link to Document (Optional)</label>
                    <select id="add_document_id" name="document_id"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Select Document --</option>
                        <?php foreach ($all_documents as $doc): ?>
                            <option value="<?php echo htmlspecialchars($doc['id']); ?>"><?php echo htmlspecialchars($doc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Add Reminder</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('addReminderModal').classList.add('hidden');">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Reminder Modal -->
    <div id="editReminderModal" class="modal-overlay hidden">
        <div class="modal-content">
            <span class="close-modal-btn" onclick="document.getElementById('editReminderModal').classList.add('hidden');">&times;</span>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Reminder</h3>
            <form action="reminder.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_reminder">
                <input type="hidden" name="reminder_id" id="edit_reminder_id">
                <div>
                    <label for="edit_title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_title" name="title" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="edit_description" name="description" rows="3"
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                </div>
                <div>
                    <label for="edit_reminder_date" class="block text-sm font-medium text-gray-700">Reminder Date <span class="text-red-500">*</span></label>
                    <input type="date" id="edit_reminder_date" name="reminder_date" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_document_id" class="block text-sm font-medium text-gray-700">Link to Document (Optional)</label>
                    <select id="edit_document_id" name="document_id"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Select Document --</option>
                        <?php foreach ($all_documents as $doc): ?>
                            <option value="<?php echo htmlspecialchars($doc['id']); ?>"><?php echo htmlspecialchars($doc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save Changes</button>
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="document.getElementById('editReminderModal').classList.add('hidden');">Cancel</button>
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

            // Add Reminder Modal Logic
            const addReminderBtn = document.getElementById('add-reminder-btn');
            const addReminderModal = document.getElementById('addReminderModal');

            if (addReminderBtn) {
                addReminderBtn.addEventListener('click', function() {
                    addReminderModal.classList.remove('hidden');
                });
            }

            // Edit Reminder Modal Logic
            const editReminderModal = document.getElementById('editReminderModal');
            const editReminderIdInput = document.getElementById('edit_reminder_id');
            const editTitleInput = document.getElementById('edit_title');
            const editDescriptionInput = document.getElementById('edit_description');
            const editReminderDateInput = document.getElementById('edit_reminder_date');
            const editDocumentIdSelect = document.getElementById('edit_document_id');
            const editReminderButtons = document.querySelectorAll('.edit-reminder-btn');

            editReminderButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reminderId = this.dataset.reminderId;
                    const title = this.dataset.title;
                    const description = this.dataset.description;
                    const reminderDate = this.dataset.reminderDate;
                    const documentId = this.dataset.documentId;

                    editReminderIdInput.value = reminderId;
                    editTitleInput.value = title;
                    editDescriptionInput.value = description;
                    editReminderDateInput.value = reminderDate;
                    editDocumentIdSelect.value = documentId;

                    editReminderModal.classList.remove('hidden');
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
                if (event.target == addReminderModal) {
                    addReminderModal.classList.add('hidden');
                }
                if (event.target == editReminderModal) {
                    editReminderModal.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>

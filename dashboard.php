<?php
// dashboard.php

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include the authentication and language check file
include 'auth_check.php';

// --- Database Connection (from auth_check.php, already open) ---

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

// Fetch dashboard data (example)
$total_documents = 0;
$total_users = 0;
$total_roles = 0;
$recent_documents = [];

// Example: Fetch total documents
$stmt_docs = $conn->prepare("SELECT COUNT(id) AS total FROM documents");
if ($stmt_docs) {
    $stmt_docs->execute();
    $result_docs = $stmt_docs->get_result()->fetch_assoc();
    $total_documents = $result_docs['total'];
    $stmt_docs->close();
}

// Example: Fetch total users
$stmt_users = $conn->prepare("SELECT COUNT(id) AS total FROM users");
if ($stmt_users) {
    $stmt_users->execute();
    $result_users = $stmt_users->get_result()->fetch_assoc();
    $total_users = $result_users['total'];
    $stmt_users->close();
}

// Example: Fetch total roles
$stmt_roles = $conn->prepare("SELECT COUNT(id) AS total FROM roles");
if ($stmt_roles) {
    $stmt_roles->execute();
    $result_roles = $stmt_roles->get_result()->fetch_assoc();
    $total_roles = $result_roles['total'];
    $stmt_roles->close();
}

// Example: Fetch recent documents (e.g., last 5)
$stmt_recent_docs = $conn->prepare("SELECT d.id, d.name, d.created_at, c.name as category_name
                                    FROM documents d
                                    LEFT JOIN categories c ON d.category_id = c.id
                                    ORDER BY d.created_at DESC LIMIT 5");
if ($stmt_recent_docs) {
    $stmt_recent_docs->execute();
    $result_recent_docs = $stmt_recent_docs->get_result();
    while ($row = $result_recent_docs->fetch_assoc()) {
        $recent_documents[] = $row;
    }
    $stmt_recent_docs->close();
}

// --- Data for Documents by Category Chart ---
$document_categories_data = [];
$stmt_category_counts = $conn->prepare("SELECT dc.name, COUNT(d.id) as doc_count
    FROM categories dc
    LEFT JOIN documents d ON dc.id = d.category_id
    GROUP BY dc.id, dc.name
    ORDER BY dc.name ASC");
if ($stmt_category_counts) {
    $stmt_category_counts->execute();
    $result_category_counts = $stmt_category_counts->get_result();
    while ($row = $result_category_counts->fetch_assoc()) {
        $document_categories_data[] = $row;
    }
    $stmt_category_counts->close();
}

// Prepare data for Chart.js
$chart_labels = [];
$chart_data = [];
$chart_colors = [];
$base_colors = ['#4F46E5', '#8B5CF6', '#EC4899', '#F59E0B', '#10B981', '#EF4444', '#3B82F6', '#6D28D9', '#F472B6', '#F87171', '#34D399', '#FBBF24', '#60A5FA', '#A78BFA', '#FCD34D', '#6EE7B7']; // Tailwind indigo, purple, pink, amber, emerald, red, blue, violet

foreach ($document_categories_data as $index => $data) {
    $chart_labels[] = $data['name'];
    $chart_data[] = $data['doc_count'];
    $chart_colors[] = $base_colors[$index % count($base_colors)]; // Cycle through base colors
}

// --- Data for Reminders Calendar ---
$reminders_data = [];
$stmt_reminders = $conn->prepare("
    SELECT r.id, r.title, r.description, r.reminder_date
    FROM reminders r
    WHERE r.user_id = ?
    ORDER BY r.reminder_date ASC
");
if ($stmt_reminders) {
    $stmt_reminders->bind_param("i", $_SESSION['user_id']);
    $stmt_reminders->execute();
    $result_reminders = $stmt_reminders->get_result();
    while ($row = $result_reminders->fetch_assoc()) {
        $reminders_data[] = $row;
    }
    $stmt_reminders->close();
}

// Close connection (from auth_check.php)
$conn->close();

// Count today's reminders
$today_reminder_count = 0;
$today = date('Y-m-d');
foreach ($reminders_data as $reminder) {
    if (substr($reminder['reminder_date'], 0, 10) === $today) {
        $today_reminder_count++;
    }
}

ob_end_flush(); // End output buffering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - <?php echo $lang['dashboard']; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <!-- Google Fonts for Inter (English) and Battambang (Khmer) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* Custom styles for fonts */
        body {
        font-family: 'Battambang', 'Inter', sans-serif;
        background-color: #e0f2fe; /* Tailwind's sky-100 */
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
        /* Calendar specific styles */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #e2e8f0; /* Border color for cells */
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .calendar-day-header {
            background-color: #f8fafc; /* Lightest gray */
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-weight: 600;
            color: #4b5563;
            font-size: 0.875rem;
        }
        .calendar-day-cell {
            background-color: #ffffff;
            min-height: 100px; /* Adjust height as needed */
            padding: 0.5rem;
            position: relative;
            font-size: 0.875rem;
            color: #4b5563;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .calendar-day-cell.other-month {
            background-color: #f1f5f9; /* Lighter background for days outside current month */
            color: #94a3b8; /* Lighter text color */
        }
        .calendar-date-number {
            font-weight: 700;
            font-size: 1.125rem;
            color: #1a202c; /* Darker for current month dates */
            margin-bottom: 0.25rem;
        }
        .calendar-day-cell.other-month .calendar-date-number {
            color: #94a3b8;
        }
        .reminder-dot {
            width: 8px;
            height: 8px;
            background-color: #4f46e5; /* Indigo dot */
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
            vertical-align: middle;
        }
        .reminder-count {
            background-color: #4f46e5; /* Indigo background */
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 9999px; /* Full rounded */
            margin-left: auto; /* Push to right */
        }
        .calendar-reminder-item {
            font-size: 0.75rem;
            color: #4f46e5; /* Indigo text for reminders */
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        .calendar-reminder-item:hover {
            text-decoration: underline;
            cursor: pointer;
        }
        .calendar-day-cell.has-reminders {
            border: 1px solid #6366f1; /* Highlight days with reminders */
            box-shadow: 0 0 0 1px #6366f1;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0 0.5rem;
        }
        .calendar-header button {
            padding: 0.5rem 1rem;
            background-color: #4f46e5;
            color: white;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out;
        }
        .calendar-header button:hover {
            background-color: #4338ca;
        }
        /* Add these to your <style> block */
        .calendar-reminder-item.color-0 { background-color: #fef3c7; border-left: 4px solid #f59e0b; }
        .calendar-reminder-item.color-1 { background-color: #d1fae5; border-left: 4px solid #10b981; }
        .calendar-reminder-item.color-2 { background-color: #e0e7ff; border-left: 4px solid #6366f1; }
        .calendar-reminder-item.color-3 { background-color: #fee2e2; border-left: 4px solid #ef4444; }
        .calendar-reminder-item.color-4 { background-color: #f3e8ff; border-left: 4px solid #a21caf; }
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
                <h1 class="text-2xl font-semibold text-gray-800"><?php echo $lang['dashboard']; ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <a href="#reminders-section" class="relative text-gray-500 hover:text-gray-700 focus:outline-none focus:text-gray-700">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if ($today_reminder_count > 0): ?>
                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                            <?php echo $today_reminder_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-blue-100 p-6 rounded-lg shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-blue-700 text-1sm font-medium"><?php echo $lang['total_documents']; ?></p>
                            <h2 class="text-3xl font-bold text-blue-900"><?php echo $total_documents; ?></h2>
                        </div>
                        <i class="fas fa-file-alt text-4xl text-blue-400"></i>
                    </div>
                    <div class="bg-green-100 p-6 rounded-lg shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-green-700 text-1sm font-medium"><?php echo $lang['total_users']; ?></p>
                            <h2 class="text-3xl font-bold text-green-900"><?php echo $total_users; ?></h2>
                        </div>
                        <i class="fas fa-users text-4xl text-green-400"></i>
                    </div>
                    <div class="bg-purple-100 p-6 rounded-lg shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-purple-700 text-1sm font-medium"><?php echo $lang['total_roles']; ?></p>
                            <h2 class="text-3xl font-bold text-purple-900"><?php echo $total_roles; ?></h2>
                        </div>
                        <i class="fas fa-user-tag text-4xl text-purple-400"></i>
                    </div>
                </div>

                <!-- Documents by Category Chart -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4"><?php echo $lang['documents_by_category'] ?? 'Documents by Category'; ?></h2>
                    <div class="flex flex-col lg:flex-row items-center justify-center space-y-4 lg:space-y-0 lg:space-x-8">
                        <div class="relative w-full max-w-sm lg:w-1/2">
                            <canvas id="documentsByCategoryChart" height="300"></canvas>
                        </div>
                        <div class="relative w-full max-w-sm lg:w-1/2 mt-8">
                            <canvas id="documentsByCategoryBarChart" height="300"></canvas>
                        </div>
                        <!-- <div class="w-full lg:w-1/2">
                            <h3 class="text-lg font-medium text-gray-700 mb-2"><?php echo $lang['document_categories'] ?? 'Document Categories'; ?></h3>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <?php foreach ($document_categories_data as $data): ?>
                                    <li class="flex items-center">
                                        <span class="inline-block w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $chart_colors[array_search($data['name'], $chart_labels)]; ?>;"></span>
                                        <?php echo htmlspecialchars($data['name']); ?> (<?php echo $data['doc_count']; ?>)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div> -->
                    </div>
                </div>

                <!-- Reminders Section -->
                <div id="reminders-section" class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4"><?php echo $lang['reminder'] ?? 'Reminders'; ?></h2>
                    <div class="calendar-header">
                        <h3 id="current-month-year" class="text-xl font-semibold text-gray-800"></h3>
                        <div class="space-x-2">
                            <button id="prev-month-btn"><?php echo $lang['previous'] ?? 'Previous'; ?></button>
                            <button id="today-btn"><?php echo $lang['today'] ?? 'Today'; ?></button>
                            <button id="next-month-btn"><?php echo $lang['next'] ?? 'Next'; ?></button>
                        </div>
                    </div>
                    <div class="calendar-grid">
                        <div class="calendar-day-header"><?php echo $lang['sunday'] ?? 'Sun'; ?></div>
                        <div class="calendar-day-header"><?php echo $lang['monday'] ?? 'Mon'; ?></div>
                        <div class="calendar-day-header"><?php echo $lang['tuesday'] ?? 'Tue'; ?></div>
                        <div class="calendar-day-header"><?php echo $lang['wednesday'] ?? 'Wed'; ?></div>
                        <div class="calendar-day-header"><?php echo $lang['thursday'] ?? 'Thu'; ?></div>
                        <div class="calendar-day-header"><?php echo $lang['friday'] ?? 'Fri'; ?></div>
                        <div class="calendar-day-header"><?php echo $lang['saturday'] ?? 'Sat'; ?></div>
                        <!-- Calendar cells will be generated by JavaScript -->
                    </div>
                </div>

                <!-- Recent Documents Section (moved below chart and reminders for better flow) -->
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4"><?php echo $lang['recent_documents'] ?? 'Recent Documents'; ?></h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['name'] ?? 'Name'; ?></th>
                                    <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['category_name'] ?? 'Category'; ?></th>
                                    <th scope="col" class="px-6 py-3 text-left text-1xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $lang['upload_date'] ?? 'Upload Date'; ?></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_documents)): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?php echo $lang['no_documents_found'] ?? 'No recent documents found.'; ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_documents as $doc): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($doc['category_name'] ?: 'N/A'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($doc['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-right">
                        <a href="all_documents.php" class="text-indigo-600 hover:text-indigo-800 font-medium"><?php echo $lang['view_all_documents']?> &rarr;</a>
                    </div>
                </div>

                <!-- Reminder Edit Modal -->
                <div id="reminderEditModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 hidden">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h2 class="text-lg font-semibold mb-4">Edit Reminder</h2>
                        <form id="reminderEditForm" method="post" action="reminder.php">
                            <input type="hidden" name="reminder_id" id="edit-reminder-id">
                            <div class="mb-3">
                                <label class="block text-sm font-medium mb-1">Title</label>
                                <input type="text" name="title" id="edit-reminder-title" class="w-full border rounded px-3 py-2" required>
                            </div>
                            <div class="mb-3">
                                <label class="block text-sm font-medium mb-1">Description</label>
                                <textarea name="description" id="edit-reminder-description" class="w-full border rounded px-3 py-2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="block text-sm font-medium mb-1">Reminder Date</label>
                                <input type="date" name="reminder_date" id="edit-reminder-date" class="w-full border rounded px-3 py-2" required>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button type="button" id="closeReminderEditModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Update</button>
                                <button type="button" id="confirmReminderBtn" class="px-4 py-2 bg-green-600 text-white rounded">Confirm</button>
                            </div>
                        </form>
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
                window.location.href = '/dcms/logout.php'; // Ensure absolute path
            });

            // Language Switcher Logic
            const langSelect = document.getElementById('lang-select');
            if (langSelect) {
                langSelect.addEventListener('change', function() {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('lang', this.value);
                    window.location.href = currentUrl.toString();
                });
            }

            // --- Chart.js for Documents by Category ---
            const ctx = document.getElementById('documentsByCategoryChart').getContext('2d');
            ctx.canvas.height = 300; // Set height in pixels
            const chartLabels = <?php echo json_encode($chart_labels); ?>;
            const chartData = <?php echo json_encode($chart_data); ?>;
            const chartColors = <?php echo json_encode($chart_colors); ?>;

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: chartColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                legend: {
                    display: true, // Show legend
                    position: 'right' // or 'bottom'
                },
                datalabels: {
                    color: '#222',
                    font: { weight: 'bold' },
                    formatter: (value, ctx) => value
                }
                    }
                }
            });

            // --- Chart.js for Documents by Category (Bar Chart) ---
            const ctxBar = document.getElementById('documentsByCategoryBarChart').getContext('2d');
            ctxBar.canvas.height = 200;
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [
                        {
                            label: 'Documents',
                            data: chartData,
                            backgroundColor: chartColors,
                            borderColor: chartColors,
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Trend',
                            type: 'line',
                            data: chartData, // You can use chartData or calculate your own trend/average
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.1)',
                            borderWidth: 3,
                            fill: false,
                            tension: 0.3,
                            pointBackgroundColor: '#6366f1',
                            pointBorderColor: '#222',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true },
                        datalabels: {
                            color: '#222',
                            font: { weight: 'bold' },
                            anchor: 'end',
                            align: 'top',
                            formatter: (value, ctx) => value
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });

            // --- Reminders Calendar Logic ---
            const calendarGrid = document.querySelector('.calendar-grid');
            const currentMonthYearDisplay = document.getElementById('current-month-year');
            const prevMonthBtn = document.getElementById('prev-month-btn');
            const todayBtn = document.getElementById('today-btn');
            const nextMonthBtn = document.getElementById('next-month-btn');

            let currentCalendarDate = new Date(); // Tracks the month currently displayed

            const reminders = <?php echo json_encode($reminders_data); ?>;

            // Helper function to parse reminder dates
            function parseReminderDate(dateString) {
                return new Date(dateString);
            }

            // Function to check if a reminder is active on a specific date
            function isReminderActiveOnDate(reminder, date) {
                const reminderDate = parseReminderDate(reminder.reminder_date);
                return (
                    date.getFullYear() === reminderDate.getFullYear() &&
                    date.getMonth() === reminderDate.getMonth() &&
                    date.getDate() === reminderDate.getDate()
                );
            }

            function generateCalendar() {
                calendarGrid.innerHTML = ''; // Clear existing cells

                // Add day headers
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                const localizedDayNames = [
                    '<?php echo $lang['sunday'] ?? 'Sun'; ?>',
                    '<?php echo $lang['monday'] ?? 'Mon'; ?>',
                    '<?php echo $lang['tuesday'] ?? 'Tue'; ?>',
                    '<?php echo $lang['wednesday'] ?? 'Wed'; ?>',
                    '<?php echo $lang['thursday'] ?? 'Thu'; ?>',
                    '<?php echo $lang['friday'] ?? 'Fri'; ?>',
                    '<?php echo $lang['saturday'] ?? 'Sat'; ?>'
                ];

                localizedDayNames.forEach(day => {
                    const header = document.createElement('div');
                    header.className = 'calendar-day-header';
                    header.textContent = day;
                    calendarGrid.appendChild(header);
                });

                const year = currentCalendarDate.getFullYear();
                const month = currentCalendarDate.getMonth(); // 0-indexed
                const firstDayOfMonth = new Date(year, month, 1);
                const lastDayOfMonth = new Date(year, month + 1, 0);
                const daysInMonth = lastDayOfMonth.getDate();

                // Calculate the day of the week for the first day of the month (0 = Sunday, 6 = Saturday)
                const startDayOfWeek = firstDayOfMonth.getDay();

                // Calculate days from previous month to fill the first row
                const daysFromPrevMonth = startDayOfWeek;
                const prevMonthLastDay = new Date(year, month, 0).getDate();

                // Display current month and year
                currentMonthYearDisplay.textContent = currentCalendarDate.toLocaleString(
                    '<?php echo ($_SESSION['lang'] === 'km') ? 'km-KH' : 'en-US'; ?>',
                    { month: 'long', year: 'numeric' }
                );

                // Fill in days from previous month
                for (let i = 0; i < daysFromPrevMonth; i++) {
                    const dayCell = document.createElement('div');
                    dayCell.className = 'calendar-day-cell other-month';
                    dayCell.innerHTML = `<span class="calendar-date-number">${prevMonthLastDay - daysFromPrevMonth + 1 + i}</span>`;
                    calendarGrid.appendChild(dayCell);
                }

                // Fill in days of the current month
                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(year, month, day);
                    const dayCell = document.createElement('div');
                    dayCell.className = 'calendar-day-cell';
                    dayCell.innerHTML = `<span class="calendar-date-number">${day}</span>`;

                    const remindersForThisDay = reminders.filter(reminder =>
                        isReminderActiveOnDate(reminder, date)
                    );

                    if (remindersForThisDay.length > 0) {
                        dayCell.classList.add('has-reminders');
                        // Add reminder count
                        const reminderCountSpan = document.createElement('span');
                        reminderCountSpan.className = 'reminder-count';
                        reminderCountSpan.textContent = remindersForThisDay.length;
                        dayCell.querySelector('.calendar-date-number').appendChild(reminderCountSpan);

                        // Add first few reminder subjects
                        remindersForThisDay.slice(0, 2).forEach((r, idx) => { // Show up to 2 reminders
                            const reminderItem = document.createElement('div');
                            reminderItem.className = 'calendar-reminder-item color-' + (idx % 5);
                            reminderItem.textContent = r.title;
                            reminderItem.title = r.title + (r.description ? '\n' + r.description : '');

                            // Add click event for this reminder only
                            reminderItem.style.cursor = 'pointer';
                            reminderItem.addEventListener('click', function(e) {
                                e.stopPropagation();
                                // Fill modal fields
                                document.getElementById('edit-reminder-id').value = r.id;
                                document.getElementById('edit-reminder-title').value = r.title;
                                document.getElementById('edit-reminder-description').value = r.description || '';
                                document.getElementById('edit-reminder-date').value = r.reminder_date.substring(0, 10);
                                // Show modal
                                document.getElementById('reminderEditModal').classList.remove('hidden');
                            });

                            dayCell.appendChild(reminderItem);
                        });
                        if (remindersForThisDay.length > 2) {
                            const moreReminders = document.createElement('div');
                            moreReminders.className = 'calendar-reminder-item text-gray-500';
                            moreReminders.textContent = `+${remindersForThisDay.length - 2} more`;
                            dayCell.appendChild(moreReminders);
                        }
                    }

                    // Make the cell clickable
                    dayCell.style.cursor = 'pointer';
                    dayCell.addEventListener('click', function() {
                        // Show reminders for this day
                        const remindersForThisDay = reminders.filter(reminder =>
                            isReminderActiveOnDate(reminder, date)
                        );
                        if (remindersForThisDay.length > 0) {
                            let msg = remindersForThisDay.map(r => `• ${r.title}${r.description ? ' - ' + r.description : ''}`).join('\n');
                            alert(`Reminders for ${date.toLocaleDateString()}\n\n${msg}`);
                        } else {
                            alert(`No reminders for ${date.toLocaleDateString()}`);
                        }
                    });

                    calendarGrid.appendChild(dayCell);
                }

                // Fill in days from next month to complete the last row
                const totalCells = daysFromPrevMonth + daysInMonth;
                const remainingCells = (7 - (totalCells % 7)) % 7; // Ensure a full last row if needed
                for (let i = 1; i <= remainingCells; i++) {
                    const dayCell = document.createElement('div');
                    dayCell.className = 'calendar-day-cell other-month';
                    dayCell.innerHTML = `<span class="calendar-date-number">${i}</span>`;
                    calendarGrid.appendChild(dayCell);
                }
            }

            // Navigation buttons for calendar
            prevMonthBtn.addEventListener('click', () => {
                currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
                generateCalendar();
            });

            todayBtn.addEventListener('click', () => {
                currentCalendarDate = new Date(); // Reset to today's month
                generateCalendar();
            });

            nextMonthBtn.addEventListener('click', () => {
                currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
                generateCalendar();
            });

            // Initial calendar generation
            generateCalendar();
            // Initial calendar generation

            // Modal close button
            document.getElementById('closeReminderEditModal').addEventListener('click', function() {
                document.getElementById('reminderEditModal').classList.add('hidden');
            });

            document.getElementById('confirmReminderBtn').addEventListener('click', function() {
    // Add a hidden input to indicate confirmation
    let form = document.getElementById('reminderEditForm');
    let input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'confirm_reminder';
    input.value = '1';
    form.appendChild(input);
    form.submit();
});
}); // End of DOMContentLoaded;
</script>
</body>
</html>

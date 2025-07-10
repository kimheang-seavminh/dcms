<?php
// index.php - Homepage

// Start output buffering
ob_start();

// Start the session. This MUST be the very first executable line.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Language selection logic for public pages (similar to auth_check but simpler)
if (isset($_GET['lang'])) {
    $requested_lang = $_GET['lang'];
    if (in_array($requested_lang, ['en', 'kh'])) { // Use 'kh' to match the filename 'kh.php'
        $_SESSION['lang'] = $requested_lang;
    }
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // Default to English
}

// Initialize $lang as an empty array to prevent "Undefined variable" notice
// in case the language file fails to load or is malformed.
$lang = [];

// Use __DIR__ for a more robust include path
$lang_file = __DIR__ . '/lang/lang/' . $_SESSION['lang'] . '.php'; // Corrected path
if (file_exists($lang_file)) {
    include_once $lang_file;
} 

// After including, ensure $lang is still an array. If the included file
// didn't define it, or defined it incorrectly, this will catch it.
if (!is_array($lang)) {
    $lang = []; // Re-initialize as empty array if it's not valid
    // Optionally, log an error here if you want to track malformed language files
    error_log("Warning: Language file " . $lang_file . " did not properly define \$lang array.");
}


// Check if user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

ob_end_flush(); // End output buffering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['dms_title'] ?? 'Document Management System'; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: <?php echo (isset($_SESSION['lang']) && $_SESSION['lang'] === 'kh') ? "'Battambang', sans-serif" : "'Inter', sans-serif"; ?>;
            background-color: #f0f2f5;
        }
        /* Custom styles for gradients and shadows */
        .btn-primary {
            background: linear-gradient(to right, #4F46E5, #6366F1); /* Indigo gradient */
            box-shadow: 0 4px 14px 0 rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #4338CA, #4F46E5);
            box-shadow: 0 6px 20px 0 rgba(99, 102, 241, 0.6);
            transform: translateY(-2px);
        }
        .card {
            background-color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- Header Section -->
    <header class="bg-white shadow-md py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="/dcms/index.php" class="text-2xl font-bold text-gray-800 rounded-lg p-2 hover:text-indigo-600 transition-colors">
                DMS
            </a>
            <nav>
                <ul class="flex space-x-6 items-center">
                    <li><a href="/dcms/index.php" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors rounded-lg p-2"><?php echo $lang['home'] ?? 'Home'; ?></a></li>
                    <li><a href="#features" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors rounded-lg p-2"><?php echo $lang['features'] ?? 'Features'; ?></a></li>
                    <li><a href="#contact" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors rounded-lg p-2"><?php echo $lang['contact'] ?? 'Contact'; ?></a></li>
                    <li><a href="/dcms/login.php" class="btn-primary text-white px-6 py-2 rounded-full font-semibold shadow-lg hover:shadow-xl transition-all duration-300"><?php echo $lang['login'] ?? 'Login'; ?></a></li>
                    <!-- Language Switcher -->
                    <li class="relative">
                        <button id="language-switcher-btn" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors rounded-lg p-2">
                            <?php echo ($_SESSION['lang'] === 'en') ? 'English' : 'ខ្មែរ'; ?> <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </button>
                        <div id="language-dropdown" class="absolute right-0 mt-2 w-32 bg-white rounded-md shadow-lg hidden z-20">
                            <a href="?lang=en" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">English</a>
                            <a href="?lang=kh" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">ខ្មែរ</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white py-20 flex-grow flex items-center justify-center">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-3xl md:text-4xl font-extrabold leading-tight mb-6 animate-fade-in-up">
                <?php echo $lang['hero_heading_part1'] ?? 'Management'; ?>
            </h1>
            <p class="text-xl md:text-2xl mb-10 opacity-90 animate-fade-in-up delay-200">
                <?php echo $lang['hero_subheading'] ?? 'Securely store, organize, and share your important documents with ease.'; ?>
            </p>
            <div class="space-x-4 animate-fade-in-up delay-400">
                <a href="/dcms/login.php" class="btn-primary text-white px-8 py-4 rounded-full text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300">
                    <?php echo $lang['get_started'] ?? 'Get Started'; ?>
                </a>
                <a href="#features" class="bg-white text-indigo-600 px-8 py-4 rounded-full text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300">
                    <?php echo $lang['learn_more'] ?? 'Learn More'; ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center text-gray-800 mb-12"><?php echo $lang['key_features'] ?? 'Key Features'; ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature Card 1 -->
                <div class="card p-8 rounded-xl text-center hover:scale-105 transition-transform duration-300">
                    <div class="text-indigo-600 text-5xl mb-6">
                        <!-- SVG Icon for Security -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.001 12.001 0 002.92 11.618c-.504 2.29.307 4.717 2.22 6.834L12 22l6.86-7.548c1.913-2.117 2.724-4.544 2.22-6.834a12.001 12.001 0 00-1.382-5.594z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4"><?php echo $lang['feature_security_heading'] ?? 'Robust Security'; ?></h3>
                    <p class="text-gray-600"><?php echo $lang['feature_security_desc'] ?? 'Your documents are protected with advanced security measures and access controls.'; ?></p>
                </div>

                <!-- Feature Card 2 -->
                <div class="card p-8 rounded-xl text-center hover:scale-105 transition-transform duration-300">
                    <div class="text-indigo-600 text-5xl mb-6">
                        <!-- SVG Icon for Organization -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4"><?php echo $lang['feature_organization_heading'] ?? 'Seamless Organization'; ?></h3>
                    <p class="text-gray-600"><?php echo $lang['feature_organization_desc'] ?? 'Categorize and tag your documents for quick retrieval and better management.'; ?></p>
                </div>

                <!-- Feature Card 3 -->
                <div class="card p-8 rounded-xl text-center hover:scale-105 transition-transform duration-300">
                    <div class="text-indigo-600 text-5xl mb-6">
                        <!-- SVG Icon for Collaboration -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h2a2 2 0 002-2V7a2 2 0 00-2-2h-2V3a1 1 0 00-1-1H8a1 1 0 00-1 1v2H5a2 2 0 00-2 2v11a2 2 0 002 2h2m0 0l-1 1h8l-1-1m-1-9H9m0 0h6m-6 0v6m6-6v6m-6-6H9m0 0h6m-6 0v6m6-6v6" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4"><?php echo $lang['feature_collaboration_heading'] ?? 'Easy Collaboration'; ?></h3>
                    <p class="text-gray-600"><?php echo $lang['feature_collaboration_desc'] ?? 'Share documents with team members and track changes effortlessly.'; ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="bg-indigo-700 text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-4xl font-bold mb-6"><?php echo $lang['cta_heading'] ?? 'Ready to Streamline Your Document Workflow?'; ?></h2>
            <p class="text-xl mb-10 opacity-90"><?php echo $lang['cta_subheading'] ?? 'Join thousands of users who trust our DMS for their document needs.'; ?></p>
            <a href="/dcms/register.php" class="btn-primary text-white px-8 py-4 rounded-full text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300">
                <?php echo $lang['sign_up_now'] ?? 'Sign Up Now'; ?>
            </a>
        </div>
    </section>

    <!-- Footer Section -->
    <footer id="contact" class="bg-gray-800 text-gray-300 py-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2025 <?php echo $lang['dms_title'] ?? 'DMS'; ?>. <?php echo $lang['all_rights_reserved'] ?? 'All Rights Reserved'; ?></p>
            <p class="mt-2"><?php echo $lang['contact_us'] ?? 'Contact Us at'; ?> <a href="mailto:<?php echo $lang['email_address'] ?? 'cheasopheak@gmail.com'; ?>" class="text-indigo-400 hover:underline"><?php echo $lang['email_address'] ?? 'cheasopheak@gmail.com'; ?></a></p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const langSwitcherBtn = document.getElementById('language-switcher-btn');
            const langDropdown = document.getElementById('language-dropdown');

            if (langSwitcherBtn && langDropdown) {
                langSwitcherBtn.addEventListener('click', function() {
                    langDropdown.classList.toggle('hidden');
                });

                // Close dropdown if clicked outside
                window.addEventListener('click', function(event) {
                    if (!langSwitcherBtn.contains(event.target) && !langDropdown.contains(event.target)) {
                        langDropdown.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html>

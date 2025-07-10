<?php
// delete_document.php - PHP Backend for Deleting a Document

session_start();
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

$document_id = $_POST['document_id'] ?? null;

if ($document_id) {
    // First, get the file path to delete the actual file
    $stmt_get_path = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt_get_path->bind_param("i", $document_id);
    $stmt_get_path->execute();
    $result_path = $stmt_get_path->get_result();

    if ($result_path->num_rows > 0) {
        $row = $result_path->fetch_assoc();
        $file_to_delete = $row['file_path'];

        // Then, delete the document record from the database
        $stmt_delete_db = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt_delete_db->bind_param("i", $document_id);

        if ($stmt_delete_db->execute()) {
            // If database record deleted, try to delete the file
            if (file_exists($file_to_delete) && is_file($file_to_delete)) {
                if (unlink($file_to_delete)) {
                    // File deleted successfully
                    header("Location: all_documents.php?message=Document deleted successfully.&type=success");
                    exit();
                } else {
                    // File deletion failed (but DB record is gone)
                    header("Location: all_documents.php?message=Document record deleted, but file could not be deleted from server.&type=error");
                    exit();
                }
            } else {
                // File not found on server (but DB record is gone)
                header("Location: all_documents.php?message=Document record deleted, file not found on server.&type=success");
                exit();
            }
        } else {
            // Database deletion failed
            header("Location: all_documents.php?message=Error deleting document from database: " . $stmt_delete_db->error . "&type=error");
            exit();
        }
        $stmt_delete_db->close();
    } else {
        // Document not found in DB
        header("Location: all_documents.php?message=Document not found.&type=error");
        exit();
    }
    $stmt_get_path->close();
} else {
    header("Location: all_documents.php?message=No document ID provided for deletion.&type=error");
    exit();
}

$conn->close();
?>

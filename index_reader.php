<?php
session_start();

unset($_SESSION['original_file']); // Reset file tracking

$ebookFolder = 'ebooks/';

// Handle File Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["epub_file"])) {
    $targetFile = $ebookFolder . basename($_FILES["epub_file"]["name"]);
    
    // Ensure it's an EPUB file
    if (pathinfo($targetFile, PATHINFO_EXTENSION) !== "epub") {
        echo "<p style='color:red;'>Only EPUB files are allowed!</p>";
    } elseif (move_uploaded_file($_FILES["epub_file"]["tmp_name"], $targetFile)) {
        echo "<p style='color:green;'>File uploaded successfully!</p>";
    } else {
        echo "<p style='color:red;'>File upload failed!</p>";
    }
}

// Handle File Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_file"])) {
    $fileToDelete = $ebookFolder . basename($_POST["delete_file"]);
    if (file_exists($fileToDelete)) {
        unlink($fileToDelete);
        echo "<p style='color:green;'>File deleted successfully!</p>";
    } else {
        echo "<p style='color:red;'>File not found!</p>";
    }
}

// Get available EPUB files
$files = glob($ebookFolder . '*.epub');


// Get bookmarks
$books = glob("$ebookFolder/*.epub");

echo "<h2>Select a bookmark</h2>";

foreach ($books as $book) {
    $bookTitle = basename($book);
    $savedPage = isset($_SESSION["bookmark_$bookTitle"]) ? $_SESSION["bookmark_$bookTitle"] : 0;

    echo "<p>
        <a href='reader.php?file=" . urlencode($bookTitle) . "&page=$savedPage'>
            📖 $bookTitle (Last Page: $savedPage)
        </a>
    </p>";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPUB Reader</title>
</head>
<body>
    <h2>Upload an EPUB Book</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="file" name="epub_file" accept=".epub" required>
        <button type="submit">Upload EPUB</button>
    </form>

    <h2>Select an EPUB Book</h2>
    <form action="reader.php" method="GET">
        <select name="file">
            <?php foreach ($files as $file) { ?>
                <option value="<?php echo basename($file); ?>"><?php echo basename($file); ?></option>
            <?php } ?>
        </select>
        <button type="submit">Open Book</button>
    </form>

    <h2>Delete an EPUB Book</h2>
    <form action="" method="POST">
        <select name="delete_file">
            <?php foreach ($files as $file) { ?>
                <option value="<?php echo basename($file); ?>"><?php echo basename($file); ?></option>
            <?php } ?>
        </select>
        <button type="submit">Delete Book</button>
    </form>
</body>
</html>

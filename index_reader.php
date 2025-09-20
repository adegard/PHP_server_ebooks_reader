<?php
session_start();

unset($_SESSION['original_file']); // Reset file tracking

$ebookFolder = 'ebooks/';



//Cloudconvert
$cloudConvertApiKey = 'APIKEY'; // Replace with your actual API key
function convertEpubToMobiCloudConvert($epubPath, $ebookFolder, $apiKey) {
    $filename = basename($epubPath);
    $mobiFilename = pathinfo($filename, PATHINFO_FILENAME) . '.mobi';

    // Step 1: Create a job
    $jobPayload = json_encode([
        "tasks" => [
            "import-epub" => [ "operation" => "import/upload" ],
            "convert" => [
                "operation" => "convert",
                "input" => "import-epub",
                "input_format" => "epub",
                "output_format" => "mobi"
            ],
            "export-mobi" => [
                "operation" => "export/url",
                "input" => "convert"
            ]
        ]
    ]);

    $ch = curl_init("https://api.cloudconvert.com/v2/jobs");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jobPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $job = json_decode($response, true);

    if (!isset($job['data']['id'])) {
        echo "<p style='color:red;'>Failed to create CloudConvert job.</p>";
        return;
    }

    // Step 2: Upload EPUB
    $uploadTask = $job['data']['tasks'][0];
    $uploadUrl = $uploadTask['result']['form']['url'];
    $uploadParams = $uploadTask['result']['form']['parameters'];

    $cfile = curl_file_create($epubPath, 'application/epub+zip', $filename);
    $uploadParams['file'] = $cfile;

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $uploadParams);
    $uploadResponse = curl_exec($ch);
    curl_close($ch);

    echo "<p style='color:green;'>EPUB uploaded to CloudConvert. Waiting for MOBI export…</p>";

	// Step 3: Poll for export URL (wait up to 60 seconds)
	$maxWait = 60;
	$interval = 5;
	$elapsed = 0;
	$downloadUrl = null;

	while ($elapsed < $maxWait) {
		sleep($interval);
		$elapsed += $interval;

		$ch = curl_init("https://api.cloudconvert.com/v2/jobs/" . $job['data']['id']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Authorization: Bearer $apiKey"
		]);
		$jobStatus = json_decode(curl_exec($ch), true);
		curl_close($ch);

		$exportTask = array_filter($jobStatus['data']['tasks'], function ($task) {
			return $task['name'] === 'export-mobi' && $task['status'] === 'finished';
		});

		if (!empty($exportTask)) {
			$exportTask = array_values($exportTask)[0];
			$downloadUrl = $exportTask['result']['files'][0]['url'];
			break;
		}
	}

	if (!$downloadUrl) {
		echo "<p style='color:red;'>MOBI export failed or timed out.</p>";
		return;
	}

	// Step 4: Download MOBI
	$mobiPath = $ebookFolder . $mobiFilename;
	file_put_contents($mobiPath, file_get_contents($downloadUrl));
	echo "<p style='color:green;'>MOBI file downloaded and saved as $mobiFilename</p>";
}



// Handle File Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["epub_file"])) {
    $targetFile = $ebookFolder . basename($_FILES["epub_file"]["name"]);

    // Ensure it's an EPUB file
    if (pathinfo($targetFile, PATHINFO_EXTENSION) !== "epub") {
        echo "<p style='color:red;'>Only EPUB files are allowed!</p>";
    } elseif (move_uploaded_file($_FILES["epub_file"]["tmp_name"], $targetFile)) {
        echo "<p style='color:green;'>EPUB uploaded successfully!</p>";
		convertEpubToMobiCloudConvert($targetFile, $ebookFolder, $cloudConvertApiKey);


        
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

$deleteFiles = array_merge(
    glob($ebookFolder . '*.epub'),
    glob($ebookFolder . '*.mobi')
);


// Get bookmarks
$books = glob("$ebookFolder/*.epub");

echo "<h2>Select a bookmark</h2>";



foreach ($books as $book) {
    $bookTitle = basename($book);
    $savedBookmark = isset($_SESSION["bookmark_$bookTitle"]) ? $_SESSION["bookmark_$bookTitle"] : null;

    if ($savedBookmark) {
        $bookmarkData = json_decode($savedBookmark, true);
        $lastPage = $bookmarkData['page'];
        $scrollPosition = $bookmarkData['scroll'];
    } else {
        $lastPage = 0; // Default to first page if no bookmark exists
        $scrollPosition = 0;
    }

    echo "<p>
        <a href='reader.php?file=" . urlencode($bookTitle) . "&page=$lastPage&scroll=$scrollPosition'>
            📖 $bookTitle (Last Page: $lastPage, Scroll: $scrollPosition)
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
            <?php foreach ($deleteFiles as $deleteFiles) { ?>
                <option value="<?php echo basename($deleteFiles); ?>"><?php echo basename($deleteFiles); ?></option>
            <?php } ?>
        </select>
        <button type="submit">Delete Book</button>
    </form>
    
    <h2>Download Available Books</h2>
	<ul>
	<?php
	$allBooks = array_merge(
		glob($ebookFolder . '*.epub'),
		glob($ebookFolder . '*.mobi')
	);

	foreach ($allBooks as $bookFile) {
		$bookName = basename($bookFile);
		echo "<li><a href='$ebookFolder$bookName' download>$bookName</a></li>";
	}
	?>
	</ul>


</body>
</html>

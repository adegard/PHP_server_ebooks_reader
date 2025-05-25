<?php
session_start();

$ebookFolder = 'ebooks/';
$extractPath = 'ebooks/extracted/';

$file = isset($_GET['file']) ? $_GET['file'] : '';
$page = isset($_GET['page']) ? $_GET['page'] : 0;

if (!$file) {
    die("No file selected!");
}

// Function to sanitize filenames
function sanitizeFilename($filename) {
    // Remove special characters, apostrophes, spaces, and accents
    $filename = iconv("UTF-8", "ASCII//TRANSLIT", $filename); // Convert special characters
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $filename); // Keep only safe characters
    return $filename;
}

// Rename file to a clean format
$originalFile = $ebookFolder . $file;
$sanitizedFile = $ebookFolder . sanitizeFilename($file);
if ($originalFile !== $sanitizedFile) {
    rename($originalFile, $sanitizedFile);
    $file = basename($sanitizedFile);
}

// Ensure extraction folder exists
$bookFolder = $extractPath . pathinfo($file, PATHINFO_FILENAME) . '/';
if (!is_dir($bookFolder)) {
    mkdir($bookFolder, 0755, true);
}

// Extract EPUB using system command
$epubFile = escapeshellarg(realpath($ebookFolder . $file));
$destination = escapeshellarg(realpath($bookFolder));
exec("unzip -o $epubFile -d $destination 2>&1", $output, $returnCode);

if ($returnCode !== 0) {
    die("Failed to extract EPUB file! Error:\n" . implode("\n", $output));
}

// Search for HTML files in multiple locations, including /text folder
$htmlFiles = glob("$bookFolder/{OEBPS,Text,text}/*.{html,xhtml,htm}", GLOB_BRACE);
if (empty($htmlFiles)) {
    $htmlFiles = glob("$bookFolder/*.{html,xhtml,htm}", GLOB_BRACE);
}

if (empty($htmlFiles)) {
    die("No readable HTML content found in EPUB!");
}

// Sort files in natural order
natsort($htmlFiles);
$htmlFiles = array_values($htmlFiles);

if (!isset($htmlFiles[$page])) {
    die("Page not found!");
}

// Save progress
$_SESSION['last_page'] = $page;
$content = file_get_contents($htmlFiles[$page]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPUB Reader</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: white;
            color: black;
            transition: background 0.3s, color 0.3s;
            overflow: hidden; /* Prevent unwanted scrolling */
        }
        .dark-mode {
            background-color: black;
            color: white;
        }
        .book-container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
            text-align: justify; /* Justify text */
            line-height: 1.6;
            height: 90vh; /* Keep container viewable within screen */
            overflow: hidden; 
            /* overflow-y: auto; Enable natural scrolling */
        }
        .nav-buttons {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 10px;
        }
        .nav-button {
            padding: 20px;
            font-size: 22px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border-radius: 50%;
            cursor: pointer;
            text-decoration: none;
            border: none;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nav-button:hover {
            background-color: rgba(0, 0, 0, 0.8);
        }
    </style>
</head>
<body onload="applyDarkModePreference(); toggleScrolling()">

    <button onclick="toggleDarkMode()">DARK</button>
    <button id="increaseFont">+Incr.</button>
	<button id="decreaseFont">-Decr.</button>
	<button id="toggleNavButtons">NAV</button>
    <button onclick="window.location.href='index_reader.php'" class="top-button">📚 MENU</button>
	    
    <div class="book-container" id="book-content">
        <?php echo $content; ?>
    </div>

    <div class="nav-buttons">
        <button class="nav-button" onclick="scrollUp()">⬅</button>
        <button class="nav-button" onclick="scrollDown()">➡</button>
    </div>

    <script>
        // Toggle Invert Colors & Save Preference
        function toggleDarkMode() {
            document.body.classList.toggle("dark-mode");
            localStorage.setItem("darkMode", document.body.classList.contains("dark-mode"));
        }

        // Apply Dark Mode Preference
        function applyDarkModePreference() {
            if (localStorage.getItem("darkMode") === "true") {
                document.body.classList.add("dark-mode");
            }
        }

		let topReached = false; // Track top state

		function scrollUp() {
			let bookContent = document.getElementById('book-content');
			bookContent.scrollBy({
				top: -window.innerHeight * 0.9,
				behavior: 'smooth'
			});


			if (bookContent.scrollTop <= 10) { // Small tolerance
				window.location.href = "reader.php?file=<?php echo $file; ?>&page=<?php echo max($page - 1, 0); ?>";
			}

		}


		function scrollDown() {
			let bookContent = document.getElementById('book-content');
			bookContent.scrollBy({
				top: window.innerHeight * 0.9,
				behavior: 'smooth'
			});


			let tolerance = 10; // Add some buffer
			let reachedBottom = bookContent.scrollTop + bookContent.clientHeight >= bookContent.scrollHeight + tolerance;

			if (reachedBottom) {
			window.location.href = "reader.php?file=<?php echo $file; ?>&page=<?php echo min($page + 1, count($htmlFiles) - 1); ?>";
			}

		}


        //font size
            const increaseFontButton = document.getElementById("increaseFont");
			const decreaseFontButton = document.getElementById("decreaseFont");
			const readerContent = document.getElementById("book-content"); // Change from "reader" to "book-content"

			// Load stored font size or set default
			let fontSize = localStorage.getItem("fontSize") ? parseInt(localStorage.getItem("fontSize")) : 16;
			readerContent.style.fontSize = fontSize + "px";

			increaseFontButton.addEventListener("click", () => {
				fontSize += 2;
				readerContent.style.fontSize = fontSize + "px";
				localStorage.setItem("fontSize", fontSize); // Save preference
			});

			decreaseFontButton.addEventListener("click", () => {
				fontSize -= 2;
				readerContent.style.fontSize = fontSize + "px";
				localStorage.setItem("fontSize", fontSize); // Save preference
			});
			
			//toggle buttons next yes/no
			const toggleNavButton = document.getElementById("toggleNavButtons");
			const navButtons = document.querySelector(".nav-buttons");

			toggleNavButton.addEventListener("click", () => {
				if (navButtons.style.display === "none") {
					navButtons.style.display = "flex"; // Show overlay buttons
				} else {
					navButtons.style.display = "none"; // Hide overlay buttons
				}
			});
	

    </script>

</body>
</html>

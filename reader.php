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
            margin-top: 85px;  /* Push content below toolbar */
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
            padding: 15px;
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
        
        .light-mode { background: white; color: black; }
		.dark-mode { background: black; color: white; }
		.sepia-mode { background: #f4ecd8; color: #5f4b32; }
		
		/* menu icons */
		.top-buttons {
			display: flex;
			justify-content: center;
			gap: 10px;
			padding: 10px;
			position: fixed;
			top: 0;
			width: 100%;
			background-color: rgba(0, 0, 0, 0.8);
			z-index: 1000;
			height: 60px;
			align-items: center;
		}

		.button-icon {
			padding: 15px;
			font-size: 24px;
			background-color: transparent;
			color: white;
			border-radius: 50%;
			cursor: pointer;
			text-decoration: none;
			border: none;
			width: 50px;
			height: 50px;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: background 0.3s;
		}

		.button-icon:hover {
			background-color: rgba(255, 255, 255, 0.2);
		}
		
		.progress-container {
			position: fixed;
			bottom: 0;
			width: 100%;
			height: 6px;
			background: #ccc;
		}

		.progress-bar {
			height: 100%;
			width: 0%;
			background: #4CAF50;
			transition: width 0.3s;
		}

		.page-counter {
			position: fixed;
			bottom: 8px;
			right: 20px;
			font-size: 14px;
			color: white;
			background: rgba(0, 0, 0, 0.7);
			padding: 5px;
			border-radius: 5px;
		}

		
    </style>
</head>
<body onload="applyTheme(); toggleScrolling()">
<!--applyDarkModePreference(); -->

	<div class="top-buttons">
		<button class="button-icon" onclick="setTheme('light-mode')">🎨</button>
		<button class="button-icon" onclick="setTheme('dark-mode')">🌙</button>
		<button class="button-icon" onclick="setTheme('sepia-mode')">📜</button>

		<button class="button-icon" onclick="readAloud()">🔊</button>
<!--		<button class="button-icon" onclick="saveBookmark()">📌</button>
		<button class="button-icon" onclick="loadBookmark()">📖</button>
		<button class="button-icon" onclick="window.location.href='reader.php?file=<?php echo $file; ?>&page=<?php echo max($page - 1, 0); ?>'">⏪</button>
		<button class="button-icon" onclick="window.location.href='reader.php?file=<?php echo $file; ?>&page=<?php echo min($page + 1, count($htmlFiles) - 1); ?>'">⏩</button>
-->
		<button class="button-icon" id="increaseFont">➕</button>
		<button class="button-icon" id="decreaseFont">➖</button>
		<button class="button-icon" onclick="window.location.href='index_reader.php'">🏠</button>
	</div>

	    
    <div class="book-container" id="book-content">
        <?php echo $content; ?>
    </div>

    <div class="nav-buttons">
        <button class="nav-button" onclick="scrollUp()">⬅</button>
        <button class="nav-button" onclick="scrollDown()">➡</button>
    </div>
    
    <div class="progress-container">
		<div class="progress-bar" id="progress-bar"></div>
	</div>

	<!--<div class="page-counter" id="page-counter"></div>-->


    <script>
		/*
		function updateProgress() {
			let bookContent = document.getElementById("book-content");
			let progress = (bookContent.scrollTop / (bookContent.scrollHeight - bookContent.clientHeight)) * 100;
			document.getElementById("progress-bar").style.width = progress + "%";
		}

		document.getElementById("book-content").addEventListener("scroll", updateProgress);
		*/
		function updatePageProgress() {
			let currentPage = <?php echo $page; ?> + 1;
			let totalPages = <?php echo count($htmlFiles); ?>;
			let progress = (currentPage / totalPages) * 100;

			document.getElementById("progress-bar").style.width = progress + "%";
			document.getElementById("page-counter").innerText = `📖 Page ${currentPage} / ${totalPages}`;
		}

		document.addEventListener("DOMContentLoaded", updatePageProgress);



		//Not used:
		/*
		function saveBookmark() {
			localStorage.setItem("bookmark", "<?php echo $page; ?>");
		}

		function loadBookmark() {
			const savedPage = localStorage.getItem("bookmark") || "0";
			window.location.href = "reader.php?file=<?php echo $file; ?>&page=" + savedPage;
		}
		*/
		
		function readAloud() {
			const text = document.getElementById("book-content").innerText;
			const speech = new SpeechSynthesisUtterance(text);
			speech.lang = "en-US";
			window.speechSynthesis.speak(speech);
		}

		function setTheme(theme) {
			document.body.className = theme;
			localStorage.setItem("theme", theme);
		}

		function applyTheme() {
			const savedTheme = localStorage.getItem("theme") || "light-mode";
			document.body.className = savedTheme;
		}

		document.addEventListener("DOMContentLoaded", applyTheme);


        // Toggle Invert Colors & Save Preference
        /*
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
		*/

		function scrollUp() {
			let bookContent = document.getElementById('book-content');

			if (bookContent.scrollTop > 10) {
				// If there's space above, scroll up
				bookContent.scrollBy({
					top: -window.innerHeight * 0.9,
					behavior: 'smooth'
				});
			} else {
				// If at the top, switch to previous page
				window.location.href = "reader.php?file=<?php echo $file; ?>&page=<?php echo max($page - 1, 0); ?>";
			}
		}


		function scrollDown() {
			let bookContent = document.getElementById('book-content');

			// Get current scroll position
			let maxScroll = bookContent.scrollHeight - bookContent.clientHeight;
			let currentScroll = bookContent.scrollTop;

			if (currentScroll < maxScroll - 10) {
				// If there's more space to scroll, move down
				bookContent.scrollBy({
					top: window.innerHeight * 0.9,
					behavior: 'smooth'
				});
			} else {
				// If already at bottom, switch to next page
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

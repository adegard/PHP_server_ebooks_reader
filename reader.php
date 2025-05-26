<?php
session_start();

$ebookFolder = 'ebooks/';
$extractPath = 'ebooks/extracted/';


$page = isset($_GET['page']) ? intval($_GET['page']) : 0;

// Reset stored file when a new book is loaded
if (isset($_GET['file'])) {
    $_SESSION['original_file'] = $_GET['file']; // Store new file
}

// Retrieve the correct file for reading
$file = isset($_SESSION['original_file']) ? $_SESSION['original_file'] : '';

//retrieve the correct theme
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : (isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light-mode');
$_SESSION['theme'] = $theme; // Keep theme across pages
echo "<body class='$theme' onload='applyTheme();'>";



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
// Search inside OEBPS, Text, or text folders (including subdirectories)
$htmlFiles = glob("$bookFolder/{OEBPS,Text,text}/**/*.{html,xhtml,htm}", GLOB_BRACE);

// If no files are found, check directly inside all subdirectories
if (empty($htmlFiles)) {
    $htmlFiles = glob("$bookFolder/**/*.{html,xhtml,htm}", GLOB_BRACE);
}

// If still empty, check the root folder of the extracted EPUB
if (empty($htmlFiles)) {
    $htmlFiles = glob("$bookFolder/*.{html,xhtml,htm}", GLOB_BRACE);
}

if (empty($htmlFiles)) {
    die("No readable HTML content found");
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

// Store last read page for this book
$_SESSION["bookmark_$file"] = $page;

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

        .book-container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
            text-align: justify; /* Justify text */
            line-height: 1.6;
            margin-top: 80px;  /* Push content below toolbar */
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
            padding: 0 -5px;
        }
        .nav-button {
            padding: 20px;
            font-size: 22px;
            background-color: rgba(0, 0, 0, 0.3);
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
			height: 45px; /* 60px; */
			align-items: center;
		}

		.button-icon {
			padding: 10px; /* 15px */
			font-size: 20px; /* Slightly smaller icons, was 22 */
			background-color: transparent;
			color: white;
			border-radius: 50%;
			cursor: pointer;
			text-decoration: none;
			border: none;
			width: 40px; /* 50px; */
			height: 40px; /* 50px; */
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
			font-size: 8px;
			color: white;
			background: rgba(0, 0, 0, 0.4);
			padding: 5px;
			border-radius: 5px;
		}




		
    </style>
</head>
<body onload="applyTheme();">

	<div class="top-buttons">
	
		<select id="toc-dropdown" onchange="jumpToChapter(this.value)">
			<?php foreach ($htmlFiles as $index => $file) { ?>
				<option value="<?php echo $index; ?>">
					Pag.<?php echo $index + 1; ?>
				</option>
			<?php } ?>
		</select>

		<button class="button-icon" onclick="setTheme('light-mode')">🎨</button>
		<button class="button-icon" onclick="setTheme('dark-mode')">🌙</button>
		<button class="button-icon" onclick="setTheme('sepia-mode')">📜</button>
		<button class="button-icon" onclick="readAloud()">🔊</button>-
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

	<div class="page-counter" id="page-counter"></div>


    <script>
		//no more used
		function saveBookmark() {
			let currentPage = <?php echo $page; ?>;
			let bookTitle = "<?php echo urlencode($_SESSION['original_file']); ?>";
			localStorage.setItem("bookmark_" + bookTitle, currentPage);
			alert("📌 Bookmark saved!");
		}
		
		function loadBookmark() {
			let bookTitle = "<?php echo urlencode($_SESSION['original_file']); ?>";
			let savedPage = localStorage.getItem("bookmark_" + bookTitle);

			if (savedPage !== null) {
				window.location.href = "reader.php?file=" + bookTitle + "&page=" + savedPage;
			} else {
				alert("❌ No bookmark found!");
			}
		}



		//table of content jumping
		function jumpToChapter(pageIndex) {
			window.location.href = "reader.php?file=" + "<?php echo urlencode($_SESSION['original_file']); ?>" + "&page=" + pageIndex;
		}

		// progress bar
		function updatePageProgress() {
			let currentPage = <?php echo $page; ?> + 1;
			let totalPages = <?php echo count($htmlFiles); ?>;
			let progress = (currentPage / totalPages) * 100;

			document.getElementById("progress-bar").style.width = progress + "%";
			document.getElementById("page-counter").innerText = `📖 ${currentPage} / ${totalPages}`;
		}

		document.addEventListener("DOMContentLoaded", updatePageProgress);

		
		function readAloud() {
			const text = document.getElementById("book-content").innerText;
			const speech = new SpeechSynthesisUtterance(text);
			speech.lang = "en-US";
			window.speechSynthesis.speak(speech);
		}


		function setTheme(theme) {
			document.body.className = theme;
			localStorage.setItem("theme", theme);
			document.cookie = "theme=" + theme + "; path=/"; // Store theme in cookie for persistence
			applyTheme(); // Refresh theme immediately
		}

		function applyTheme() {
			const savedTheme = localStorage.getItem("theme") || "light-mode"; 
			document.body.className = savedTheme;
		}
		document.addEventListener("DOMContentLoaded", applyTheme);

				

		function scrollUp() {
			let bookContent = document.getElementById('book-content');

			if (bookContent.scrollTop > 10) {
				bookContent.scrollBy({
					top: -window.innerHeight * 0.9,
					behavior: 'smooth'
				});
			} else {
				window.location.href = "reader.php?file=" + "<?php echo urlencode($_SESSION['original_file']); ?>" + "&page=<?php echo max($page - 1, 0); ?>";
			}
		}

		function scrollDown() {
			let bookContent = document.getElementById('book-content');
			let maxScroll = bookContent.scrollHeight - bookContent.clientHeight;
			let currentScroll = bookContent.scrollTop;

			if (currentScroll < maxScroll - 10) {
				bookContent.scrollBy({
					top: window.innerHeight * 0.9,
					behavior: 'smooth'
				});
			} else {
				// Use the correct stored filename for navigation
				window.location.href = "reader.php?file=" + "<?php echo urlencode($_SESSION['original_file']); ?>" + "&page=<?php echo min($page + 1, count($htmlFiles) - 1); ?>";
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
			

    </script>

</body>
</html>

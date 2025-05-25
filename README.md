
### **README.md**

# EPUB Web Reader 📖

A lightweight web-based EPUB reader with **scroll navigation, page transitions, dark mode, and font size adjustments**. Designed for a seamless e-reader experience directly in a browser.

## Features ✨
- 📚 **Upload & Delete EPUBs** from the server (`index_reader.php`) from /ebooks sub-folder (usually in /var/www)
- 📖 **Read EPUBs** in the browser (`reader.php`)
- 🔄 **Navigation Buttons** to scroll **page by page**
- 🌙 **Dark Mode** toggle (saves preference)
- 🔍 **Font Size Adjustment** (+/- buttons)
- 📜 **Auto Page Switch** when reaching bottom/top
- ⬆ **Previous Page** when reaching top
- 🎯 **Mobile Optimized** for small screen & preventing bad gesture (to avoid selecting by better handing)

![Screenshot](screen_reader.jpg?raw=true "Screenshot")

## Installation 🛠️

### **1. Requirements**
- PHP 7.4+  
- Web server (Apache, Nginx)  
- `unzip` command installed  

### **2. Setup**
```sh
cd /var/www
git clone https://github.com/YOUR-USERNAME/epub-reader.git
cd epub-reader
```

### **3. Configure Web Server**
Point your web server to the `epub-reader` directory.

### **4. Run the EPUB Reader**
Simply open your browser and go to:
```
http://your-server/epub-reader/index.php
```


## Usage 🚀

### **Upload an EPUB**
1. Click `Upload EPUB`
2. Select an `.epub` file from your device
3. The file will be stored in `/ebooks/` folder

### **Read an EPUB**
1. Choose a file from the dropdown
2. Click `Open Book`

### **Navigation Controls**
- **⬅ Left Button** → Scroll **up** (previous section)
- **➡ Right Button** → Scroll **down** (next section)
- **Next Page Loads** when reaching the bottom  
- **Previous Page Loads** when reaching the top  

### **Extra Features**
- `DARK` button → Toggle dark mode
- `+INCR` / `-DECR` → Adjust font size
- `NAV` button → Show/hide navigation buttons
- `📚 MENU` → Return to index for ebook selection  

## Contributing 🤝
Feel free to fork, submit PRs, or suggest improvements! 🚀

## License ⚖️
MIT License - Free to use and modify.  


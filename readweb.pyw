import tkinter as tk
import requests
from bs4 import BeautifulSoup
import os
import difflib


class WebReaderApp:
    """A simple e-book style web reader with navigation, themes, and Wikipedia search."""

    def __init__(self, root):
        """Initialize the WebReaderApp with UI elements and theme settings."""
        self.root = root
        self.root.title("E-Book Style Web Reader")

        # Theme settings
        self.theme = "light"
        self.bg_colors = {"light": "white", "dark": "#101524", "sepia": "#F4E1C1"}
        self.fg_colors = {"light": "black", "dark": "#EFDACA", "sepia": "#5C4033"}

        self.font_size = 14

        self.create_ui()

    def create_ui(self):
        """Set up the UI components including Wikipedia search."""
        # Top Control Panel
        top_frame = tk.Frame(self.root)
        top_frame.pack(pady=10, fill="x")

        # Wikipedia Search Entry
        tk.Label(top_frame, text="Search Wikipedia:").grid(row=0, column=0, padx=5)
        self.search_entry = tk.Entry(top_frame, width=50)
        self.search_entry.grid(row=0, column=1, padx=5)
        self.search_entry.bind("<Return>", self.search_wikipedia)

        tk.Button(top_frame, text="Search", command=self.search_wikipedia).grid(row=0, column=2, padx=5)

        # URL Entry for general web browsing
        tk.Label(top_frame, text="Load Page:").grid(row=1, column=0, padx=5)
        self.url_entry = tk.Entry(top_frame, width=50)
        self.url_entry.grid(row=1, column=1, padx=5)
        self.url_entry.bind("<Return>", self.load_page)

        tk.Button(top_frame, text="Load", command=self.load_page).grid(row=1, column=2, padx=5)
        tk.Button(top_frame, text="A+", command=self.increase_font).grid(row=1, column=3, padx=5)
        tk.Button(top_frame, text="A-", command=self.decrease_font).grid(row=1, column=4, padx=5)
        tk.Button(top_frame, text="Theme", command=self.toggle_theme, bg="gray", fg="white").grid(row=1, column=5, padx=5)

        # Text Display Frame
        frame = tk.Frame(self.root)
        frame.pack(pady=10, fill="both", expand=True)

        scrollbar = tk.Scrollbar(frame)
        scrollbar.pack(side="right", fill="y")

        self.text_display = tk.Text(frame, wrap="word", font=("Arial", self.font_size + 2), height=20, width=60, yscrollcommand=scrollbar.set)
        self.text_display.pack(fill="both", expand=True)
        scrollbar.config(command=self.text_display.yview)
        
        # Navigation Buttons
        nav_frame = tk.Frame(self.root)
        nav_frame.pack(fill="x")

        tk.Button(nav_frame, text="← Previous Page", command=self.prev_page).pack(side="left", padx=20, pady=5)
        tk.Button(nav_frame, text="Next Page →", command=self.next_page).pack(side="right", padx=20, pady=5)

        # Keyboard shortcuts
        self.root.bind("<Left>", lambda event: self.prev_page())
        self.root.bind("<Right>", lambda event: self.next_page())

        # Load last page and apply theme
        self.load_last_page()
        self.apply_theme()

    def search_wikipedia(self, event=None):
        """Search Wikipedia and fetch the first valid result."""
        query = self.search_entry.get().strip()
        if not query:
            return

        search_url = f"https://en.wikipedia.org/wiki/Special:Search?search={query.replace(' ', '+')}&go=Go"
        
        try:
            response = requests.get(search_url)
            soup = BeautifulSoup(response.text, 'html.parser')

            # Check if it landed directly on a Wikipedia article
            title = soup.find("title").text if soup.find("title") else ""
            if "Search results" not in title:
                wiki_url = search_url  # It's a direct page
            else:
                # Extract first result link from search page
                first_result = soup.find("ul", class_="mw-search-results")
                if first_result:
                    first_link = first_result.find("a")["href"]
                    wiki_url = f"https://en.wikipedia.org{first_link}"
                else:
                    self.text_display.delete("1.0", tk.END)
                    self.text_display.insert(tk.END, "No Wikipedia results found.")
                    return

            # Fetch article content
            response = requests.get(wiki_url)
            soup = BeautifulSoup(response.text, 'html.parser')

            paragraph = soup.find('p')
            content = paragraph.get_text().strip() if paragraph else "No content found for this Wikipedia page."

            # Display results
            self.text_display.delete("1.0", tk.END)
            self.text_display.insert(tk.END, f"**Wikipedia: {query}**\n\n{content}")

        except Exception as e:
            self.text_display.delete("1.0", tk.END)
            self.text_display.insert(tk.END, f"Error: {e}")

    def load_page(self, event=None):
        """Fetch and display webpage content."""
        url = self.url_entry.get().strip()
        if not url:
            return

        if not url.startswith(("http://", "https://")):
            url = "https://" + url

        try:
            response = requests.get(url)
            soup = BeautifulSoup(response.text, 'html.parser')

            # Collect headings (including h3)
            headings = [h.get_text().strip() for h in soup.find_all(['h1', 'h2', 'h3'])]

            # Collect bolded text separately
            bold_titles = [b.get_text().strip() for b in soup.find_all(['b', 'strong'])]

            # Merge both lists while ensuring no duplicates
            #all_titles = list(dict.fromkeys(headings + bold_titles))  # Preserves order while removing duplicates
            # Extract headings including those inside special div wrappers
            headings = [h.get_text().strip() for h in soup.find_all(['h1', 'h2', 'h3'])]

            # Look for additional headings inside <div> elements with class "mw-heading"
            wrapped_headings = [div.find('h3').get_text().strip() for div in soup.find_all('div', class_="mw-heading") if div.find('h3')]

            # Merge both lists while removing duplicates
            all_titles = list(dict.fromkeys(headings + wrapped_headings))  # Keeps order while eliminating duplicates


            paragraphs = [p.get_text().strip() for p in soup.find_all('p') if len(p.get_text().strip()) > 50]

            text_content = "\n\n".join(paragraphs)

            self.text_display.delete("1.0", tk.END)
            self.text_display.insert(tk.END, text_content)

            # Apply bold formatting to detected titles
            self.apply_bold_titles(all_titles)

        except Exception as e:
            self.text_display.delete("1.0", tk.END)
            self.text_display.insert(tk.END, f"Error: {e}")

    def apply_bold_titles(self, headings):
        """Formats headings to bold in the text display."""
        self.text_display.tag_configure("bold", font=("Arial", self.font_size + 4, "bold"))

        for heading in headings:
            start_idx = self.text_display.search(heading, "1.0", tk.END)
            if start_idx:
                end_idx = f"{start_idx} + {len(heading)} chars"
                self.text_display.tag_add("bold", start_idx, end_idx)

        

    def prev_page(self):
        """Scrolls up."""
        self.text_display.yview_scroll(-1, "pages")

    def next_page(self):
        """Scrolls down."""
        self.text_display.yview_scroll(1, "pages")
        
    def increase_font(self):
        """Increase font size."""
        self.font_size += 2
        self.text_display.configure(font=("Arial", self.font_size))

    def decrease_font(self):
        """Decrease font size."""
        self.font_size -= 2
        self.text_display.configure(font=("Arial", self.font_size))

    def toggle_theme(self):
        """Cycle through available themes."""
        themes = ["light", "sepia", "dark"]
        self.theme = themes[(themes.index(self.theme) + 1) % len(themes)]
        self.apply_theme()

    def apply_theme(self):
        """Apply selected theme to UI."""
        self.text_display.config(bg=self.bg_colors[self.theme], fg=self.fg_colors[self.theme])

    def load_last_page(self):
        """Load the last visited webpage."""
        if os.path.exists("last_url.txt"):
            with open("last_url.txt", "r") as file:
                self.url_entry.insert(0, file.read().strip())
            self.load_page()


if __name__ == "__main__":
    root = tk.Tk()
    app = WebReaderApp(root)
    root.mainloop()

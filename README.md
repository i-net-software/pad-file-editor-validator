# 🔍 PAD File Validator

A modern, user-friendly web application for validating Portable Application Description (PAD) XML files. This tool helps software developers ensure their PAD files conform to the official PAD specification before submission to software directories.

![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)

## ✨ Features

- **🌐 URL-based Validation**: Simply paste a PAD file URL and validate it instantly
- **⚡ Asynchronous Processing**: No page reloads - smooth, modern user experience
- **🎨 Beautiful Modern UI**: Clean, responsive design with gradient backgrounds and smooth animations
- **📊 Detailed Reports**: Comprehensive validation reports with clear error and warning messages
- **📄 XML Source Viewer**: View the raw XML source with syntax highlighting
- **📋 Copy to Clipboard**: Easily copy XML content with one click
- **🔧 Encoding Detection**: Automatically detects and handles various character encodings (UTF-8, ISO-8859-1, Windows-1252)
- **🛡️ Robust Error Handling**: Graceful handling of network errors, parse errors, and encoding issues
- **📱 Responsive Design**: Works perfectly on desktop, tablet, and mobile devices

## 🚀 Quick Start

### Prerequisites

- **PHP 7.4 or higher** (PHP 8.0+ recommended)
- **PHP Extensions**:
  - `mbstring` (for encoding detection and conversion)
  - `xml` (for XML parsing)
  - `dom` (for XML manipulation)
  - `curl` (recommended) or `allow_url_fopen` enabled (for fetching remote files)

### Installation

1. **Clone or download this repository**:
   ```bash
   git clone https://github.com/yourusername/pad-file-validator.git
   cd pad-file-validator
   ```

2. **That's it!** No dependencies to install - it's pure PHP with no external package managers needed.

### Running Locally

1. **Navigate to the project directory**:
   ```bash
   cd pad-file-validator
   ```

2. **Start the PHP built-in web server**:
   ```bash
   php -S localhost:8000
   ```

3. **Open your browser** and navigate to:
   ```
   http://localhost:8000
   ```

4. **Enter a PAD file URL** (for example):
   ```
   https://download.inetsoftware.de/i-net-clear-reports.pad.xml
   ```

5. **Click "Validate PAD File"** and wait for the results! 🎉

### Alternative: Using a Web Server

If you prefer using Apache, Nginx, or another web server:

1. **Copy the project** to your web server's document root (e.g., `htdocs`, `www`, or `public_html`)
2. **Ensure PHP is configured** with the required extensions
3. **Access via your web browser** at the appropriate URL

## 📋 Requirements Checklist

Before running, make sure you have:

- ✅ PHP 7.4+ installed
- ✅ `mbstring` extension enabled
- ✅ `xml` extension enabled
- ✅ `dom` extension enabled
- ✅ `curl` extension enabled (recommended) OR `allow_url_fopen` enabled in `php.ini`

### Checking PHP Extensions

To verify your PHP installation has the required extensions, run:

```bash
php -m | grep -E "(mbstring|xml|dom|curl)"
```

You should see all four extensions listed. If any are missing, install them using your system's package manager:

**Ubuntu/Debian:**
```bash
sudo apt-get install php-mbstring php-xml php-curl
```

**macOS (Homebrew):**
```bash
brew install php
# Extensions are usually included by default
```

**Windows:**
Edit your `php.ini` file and uncomment the extension lines:
```ini
extension=mbstring
extension=xml
extension=dom
extension=curl
```

## 🎯 Usage

1. **Enter a PAD File URL**: Paste the full URL to your PAD XML file
2. **Click Validate**: The validator will fetch and analyze the file
3. **Review Results**: 
   - ✅ Green badges indicate success
   - ⚠️ Yellow badges show warnings
   - ❌ Red badges indicate errors
4. **View XML Source**: Click "View XML Source" to see the raw XML with syntax highlighting
5. **Copy XML**: Use the copy button to quickly copy the XML content

## 🏗️ Project Structure

```
pad-file-validator/
├── index.php              # Main entry point (HTML + PHP)
├── include/
│   ├── padfile.php       # PAD file loading and parsing
│   ├── padvalidator.php  # Validation logic
│   ├── padspec.php       # PAD specification handling
│   └── xmlfile.php       # XML parsing and encoding detection
├── README.md             # This file
└── .gitignore           # Git ignore rules
```

## 🔧 Technical Details

### Encoding Handling

The validator automatically:
- Detects declared XML encoding
- Verifies actual content encoding
- Converts to UTF-8 for consistent processing
- Reports encoding mismatches as warnings

### Validation Process

1. **Load PAD File**: Fetches XML from the provided URL
2. **Parse XML**: Uses multiple parsers (DOMDocument, SimpleXML, xml_parse) for robustness
3. **Load PAD Spec**: Fetches the official PAD specification
4. **Validate**: Checks all required fields and formats
5. **Report**: Displays errors, warnings, and success messages

### Browser Support

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Opera (latest)

## 🐛 Troubleshooting

### "Cannot open URL" Error

- **Check your internet connection**
- **Verify the URL is accessible** (try opening it in a browser)
- **Ensure `curl` extension is installed** or `allow_url_fopen` is enabled
- **Check for firewall/proxy issues**

### "Error loading Validator" Message

- **Ensure you have internet access** (the validator needs to fetch the PAD spec)
- **Check PHP configuration** for URL access permissions
- **Verify `curl` or `allow_url_fopen` is enabled**

### Encoding Warnings

- These are **informational warnings**, not errors
- The validator automatically converts encodings
- Your PAD file will still be validated correctly

### JSON Parse Errors

- **Clear your browser cache** and try again
- **Check browser console** for detailed error messages
- **Ensure PHP error reporting** is not outputting to the response

## 🤝 Contributing

Contributions are welcome! Feel free to:

- 🐛 Report bugs
- 💡 Suggest new features
- 🔧 Submit pull requests
- 📝 Improve documentation

## 📝 License

This project is open source and available under the [MIT License](LICENSE).

## 🙏 Acknowledgments

- Built with PHP and vanilla JavaScript
- Uses [Prism.js](https://prismjs.com/) for syntax highlighting
- Validates against the official [PAD Specification](http://repository.appvisor.com/padspec/files/padspec.xml)

## 📞 Support

If you encounter any issues or have questions:

1. Check the [Troubleshooting](#-troubleshooting) section
2. Review existing [GitHub Issues](https://github.com/yourusername/pad-file-validator/issues)
3. Create a new issue with details about your problem

---

**Happy Validating! 🎉**

Made with ❤️ for the software development community


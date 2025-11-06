<?php

define ("DEBUG_MODE", FALSE);
// Includes
include_once("./include/padfile.php");
include_once("./include/padvalidator.php");

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$isAjax = $isAjax || (isset($_GET['format']) && $_GET['format'] == 'json');

// Read input
$URL = @$_POST["ValidateURL"];
if ( $URL == "" ) $URL = "https://";

// If AJAX request, return JSON
if ($isAjax && !empty($URL) && $URL != "https://" && $URL != "http://") {
    // Start output buffering early to catch any accidental output
    if (ob_get_level() == 0) {
        ob_start();
    } else {
        // Clear any existing buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
    }
    
    // Suppress warnings/notices that might corrupt JSON (but keep errors)
    $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);
    $oldDisplayErrors = ini_get('display_errors');
    ini_set('display_errors', '0');
    
    // Clear any output that might have been generated before this point
    ob_clean();
    
    // Set proper headers (must be before any output)
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    $result = array(
        'success' => false,
        'url' => $URL,
        'loading' => array('status' => '', 'message' => ''),
        'errors' => array(),
        'warnings' => array(),
        'summary' => array('errors' => 0, 'warnings' => 0),
        'xmlContent' => ''
    );
    
    // Create PAD file object
    $PAD = new PADFile($URL);
    if (empty($PAD->URL)) {
        $PAD->URL = $URL;
    }
    if (!isset($PAD->XML) || $PAD->XML === null) {
        $PAD->XML = new XMLNode("[root]");
    }
    
    // Load PAD file
    $result['loading']['status'] = 'loading';
    $result['loading']['message'] = 'Loading ' . $URL . '...';
    
    $PAD->Load();
    switch ( $PAD->LastError )
    {
      case ERR_NO_ERROR:
        $result['loading']['status'] = 'success';
        $result['loading']['message'] = 'File loaded successfully';
        break;
      case ERR_NO_URL_SPECIFIED:
        $result['loading']['status'] = 'error';
        $result['loading']['message'] = 'No URL specified.';
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        if ($json === false) {
            $result['loading']['message'] = 'No URL specified. (JSON encoding error)';
            $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
        // Clean buffer and output JSON
        ob_clean();
        if (isset($oldErrorReporting)) error_reporting($oldErrorReporting);
        if (isset($oldDisplayErrors)) ini_set('display_errors', $oldDisplayErrors);
        echo $json;
        if (ob_get_level() > 0) ob_end_flush();
        flush();
        exit(0);
      case ERR_READ_FROM_URL_FAILED:
        $result['loading']['status'] = 'error';
        $result['loading']['message'] = 'Cannot open URL.' . ($PAD->LastErrorMsg != "" ? " (" . $PAD->LastErrorMsg . ")" : "");
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        if ($json === false) {
            $result['loading']['message'] = 'Cannot open URL.';
            $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
        // Clean buffer and output JSON
        ob_clean();
        if (isset($oldErrorReporting)) error_reporting($oldErrorReporting);
        if (isset($oldDisplayErrors)) ini_set('display_errors', $oldDisplayErrors);
        echo $json;
        if (ob_get_level() > 0) ob_end_flush();
        flush();
        exit(0);
      case ERR_PARSE_ERROR:
        $result['loading']['status'] = 'error';
        $result['loading']['message'] = 'Parse Error: ' . $PAD->ParseError;
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        if ($json === false) {
            $result['loading']['message'] = 'Parse Error occurred.';
            $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
        // Clean buffer and output JSON
        ob_clean();
        if (isset($oldErrorReporting)) error_reporting($oldErrorReporting);
        if (isset($oldDisplayErrors)) ini_set('display_errors', $oldDisplayErrors);
        echo $json;
        if (ob_get_level() > 0) ob_end_flush();
        flush();
        exit(0);
    }
    
    // Create validator
    $PADValidator = new PADValidator("http://repository.appvisor.com/padspec/files/padspec.xml");
    if (empty($PADValidator->URL)) {
        $PADValidator->URL = "http://repository.appvisor.com/padspec/files/padspec.xml";
    }
    if (!isset($PADValidator->XML) || $PADValidator->XML === null) {
        $PADValidator->XML = new XMLNode("[root]");
    }
    
    if ( !$PADValidator->Load() )
    {
        $result['loading']['status'] = 'error';
        $result['loading']['message'] = 'Error loading Validator. Make sure to enable the allow_url_fopen PHP option or install cURL.';
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        if ($json === false) {
            $result['loading']['message'] = 'Error loading Validator.';
            $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
        // Clean buffer and output JSON
        ob_clean();
        if (isset($oldErrorReporting)) error_reporting($oldErrorReporting);
        if (isset($oldDisplayErrors)) ini_set('display_errors', $oldDisplayErrors);
        echo $json;
        if (ob_get_level() > 0) ob_end_flush();
        flush();
        exit(0);
    }
    
    // Validate
    $nErrors = $PADValidator->Validate($PAD);
    $nWarnings = count($PADValidator->ValidationWarnings);
    
    $result['summary']['errors'] = $nErrors;
    $result['summary']['warnings'] = $nWarnings;
    $result['success'] = true;
    
    // Store raw XML content for display (pretty-print it)
    $xmlContent = !empty($PAD->RawContent) ? $PAD->RawContent : '';
    if (!empty($xmlContent)) {
        // Ensure XML content is UTF-8 for JSON encoding
        if (!mb_check_encoding($xmlContent, 'UTF-8')) {
            $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', 'auto');
        }
        
        // Try to pretty-print the XML
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (@$dom->loadXML($xmlContent)) {
            $xmlContent = $dom->saveXML();
        }
    }
    // Ensure XML content doesn't break JSON (remove any invalid UTF-8 sequences)
    $result['xmlContent'] = mb_convert_encoding($xmlContent, 'UTF-8', 'UTF-8');
    
    // Collect warnings - use a separate buffer to capture Dump() output
    foreach($PADValidator->ValidationWarnings as $warn) {
        // Create a temporary buffer for this dump
        $tempBuffer = ob_get_level();
        ob_start();
        try {
            $warn->Dump();
            $warningHtml = ob_get_clean();
            // Restore buffer level
            while (ob_get_level() > $tempBuffer) {
                ob_end_clean();
            }
            // Clean up any output buffer issues
            if (!empty($warningHtml) && trim($warningHtml) !== '') {
                $result['warnings'][] = trim($warningHtml);
            }
        } catch (Exception $e) {
            // Clean up on error
            ob_end_clean();
            while (ob_get_level() > $tempBuffer) {
                ob_end_clean();
            }
            $result['warnings'][] = 'Warning: ' . htmlspecialchars($e->getMessage());
        }
    }
    
    // Collect errors - use a separate buffer to capture Dump() output
    foreach($PADValidator->ValidationErrors as $err) {
        // Create a temporary buffer for this dump
        $tempBuffer = ob_get_level();
        ob_start();
        try {
            $err->Dump();
            $errorHtml = ob_get_clean();
            // Restore buffer level
            while (ob_get_level() > $tempBuffer) {
                ob_end_clean();
            }
            // Clean up any output buffer issues
            if (!empty($errorHtml) && trim($errorHtml) !== '') {
                $result['errors'][] = trim($errorHtml);
            }
        } catch (Exception $e) {
            // Clean up on error
            ob_end_clean();
            while (ob_get_level() > $tempBuffer) {
                ob_end_clean();
            }
            $result['errors'][] = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
    
    // Clean all string values to ensure valid UTF-8
    array_walk_recursive($result, function(&$value) {
        if (is_string($value)) {
            // Remove any invalid UTF-8 sequences
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            // Remove null bytes and other problematic characters
            $value = str_replace("\0", '', $value);
        }
    });
    
    // Encode JSON with proper flags
    $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    
    // Check for JSON encoding errors
    if ($json === false) {
        $jsonError = json_last_error_msg();
        $jsonErrorCode = json_last_error();
        
        // Fallback: try without XML content if it's causing issues
        $result['xmlContent'] = ''; // Remove XML if it's causing issues
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        
        if ($json === false) {
            // Last resort: minimal error response
            $result = array(
                'success' => false,
                'url' => $URL,
                'loading' => array('status' => 'error', 'message' => 'JSON encoding error: ' . $jsonError . ' (code: ' . $jsonErrorCode . ')'),
                'errors' => array(),
                'warnings' => array(),
                'summary' => array('errors' => 0, 'warnings' => 0),
                'xmlContent' => ''
            );
            $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
    
    // Ensure no output before JSON - clear any remaining buffers except the main one
    $mainBufferLevel = ob_get_level();
    while (ob_get_level() > $mainBufferLevel) {
        ob_end_clean();
    }
    
    // Clean the main buffer to ensure no accidental output
    ob_clean();
    
    // Restore error reporting
    error_reporting($oldErrorReporting);
    ini_set('display_errors', $oldDisplayErrors);
    
    // Output JSON - ensure it's the only output
    echo $json;
    
    // Flush and end all buffers
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    
    // Exit immediately to prevent any trailing output
    exit(0);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAD File Validator</title>
    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-xml-doc.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        label {
            font-weight: 600;
            color: #555;
            font-size: 1rem;
        }
        
        input[type="text"] {
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            align-self: center;
            min-width: 150px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .status {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-loading {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-error {
            background: #ffebee;
            color: #c62828;
        }
        
        .report-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .report-header h2 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .report-header .url {
            color: #666;
            font-size: 0.9rem;
            word-break: break-all;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .error-item, .warning-item {
            background: #f8f9fa;
            border-left: 4px solid;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .error-item {
            border-color: #dc3545;
        }
        
        .warning-item {
            border-color: #ffc107;
        }
        
        .summary {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .summary-badge {
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .summary-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .summary-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .summary-badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 600px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .card {
                padding: 20px;
            }
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        #resultsContainer {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .xml-viewer {
            margin-top: 20px;
        }
        
        .xml-toggle {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-bottom: 10px;
        }
        
        .xml-toggle:hover {
            background: #5a6268;
        }
        
        .xml-container {
            display: none;
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            margin-top: 10px;
            max-height: 600px;
            overflow: auto;
            position: relative;
        }
        
        .xml-container.show {
            display: block;
        }
        
        .xml-container pre {
            margin: 0;
            padding: 0;
            background: transparent !important;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .xml-container code {
            background: transparent !important;
            color: inherit;
        }
        
        /* Prism.js theme overrides for dark background */
        .xml-container .token.tag,
        .xml-container .token.punctuation {
            color: #569cd6;
        }
        
        .xml-container .token.attr-name {
            color: #9cdcfe;
        }
        
        .xml-container .token.attr-value {
            color: #ce9178;
        }
        
        .xml-container .token.comment {
            color: #6a9955;
        }
        
        .xml-container .token.prolog {
            color: #569cd6;
        }
        
        .copy-xml-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .copy-xml-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .copy-xml-btn.copied {
            background: #28a745;
            border-color: #28a745;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('validateForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            const resultsContainer = document.getElementById('resultsContainer');
            const urlInput = document.getElementById('ValidateURL');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const url = urlInput.value.trim();
                if (!url || url === 'https://' || url === 'http://') {
                    alert('Please enter a valid PAD file URL');
                    return;
                }
                
                // Disable form
                submitBtn.disabled = true;
                btnText.style.display = 'none';
                btnSpinner.style.display = 'inline';
                
                // Clear previous results
                resultsContainer.innerHTML = '<div class="card"><div class="status status-loading">⏳ Loading <strong>' + escapeHtml(url) + '</strong>...</div></div>';
                
                // Scroll to results
                resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Create FormData
                const formData = new FormData();
                formData.append('ValidateURL', url);
                
                // Create XHR request
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.pathname + '?format=json', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    submitBtn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                    
                    if (xhr.status === 200) {
                        try {
                            // Check if response is empty
                            if (!xhr.responseText || xhr.responseText.trim() === '') {
                                resultsContainer.innerHTML = '<div class="card"><div class="status status-error">❌ Empty response from server</div></div>';
                                return;
                            }
                            
                            // Check if response looks like JSON (starts with { or [)
                            const trimmed = xhr.responseText.trim();
                            if (trimmed.length === 0) {
                                resultsContainer.innerHTML = '<div class="card"><div class="status status-error">❌ Empty response from server</div></div>';
                                return;
                            }
                            
                            if (trimmed[0] !== '{' && trimmed[0] !== '[') {
                                // Response doesn't look like JSON
                                let errorMsg = 'Response is not valid JSON. First 500 characters:<br><pre style="background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;max-height:200px;white-space:pre-wrap;">' + escapeHtml(trimmed.substring(0, 500)) + '</pre>';
                                errorMsg += '<br><strong>Response length:</strong> ' + xhr.responseText.length + ' characters';
                                errorMsg += '<br><strong>Content-Type:</strong> ' + (xhr.getResponseHeader('Content-Type') || 'not set');
                                resultsContainer.innerHTML = '<div class="card"><div class="status status-error">❌ ' + errorMsg + '</div></div>';
                                return;
                            }
                            
                            // Try to parse JSON
                            const result = JSON.parse(trimmed);
                            displayResults(result);
                        } catch (e) {
                            // Show detailed error information
                            let errorMsg = 'Error parsing JSON response: ' + escapeHtml(e.message);
                            if (e instanceof SyntaxError) {
                                errorMsg += '<br><strong>JSON Syntax Error</strong>';
                                if (e.message.includes('position')) {
                                    errorMsg += ' at position ' + (e.message.match(/\d+/) || ['unknown'])[0];
                                }
                            }
                            if (xhr.responseText) {
                                const preview = xhr.responseText.substring(0, 500);
                                const lastChars = xhr.responseText.length > 100 ? 
                                    xhr.responseText.substring(xhr.responseText.length - 100) : '';
                                errorMsg += '<br><br><strong>Response preview (first 500 chars):</strong><br><pre style="background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;max-height:200px;white-space:pre-wrap;font-size:0.85rem;">' + escapeHtml(preview) + '</pre>';
                                if (lastChars) {
                                    errorMsg += '<br><strong>Last 100 characters:</strong><br><pre style="background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;max-height:100px;white-space:pre-wrap;font-size:0.85rem;">' + escapeHtml(lastChars) + '</pre>';
                                }
                                errorMsg += '<br><strong>Response length:</strong> ' + xhr.responseText.length + ' characters';
                                errorMsg += '<br><strong>Content-Type:</strong> ' + (xhr.getResponseHeader('Content-Type') || 'not set');
                            }
                            resultsContainer.innerHTML = '<div class="card"><div class="status status-error">❌ ' + errorMsg + '</div></div>';
                        }
                    } else {
                        let errorMsg = 'Server error: ' + xhr.status + ' ' + xhr.statusText;
                        if (xhr.responseText) {
                            errorMsg += '<br><br><strong>Response:</strong><br><pre style="background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;max-height:200px;white-space:pre-wrap;">' + escapeHtml(xhr.responseText.substring(0, 500)) + '</pre>';
                        }
                        resultsContainer.innerHTML = '<div class="card"><div class="status status-error">❌ ' + errorMsg + '</div></div>';
                    }
                };
                
                xhr.onerror = function() {
                    submitBtn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                    resultsContainer.innerHTML = '<div class="card"><div class="status status-error">❌ Network error. Please check your connection and try again.</div></div>';
                };
                
                xhr.send(formData);
            });
            
            function displayResults(result) {
                let html = '<div class="card">';
                
                // Loading status
                const statusClass = result.loading.status === 'success' ? 'status-success' : 
                                   result.loading.status === 'error' ? 'status-error' : 'status-loading';
                const statusIcon = result.loading.status === 'success' ? '✅' : 
                                  result.loading.status === 'error' ? '❌' : '⏳';
                html += '<div class="status ' + statusClass + '">' + statusIcon + ' ' + escapeHtml(result.loading.message) + '</div>';
                
                if (result.loading.status === 'error') {
                    html += '</div>';
                    resultsContainer.innerHTML = html;
                    return;
                }
                
                // Report header
                html += '<div class="report-header">';
                html += '<h2>Validation Report</h2>';
                html += '<div class="url">' + escapeHtml(result.url) + '</div>';
                html += '</div>';
                
                // Summary badges
                html += '<div class="summary">';
                if (result.summary.errors == 0) {
                    html += '<div class="summary-badge success">✅ No Errors</div>';
                } else {
                    html += '<div class="summary-badge error">❌ ' + result.summary.errors + ' Error' + (result.summary.errors != 1 ? 's' : '') + '</div>';
                }
                if (result.summary.warnings > 0) {
                    html += '<div class="summary-badge warning">⚠️ ' + result.summary.warnings + ' Warning' + (result.summary.warnings != 1 ? 's' : '') + '</div>';
                }
                html += '</div>';
                
                // Warnings
                if (result.summary.warnings > 0) {
                    html += '<div class="alert alert-warning">';
                    html += '<div class="alert-icon">⚠️</div>';
                    html += '<div class="alert-content">';
                    html += '<div class="alert-title">' + result.summary.warnings + ' Warning' + (result.summary.warnings != 1 ? 's' : '') + ' Found</div>';
                    result.warnings.forEach(function(warning) {
                        html += '<div class="warning-item">' + warning + '</div>';
                    });
                    html += '</div></div>';
                }
                
                // Errors or Success
                if (result.summary.errors == 0) {
                    html += '<div class="alert alert-success">';
                    html += '<div class="alert-icon">✅</div>';
                    html += '<div class="alert-content">';
                    html += '<div class="alert-title">Validation Successful</div>';
                    html += '<div>Your PAD file passed all validation checks!</div>';
                    html += '</div></div>';
                } else {
                    html += '<div class="alert alert-error">';
                    html += '<div class="alert-icon">❌</div>';
                    html += '<div class="alert-content">';
                    html += '<div class="alert-title">' + result.summary.errors + ' Error' + (result.summary.errors != 1 ? 's' : '') + ' Found</div>';
                    result.errors.forEach(function(error) {
                        html += '<div class="error-item">' + error + '</div>';
                    });
                    html += '</div></div>';
                }
                
                // XML Viewer
                if (result.xmlContent) {
                    html += '<div class="xml-viewer">';
                    html += '<button class="xml-toggle" onclick="toggleXmlView(this)">📄 View XML Source</button>';
                    html += '<div class="xml-container">';
                    html += '<button class="copy-xml-btn" onclick="copyXmlContent(this)" title="Copy to clipboard">📋 Copy</button>';
                    html += '<pre><code class="language-xml">' + escapeHtml(result.xmlContent) + '</code></pre>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '</div>';
                resultsContainer.innerHTML = html;
                
                // Highlight XML syntax when container is shown
                // Prism will auto-highlight when the container becomes visible
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
        
        function toggleXmlView(btn) {
            const container = btn.nextElementSibling;
            if (container.classList.contains('show')) {
                container.classList.remove('show');
                btn.textContent = '📄 View XML Source';
            } else {
                container.classList.add('show');
                btn.textContent = '📄 Hide XML Source';
                // Highlight syntax when shown
                if (typeof Prism !== 'undefined') {
                    // Use highlightElement for better performance
                    const code = container.querySelector('code');
                    if (code && !code.classList.contains('language-xml')) {
                        code.className = 'language-xml';
                    }
                    Prism.highlightElement(code);
                }
            }
        }
        
        function copyXmlContent(btn) {
            const container = btn.closest('.xml-container');
            const code = container.querySelector('code');
            const text = code.textContent;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    btn.textContent = '✓ Copied!';
                    btn.classList.add('copied');
                    setTimeout(function() {
                        btn.textContent = '📋 Copy';
                        btn.classList.remove('copied');
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    btn.textContent = '✓ Copied!';
                    btn.classList.add('copied');
                    setTimeout(function() {
                        btn.textContent = '📋 Copy';
                        btn.classList.remove('copied');
                    }, 2000);
                } catch (err) {
                    alert('Failed to copy');
                }
                document.body.removeChild(textarea);
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 PAD File Validator</h1>
            <p>Validate your Portable Application Description (PAD) XML files</p>
            <div style="margin-top: 20px;">
                <a href="editor.php" class="btn" style="text-decoration: none; display: inline-block;">✏️ Open Editor</a>
            </div>
        </div>
        
        <div class="card">
            <form id="validateForm" method='POST'>
                <div class="form-group">
                    <label for="ValidateURL">PAD File URL</label>
                    <input type='text' 
                           id="ValidateURL"
                           name='ValidateURL' 
                           placeholder='https://example.com/padfile.xml'
                           value='<?php echo htmlspecialchars($URL); ?>'
                           required>
                    <button type='submit' class="btn" id="submitBtn">
                        <span id="btnText">Validate PAD File</span>
                        <span id="btnSpinner" style="display: none;">⏳ Validating...</span>
                    </button>
                </div>
            </form>
        </div>
        
        <div id="resultsContainer"></div>
        
        <a name="validate"></a>
<?php

// Only show results on initial page load (not AJAX)
if (!$isAjax) {
// Create PAD file object
$PAD = new PADFile($URL);
// Ensure URL is set (workaround for constructor issue)
if (empty($PAD->URL)) {
    $PAD->URL = $URL;
}
// Ensure XML is initialized
if (!isset($PAD->XML) || $PAD->XML === null) {
    $PAD->XML = new XMLNode("[root]");
}

// If the form above has been posted, load the PAD file from the entered URL
if ( $URL != "https://" && $URL != "http://" && !empty($URL) )
{
  echo '<div class="card">';
  echo '<div class="status status-loading">';
  echo '⏳ Loading <strong>' . htmlspecialchars($PAD->URL) . '</strong>...';
  echo '</div>';
  flush();
  
  $PAD->Load();
  switch ( $PAD->LastError )
  {
    case ERR_NO_ERROR:
      echo '<div class="status status-success">';
      echo '✅ File loaded successfully';
      echo '</div>';
      break;
    case ERR_NO_URL_SPECIFIED:
      echo '<div class="status status-error">';
      echo '❌ No URL specified.';
      echo '</div>';
      echo '</div></body></html>';
      exit;
    case ERR_READ_FROM_URL_FAILED:
      echo '<div class="status status-error">';
      echo '❌ Cannot open URL.';
      if ($PAD->LastErrorMsg != "")
        echo ' (' . htmlspecialchars($PAD->LastErrorMsg) . ')';
      echo '</div>';
      echo '</div></body></html>';
      exit;
    case ERR_PARSE_ERROR:
      echo '<div class="status status-error">';
      echo '❌ Parse Error: ' . htmlspecialchars($PAD->ParseError);
      echo '</div>';
      echo '</div></body></html>';
      exit;
  }

  // Output
  echo '<div class="report-header">';
  echo '<h2>Validation Report</h2>';
  echo '<div class="url">' . htmlspecialchars($PAD->URL) . '</div>';
  echo '</div>';

  // Create validator
  $PADValidator = new PADValidator("http://repository.appvisor.com/padspec/files/padspec.xml");
  // Ensure URL and XML are set (workaround for constructor issue)
  if (empty($PADValidator->URL)) {
      $PADValidator->URL = "http://repository.appvisor.com/padspec/files/padspec.xml";
  }
  if (!isset($PADValidator->XML) || $PADValidator->XML === null) {
      $PADValidator->XML = new XMLNode("[root]");
  }
  if ( !$PADValidator->Load() )
  {
    echo '<div class="alert alert-error">';
    echo '<div class="alert-icon">⚠️</div>';
    echo '<div class="alert-content">';
    echo '<div class="alert-title">Error loading Validator</div>';
    echo '<div>Make sure to enable the allow_url_fopen PHP option or install cURL.</div>';
    echo '</div></div>';
  }
  else
  {
    // Validate
    $nErrors = $PADValidator->Validate($PAD);
    $nWarnings = count($PADValidator->ValidationWarnings);
    
    // Summary badges
    echo '<div class="summary">';
    if ( $nErrors == 0 ) {
      echo '<div class="summary-badge success">✅ No Errors</div>';
    } else {
      echo '<div class="summary-badge error">❌ ' . $nErrors . ' Error' . ($nErrors != 1 ? 's' : '') . '</div>';
    }
    if ( $nWarnings > 0 ) {
      echo '<div class="summary-badge warning">⚠️ ' . $nWarnings . ' Warning' . ($nWarnings != 1 ? 's' : '') . '</div>';
    }
    echo '</div>';
    
    // Display warnings first (if any)
    if ( $nWarnings > 0 )
    {
      echo '<div class="alert alert-warning">';
      echo '<div class="alert-icon">⚠️</div>';
      echo '<div class="alert-content">';
      echo '<div class="alert-title">' . $nWarnings . ' Warning' . ($nWarnings != 1 ? 's' : '') . ' Found</div>';
      foreach($PADValidator->ValidationWarnings as $warn)
      {
        echo '<div class="warning-item">';
        $warn->Dump();
        echo '</div>';
      }
      echo '</div></div>';
    }
    
    // Display errors
    if ( $nErrors == 0 )
    {
      echo '<div class="alert alert-success">';
      echo '<div class="alert-icon">✅</div>';
      echo '<div class="alert-content">';
      echo '<div class="alert-title">Validation Successful</div>';
      echo '<div>Your PAD file passed all validation checks!</div>';
      echo '</div></div>';
    }
    else
    {
      echo '<div class="alert alert-error">';
      echo '<div class="alert-icon">❌</div>';
      echo '<div class="alert-content">';
      echo '<div class="alert-title">' . $nErrors . ' Error' . ($nErrors != 1 ? 's' : '') . ' Found</div>';
      foreach($PADValidator->ValidationErrors as $err)
      {
        echo '<div class="error-item">';
        $err->Dump();
        echo '</div>';
      }
      echo '</div></div>';
    }

    if ( DEBUG_MODE )
    {
      $nErrors = $PADValidator->ValidateRegEx($PAD);
      if ( $nErrors == 0 )
      {
        // No errors
        echo '<div class="alert alert-success">';
        echo '<div class="alert-icon">✅</div>';
        echo '<div class="alert-content">';
        echo '<div class="alert-title">No RegExp Errors</div>';
        echo '</div></div>';
      }
      else
      {
        // Print validation errors
        echo '<div class="alert alert-error">';
        echo '<div class="alert-icon">❌</div>';
        echo '<div class="alert-content">';
        echo '<div class="alert-title">' . $nErrors . ' RegExp Error' . ($nErrors != 1 ? 's' : '') . '</div>';
        foreach($PADValidator->ValidationErrors as $err)
        {
          echo '<div class="error-item">';
          $err->Dump();
          echo '</div>';
        }
        echo '</div></div>';
      }
    }

  }
  echo '</div>'; // Close card
}
} // End if (!$isAjax)
?>

    </div>
</body>
</html>


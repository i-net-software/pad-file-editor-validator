<?php

//////////////////////////////////////////////////////////////////////////////
// PAD SDK Version 2.0
//
// Copyright 2004-2006 by Association of Shareware Professionals, Inc.
// All Rights Reserved.
// PAD, PADGen, and PADKit are trademarks of the Association of Shareware
// Professionals in the United States and/or other countries.
//
// Use the PAD SDK on your own risk. Read the disclaimer in index.html
// Use, modify and distribute the SDK for free - but do not remove or modify
// this complete copyright and disclaimer section.
//
// http://www.asp-shareware.org/pad/
//
//////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////
// XMLFILE.PHP
//
// DESCRIPTION
//
// Representation of a XML file in the XMLFile base class.
// The XML can be loaded from an URL or from a local file. After loading the
// XML, a tree of XMLNode objects will be created based on the XML. Now the
// tree can be used to walk through, search for a node, etc.
//
// SAMPLE CODE
//
// // Create XML file object for an URL (this could also be the path to a local file)
// $XMLFile = new XMLFile("http://host.com/file.xml");
//
// // Load file (see Constants section for possible error codes)
// if ( !$XMLFile->Load() )
//   die "Cannot load XML. Error Code: " . $XMLFile->LastError;
//
// // Walk through the first level of the tree
// foreach($XMLFile->XML->ChildNodes as $Node)
//   echo $Node->Name . " = " . $Node->Value;
//
// // Find a specific node value
// echo $XMLFile->XML->GetValue("tag1/tag2/tag3");
//
// HISTORY
//
// 2006-08-31 PHP5 compatibility: avoid allow_call_time_pass_reference
//            warning (Oliver Grahl, ASP PAD Support)
// 2006-06-20 PHP5 compatibility (Oliver Grahl, ASP PAD Support)
// 2006-02-17 LastErrorMsg: additional error message when Load() failed
//            (Oliver Grahl, ASP PAD Support)
// 2004-11-11 Fixed bug with encoding conversion (Oliver Grahl, ASP PAD Support)
// 2004-10-29 Improved support for other encodings, like Windows-1252
//            (Oliver Grahl, ASP PAD Support)
// 2004-09-30 Improved UTF-8 support, added XMLFile->OutputEncoding, which
//            defaults to ISO-8859-1 (Oliver Grahl, ASP PAD Support)
// 2004-08-16 Initial Release (Oliver Grahl, ASP PAD Support)
//
//////////////////////////////////////////////////////////////////////////////


//////////////////////////////////////////////////////////////////////////////
// Constants
//////////////////////////////////////////////////////////////////////////////

// Error values
define("ERR_NO_ERROR",              0);
define("ERR_NO_URL_SPECIFIED",      1);
define("ERR_READ_FROM_URL_FAILED",  2);
define("ERR_PARSE_ERROR",           3);


//////////////////////////////////////////////////////////////////////////////
// Classes
//////////////////////////////////////////////////////////////////////////////

// XMLNode class
// Represents a simple XML Node with tag name, value and an array of child nodes
class XMLNode
{
  //////////////////////////////////////////////////////////////////////////////
  // Public Properties
  //////////////////////////////////////////////////////////////////////////////

  var $Name;
  var $Value = "";
  var $ParentNode;
  var $ChildNodes;
  var $Level = 0;


  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  function XMLNode($Name)
  {
    // Initializations
    $this->Name = $Name;
    $this->ChildNodes = array();
  }


  //////////////////////////////////////////////////////////////////////////////
  // Public Methods
  //////////////////////////////////////////////////////////////////////////////

  // Set parent node
  // IN: &$ParentNode - reference to the new parent node
  function SetParent(&$ParentNode)
  {
    $this->ParentNode = &$ParentNode;
    $this->Level = $this->ParentNode->Level + 1;
  }


  // Clear contents
  function Clear()
  {
    $this->Name = "";
    $this->ChildNodes = array();
    unset($this->ParentNode);
    $this->Level = 0;
  }

  // Append a node
  // IN:      $Name - the tag name of the node to add
  // RETURNS: reference to the XMLNode object that has been created
  function &AppendNode($Name)
  {
    $node = new XMLNode($Name);
    $node->SetParent($this);

    // Do not use array_push with pass-by-reference any more to avoid
    // allow_call_time_pass_reference warning (Oliver Grahl, 2006-08-31)
    //array_push($this->ChildNodes, &$node);
    $this->ChildNodes[] =& $node;

    return $node;
  }

  // Returns the node according to the path (/-separated)
  // IN:      $Path  - the path to the XML node, e.g. Root/Child/Name
  // RETURNS: reference to the XMLNode object, NULL if node is not found
  function &FindNodeByPath($Path)
  {
    // To make PHP5 happy, we will not return NULL, but a variable which
    // has a value of NULL.
    $NULL = NULL;

    $tags = explode("/", $Path);

    if ( count($tags) <= 0 )
      return $NULL;

    $node =& $this;
    foreach($tags as $tag)
    {
      if ( $tag != "" )
      {
        $found =& $node->FindNodeByName($tag);
        if ( $found === NULL )
          return $NULL;
        $node =& $found;
      }
    }

    return $node;
  }

  // Returns a node value according to the path (/-separated)
  // IN:      $Path    - the path to the XML node, e.g. Root/Child/Name
  // IN:      $Default - value to use if node does not exists (optional)
  // RETURNS: value of the node as string, empty string or default value if
  //          node is not found
  function GetValue($Path, $Default = "")
  {
    $node =& $this->FindNodeByPath($Path);
    if ( $node )
      return $node->Value;
    else
      return $Default;
  }

  // Transforms XML tree to XML text
  // RETURNS: the XML string
  function ToString()
  {
    if ( $this->Level == 0 )
    {
      // This is the root node, only walk through children
      $out = "";
      foreach($this->ChildNodes as $node)
        $out .= $node->ToString();
    }
    else
    {
      // Print node depending of it's type
      $indent = str_repeat("\t", $this->Level - 1);

      if ( count($this->ChildNodes) <= 0 )
      {
        // A node without children
        if ( $this->Value == "" )
          $out = $indent . "<" . $this->Name . " />\n";
        else
          $out = $indent . "<" . $this->Name . ">" . $this->Value . "</" . $this->Name . ">\n";
      }
      else
      {
        // A node with children
        $out = $indent . "<" . $this->Name . ">" . $this->Value . "\n";
        foreach($this->ChildNodes as $node)
          $out .= $node->ToString();
        $out .= $indent . "</" . $this->Name . ">\n";
      }
    }

    return $out;
  }

  // Dumps the XML text to HTML
  function Dump()
  {
    $str = $this->ToString();
    if ($str === null) {
      $str = "";
    }
    echo "<pre>" . htmlspecialchars($str) . "</pre>";
  }

  // Returns the node according to the name
  // IN:      $Name  - the name of the XML child node, e.g. Child
  // RETURNS: reference to the XMLNode object, NULL if node is not found
  function &FindNodeByName($Name)
  {
    if (isset($this->ChildNodes) && is_array($this->ChildNodes)) {
      foreach($this->ChildNodes as $node)
        if ( $node->Name == $Name )
          return $node;
    }

    // To make PHP5 happy, we will not return NULL, but a variable which
    // has a value of NULL.
    $NULL = NULL;
    return $NULL;
  }
}


// XMLFile class
// Represents the XML file read from URL (URL or local file).
// Parses the XML into a tree of XMLNode objects (property XML).
class XMLFile
{
  //////////////////////////////////////////////////////////////////////////////
  // Public Properties
  //////////////////////////////////////////////////////////////////////////////

  var $URL = "";
  var $XML;
  var $ParseError = "";
  var $LastError = ERR_NO_ERROR;
  var $LastErrorMsg = "";
  var $OutputEncoding = "ISO-8859-1";
  var $EncodingWarning = "";  // Warning message if encoding mismatch detected
  var $DeclaredEncoding = ""; // Encoding declared in XML
  var $DetectedEncoding = ""; // Actual encoding detected
  var $RawContent = ""; // Raw XML content for display

  //////////////////////////////////////////////////////////////////////////////
  // Private Properties - DO NOT CALL FROM EXTERNAL
  //////////////////////////////////////////////////////////////////////////////

  var $_CurrentNode;


  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  // IN: $URL - the URL or local path of the XML file (optional)
  function XMLFile($URL = "")
  {
    // Initializations
    $this->URL = $URL;
    $this->XML = new XMLNode("[root]");
  }


  //////////////////////////////////////////////////////////////////////////////
  // Public Methods
  //////////////////////////////////////////////////////////////////////////////

  // Load file from URL
  // RETURNS: true  - Success (LastError is ERR_NO_ERROR)
  //          false - Failure (see LastError, LastErrorMsg)
  function Load()
  {
    $this->LastErrorMsg = "";

    // Check if URL exists
    if ( $this->URL == "" )
    {
      $this->LastError = ERR_NO_URL_SPECIFIED;
      return false;
    }

    // Set track_errors, so that $php_errormsg can be used
    // (possibly this will fail if PHP is running in SAFE MODE)
    ini_set('track_errors', true);

    // Try to read the file using file_get_contents (better HTTPS support)
    $raw = false;
    $isHttp = (strpos($this->URL, 'http://') === 0 || strpos($this->URL, 'https://') === 0);
    
    if (function_exists('curl_init') && $isHttp)
    {
      // Use cURL for better HTTPS support and localhost compatibility
      // For localhost URLs, try to use 127.0.0.1 explicitly to avoid IPv6 issues
      $urlToUse = $this->URL;
      if (strpos($this->URL, 'localhost') !== false) {
        // Replace localhost with 127.0.0.1 to force IPv4
        $urlToUse = str_replace('localhost', '127.0.0.1', $this->URL);
      }
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $urlToUse);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_USERAGENT, 'PAD Validator');
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      
      // Force IPv4 for localhost connections to avoid IPv6 issues
      if (strpos($urlToUse, '127.0.0.1') !== false || strpos($this->URL, 'localhost') !== false) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
      }
      
      $raw = curl_exec($ch);
      $curl_error = curl_error($ch);
      $curl_errno = curl_errno($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      // If localhost failed, try the original URL as fallback
      if ($raw === false && $urlToUse !== $this->URL && $curl_errno == 7) {
        // Retry with original localhost URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PAD Validator');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $raw = curl_exec($ch);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
      }
      
      if ($raw === false)
      {
        $this->LastError = ERR_READ_FROM_URL_FAILED;
        $errorMsg = $curl_error ? $curl_error : "cURL failed";
        if ($curl_errno) {
          $errorMsg .= " (Error code: $curl_errno)";
          if ($curl_errno == 7) {
            $errorMsg .= " - Connection refused. Make sure the server is running and accessible.";
          }
        }
        $this->LastErrorMsg = $errorMsg;
        return false;
      }
      
      // Check HTTP response code
      if ($http_code >= 400) {
        $this->LastError = ERR_READ_FROM_URL_FAILED;
        $this->LastErrorMsg = "HTTP error: $http_code";
        return false;
      }
    }
    else
    {
      // Check if allow_url_fopen is enabled (required for HTTP URLs)
      if ($isHttp && !ini_get('allow_url_fopen')) {
        $this->LastError = ERR_READ_FROM_URL_FAILED;
        $this->LastErrorMsg = "allow_url_fopen is disabled in PHP configuration. Cannot load HTTP/HTTPS URLs without cURL or allow_url_fopen enabled.";
        return false;
      }
      
      // Fall back to file_get_contents with stream context for HTTP/HTTPS
      $context = null;
      if ($isHttp) {
        $contextOptions = array(
          'http' => array(
            'method' => 'GET',
            'header' => "User-Agent: PAD Validator\r\n",
            'timeout' => 30,
            'ignore_errors' => true,
            'follow_location' => true
          )
        );
        
        // Add SSL options only for HTTPS
        if (strpos($this->URL, 'https://') === 0) {
          $contextOptions['ssl'] = array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
          );
        }
        
        $context = stream_context_create($contextOptions);
      }
      
      $raw = @file_get_contents($this->URL, false, $context);
      if ($raw === false)
      {
        // Try file() as fallback
        $raw = @file($this->URL);
        if ($raw !== false)
        {
          $raw = implode("", $raw);
        }
      }
    }
    
    if ( $raw === false || $raw === "" )
    {
      $this->LastError = ERR_READ_FROM_URL_FAILED;
      $errorDetails = array();
      
      if (isset($php_errormsg) && !empty(trim($php_errormsg)))
      {
        $errorDetails[] = trim($php_errormsg);
      }
      
      // Provide more helpful error messages
      if (strpos($this->URL, 'localhost') !== false || strpos($this->URL, '127.0.0.1') !== false) {
        $errorDetails[] = "Make sure your local server is running and accessible at this URL.";
        $errorDetails[] = "Try accessing the URL directly in your browser to verify it's working.";
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
          $errorDetails[] = "Both cURL and allow_url_fopen are unavailable. Enable at least one in PHP configuration.";
        }
      }
      
      if (empty($errorDetails)) {
        $this->LastErrorMsg = "Failed to read from URL. Check that the URL is accessible and your server configuration allows HTTP requests.";
      } else {
        $this->LastErrorMsg = implode(" ", $errorDetails);
      }
      return false;
    }

    // Remove Byte-Order-Mark (BOM) if existing
    // (required to avoid problems on some PHP versions)
    // UNCOMMENT THIS LINE IF YOU HAVE PROBLEMS WITH PAD FILES WITH A BOM
    // $raw = substr($raw, strpos($raw, "<"));

    // Store raw content for display (before parsing/conversion)
    $this->RawContent = $raw;

    // Parse the raw XML into Nodes
    if ( !$this->Parse($raw) )
    {
      $this->LastError = ERR_PARSE_ERROR;
      return false;
    }

    // Succeeded
    $this->LastError = ERR_NO_ERROR;
    return true;
  }

  // Read from XML
  // IN:      $Raw  - the XML string
  // RETURNS: true  - Success
  //          false - Failure (see ParseError)
  function Parse($Raw)
  {
    // Inits
    $this->ParseError = "";
    // Ensure XML is initialized
    if (!isset($this->XML) || $this->XML === null) {
      $this->XML = new XMLNode("[root]");
    }
    $this->XML->Clear();
    $this->_CurrentNode =& $this->XML;

    // Look up the XML encoding declaration
    $this->DeclaredEncoding = "UTF-8";
    $this->DetectedEncoding = "";
    $this->EncodingWarning = "";
    
    if ( preg_match("/<\?xml [^>]*encoding=['\"](.*?)['\"][^>]*>.*/", $Raw, $m) )
      $this->DeclaredEncoding = strtoupper(trim($m[1]));
    
    // Detect actual encoding - XML may declare one encoding but actually use another
    $actual_encoding = $this->DeclaredEncoding;
    $encoding_mismatch = false;
    
    if (function_exists('mb_check_encoding') && function_exists('mb_detect_encoding')) {
      // First, check if the declared encoding matches the actual content
      if ($this->DeclaredEncoding == 'UTF-8') {
        // Check if content is actually valid UTF-8
        if (!mb_check_encoding($Raw, 'UTF-8')) {
          $encoding_mismatch = true;
          // Try to detect the actual encoding
          $detected = mb_detect_encoding($Raw, array('UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-2'), true);
          if ($detected !== false && $detected != 'UTF-8') {
            $actual_encoding = $detected;
            $this->DetectedEncoding = $detected;
            $this->EncodingWarning = "XML declares encoding as '" . $this->DeclaredEncoding . "' but content appears to be '" . $detected . "'. The file has been converted to UTF-8 for processing.";
          } else {
            // Couldn't detect, but it's not valid UTF-8
            $actual_encoding = 'ISO-8859-1'; // Safe default for Western European
            $this->DetectedEncoding = 'ISO-8859-1 (assumed)';
            $this->EncodingWarning = "XML declares encoding as '" . $this->DeclaredEncoding . "' but content is not valid UTF-8. Assuming ISO-8859-1 and converting to UTF-8.";
          }
        }
      } else {
        // Declared encoding is not UTF-8, verify it's correct
        if (function_exists('mb_check_encoding')) {
          // Try to verify the declared encoding
          $is_valid = @mb_check_encoding($Raw, $this->DeclaredEncoding);
          if (!$is_valid) {
            // Declared encoding doesn't match, try to detect
            $detected = mb_detect_encoding($Raw, array('UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15', $this->DeclaredEncoding), true);
            if ($detected !== false && strtoupper($detected) != $this->DeclaredEncoding) {
              $encoding_mismatch = true;
              $actual_encoding = $detected;
              $this->DetectedEncoding = $detected;
              $this->EncodingWarning = "XML declares encoding as '" . $this->DeclaredEncoding . "' but content appears to be '" . $detected . "'. The file has been converted to UTF-8 for processing.";
            }
          }
        }
      }
    }
    
    // Convert to UTF-8 if needed (for consistent processing)
    $needs_conversion = ($actual_encoding != 'UTF-8');
    if ($needs_conversion && function_exists('mb_convert_encoding')) {
      $converted = @mb_convert_encoding($Raw, 'UTF-8', $actual_encoding);
      if ($converted !== false && $converted !== '' && $converted != $Raw) {
        $Raw = $converted;
        // Update XML declaration to reflect UTF-8 (preserve version)
        $Raw = preg_replace("/(<\?xml [^>]*encoding=['\"])(.*?)(['\"][^>]*>)/", "$1UTF-8$3", $Raw);
      } else if ($needs_conversion) {
        // Conversion failed, but we'll try to proceed anyway
        if (empty($this->EncodingWarning)) {
          $this->EncodingWarning = "Failed to convert from '" . $actual_encoding . "' to UTF-8. Proceeding with original encoding.";
        }
      }
    }
    
    $encoding = "UTF-8"; // Always use UTF-8 for XML parser after conversion

    // Try using DOMDocument first (most robust with encoding)
    if (function_exists('dom_import_simplexml') || class_exists('DOMDocument')) {
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      $dom = new DOMDocument();
      $dom->encoding = 'UTF-8';
      $loaded = @$dom->loadXML($Raw);
      $errors = libxml_get_errors();
      libxml_clear_errors();
      
      if ($loaded !== false) {
        // Successfully parsed with DOMDocument, convert to SimpleXML then to our structure
        $sxml = simplexml_import_dom($dom);
        if ($sxml !== false) {
          $this->XML->Clear();
          $this->_CurrentNode =& $this->XML;
          // Process all root-level children (SimpleXML gives us the root element directly)
          foreach ($sxml->children() as $child) {
            $this->_SimpleXMLToXMLNode($child, $this->XML);
          }
          unset($this->_CurrentNode);
          return true;
        }
      }
    }
    
    // Try using SimpleXML directly (more robust with encoding)
    if (function_exists('simplexml_load_string')) {
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      $sxml = @simplexml_load_string($Raw, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
      $errors = libxml_get_errors();
      libxml_clear_errors();
      
      if ($sxml !== false) {
        // Successfully parsed with SimpleXML, now convert to our XMLNode structure
        $this->XML->Clear();
        $this->_CurrentNode =& $this->XML;
        // Process all root-level children (SimpleXML gives us the root element directly)
        foreach ($sxml->children() as $child) {
          $this->_SimpleXMLToXMLNode($child, $this->XML);
        }
        unset($this->_CurrentNode);
        return true;
      }
    }
    
    // Fall back to xml_parse
    // Create and initialize parser
    $xml_parser = xml_parser_create($encoding);
    xml_parser_set_option($xml_parser, XML_OPTION_TARGET_ENCODING, $this->OutputEncoding);
    xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
    // PHP 8.4+ compatibility: use callable arrays instead of xml_set_object
    if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
      xml_set_element_handler($xml_parser, array($this, "_StartElement"), array($this, "_EndElement"));
      xml_set_character_data_handler($xml_parser, array($this, "_CharacterData"));
    } else {
      // Do not pass by reference (&$this) any more to avoid
      // allow_call_time_pass_reference warning (Oliver Grahl, 2006-08-31)
      xml_set_object($xml_parser, $this);
      xml_set_element_handler($xml_parser, "_StartElement", "_EndElement");
      xml_set_character_data_handler($xml_parser, "_CharacterData");
    }

    // Parse
    if ( !xml_parse($xml_parser, $Raw, true) )
    {
      // Save parse error and free parser
      $this->ParseError = sprintf("%s at line %d",
        xml_error_string(xml_get_error_code($xml_parser)),
        xml_get_current_line_number($xml_parser));
      xml_parser_free($xml_parser);
      unset($this->_CurrentNode);
      return false;
    }

    // Free parser
    xml_parser_free($xml_parser);
    unset($this->_CurrentNode);

    return true;
  }


  //////////////////////////////////////////////////////////////////////////////
  // Private methods - DO NOT CALL FROM EXTERNAL
  //////////////////////////////////////////////////////////////////////////////

  // XML Parser: Node start
  function _StartElement($parser, $name, $attrs) {
    $this->_CurrentNode =& $this->_CurrentNode->AppendNode($name);
  }

  // XML Parser: Node end
  function _EndElement($parser, $name) {
    $this->_CurrentNode->Value = trim($this->_CurrentNode->Value);
    $this->_CurrentNode =& $this->_CurrentNode->ParentNode;
  }

  // XML Parser: Node value
  function _CharacterData($parser, $data) {
    $this->_CurrentNode->Value .= $data;
  }

  // Convert SimpleXML object to XMLNode structure
  function _SimpleXMLToXMLNode($sxml, &$parentNode) {
    if ($sxml === null) return;
    
    // Get the name of the current element
    $name = $sxml->getName();
    if ($name === null) return;
    
    // Create a new node
    $node =& $parentNode->AppendNode($name);
    
    // Get text content
    $text = (string)$sxml;
    if (!empty($text)) {
      $node->Value = $text;
    }
    
    // Get attributes
    $attributes = $sxml->attributes();
    if ($attributes !== null) {
      foreach ($attributes as $key => $value) {
        $attrNode =& $node->AppendNode("@" . $key);
        $attrNode->Value = (string)$value;
      }
    }
    
    // Process children
    $children = $sxml->children();
    if ($children !== null) {
      foreach ($children as $child) {
        $this->_SimpleXMLToXMLNode($child, $node);
      }
    }
  }
}

?>
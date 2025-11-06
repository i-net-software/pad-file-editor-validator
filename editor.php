<?php
// PAD File Editor
include_once("./include/padfile.php");
include_once("./include/padvalidator.php");

// Check if this is an API request
$isApi = isset($_GET['api']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// API Endpoints
if ($isApi) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $response = array('success' => false, 'message' => '', 'data' => null);
    
    try {
        switch ($action) {
            case 'load':
                $url = trim($_POST['url'] ?? $_GET['url'] ?? '');
                if (empty($url)) {
                    // Return blank PAD structure
                    $response['data'] = getBlankPadStructure();
                    $response['success'] = true;
                } else {
                    // Normalize file paths (handle both absolute and relative paths)
                    $isLocalPath = !preg_match('/^https?:\/\//', $url);
                    if ($isLocalPath) {
                        // If it's a relative path, make it absolute relative to the script directory
                        if (!preg_match('/^\//', $url)) {
                            // Relative path - make it relative to the editor.php location
                            $url = dirname(__FILE__) . '/' . $url;
                        }
                        // Clean up the path (remove .. and .)
                        $url = realpath($url);
                        if ($url === false || !file_exists($url)) {
                            $response['message'] = 'Local file not found: ' . htmlspecialchars($_POST['url'] ?? $_GET['url'] ?? '');
                            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                            exit;
                        }
                        // Check if it's readable
                        if (!is_readable($url)) {
                            $response['message'] = 'Local file is not readable. Check file permissions.';
                            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                            exit;
                        }
                    }
                    
                    // Ensure URL is set properly
                    $PAD = new PADFile($url);
                    // Double-check URL is set (workaround for constructor issues)
                    if (empty($PAD->URL)) {
                        $PAD->URL = $url;
                    }
                    if (!isset($PAD->XML) || $PAD->XML === null) {
                        $PAD->XML = new XMLNode("[root]");
                    }
                    $PAD->Load();
                    
                    if ($PAD->LastError == ERR_NO_ERROR) {
                        // Since the file was successfully parsed, the XML structure is valid
                        // We only need to check for encoding warnings, not re-validate
                        $validationWarnings = array();
                        
                        // Check for encoding warnings
                        if (!empty($PAD->EncodingWarning)) {
                            $validationWarnings[] = 'Encoding: ' . $PAD->EncodingWarning;
                        }
                        
                        // The XML was already successfully parsed, so structure is valid
                        // No need to re-validate (parsing IS validation)
                        
                        // Check if XML was loaded correctly
                        if (isset($PAD->XML) && $PAD->XML !== null) {
                            // Try parsing from raw XML content using DOMDocument (more reliable)
                            // Convert to UTF-8 first if needed
                            $rawContentForParsing = '';
                            if (!empty($PAD->RawContent)) {
                                $rawContentForParsing = $PAD->RawContent;
                                
                                // If there was an encoding warning, convert to UTF-8
                                if (!empty($PAD->EncodingWarning) || !empty($PAD->DetectedEncoding)) {
                                    $declaredEncoding = !empty($PAD->DeclaredEncoding) ? $PAD->DeclaredEncoding : 'UTF-8';
                                    $detectedEncoding = !empty($PAD->DetectedEncoding) ? $PAD->DetectedEncoding : $declaredEncoding;
                                    
                                    // Convert to UTF-8 if needed
                                    if ($detectedEncoding != 'UTF-8' && function_exists('mb_convert_encoding')) {
                                        $converted = @mb_convert_encoding($rawContentForParsing, 'UTF-8', $detectedEncoding);
                                        if ($converted !== false && $converted !== '' && $converted != $rawContentForParsing) {
                                            $rawContentForParsing = $converted;
                                            // Update XML declaration to reflect UTF-8
                                            $rawContentForParsing = preg_replace("/(<\?xml [^>]*encoding=['\"])(.*?)(['\"][^>]*>)/", "$1UTF-8$3", $rawContentForParsing);
                                        }
                                    } elseif ($declaredEncoding != 'UTF-8' && function_exists('mb_convert_encoding')) {
                                        // Try converting from declared encoding
                                        $converted = @mb_convert_encoding($rawContentForParsing, 'UTF-8', $declaredEncoding);
                                        if ($converted !== false && $converted !== '' && $converted != $rawContentForParsing) {
                                            $rawContentForParsing = $converted;
                                            // Update XML declaration to reflect UTF-8
                                            $rawContentForParsing = preg_replace("/(<\?xml [^>]*encoding=['\"])(.*?)(['\"][^>]*>)/", "$1UTF-8$3", $rawContentForParsing);
                                        }
                                    }
                                }
                                
                                // Try parsing the (potentially converted) content
                                $padArray = parsePadXmlFromString($rawContentForParsing);
                                if (is_array($padArray) && count($padArray) > 0) {
                                    $response['data'] = $padArray;
                                    $response['success'] = true;
                                    if (!empty($validationWarnings)) {
                                        $response['message'] = 'Loaded successfully. Warnings: ' . implode('; ', $validationWarnings);
                                    }
                                }
                            }
                            
                            // If parsePadXmlFromString didn't work, try GetValue method
                            if (!$response['success']) {
                                $testValue = $PAD->XML->GetValue('XML_DIZ_INFO/Program_Info/Program_Name');
                                if ($testValue === false || empty($testValue)) {
                                    // Try without XML_DIZ_INFO prefix (some files might have it as root)
                                    $testValue = $PAD->XML->GetValue('Program_Info/Program_Name');
                                }
                                
                                if ($testValue !== false && !empty($testValue)) {
                                    $padArray = buildArrayFromGetValue($PAD->XML);
                                    if (is_array($padArray) && count($padArray) > 0) {
                                        $response['data'] = $padArray;
                                        $response['success'] = true;
                                        if (!empty($validationWarnings)) {
                                            $response['message'] = 'Loaded successfully. Warnings: ' . implode('; ', $validationWarnings);
                                        }
                                    }
                                }
                            }
                            
                            // Last resort: try node parsing
                            if (!$response['success']) {
                                $padArray = padXmlToArray($PAD->XML);
                                if (is_array($padArray) && count($padArray) > 0) {
                                    $response['data'] = $padArray;
                                    $response['success'] = true;
                                    if (!empty($validationWarnings)) {
                                        $response['message'] = 'Loaded successfully. Warnings: ' . implode('; ', $validationWarnings);
                                    }
                                }
                            }
                            
                            // If all methods failed, report error
                            if (!$response['success']) {
                                $response['message'] = 'PAD file loaded but could not extract data structure. The XML may have an unexpected format.';
                                if (!empty($validationWarnings)) {
                                    $response['message'] .= ' Warnings: ' . implode('; ', $validationWarnings);
                                }
                            }
                        } else {
                            $response['message'] = 'PAD file loaded but XML structure is invalid';
                            if (!empty($validationWarnings)) {
                                $response['message'] .= ' Warnings: ' . implode('; ', $validationWarnings);
                            }
                        }
                    } else {
                        $errorMsg = 'Error loading PAD file';
                        if (!empty($PAD->LastErrorMsg)) {
                            $errorMsg .= ': ' . $PAD->LastErrorMsg;
                        } else {
                            switch($PAD->LastError) {
                                case ERR_NO_URL_SPECIFIED:
                                    $errorMsg .= ': No URL specified. Please check that the URL was passed correctly.';
                                    break;
                                case ERR_READ_FROM_URL_FAILED:
                                    $errorMsg .= ': Cannot open URL. Please check the URL is accessible and your internet connection.';
                                    break;
                                case ERR_PARSE_ERROR:
                                    $errorMsg .= ': Parse error' . (!empty($PAD->ParseError) ? ' - ' . $PAD->ParseError : '');
                                    break;
                                default:
                                    $errorMsg .= ' (Error code: ' . $PAD->LastError . ')';
                            }
                        }
                        // Add debug info if URL was provided
                        if (!empty($url)) {
                            $errorMsg .= ' [URL provided: ' . htmlspecialchars($url) . ']';
                        }
                        $response['message'] = $errorMsg;
                    }
                }
                break;
                
            case 'save':
                $xmlData = $_POST['xml'] ?? '';
                if (!empty($xmlData)) {
                    // Validate XML structure
                    $dom = new DOMDocument();
                    $dom->preserveWhiteSpace = false;
                    $dom->formatOutput = true;
                    
                    if (@$dom->loadXML($xmlData)) {
                        $response['data'] = $dom->saveXML();
                        $response['success'] = true;
                        $response['message'] = 'PAD file saved successfully';
                    } else {
                        $response['message'] = 'Invalid XML structure';
                    }
                } else {
                    $response['message'] = 'No XML data provided';
                }
                break;
                
            case 'validate':
                $xmlData = $_POST['xml'] ?? '';
                if (!empty($xmlData)) {
                    // Create temporary PAD file
                    $tempFile = tempnam(sys_get_temp_dir(), 'pad_');
                    file_put_contents($tempFile, $xmlData);
                    
                    $PAD = new PADFile($tempFile);
                    if (!isset($PAD->XML) || $PAD->XML === null) {
                        $PAD->XML = new XMLNode("[root]");
                    }
                    $PAD->Load();
                    
                    if ($PAD->LastError == ERR_NO_ERROR) {
                        $PADValidator = new PADValidator("http://repository.appvisor.com/padspec/files/padspec.xml");
                        if (!isset($PADValidator->XML) || $PADValidator->XML === null) {
                            $PADValidator->XML = new XMLNode("[root]");
                        }
                        
                        if ($PADValidator->Load()) {
                            $nErrors = $PADValidator->Validate($PAD);
                            $nWarnings = count($PADValidator->ValidationWarnings);
                            
                            $errors = array();
                            $warnings = array();
                            
                            foreach($PADValidator->ValidationErrors as $err) {
                                ob_start();
                                $err->Dump();
                                $errors[] = ob_get_clean();
                            }
                            
                            foreach($PADValidator->ValidationWarnings as $warn) {
                                ob_start();
                                $warn->Dump();
                                $warnings[] = ob_get_clean();
                            }
                            
                            $response['success'] = true;
                            $response['data'] = array(
                                'errors' => $errors,
                                'warnings' => $warnings,
                                'errorCount' => $nErrors,
                                'warningCount' => $nWarnings
                            );
                        } else {
                            $response['message'] = 'Could not load validator';
                        }
                    } else {
                        $response['message'] = 'Error loading PAD file: ' . $PAD->LastErrorMsg;
                    }
                    
                    unlink($tempFile);
                } else {
                    $response['message'] = 'No XML data provided';
                }
                break;
                
            default:
                $response['message'] = 'Unknown action';
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    exit;
}

// Helper function to convert XML to array
function padXmlToArray($xmlNode) {
    $result = array();
    
    // Find XML_DIZ_INFO node (could be root or child)
    $rootNode = $xmlNode;
    if ($xmlNode->Name != 'XML_DIZ_INFO' && isset($xmlNode->ChildNodes) && is_array($xmlNode->ChildNodes)) {
        foreach ($xmlNode->ChildNodes as $child) {
            // Skip empty or whitespace-only nodes
            if (empty($child->Name) || trim($child->Name) === '') {
                continue;
            }
            if ($child->Name == 'XML_DIZ_INFO') {
                $rootNode = $child;
                break;
            }
        }
    }
    
    // If still not found, try using GetValue to access the structure
    if ($rootNode->Name != 'XML_DIZ_INFO' && isset($rootNode->ChildNodes)) {
        // Try to find it in children by checking if we can access values
        foreach ($rootNode->ChildNodes as $child) {
            if (!empty($child->Name) && $child->Name == 'XML_DIZ_INFO') {
                $rootNode = $child;
                break;
            }
        }
    }
    
    // Process root node - use GetValue method if available for more reliable access
    if (isset($rootNode->ChildNodes) && is_array($rootNode->ChildNodes)) {
        foreach ($rootNode->ChildNodes as $child) {
            // Skip nodes with empty names (likely text/whitespace nodes)
            if (empty($child->Name) || trim($child->Name) === '') {
                continue;
            }
            $result[$child->Name] = processXmlNode($child);
        }
    }
    
    // If result is empty or doesn't have expected keys, try using GetValue method to extract data
    $expectedKeys = array('Program_Info', 'Company_Info', 'MASTER_PAD_VERSION_INFO');
    $hasExpectedKeys = false;
    foreach ($expectedKeys as $key) {
        if (isset($result[$key])) {
            $hasExpectedKeys = true;
            break;
        }
    }
    
    if ((empty($result) || !$hasExpectedKeys) && method_exists($xmlNode, 'GetValue')) {
        // Try to get some test values to see if GetValue works
        $testName = $xmlNode->GetValue('XML_DIZ_INFO/Program_Info/Program_Name');
        if ($testName !== false && !empty($testName)) {
            // GetValue works, so use it to build the structure
            $getValueResult = buildArrayFromGetValue($xmlNode);
            if (is_array($getValueResult) && count($getValueResult) > 0) {
                return $getValueResult;
            }
        }
    }
    
    return $result;
}

function processXmlNode($node) {
    // Skip nodes with empty names
    if (empty($node->Name) || trim($node->Name) === '') {
        return '';
    }
    
    // Check if node has children (excluding empty-named children)
    $hasChildren = false;
    if (isset($node->ChildNodes) && is_array($node->ChildNodes)) {
        foreach ($node->ChildNodes as $child) {
            if (!empty($child->Name) && trim($child->Name) !== '') {
                $hasChildren = true;
                break;
            }
        }
    }
    
    if (!$hasChildren) {
        // No children with names - return the value
        $value = isset($node->Value) ? trim($node->Value) : '';
        return $value;
    }
    
    // Node has children - process them
    $result = array();
    foreach ($node->ChildNodes as $child) {
        // Skip nodes with empty names
        if (empty($child->Name) || trim($child->Name) === '') {
            continue;
        }
        
        $name = $child->Name;
        $childValue = processXmlNode($child);
        
        if (isset($result[$name])) {
            // Convert to array if multiple nodes with same name
            if (!is_array($result[$name])) {
                $result[$name] = array($result[$name]);
            }
            $result[$name][] = $childValue;
        } else {
            $result[$name] = $childValue;
        }
    }
    
    return $result;
}

// Parse PAD XML directly from string using DOMDocument
function parsePadXmlFromString($xmlString) {
    $result = array();
    
    try {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        if (@$dom->loadXML($xmlString)) {
            $root = $dom->documentElement;
            if ($root) {
                // Handle both XML_DIZ_INFO as root or as a child
                if ($root->nodeName == 'XML_DIZ_INFO') {
                    $result = domNodeToArray($root);
                } else {
                    // Look for XML_DIZ_INFO as a child
                    $xmlDizNodes = $dom->getElementsByTagName('XML_DIZ_INFO');
                    if ($xmlDizNodes->length > 0) {
                        $result = domNodeToArray($xmlDizNodes->item(0));
                    } else {
                        // If no XML_DIZ_INFO found, use root
                        $result = domNodeToArray($root);
                    }
                }
            }
        }
        
        libxml_clear_errors();
    } catch (Exception $e) {
        // Fallback to empty array
        libxml_clear_errors();
    }
    
    return $result;
}

function domNodeToArray($node) {
    $result = array();
    
    if ($node->nodeType == XML_ELEMENT_NODE) {
        // Process element node
        $nodeName = $node->nodeName;
        
        // Check if node has child elements
        $hasElementChildren = false;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType == XML_ELEMENT_NODE) {
                $hasElementChildren = true;
                break;
            }
        }
        
        if ($hasElementChildren) {
            // Node has child elements - create array structure
            foreach ($node->childNodes as $child) {
                if ($child->nodeType == XML_ELEMENT_NODE) {
                    $childName = $child->nodeName;
                    $childValue = domNodeToArray($child);
                    
                    if (isset($result[$childName])) {
                        // Convert to array if multiple nodes with same name
                        if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                            $result[$childName] = array($result[$childName]);
                        }
                        $result[$childName][] = $childValue;
                    } else {
                        $result[$childName] = $childValue;
                    }
                }
            }
        } else {
            // Node has no element children - return text content
            $textContent = trim($node->textContent);
            return $textContent;
        }
    }
    
    return $result;
}

// Alternative method: Build array using GetValue for known paths
function buildArrayFromGetValue($xmlNode) {
    // This is a fallback - we'll use GetValue to extract known PAD fields
    // This is less ideal but works if the node structure isn't accessible
    $result = array();
    
    // Define known PAD file paths
    $paths = array(
        'MASTER_PAD_VERSION_INFO' => array(
            'MASTER_PAD_VERSION' => 'XML_DIZ_INFO/MASTER_PAD_VERSION_INFO/MASTER_PAD_VERSION',
            'MASTER_PAD_EDITOR' => 'XML_DIZ_INFO/MASTER_PAD_VERSION_INFO/MASTER_PAD_EDITOR',
            'MASTER_PAD_INFO' => 'XML_DIZ_INFO/MASTER_PAD_VERSION_INFO/MASTER_PAD_INFO'
        ),
        'Program_Info' => array(
            'Program_Name' => 'XML_DIZ_INFO/Program_Info/Program_Name',
            'Program_Version' => 'XML_DIZ_INFO/Program_Info/Program_Version',
            'Program_Type' => 'XML_DIZ_INFO/Program_Info/Program_Type',
            'Program_Release_Status' => 'XML_DIZ_INFO/Program_Info/Program_Release_Status',
            'Program_Category_Class' => 'XML_DIZ_INFO/Program_Info/Program_Category_Class',
            'Program_Release_Year' => 'XML_DIZ_INFO/Program_Info/Program_Release_Year',
            'Program_Release_Month' => 'XML_DIZ_INFO/Program_Info/Program_Release_Month',
            'Program_Release_Day' => 'XML_DIZ_INFO/Program_Info/Program_Release_Day',
            'Program_Cost_Dollars' => 'XML_DIZ_INFO/Program_Info/Program_Cost_Dollars',
            'Program_Cost_Other' => 'XML_DIZ_INFO/Program_Info/Program_Cost_Other',
            'Program_Cost_Other_Code' => 'XML_DIZ_INFO/Program_Info/Program_Cost_Other_Code',
            'Program_OS_Support' => 'XML_DIZ_INFO/Program_Info/Program_OS_Support',
            'Program_Language' => 'XML_DIZ_INFO/Program_Info/Program_Language',
            'Program_Install_Support' => 'XML_DIZ_INFO/Program_Info/Program_Install_Support',
            'Program_System_Requirements' => 'XML_DIZ_INFO/Program_Info/Program_System_Requirements',
            'Program_Change_Info' => 'XML_DIZ_INFO/Program_Info/Program_Change_Info',
            'Program_Specific_Category' => 'XML_DIZ_INFO/Program_Info/Program_Specific_Category',
            'File_Info' => array(
                'File_Size_Bytes' => 'XML_DIZ_INFO/Program_Info/File_Info/File_Size_Bytes',
                'File_Size_K' => 'XML_DIZ_INFO/Program_Info/File_Info/File_Size_K',
                'File_Size_MB' => 'XML_DIZ_INFO/Program_Info/File_Info/File_Size_MB'
            ),
            'Expire_Info' => array(
                'Has_Expire_Info' => 'XML_DIZ_INFO/Program_Info/Expire_Info/Has_Expire_Info',
                'Expire_Based_On' => 'XML_DIZ_INFO/Program_Info/Expire_Info/Expire_Based_On',
                'Expire_Count' => 'XML_DIZ_INFO/Program_Info/Expire_Info/Expire_Count'
            )
        ),
        'Company_Info' => array(
            'Company_Name' => 'XML_DIZ_INFO/Company_Info/Company_Name',
            'Address_1' => 'XML_DIZ_INFO/Company_Info/Address_1',
            'Address_2' => 'XML_DIZ_INFO/Company_Info/Address_2',
            'Zip_Postal_Code' => 'XML_DIZ_INFO/Company_Info/Zip_Postal_Code',
            'City_Town' => 'XML_DIZ_INFO/Company_Info/City_Town',
            'State_Province' => 'XML_DIZ_INFO/Company_Info/State_Province',
            'Country' => 'XML_DIZ_INFO/Company_Info/Country',
            'Company_WebSite_URL' => 'XML_DIZ_INFO/Company_Info/Company_WebSite_URL',
            'Contact_Info' => array(
                'Author_First_Name' => 'XML_DIZ_INFO/Company_Info/Contact_Info/Author_First_Name',
                'Author_Last_Name' => 'XML_DIZ_INFO/Company_Info/Contact_Info/Author_Last_Name',
                'Author_Email' => 'XML_DIZ_INFO/Company_Info/Contact_Info/Author_Email',
                'Contact_First_Name' => 'XML_DIZ_INFO/Company_Info/Contact_Info/Contact_First_Name',
                'Contact_Last_Name' => 'XML_DIZ_INFO/Company_Info/Contact_Info/Contact_Last_Name',
                'Contact_Email' => 'XML_DIZ_INFO/Company_Info/Contact_Info/Contact_Email'
            ),
            'Support_Info' => array(
                'General_Email' => 'XML_DIZ_INFO/Company_Info/Support_Info/General_Email',
                'Support_Email' => 'XML_DIZ_INFO/Company_Info/Support_Info/Support_Email',
                'Sales_Email' => 'XML_DIZ_INFO/Company_Info/Support_Info/Sales_Email',
                'General_Phone' => 'XML_DIZ_INFO/Company_Info/Support_Info/General_Phone',
                'Support_Phone' => 'XML_DIZ_INFO/Company_Info/Support_Info/Support_Phone',
                'Sales_Phone' => 'XML_DIZ_INFO/Company_Info/Support_Info/Sales_Phone',
                'Fax_Phone' => 'XML_DIZ_INFO/Company_Info/Support_Info/Fax_Phone'
            )
        ),
        'Web_Info' => array(
            'Application_URLs' => array(
                'Application_Info_URL' => 'XML_DIZ_INFO/Web_Info/Application_URLs/Application_Info_URL',
                'Application_Order_URL' => 'XML_DIZ_INFO/Web_Info/Application_URLs/Application_Order_URL',
                'Application_Screenshot_URL' => 'XML_DIZ_INFO/Web_Info/Application_URLs/Application_Screenshot_URL',
                'Application_Icon_URL' => 'XML_DIZ_INFO/Web_Info/Application_URLs/Application_Icon_URL'
            )
        ),
        'ASP' => array(
            'ASP_Member' => 'XML_DIZ_INFO/ASP/ASP_Member',
            'ASP_Member_Number' => 'XML_DIZ_INFO/ASP/ASP_Member_Number'
        )
    );
    
    // Extract values using GetValue
    foreach ($paths as $section => $fields) {
        if (is_array($fields)) {
            $result[$section] = array();
            foreach ($fields as $key => $path) {
                if (is_array($path)) {
                    // Nested structure
                    $result[$section][$key] = array();
                    foreach ($path as $subKey => $subPath) {
                        $value = $xmlNode->GetValue($subPath);
                        $result[$section][$key][$subKey] = $value !== false ? $value : '';
                    }
                } else {
                    $value = $xmlNode->GetValue($path);
                    $result[$section][$key] = $value !== false ? $value : '';
                }
            }
        }
    }
    
    // Extract Program_Descriptions (languages)
    $result['Program_Descriptions'] = array();
    // Try common languages
    $languages = array('English', 'German', 'French', 'Spanish', 'Italian', 'Dutch', 'Portuguese');
    foreach ($languages as $lang) {
        $desc45 = $xmlNode->GetValue("XML_DIZ_INFO/Program_Descriptions/$lang/Char_Desc_45");
        if ($desc45 !== false && !empty($desc45)) {
            $result['Program_Descriptions'][$lang] = array(
                'Char_Desc_45' => $xmlNode->GetValue("XML_DIZ_INFO/Program_Descriptions/$lang/Char_Desc_45") ?: '',
                'Char_Desc_80' => $xmlNode->GetValue("XML_DIZ_INFO/Program_Descriptions/$lang/Char_Desc_80") ?: '',
                'Char_Desc_250' => $xmlNode->GetValue("XML_DIZ_INFO/Program_Descriptions/$lang/Char_Desc_250") ?: '',
                'Char_Desc_450' => $xmlNode->GetValue("XML_DIZ_INFO/Program_Descriptions/$lang/Char_Desc_450") ?: '',
                'Char_Desc_2000' => $xmlNode->GetValue("XML_DIZ_INFO/Program_Descriptions/$lang/Char_Desc_2000") ?: ''
            );
        }
    }
    
    return $result;
}

// Helper function to create blank PAD structure
function getBlankPadStructure() {
    return array(
        'MASTER_PAD_VERSION_INFO' => array(
            'MASTER_PAD_VERSION' => '4.0',
            'MASTER_PAD_EDITOR' => '',
            'MASTER_PAD_INFO' => 'Portable Application Description, or PAD for short, is a data set that is used by shareware authors to dissemminate information to anyone interested in their software products. To find out more go to http://www.asp-shareware.org/pad'
        ),
        'Program_Info' => array(
            'Program_Name' => '',
            'Program_Version' => '',
            'Program_Type' => 'Commercial',
            'Program_Release_Status' => 'New Release',
            'Program_Category_Class' => '',
            'Program_Release_Year' => date('Y'),
            'Program_Release_Month' => date('m'),
            'Program_Release_Day' => date('d'),
            'Program_Cost_Dollars' => '0.00',
            'Program_Cost_Other' => '0.00',
            'Program_Cost_Other_Code' => 'USD',
            'Program_OS_Support' => '',
            'Program_Language' => 'English',
            'Program_Install_Support' => '',
            'Program_System_Requirements' => '',
            'Program_Change_Info' => '',
            'Program_Specific_Category' => '',
            'File_Info' => array(
                'File_Size_Bytes' => '',
                'File_Size_K' => '',
                'File_Size_MB' => ''
            ),
            'Expire_Info' => array(
                'Has_Expire_Info' => 'N',
                'Expire_Based_On' => 'Days',
                'Expire_Count' => '',
                'Expire_Other_Info' => '',
                'Expire_Year' => '',
                'Expire_Month' => '',
                'Expire_Day' => ''
            )
        ),
        'Company_Info' => array(
            'Company_Name' => '',
            'Address_1' => '',
            'Address_2' => '',
            'Zip_Postal_Code' => '',
            'City_Town' => '',
            'State_Province' => '',
            'Country' => '',
            'Company_WebSite_URL' => '',
            'Contact_Info' => array(
                'Author_First_Name' => '',
                'Author_Last_Name' => '',
                'Author_Email' => '',
                'Contact_First_Name' => '',
                'Contact_Last_Name' => '',
                'Contact_Email' => ''
            ),
            'Support_Info' => array(
                'General_Email' => '',
                'Support_Email' => '',
                'Sales_Email' => '',
                'General_Phone' => '',
                'Support_Phone' => '',
                'Sales_Phone' => '',
                'Fax_Phone' => ''
            )
        ),
        'Web_Info' => array(
            'Application_URLs' => array(
                'Application_Info_URL' => '',
                'Application_Order_URL' => '',
                'Application_Screenshot_URL' => '',
                'Application_Icon_URL' => ''
            )
        ),
        'Program_Descriptions' => array(
            'English' => array(
                'Char_Desc_45' => '',
                'Char_Desc_80' => '',
                'Char_Desc_250' => '',
                'Char_Desc_450' => '',
                'Char_Desc_2000' => ''
            )
        ),
        'ASP' => array(
            'ASP_Member' => 'N',
            'ASP_Member_Number' => ''
        )
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAD File Editor</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .load-section {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        input[type="text"],
        input[type="url"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .tabs {
            display: flex;
            flex-wrap: wrap;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 0.95rem;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .tab:hover {
            color: #667eea;
            background: #f8f9fa;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .status {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 0;
        }
        
        .status:empty {
            display: none;
            margin: 0;
            padding: 0;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-dismiss {
            margin-left: auto;
            background: rgba(0,0,0,0.1);
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.2s ease;
        }
        
        .status-dismiss:hover {
            background: rgba(0,0,0,0.2);
        }
        
        .status-error .status-dismiss {
            background: rgba(255,255,255,0.3);
        }
        
        .status-error .status-dismiss:hover {
            background: rgba(255,255,255,0.5);
        }
        
        .language-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .language-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .language-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .add-language-btn {
            margin-bottom: 20px;
        }
        
        .hidden {
            display: none;
        }
        
        .actions-bar {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 15px 30px;
            border-top: 2px solid #e0e0e0;
            margin: 20px -30px -30px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ PAD File Editor</h1>
            <p>Create and edit Portable Application Description (PAD) XML files</p>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">🔍 Validator</a>
            </div>
        </div>
        
        <div class="card">
            <div class="load-section">
                <div class="form-group">
                    <label>Load Existing PAD File (Optional)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="loadUrl" placeholder="https://example.com/padfile.xml or /path/to/file.xml" style="flex: 1;">
                        <button class="btn" onclick="loadPadFile()">Load</button>
                        <button class="btn btn-secondary" onclick="startBlank()">Start Blank</button>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Enter a URL (http:// or https://) or a local file path (e.g., /path/to/padfile.xml or ./padfile.xml)
                    </small>
                </div>
                <div id="loadStatus"></div>
            </div>
            
            <div id="editorContainer" class="hidden">
                <div class="tabs" id="tabsContainer"></div>
                <div id="tabsContent"></div>
                
                <div class="actions-bar">
                    <button class="btn btn-secondary" onclick="validatePad()">Validate</button>
                    <button class="btn btn-success" onclick="savePad()">Save as XML</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let padData = null;
        let currentLanguages = ['English'];
        
        // Initialize with blank PAD
        window.addEventListener('DOMContentLoaded', function() {
            startBlank();
        });
        
        function startBlank() {
            fetch('editor.php?api=1&action=load')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        padData = data.data;
                        renderEditor();
                        document.getElementById('editorContainer').classList.remove('hidden');
                        showStatus('Started with blank PAD file', 'success');
                    }
                })
                .catch(err => {
                    showStatus('Error: ' + err.message, 'error');
                });
        }
        
        function loadPadFile() {
            const urlInput = document.getElementById('loadUrl');
            const url = urlInput.value.trim();
            if (!url) {
                showStatus('Please enter a URL', 'error');
                return;
            }
            
            // Validate URL or file path format
            const isUrl = /^https?:\/\//.test(url);
            const isLocalPath = /^\/|^\.\//.test(url) || (!isUrl && url.length > 0);
            
            if (!isUrl && !isLocalPath) {
                // Try to validate as URL
                try {
                    new URL(url);
                } catch (e) {
                    showStatus('Invalid format. Please enter a valid URL (http:// or https://) or a local file path (e.g., /path/to/file.xml or ./file.xml)', 'error');
                    return;
                }
            }
            
            showStatus('Loading PAD file...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'load');
            formData.append('url', url);
            
            fetch('editor.php?api=1', {
                method: 'POST',
                body: formData
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error: ' + r.status + ' ' + r.statusText);
                }
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    padData = data.data;
                    extractLanguages();
                    renderEditor();
                    document.getElementById('editorContainer').classList.remove('hidden');
                    // Show success message, or warning if there are validation warnings
                    if (data.message && data.message.includes('Warnings:')) {
                        showStatus('PAD file loaded with warnings: ' + data.message, 'info');
                    } else {
                        showStatus('PAD file loaded successfully', 'success');
                    }
                } else {
                    showStatus('Error loading PAD file: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(err => {
                let errorMsg = 'Error: ' + err.message;
                // Try to get more details if it's a JSON parse error
                if (err.message.includes('JSON')) {
                    errorMsg += '. The server response may be invalid. Please check the URL and try again.';
                }
                showStatus(errorMsg, 'error');
            });
        }
        
        function extractLanguages() {
            if (padData.Program_Descriptions) {
                currentLanguages = Object.keys(padData.Program_Descriptions);
                if (currentLanguages.length === 0) {
                    currentLanguages = ['English'];
                }
            }
        }
        
        function renderEditor() {
            const tabs = [
                { id: 'basic', label: 'Basic Info', content: renderBasicInfo() },
                { id: 'company', label: 'Company', content: renderCompanyInfo() },
                { id: 'descriptions', label: 'Descriptions', content: renderDescriptions() },
                { id: 'web', label: 'Web & URLs', content: renderWebInfo() },
                { id: 'advanced', label: 'Advanced', content: renderAdvanced() }
            ];
            
            const tabsContainer = document.getElementById('tabsContainer');
            const tabsContent = document.getElementById('tabsContent');
            
            tabsContainer.innerHTML = '';
            tabsContent.innerHTML = '';
            
            tabs.forEach((tab, index) => {
                const tabBtn = document.createElement('button');
                tabBtn.className = 'tab' + (index === 0 ? ' active' : '');
                tabBtn.textContent = tab.label;
                tabBtn.onclick = () => switchTab(index);
                tabsContainer.appendChild(tabBtn);
                
                const tabContent = document.createElement('div');
                tabContent.className = 'tab-content' + (index === 0 ? ' active' : '');
                tabContent.id = 'tab-' + tab.id;
                tabContent.innerHTML = tab.content;
                tabsContent.appendChild(tabContent);
            });
            
            // Update expiration info visibility after rendering
            setTimeout(() => updateExpireInfoVisibility(), 0);
        }
        
        function switchTab(index) {
            document.querySelectorAll('.tab').forEach((t, i) => {
                t.classList.toggle('active', i === index);
            });
            document.querySelectorAll('.tab-content').forEach((c, i) => {
                c.classList.toggle('active', i === index);
            });
        }
        
        function renderBasicInfo() {
            const info = padData.Program_Info || {};
            const fileInfo = info.File_Info || {};
            const expireInfo = info.Expire_Info || {};
            
            return `
                <div class="section">
                    <div class="section-title">Program Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Program Name *</label>
                            <input type="text" data-path="Program_Info.Program_Name" value="${escapeHtml(info.Program_Name || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Version *</label>
                            <input type="text" data-path="Program_Info.Program_Version" value="${escapeHtml(info.Program_Version || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Program Type</label>
                            <select data-path="Program_Info.Program_Type" onchange="updateField(this)">
                                <option value="Commercial" ${info.Program_Type === 'Commercial' ? 'selected' : ''}>Commercial</option>
                                <option value="Freeware" ${info.Program_Type === 'Freeware' ? 'selected' : ''}>Freeware</option>
                                <option value="Shareware" ${info.Program_Type === 'Shareware' ? 'selected' : ''}>Shareware</option>
                                <option value="Demo" ${info.Program_Type === 'Demo' ? 'selected' : ''}>Demo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Release Status</label>
                            <select data-path="Program_Info.Program_Release_Status" onchange="updateField(this)">
                                <option value="New Release" ${info.Program_Release_Status === 'New Release' ? 'selected' : ''}>New Release</option>
                                <option value="Update" ${info.Program_Release_Status === 'Update' ? 'selected' : ''}>Update</option>
                                <option value="New Product" ${info.Program_Release_Status === 'New Product' ? 'selected' : ''}>New Product</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Category Class</label>
                            <input type="text" data-path="Program_Info.Program_Category_Class" value="${escapeHtml(info.Program_Category_Class || '')}" placeholder="Business::Other" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Specific Category</label>
                            <input type="text" data-path="Program_Info.Program_Specific_Category" value="${escapeHtml(info.Program_Specific_Category || '')}" placeholder="e.g., Accounting" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>OS Support</label>
                            <input type="text" data-path="Program_Info.Program_OS_Support" value="${escapeHtml(info.Program_OS_Support || '')}" placeholder="WinXP,WinVista,Win7,Win8,Win10" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Language</label>
                            <input type="text" data-path="Program_Info.Program_Language" value="${escapeHtml(info.Program_Language || '')}" placeholder="English,German" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>System Requirements</label>
                            <textarea data-path="Program_Info.Program_System_Requirements" onchange="updateField(this)">${escapeHtml(info.Program_System_Requirements || '')}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Install Support</label>
                            <input type="text" data-path="Program_Info.Program_Install_Support" value="${escapeHtml(info.Program_Install_Support || '')}" placeholder="Install and Uninstall" onchange="updateField(this)">
                        </div>
                        <div class="form-group full-width">
                            <label>Change Info</label>
                            <textarea data-path="Program_Info.Program_Change_Info" onchange="updateField(this)" rows="4">${escapeHtml(info.Program_Change_Info || '')}</textarea>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Release Date</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" data-path="Program_Info.Program_Release_Year" value="${info.Program_Release_Year || ''}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Month</label>
                            <input type="number" data-path="Program_Info.Program_Release_Month" value="${info.Program_Release_Month || ''}" min="1" max="12" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Day</label>
                            <input type="number" data-path="Program_Info.Program_Release_Day" value="${info.Program_Release_Day || ''}" min="1" max="31" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Pricing</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Cost (USD)</label>
                            <input type="text" data-path="Program_Info.Program_Cost_Dollars" value="${escapeHtml(info.Program_Cost_Dollars || '0.00')}" placeholder="0.00" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Cost (Other Currency)</label>
                            <input type="text" data-path="Program_Info.Program_Cost_Other" value="${escapeHtml(info.Program_Cost_Other || '0.00')}" placeholder="0.00" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Currency Code</label>
                            <input type="text" data-path="Program_Info.Program_Cost_Other_Code" value="${escapeHtml(info.Program_Cost_Other_Code || 'USD')}" placeholder="USD" maxlength="3" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">File Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>File Size (Bytes)</label>
                            <input type="number" data-path="Program_Info.File_Info.File_Size_Bytes" value="${fileInfo.File_Size_Bytes || ''}" placeholder="e.g., 1048576" min="0" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>File Size (KB)</label>
                            <input type="number" data-path="Program_Info.File_Info.File_Size_K" value="${fileInfo.File_Size_K || ''}" placeholder="e.g., 1024" min="0" step="0.01" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>File Size (MB)</label>
                            <input type="number" data-path="Program_Info.File_Info.File_Size_MB" value="${fileInfo.File_Size_MB || ''}" placeholder="e.g., 1.0" min="0" step="0.01" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Expiration Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Has Expiration Info</label>
                            <select data-path="Program_Info.Expire_Info.Has_Expire_Info" onchange="updateExpireInfo(this)">
                                <option value="N" ${expireInfo.Has_Expire_Info === 'N' || !expireInfo.Has_Expire_Info ? 'selected' : ''}>No</option>
                                <option value="Y" ${expireInfo.Has_Expire_Info === 'Y' ? 'selected' : ''}>Yes</option>
                            </select>
                        </div>
                        <div class="form-group" id="expireBasedOnGroup" style="display: ${expireInfo.Has_Expire_Info === 'Y' ? 'block' : 'none'};">
                            <label>Expire Based On</label>
                            <select data-path="Program_Info.Expire_Info.Expire_Based_On" onchange="updateField(this); updateExpireInfoVisibility();">
                                <option value="Days" ${expireInfo.Expire_Based_On === 'Days' || !expireInfo.Expire_Based_On ? 'selected' : ''}>Days</option>
                                <option value="Uses" ${expireInfo.Expire_Based_On === 'Uses' ? 'selected' : ''}>Uses</option>
                                <option value="Date" ${expireInfo.Expire_Based_On === 'Date' ? 'selected' : ''}>Date</option>
                                <option value="Other" ${expireInfo.Expire_Based_On === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="form-group" id="expireCountGroup" style="display: ${(expireInfo.Has_Expire_Info === 'Y' && (expireInfo.Expire_Based_On === 'Days' || expireInfo.Expire_Based_On === 'Uses' || !expireInfo.Expire_Based_On)) ? 'block' : 'none'};">
                            <label>Expire Count</label>
                            <input type="number" data-path="Program_Info.Expire_Info.Expire_Count" value="${expireInfo.Expire_Count || ''}" placeholder="e.g., 30" min="0" onchange="updateField(this)">
                        </div>
                        <div class="form-group" id="expireDateGroup" style="display: ${(expireInfo.Has_Expire_Info === 'Y' && expireInfo.Expire_Based_On === 'Date') ? 'block' : 'none'};">
                            <label>Expiration Date</label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <input type="number" data-path="Program_Info.Expire_Info.Expire_Year" value="${expireInfo.Expire_Year || ''}" placeholder="Year" min="1900" max="2100" onchange="updateField(this)">
                                <input type="number" data-path="Program_Info.Expire_Info.Expire_Month" value="${expireInfo.Expire_Month || ''}" placeholder="Month" min="1" max="12" onchange="updateField(this)">
                                <input type="number" data-path="Program_Info.Expire_Info.Expire_Day" value="${expireInfo.Expire_Day || ''}" placeholder="Day" min="1" max="31" onchange="updateField(this)">
                            </div>
                        </div>
                        <div class="form-group full-width" id="expireOtherGroup" style="display: ${(expireInfo.Has_Expire_Info === 'Y' && expireInfo.Expire_Based_On === 'Other') ? 'block' : 'none'};">
                            <label>Expire Other Info</label>
                            <textarea data-path="Program_Info.Expire_Info.Expire_Other_Info" onchange="updateField(this)" rows="2">${escapeHtml(expireInfo.Expire_Other_Info || '')}</textarea>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function renderCompanyInfo() {
            const company = padData.Company_Info || {};
            const contact = company.Contact_Info || {};
            const support = company.Support_Info || {};
            
            return `
                <div class="section">
                    <div class="section-title">Company Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Company Name *</label>
                            <input type="text" data-path="Company_Info.Company_Name" value="${escapeHtml(company.Company_Name || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Website URL</label>
                            <input type="url" data-path="Company_Info.Company_WebSite_URL" value="${escapeHtml(company.Company_WebSite_URL || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Address Line 1</label>
                            <input type="text" data-path="Company_Info.Address_1" value="${escapeHtml(company.Address_1 || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Address Line 2</label>
                            <input type="text" data-path="Company_Info.Address_2" value="${escapeHtml(company.Address_2 || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" data-path="Company_Info.City_Town" value="${escapeHtml(company.City_Town || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>State/Province</label>
                            <input type="text" data-path="Company_Info.State_Province" value="${escapeHtml(company.State_Province || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>ZIP/Postal Code</label>
                            <input type="text" data-path="Company_Info.Zip_Postal_Code" value="${escapeHtml(company.Zip_Postal_Code || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" data-path="Company_Info.Country" value="${escapeHtml(company.Country || '')}" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Contact Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Author First Name</label>
                            <input type="text" data-path="Company_Info.Contact_Info.Author_First_Name" value="${escapeHtml(contact.Author_First_Name || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Author Last Name</label>
                            <input type="text" data-path="Company_Info.Contact_Info.Author_Last_Name" value="${escapeHtml(contact.Author_Last_Name || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Author Email</label>
                            <input type="email" data-path="Company_Info.Contact_Info.Author_Email" value="${escapeHtml(contact.Author_Email || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Contact First Name</label>
                            <input type="text" data-path="Company_Info.Contact_Info.Contact_First_Name" value="${escapeHtml(contact.Contact_First_Name || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Contact Last Name</label>
                            <input type="text" data-path="Company_Info.Contact_Info.Contact_Last_Name" value="${escapeHtml(contact.Contact_Last_Name || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" data-path="Company_Info.Contact_Info.Contact_Email" value="${escapeHtml(contact.Contact_Email || '')}" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Support Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>General Email</label>
                            <input type="email" data-path="Company_Info.Support_Info.General_Email" value="${escapeHtml(support.General_Email || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Support Email</label>
                            <input type="email" data-path="Company_Info.Support_Info.Support_Email" value="${escapeHtml(support.Support_Email || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Sales Email</label>
                            <input type="email" data-path="Company_Info.Support_Info.Sales_Email" value="${escapeHtml(support.Sales_Email || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>General Phone</label>
                            <input type="text" data-path="Company_Info.Support_Info.General_Phone" value="${escapeHtml(support.General_Phone || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Support Phone</label>
                            <input type="text" data-path="Company_Info.Support_Info.Support_Phone" value="${escapeHtml(support.Support_Phone || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Sales Phone</label>
                            <input type="text" data-path="Company_Info.Support_Info.Sales_Phone" value="${escapeHtml(support.Sales_Phone || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Fax</label>
                            <input type="text" data-path="Company_Info.Support_Info.Fax_Phone" value="${escapeHtml(support.Fax_Phone || '')}" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
            `;
        }
        
        function renderDescriptions() {
            let html = '<button class="btn btn-secondary add-language-btn" onclick="addLanguage()">+ Add Language</button>';
            
            currentLanguages.forEach(lang => {
                const desc = padData.Program_Descriptions?.[lang] || {};
                html += `
                    <div class="language-section" data-lang="${lang}">
                        <div class="language-header">
                            <span class="language-title">${escapeHtml(lang)}</span>
                            ${currentLanguages.length > 1 ? `<button class="btn btn-danger btn-small" onclick="removeLanguage('${lang}')">Remove</button>` : ''}
                        </div>
                        <div class="grid">
                            <div class="form-group">
                                <label>45 Character Description</label>
                                <textarea data-path="Program_Descriptions.${lang}.Char_Desc_45" onchange="updateField(this)" maxlength="45">${escapeHtml(desc.Char_Desc_45 || '')}</textarea>
                            </div>
                            <div class="form-group">
                                <label>80 Character Description</label>
                                <textarea data-path="Program_Descriptions.${lang}.Char_Desc_80" onchange="updateField(this)" maxlength="80">${escapeHtml(desc.Char_Desc_80 || '')}</textarea>
                            </div>
                            <div class="form-group">
                                <label>250 Character Description</label>
                                <textarea data-path="Program_Descriptions.${lang}.Char_Desc_250" onchange="updateField(this)" maxlength="250">${escapeHtml(desc.Char_Desc_250 || '')}</textarea>
                            </div>
                            <div class="form-group">
                                <label>450 Character Description</label>
                                <textarea data-path="Program_Descriptions.${lang}.Char_Desc_450" onchange="updateField(this)" maxlength="450">${escapeHtml(desc.Char_Desc_450 || '')}</textarea>
                            </div>
                            <div class="form-group full-width">
                                <label>2000 Character Description</label>
                                <textarea data-path="Program_Descriptions.${lang}.Char_Desc_2000" onchange="updateField(this)" maxlength="2000" rows="6">${escapeHtml(desc.Char_Desc_2000 || '')}</textarea>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            return html;
        }
        
        function renderWebInfo() {
            const urls = padData.Web_Info?.Application_URLs || {};
            
            return `
                <div class="section">
                    <div class="section-title">Application URLs</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>Info URL</label>
                            <input type="url" data-path="Web_Info.Application_URLs.Application_Info_URL" value="${escapeHtml(urls.Application_Info_URL || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Order URL</label>
                            <input type="url" data-path="Web_Info.Application_URLs.Application_Order_URL" value="${escapeHtml(urls.Application_Order_URL || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Screenshot URL</label>
                            <input type="url" data-path="Web_Info.Application_URLs.Application_Screenshot_URL" value="${escapeHtml(urls.Application_Screenshot_URL || '')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>Icon URL</label>
                            <input type="url" data-path="Web_Info.Application_URLs.Application_Icon_URL" value="${escapeHtml(urls.Application_Icon_URL || '')}" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
            `;
        }
        
        function renderAdvanced() {
            const master = padData.MASTER_PAD_VERSION_INFO || {};
            const asp = padData.ASP || {};
            
            return `
                <div class="section">
                    <div class="section-title">PAD Version Info</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>PAD Version</label>
                            <input type="text" data-path="MASTER_PAD_VERSION_INFO.MASTER_PAD_VERSION" value="${escapeHtml(master.MASTER_PAD_VERSION || '4.0')}" onchange="updateField(this)">
                        </div>
                        <div class="form-group">
                            <label>PAD Editor</label>
                            <input type="text" data-path="MASTER_PAD_VERSION_INFO.MASTER_PAD_EDITOR" value="${escapeHtml(master.MASTER_PAD_EDITOR || '')}" placeholder="Name of editor used" onchange="updateField(this)">
                        </div>
                        <div class="form-group full-width">
                            <label>PAD Info</label>
                            <textarea data-path="MASTER_PAD_VERSION_INFO.MASTER_PAD_INFO" onchange="updateField(this)" rows="3">${escapeHtml(master.MASTER_PAD_INFO || 'Portable Application Description, or PAD for short, is a data set that is used by shareware authors to dissemminate information to anyone interested in their software products. To find out more go to http://www.asp-shareware.org/pad')}</textarea>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">ASP Information</div>
                    <div class="grid">
                        <div class="form-group">
                            <label>ASP Member</label>
                            <select data-path="ASP.ASP_Member" onchange="updateField(this)">
                                <option value="N" ${asp.ASP_Member === 'N' ? 'selected' : ''}>No</option>
                                <option value="Y" ${asp.ASP_Member === 'Y' ? 'selected' : ''}>Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ASP Member Number</label>
                            <input type="text" data-path="ASP.ASP_Member_Number" value="${escapeHtml(asp.ASP_Member_Number || '')}" onchange="updateField(this)">
                        </div>
                    </div>
                </div>
            `;
        }
        
        function updateField(element) {
            const path = element.getAttribute('data-path');
            const value = element.value;
            
            if (!path) return;
            
            const parts = path.split('.');
            let current = padData;
            
            for (let i = 0; i < parts.length - 1; i++) {
                if (!current[parts[i]]) {
                    current[parts[i]] = {};
                }
                current = current[parts[i]];
            }
            
            current[parts[parts.length - 1]] = value;
            
            // Special handling for Expire_Based_On to show/hide related fields
            if (path === 'Program_Info.Expire_Info.Expire_Based_On') {
                updateExpireInfoVisibility();
            }
        }
        
        function updateExpireInfo(element) {
            // Update the field value
            updateField(element);
            
            // Update visibility of expiration-related fields
            updateExpireInfoVisibility();
        }
        
        function updateExpireInfoVisibility() {
            const expireInfo = padData.Program_Info?.Expire_Info || {};
            const hasExpire = expireInfo.Has_Expire_Info === 'Y';
            const expireBasedOn = expireInfo.Expire_Based_On || 'Days';
            
            // Show/hide Expire_Based_On dropdown
            const expireBasedOnGroup = document.getElementById('expireBasedOnGroup');
            if (expireBasedOnGroup) {
                expireBasedOnGroup.style.display = hasExpire ? 'block' : 'none';
            }
            
            // Show/hide Expire_Count (for Days or Uses)
            const expireCountGroup = document.getElementById('expireCountGroup');
            if (expireCountGroup) {
                expireCountGroup.style.display = (hasExpire && (expireBasedOn === 'Days' || expireBasedOn === 'Uses')) ? 'block' : 'none';
            }
            
            // Show/hide Expiration Date (for Date)
            const expireDateGroup = document.getElementById('expireDateGroup');
            if (expireDateGroup) {
                expireDateGroup.style.display = (hasExpire && expireBasedOn === 'Date') ? 'block' : 'none';
            }
            
            // Show/hide Expire_Other_Info (for Other)
            const expireOtherGroup = document.getElementById('expireOtherGroup');
            if (expireOtherGroup) {
                expireOtherGroup.style.display = (hasExpire && expireBasedOn === 'Other') ? 'block' : 'none';
            }
        }
        
        function addLanguage() {
            const lang = prompt('Enter language name (e.g., German, French, Spanish):');
            if (lang && lang.trim()) {
                if (!currentLanguages.includes(lang)) {
                    currentLanguages.push(lang);
                    if (!padData.Program_Descriptions) {
                        padData.Program_Descriptions = {};
                    }
                    padData.Program_Descriptions[lang] = {
                        Char_Desc_45: '',
                        Char_Desc_80: '',
                        Char_Desc_250: '',
                        Char_Desc_450: '',
                        Char_Desc_2000: ''
                    };
                    renderEditor();
                    switchTab(2); // Switch to descriptions tab
                    showStatus('Language added: ' + lang, 'success');
                } else {
                    showStatus('Language already exists', 'error');
                }
            }
        }
        
        function removeLanguage(lang) {
            if (currentLanguages.length <= 1) {
                showStatus('Cannot remove the last language', 'error');
                return;
            }
            if (confirm(`Remove ${lang} language?`)) {
                currentLanguages = currentLanguages.filter(l => l !== lang);
                if (padData.Program_Descriptions) {
                    delete padData.Program_Descriptions[lang];
                }
                renderEditor();
                switchTab(2);
                showStatus('Language removed: ' + lang, 'success');
            }
        }
        
        function arrayToXml(obj, rootName = 'XML_DIZ_INFO') {
            let xml = `<?xml version="1.0" encoding="UTF-8"?>\n<${rootName}>\n`;
            xml += objectToXml(obj, 1);
            xml += `</${rootName}>`;
            return xml;
        }
        
        function objectToXml(obj, indent = 0) {
            let xml = '';
            const spaces = '  '.repeat(indent);
            
            for (const key in obj) {
                if (obj.hasOwnProperty(key)) {
                    const value = obj[key];
                    if (value === null || value === undefined || value === '') {
                        xml += `${spaces}<${key} />\n`;
                    } else if (typeof value === 'object' && !Array.isArray(value)) {
                        // Check if object is empty
                        const hasContent = Object.keys(value).length > 0;
                        if (hasContent) {
                            xml += `${spaces}<${key}>\n`;
                            xml += objectToXml(value, indent + 1);
                            xml += `${spaces}</${key}>\n`;
                        } else {
                            xml += `${spaces}<${key} />\n`;
                        }
                    } else if (Array.isArray(value)) {
                        if (value.length > 0) {
                            value.forEach(item => {
                                xml += `${spaces}<${key}>\n`;
                                xml += objectToXml(item, indent + 1);
                                xml += `${spaces}</${key}>\n`;
                            });
                        } else {
                            xml += `${spaces}<${key} />\n`;
                        }
                    } else {
                        const strValue = String(value).trim();
                        if (strValue === '') {
                            xml += `${spaces}<${key} />\n`;
                        } else {
                            const escaped = strValue.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
                            xml += `${spaces}<${key}>${escaped}</${key}>\n`;
                        }
                    }
                }
            }
            
            return xml;
        }
        
        function savePad() {
            const xml = arrayToXml(padData);
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('xml', xml);
            
            fetch('editor.php?api=1', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Download the XML file
                    const blob = new Blob([data.data], { type: 'application/xml' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'padfile.xml';
                    a.click();
                    URL.revokeObjectURL(url);
                    showStatus('PAD file saved and downloaded', 'success');
                } else {
                    showStatus('Error: ' + data.message, 'error');
                }
            })
            .catch(err => {
                showStatus('Error: ' + err.message, 'error');
            });
        }
        
        function validatePad() {
            const xml = arrayToXml(padData);
            
            const formData = new FormData();
            formData.append('action', 'validate');
            formData.append('xml', xml);
            
            showStatus('Validating...', 'info');
            
            fetch('editor.php?api=1', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const result = data.data;
                    let message = `Validation complete: ${result.errorCount} error(s), ${result.warningCount} warning(s)`;
                    if (result.errorCount === 0 && result.warningCount === 0) {
                        message = '✅ Validation successful! No errors or warnings.';
                        showStatus(message, 'success');
                    } else {
                        showStatus(message, result.errorCount > 0 ? 'error' : 'info');
                        // Could show detailed errors/warnings in a modal
                        console.log('Errors:', result.errors);
                        console.log('Warnings:', result.warnings);
                    }
                } else {
                    showStatus('Error: ' + data.message, 'error');
                }
            })
            .catch(err => {
                showStatus('Error: ' + err.message, 'error');
            });
        }
        
        function showStatus(message, type) {
            const statusDiv = document.getElementById('loadStatus');
            
            // Clear any existing timeout
            if (statusDiv._timeout) {
                clearTimeout(statusDiv._timeout);
                statusDiv._timeout = null;
            }
            
            // Clear previous content
            statusDiv.innerHTML = '';
            statusDiv.className = 'status status-' + type;
            
            // Create message content
            const messageSpan = document.createElement('span');
            messageSpan.textContent = message;
            statusDiv.appendChild(messageSpan);
            
            // Add dismiss button for errors
            if (type === 'error') {
                const dismissBtn = document.createElement('button');
                dismissBtn.className = 'status-dismiss';
                dismissBtn.textContent = '✕';
                dismissBtn.title = 'Dismiss';
                dismissBtn.onclick = () => {
                    statusDiv.innerHTML = '';
                    statusDiv.className = '';
                    if (statusDiv._timeout) {
                        clearTimeout(statusDiv._timeout);
                        statusDiv._timeout = null;
                    }
                };
                statusDiv.appendChild(dismissBtn);
            }
            
            // Auto-dismiss for info and success messages only
            if (type === 'info' || type === 'success') {
                statusDiv._timeout = setTimeout(() => {
                    statusDiv.innerHTML = '';
                    statusDiv.className = '';
                    statusDiv._timeout = null;
                }, 5000);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>


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
// PADVALIDATOR.PHP
//
// DESCRIPTION
//
// Representation of a PAD validator in the PADValidator class.
// Use this class to validate a PAD file against the PAD specification.
//
// SAMPLE CODE
//
// // Create PAD file object for an URL (could also be a local file)
// $PAD = new PADFile("http://myproduct.com/pad_file.xml");
//
// // Load PAD file (see Constants section for possible error codes)
// if ( !$PAD->Load() )
//   die "Cannot load PAD file. Error Code: " . $PAD->LastError;
//
// // Create PAD validator file object for for the local pad_spec.xml file
// $PADValidator = new PADValidator("pad_spec.xml");
//
// // Load PAD file (see Constants section for possible error codes)
// if ( !$PADValidator->Load() )
//   die "Cannot load PAD Validator. Error Code: " . $PADValidator->LastError;
//
// // Validate
// $nErrors = $PADValidator->Validate($PAD);
//
// // Print validation errors
// echo $nErrors . " Errors";
// if ( $nErrors > 0 )
//   foreach($PADValidator->ValidationErrors as $err)
//     $err->Dump();
//
// HISTORY
//
// 2004-08-16 Initial Release (Oliver Grahl, ASP PAD Support)
//
//////////////////////////////////////////////////////////////////////////////


//////////////////////////////////////////////////////////////////////////////
// Includes
//////////////////////////////////////////////////////////////////////////////

include_once("padfile.php");
include_once("padspec.php");


//////////////////////////////////////////////////////////////////////////////
// Classes
//////////////////////////////////////////////////////////////////////////////

// PADValidationError class
// Represents an abstract PAD validation error
class PADValidationError
{
  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  // IN: &$PADValidator - reference to the PADValidator object holding this error
  function PADValidationError($PADValidator)
  {
    // Append this error to the array
    // Note: Warnings also go here, but they're also added to ValidationWarnings separately
    array_push($PADValidator->ValidationErrors, $this);
  }


  //////////////////////////////////////////////////////////////////////////////
  // Methods
  //////////////////////////////////////////////////////////////////////////////

  // Dump error to HTML
  function Dump()
  {
    echo "Unknown Error.";
  }

  // Dump a name/value pair
  // IN: $Name  - the name
  // IN: $Value - the corresponding value
  function DumpValue($Name, $Value)
  {
    $nameStr = ($Name === null) ? "" : (string)$Name;
    $valueStr = ($Value === null) ? "" : (string)$Value;
    echo "<pre><b>" . htmlspecialchars($nameStr . ":") . "</b> ";
    if ( $valueStr == "" )
      echo "<i>(empty)</i>";
    else
      echo htmlspecialchars($valueStr);
    echo "</pre>";
  }

  // Dump an error string
  // IN: $Title - the title
  // IN: $Text  - the error text
  // IN: $Descr - the error description
  function DumpError($Title, $Text, $Descr)
  {
    $titleStr = ($Title === null) ? "" : (string)$Title;
    $textStr = ($Text === null) ? "" : (string)$Text;
    $descrStr = ($Descr === null) ? "" : (string)$Descr;
    echo "<b>" . "Invalid " . htmlspecialchars($titleStr) . "</b> " .
         "<br/>" . htmlspecialchars($textStr) . " " .
         "<i>" . htmlspecialchars($descrStr) . "</i>";
  }
}

// PADValidationSimpleError class (derives from PADValidationError)
// Represents a PAD validation error
class PADValidationSimpleError extends PADValidationError
{
  //////////////////////////////////////////////////////////////////////////////
  // Public Properties
  //////////////////////////////////////////////////////////////////////////////

  var $SpecFieldNode;
  var $Value;
  var $ErrorText;


  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  // IN: &$PADValidator - reference to the PADValidator object holding this error
  // IN: &$SpecFieldNode - reference to the XMLNode object holding the field spec
  // IN: $Value          - current value of the field
  function PADValidationSimpleError($PADValidator, $SpecFieldNode, $Value, $ErrorText)
  {
    // Inherited
    parent::PADValidationError($PADValidator);

    $this->SpecFieldNode = $SpecFieldNode;
    $this->Value = $Value;

    $this->ErrorText = $ErrorText;
  }


  //////////////////////////////////////////////////////////////////////////////
  // Methods
  //////////////////////////////////////////////////////////////////////////////

  // Dump error to HTML
  function Dump()
  {
    $this->DumpValue($this->SpecFieldNode->GetValue("Name"), $this->Value);
    $this->DumpError($this->SpecFieldNode->GetValue("Title"), $this->ErrorText, "");
  }
}


class PADValidationRegExError extends PADValidationError
{
  //////////////////////////////////////////////////////////////////////////////
  // Public Properties
  //////////////////////////////////////////////////////////////////////////////

  var $SpecFieldNode;
  var $Value;


  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  // IN: &$PADValidator - reference to the PADValidator object holding this error
  // IN: &$SpecFieldNode - reference to the XMLNode object holding the field spec
  // IN: $Value          - current value of the field
  function PADValidationRegExError($PADValidator, $SpecFieldNode, $Value)
  {
    // Inherited
    parent::PADValidationError($PADValidator);

    $this->SpecFieldNode = $SpecFieldNode;
    $this->Value = $Value;
  }


  //////////////////////////////////////////////////////////////////////////////
  // Methods
  //////////////////////////////////////////////////////////////////////////////

  // Dump error to HTML
  function Dump()
  {
    $this->DumpValue($this->SpecFieldNode->GetValue("Name"), $this->Value);
    $this->DumpError($this->SpecFieldNode->GetValue("Title"),
                     "does not match specification:",
                     $this->SpecFieldNode->GetValue("RegExDocumentation"));
  }
}

// PADValidationWarning class (derives from PADValidationError)
// Represents a PAD validation warning (non-fatal issue)
class PADValidationWarning extends PADValidationError
{
  //////////////////////////////////////////////////////////////////////////////
  // Public Properties
  //////////////////////////////////////////////////////////////////////////////

  var $WarningText;


  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  // IN: &$PADValidator - reference to the PADValidator object holding this warning
  // IN: $WarningText   - the warning message
  function PADValidationWarning($PADValidator, $WarningText)
  {
    // Set the warning text property FIRST, before calling parent
    // This ensures it's set before any parent constructor operations
    if ($WarningText === null || $WarningText === false) {
      $this->WarningText = "";
    } elseif (is_string($WarningText)) {
      $this->WarningText = $WarningText;
    } else {
      $this->WarningText = (string)$WarningText;
    }
    
    // Inherited - this adds to ValidationErrors, but we'll also add to ValidationWarnings
    parent::PADValidationError($PADValidator);
    
    // Ensure WarningText is still set after parent constructor
    if (!isset($this->WarningText) || $this->WarningText === null) {
      if ($WarningText === null || $WarningText === false) {
        $this->WarningText = "";
      } elseif (is_string($WarningText)) {
        $this->WarningText = $WarningText;
      } else {
        $this->WarningText = (string)$WarningText;
      }
    }
  }


  //////////////////////////////////////////////////////////////////////////////
  // Methods
  //////////////////////////////////////////////////////////////////////////////

  // Dump warning to HTML
  function Dump()
  {
    // Get the warning text, handling null/empty cases
    $text = "";
    if (isset($this->WarningText) && $this->WarningText !== null) {
      $text = (string)$this->WarningText;
    }
    
    // If still empty, try to get it from the property directly
    if (empty($text) && property_exists($this, 'WarningText')) {
      $text = (string)$this->WarningText;
    }
    
    echo "<b><span style=\"color:orange\">Warning:</span></b> " . htmlspecialchars($text);
  }
}





// PADValidator class (derives from PADSpec)
// Represents a PAD Validator
class PADValidator extends PADSpec
{
  //////////////////////////////////////////////////////////////////////////////
  // Public Properties
  //////////////////////////////////////////////////////////////////////////////

  var $ValidationErrors;
  var $ValidationWarnings;


  //////////////////////////////////////////////////////////////////////////////
  // Construction
  //////////////////////////////////////////////////////////////////////////////

  // Constructor
  // IN: $URL - the URL or local path of the PAD spec file (optional)
  function PADValidator($URL = "")
  {
    // Inherited
    parent::PADSpec($URL);

    // Inits
    $this->ValidationErrors = array();
    $this->ValidationWarnings = array();
  }


  function Validate(&$PADFile)
  {
    // Clear
    $this->ValidationErrors = array();
    $this->ValidationWarnings = array();

    // Check for encoding issues
    if (!empty($PADFile->EncodingWarning)) {
      $warningText = trim((string)$PADFile->EncodingWarning);
      // Add more context about the encoding issue
      if (!empty($PADFile->DeclaredEncoding) || !empty($PADFile->DetectedEncoding)) {
        $declared = !empty($PADFile->DeclaredEncoding) ? $PADFile->DeclaredEncoding : "not specified";
        $detected = !empty($PADFile->DetectedEncoding) ? $PADFile->DetectedEncoding : "unknown";
        $warningText .= " (Declared: " . $declared . ", Detected: " . $detected . ")";
      }
      // Only create warning if we have actual text
      if (!empty($warningText)) {
        $warning = new PADValidationWarning($this, $warningText);
        // Force set the property after creation (workaround for property assignment issue)
        $warning->WarningText = $warningText;
        array_push($this->ValidationWarnings, $warning);
      }
    }

    // Verify URL against Application_XML_File_URL
    $PADFieldValue = $PADFile->XML->GetValue("XML_DIZ_INFO/Web_Info/Application_URLs/Application_XML_File_URL");
    $SpecFieldNode = $this->FindFieldNode("XML_DIZ_INFO/Web_Info/Application_URLs/Application_XML_File_URL");

    if ( strcasecmp($PADFile->URL, $PADFieldValue) != 0 )
      new PADValidationSimpleError($this, $SpecFieldNode, $PADFieldValue, "does not match the URL you entered.");

    // Parsing the PAD URL
    $host = parse_url($PADFile->URL, PHP_URL_HOST);

    // Verify that PAD comes from an authorized source
    // Note: This check is commented out to allow validation of PAD files from any source
    // Uncomment the following lines if you want to enforce the official repository requirement
    /*
    if ( strcasecmp($host, 'repository.appvisor.com') != 0 )
      new PADValidationSimpleError( $this, $SpecFieldNode, $PADFieldValue, "This PAD comes from an unauthorized source. Please make sure to publish it in <a href=\"http://repository.appvisor.com?ref=pad_validation_error\"
 target=\"_blank\">the Official PAD Repository</a>. According to the PAD Specification ver.4,
      all PAD files are hosted in <a href=\"http://repository.appvisor.com?ref=pad_validation_error\" target=\"_blank\">the Official PAD
      Repository</a> for security reasons. This ensures the validity of PAD files and protects download sites from PAD Spam.");
    */

      // Return number of errors
    return count($this->ValidationErrors);
  }

  function ValidateRegEx(&$PADFile)
  {
    // Clear
    $this->ValidationErrors = array();


    // Verify RegEx: Walk over all fields in the spec
    foreach($this->FieldsNode->ChildNodes as $SpecFieldNode)
    {
      // Get Path and RegEx for this field
      $Path = $SpecFieldNode->GetValue("Path");
      $RegEx = $SpecFieldNode->GetValue("RegEx");

      // Find the field content in the PAD
      $PADFieldValue = $PADFile->XML->GetValue($Path);

      // Match against the RegEx
      if ( !preg_match("/" . $RegEx . "/", $PADFieldValue) )
        new PADValidationRegExError($this, $SpecFieldNode, $PADFieldValue);
    }


    // Verify descriptions in languages other than English
    $DescrFieldNames = array("Keywords", "Char_Desc_45", "Char_Desc_80",
                             "Char_Desc_250", "Char_Desc_450", "Char_Desc_2000");
    $NodeDescriptions =& $PADFile->XML->FindNodeByPath("XML_DIZ_INFO/Program_Descriptions");
    if ($NodeDescriptions)
      foreach($NodeDescriptions->ChildNodes as $DescrNode)
        if ( $DescrNode->Name != "English" )
          foreach($DescrFieldNames as $DescrFieldName)
          {
            // Find the spec field (with English instead of this language)
            $Path = "XML_DIZ_INFO/Program_Descriptions/" . $DescrNode->Name . "/" . $DescrFieldName;
            $SpecFieldNode = $this->FindFieldNode("XML_DIZ_INFO/Program_Descriptions/English/" . $DescrFieldName);

            // Overriding the Path child node does not work yet, so it will always show 'English'
            //$NodePath = $SpecFieldNode->FindNodeByName("Path");
            //$NodePath->Value = $Path;

            // Get RegEx for this field
            $RegEx = $SpecFieldNode->GetValue("RegEx");

            // Find the field content in the PAD
            $PADFieldValue = $PADFile->XML->GetValue($Path);

            // Match against the RegEx
            if ( !preg_match("/" . $RegEx . "/", $PADFieldValue) )
              new PADValidationRegExError($this, $SpecFieldNode, $PADFieldValue);
          }


    // Verify URL against Application_XML_File_URL
    $PADFieldValue = $PADFile->XML->GetValue("XML_DIZ_INFO/Web_Info/Application_URLs/Application_XML_File_URL");
    $SpecFieldNode = $this->FindFieldNode("XML_DIZ_INFO/Web_Info/Application_URLs/Application_XML_File_URL");
    if ( strcasecmp($PADFile->URL, $PADFieldValue) != 0 )
      new PADValidationSimpleError($this, $SpecFieldNode, $PADFieldValue, "does not match the URL you entered.");


    // Return number of errors
    return count($this->ValidationErrors);
  }


}

?>
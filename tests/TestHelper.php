<?php
/**
 * TestHelper.php for NestedSet in /tests/
 *
 * @category   MyProject
 * @package    MyProject_UnitTest
 * @copyright  Copyright (c) 2008
 * @author     Francois Pietka (fpietka)
 */

//---------------------------------------------------------------------------
// Start output buffering
ob_start();

//---------------------------------------------------------------------------
// Set PHP Errors Reporting

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 'on');

//---------------------------------------------------------------------------
// Maximize memory limit
ini_set('memory_limit', -1);


//---------------------------------------------------------------------------
// Locale settings

ini_set('mbstring.internal_encoding', 'utf-8');
ini_set('mbstring.script_encoding', 'utf-8');
date_default_timezone_set('GMT');

//---------------------------------------------------------------------------
// Define usefull paths

define('ZF_PATH', '/var/www/lib/zf-1.9.2');
define('BASE_PATH', realpath(dirname(__FILE__) . '/..'));

//---------------------------------------------------------------------------
// file inclusion & autoload

set_include_path(

    // frameworks
    ZF_PATH . '/library' . PATH_SEPARATOR .
    BASE_PATH . '/library' . PATH_SEPARATOR .
    BASE_PATH . '/tests' . PATH_SEPARATOR .

    get_include_path()
);

//---------------------------------------------------------------------------
// Start Zend Loader

if (!@include_once('Zend/Loader/Autoloader.php')) {
    trigger_error(sprintf('Unable to load Zend Framework with constant ZF_PATH as value "%s" in %s file.', ZF_PATH, __FILE__), E_USER_ERROR);
}

$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);
$autoloader->suppressNotFoundWarnings(true);

<?php
/*
using Bootstrap 4.0
tested on PHP 7.0.15
*/

require "vendor/autoload.php";

date_default_timezone_set ( 'America/Los_Angeles' );
session_start();

// maybe i don't need these next two anymore? i dnuno :(
ini_set("include_path", '/home/whines/php:' . ini_get("include_path") );
ini_set("include_path", '/Applications/MAMP/bin/php/php7.0.15/lib/php:' . ini_get("include_path") );

// set function for autoloading classes
spl_autoload_register(function ($className) {
        $className = str_replace('\\', DIRECTORY_SEPARATOR, $className); // for subdirectories in 'classes'
        $file = __DIR__.DIRECTORY_SEPARATOR."classes".DIRECTORY_SEPARATOR."{$className}.class.php";
		//echo "$file\n";
		//die;
        if (is_readable($file)) require_once $file;
});

// some constants
define('DEBUG_MODE', true);
define('ERROR_LOG', 'info.txt');
define('URL', "http://{$_SERVER['HTTP_HOST']}/practices/");

define('WEBMASTER', "will@willhines.net");
//define('WEBMASTER', "whines@gmail.com");


// set objects, code, etc
$last_insert_id = null;
include 'libs/lib-logger.php';
include 'libs/db_pdo.php';
include 'libs/wbh_webkit.php';
include 'libs/wbh_webkit_pagination.php';
include 'libs/time_difference.php';
include 'libs/lib-users.php';
include 'libs/lib-workshops.php';
include 'libs/lib-enrollments.php';
include 'libs/lib-lookups.php';
include 'libs/lib-emails.php';

$statuses = Lookups\get_statuses();
$locations = Lookups\get_locations();
define('ENROLLED', Lookups\find_status_by_value('enrolled'));
define('WAITING', Lookups\find_status_by_value('waiting'));
define('DROPPED', Lookups\find_status_by_value('dropped'));
define('INVITED', Lookups\find_status_by_value('invited'));
$late_hours = '24'; // deprecated, use contant in next line
define('LATE_HOURS', 24);
$carriers = array();
$error = '';
$message = '';
$body = '';


Wbhkit\set_vars(array('ac', 'wid', 'uid', 'key', 'page'));

// workshop info
if ($wid) {
	$wk = Workshops\get_workshop_info($wid);
} else {
	$wk = Workshops\empty_workshop();
}

// user info
$already_here_key = (isset($_SESSION['s_key']) ? $_SESSION['s_key'] : null);

$key = Users\current_key(); // checks for key in REQUEST and SESSION and COOKIE, not logged in otherwise
if ($uid) {
	$u = Users\get_user_by_id($uid);
} elseif ($key) {
	$u = Users\key_to_user($key);
} else {
	$u = array();
}

if (isset($u['ukey']) && $u['ukey'] != $already_here_key) {
	$logger->info("{$u['fullest_name']} logged in.");
}

$view = new View();




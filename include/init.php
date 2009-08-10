<?php
if (!defined('PMVCR3')) die('Access violation error!');

$sys_paths = array(
    'inc' => ROOT.DS.'include',
    'lib' => ROOT.DS.'lib',
    'mdl' => ROOT.DS.'models',
    'ctl' => ROOT.DS.'controllers',
    'plg' => ROOT.DS.'plugins',
    'tpl' => ROOT.DS.'templates'
);

include_once(ROOT.DS.'config.php');

include_once($sys_paths['inc'].DS.'utils.php');

/**
 * Class autoloader
 *
 * @param string $class_name The name of class to be loaded
 */
function __autoload($class_name) {
	if (!class_exists($class_name)) {
        $sys_paths =& $GLOBALS['sys_paths'];
	    $class_file = transform_class_name($class_name).'.php';
	    if (file_exists($sys_paths['lib'].DS.$class_file)) { // Search for library class
	        include_once($sys_paths['lib'].DS.$class_file);
	    } else if (file_exists($sys_paths['mdl'].DS.$class_file)) { // Search for model
	        include_once($sys_paths['mdl'].DS.$class_file);
	    } else if (file_exists($sys_paths['ctl'].DS.$class_file)) { // Search for controller
	        include_once($sys_paths['ctl'].DS.$class_file);
	    } else { // Search plugin objects
	        if ($plugin_dir = opendir($sys_paths['plg'])) {
                while (($entry = readdir($plugin_dir)) !== false) {
                    if ($entry == '.' || $entry == '..') continue;
                    if (!is_dir($sys_paths['plg'].DS.$entry)) continue;
                    if (file_exists($sys_paths['plg'].DS.$entry.DS.'models'.DS.$class_file)) {
                        include_once($sys_paths['plg'].DS.$entry.DS.'models'.DS.$class_file);
                        break;
                    } else if (file_exists($sys_paths['plg'].DS.$entry.DS.'controllers'.DS.$class_file)) {
                        include_once($sys_paths['plg'].DS.$entry.DS.'controllers'.DS.$class_file);
                        break;
                    }
                }
                closedir($plugin_dir);
            }
	    }
	}
}
// Class autoloader

/**
 * Load database parameters
 */
$parameters = ActiveObject::find_all('Parameter');
if ($parameters && sizeof($parameters) > 0) {
	foreach ($parameters as $parameter) {
		$param_val = $parameter->param_val;
		settype($param_val, $parameter->param_type);
		$sys_configs[$parameter->param_key] = $param_val;
	}
}
// Load database parameters

/**
 * We do not need magic quotes.
 * If it's turned on, then strip these slashes.
 */
function stripslashes_deep($var) {
    $var = is_array($var)?
        array_map('stripslashes_deep', $var):
        stripslashes($var);
    return $var;
}

if (get_magic_quotes_gpc()) {
    if (isset($_GET) && !empty($_GET)) {
        $_GET = stripslashes_deep($_GET);
    }
    if (isset($_POST) && !empty($_POST)) {
        $_POST = stripslashes_deep($_POST);
    }
    if (isset($_COOKIE) && !empty($_COOKIE)) {
        $_COOKIE = stripslashes_deep($_COOKIE);
    }
}
// Strip slashes

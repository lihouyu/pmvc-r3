<?php
define('PMVCR3', 1);

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

include_once(ROOT.DS.'include'.DS.'init.php');

print_r($sys_configs);

MyDb::close_all();

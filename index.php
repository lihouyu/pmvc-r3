<?php
define('PMVCR3', 1);

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

include_once(ROOT.DS.'config.php');

include_once(ROOT.DS.'include'.DS.'init.php');

MyDb::close_all();

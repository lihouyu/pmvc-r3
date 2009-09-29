<?php
define('PMVCR3', 1);

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

//include_once(ROOT.DS.'config.php');

include_once(ROOT.DS.'include'.DS.'init.php');

raise_signal('onInitialize');

//echo 'Hello, '.get_var('name', false, 'HouYu Li');

//MyDb::close_all();

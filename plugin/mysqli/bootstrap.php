<?php
if (!defined('PMVCR3')) die('Access violation error!');

function load_plugin_mysqli() {
    $my_dir = dirname(__FILE__);
    include_once($my_dir.DS.'functions.php');
    include_once($my_dir.DS.'validations.php');
    include_once($my_dir.DS.'my_db.php');
    include_once($my_dir.DS.'active_object.php');
}

attach_plugin('onInitialize', 'mysqli', 'load_plugin_mysqli', 8);

<?php
if (!defined('PMVCR3')) die('Access violation error!');

function load_plugin_varhandler() {
    $my_dir = dirname(__FILE__);
    include_once($my_dir.DS.'var_handler.php');
}

attach_plugin('onInitialize', 'varhandler', 'load_plugin_varhandler', 5);

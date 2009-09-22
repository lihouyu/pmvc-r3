<?php
if (!defined('PMVCR3')) die('Access violation error!');

function load_plugin_dbparam() {
    $my_dir = dirname(__FILE__);
    include_once($my_dir.'DS'.'models'.DS.'parameter.php');

    /**
     * Load database parameters
     */
    $parameters = ActiveObject::find_all('Parameter');
    if ($parameters && sizeof($parameters) > 0) {
        foreach ($parameters as $parameter) {
            $param_val = $parameter->param_val;
            settype($param_val, $parameter->param_type);
            set_sys_param($parameter->param_key, $param_val);
        }
    }
    // Load database parameters
}

attach_plugin('onInitialize', 'dbparam', 'load_plugin_dbparam');

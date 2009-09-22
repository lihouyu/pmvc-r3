<?php
if (!defined('PMVCR3')) die('Access violation error!');

/**
 * Disable browser cache
 */
function no_cache() {
    header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
} // no_cache()

/**
 * Set system global configuration parameters
 *
 * @param string $key The parameter name
 * @param mixed $val The parameter value
 */
function set_sys_param($key, $val) {
    $global_sys_configs =& $GLOBALS['sys_configs'];
    $global_sys_configs[$key] = $val;
} // set_sys_param($key, $val)

/**
 * Singal event holder
 */
$_signals = array(
    'onInitialize' => array(),
    'beforeControllerLoad' => array(),
    'beforeActionExec' => array(),
    'beforeTplLoad' => array(),
    'onTplRender' => array(),
    'onFinalize' => array()
);

/**
 *
 */
function attach_plugin($event, $name, $entry_func, $priority) {
    $global__signals =& $GLOBALS['_signals'];
} // attach_plugin($event, $name, $entry_func, $priority)
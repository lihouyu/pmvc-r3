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
 * Attach plugin entry to event signal
 *
 * @param string $event The signal name plugin attached to
 * @param string $name The name of the plugin
 * @param string $entry_func The name of the entry function
 * @param integer $priority plugin load priority
 */
function attach_plugin($signal, $name, $entry_func, $priority = 10) {
    $global__signals =& $GLOBALS['_signals'];
    $global__signals[$signal][$priority] = array($name, $entry_func);
} // attach_plugin($event, $name, $entry_func, $priority)

/**
 * Load plugins attached to a specified signal
 *
 * @param string $signal The signal name
 */
function raise_signal($signal) {
    $global_sys_paths =& $GLOBALS['sys_paths'];
    $global__signals =& $GLOBALS['_signals'];
    if (isset($global__signals[$signal])) {
        $signal_plugins = $global__signals[$signal];
        if (sizeof($signal_plugins) > 0) {
            ksort($signal_plugins);
            foreach ($signal_plugins as $priority => $plugin_meta) {
                $plugin_conf = $global_sys_paths['plg'].DS.$plugin_meta[0]
                    .DS.$plugin_meta[0].'.config.php';
                if (file_exists($plugin_conf)) include_once($plugin_conf);
                $plugin_entry_func = $plugin_meta[1];
                $plugin_entry_func();
            }
        }
    }
} // raise_signal($signal)

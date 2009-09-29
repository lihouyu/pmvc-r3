<?php
if (!defined('PMVCR3')) die('Access violation error!');

/**
 * System configurations
 * Can be overrided by parameters loaded from database
 */
$GLOBALS['sys_configs'] = array(
    'enable_table_cache'    => false,
    'table_cache_dir'       => ROOT.DS.'var'.DS.'table_cache'
); // $GLOBALS['sys_configs']

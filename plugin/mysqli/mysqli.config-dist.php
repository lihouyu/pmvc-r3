<?php
if (!defined('PMVCR3')) die('Access violation error!');

/**
 * Database configurations
 */
$db_configs = array(
    'conn1' => array(
        'host'      => 'localhost',     // MySQL server hostname
        'user'      => 'root',          // User for connecting MySQL server
        'passwd'    => '',              // User password
        'database'  => 'test',          // The database to be used
        'charset'   => 'utf8',          // The connection charset
        'prefix'    => 'pr3_',          // The prefix of tables in the database
        'default'   => true             // Use as default connection
    ),
/**
 * Another mysqli connection configuration
    'conn2' => array(
        'host'      => 'localhost',
        'user'      => 'root',
        'passwd'    => '',
        'database'  => 'test',
        'charset'   => 'utf8',
        'prefix'    => 'pr3_',
        'default'   => false
    )
 */
); // $db_configs

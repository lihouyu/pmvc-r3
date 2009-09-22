<?php
if (!defined('PMVCR3')) die('Access violation error!');

/**
 * Variable holder functions
 */
if (!isset($_SESSION[MYHOST]) || !is_array($_SESSION[MYHOST])) {
    $_SESSION[MYHOST] = array();
}
if (!isset($_SESSION[MYHOST]['_$M']) || !is_array($_SESSION[MYHOST]['_$M'])) {
    $_SESSION[MYHOST]['_$M'] = array();
}

/**
 * Get HTTP or manually set variables
 *
 * @param string $var_name The name of the requesting variable
 * @param string $scope The context of the requesting variable. One of following characters or combine of them.
 *          'A': All context
 *          'G': $_GET
 *          'P': $_POST
 *          'C': $_COOKIE
 *          'F': $_FILE
 *          'S': $_SESSION
 *          'M': Manuals
 * @param mixed $default If the requesting variable is not set or empty, this value is returned
 * @return mixed
 */
function get_var($var_name, $scope = 'A', $default = false) {
    if (!$scope) $scope = 'A';
    if ($scope == 'A') $scope = 'SGPCFM';

    $return_var = $default;
    $raw_var = '';

    for ($i = 0; $i < strlen($scope); $i++) {
        if ($scope[$i] == 'S') {
            $raw_var = _get_var($_SESSION[MYHOST], $var_name);
        } else if ($scope[$i] == 'G') {
            $raw_var = _get_var($_GET, $var_name);
        } else if ($scope[$i] == 'P') {
            $raw_var = _get_var($_POST, $var_name);
        } else if ($scope[$i] == 'C') {
            $raw_var = _get_var($_COOKIE, $var_name);
        } else if ($scope[$i] == 'F') {
            $raw_var = _get_var($_FILE, $var_name);
        } else if ($scope[$i] == 'M') {
            $raw_var = _get_var($_SESSION[MYHOST]['_$M'], $var_name);
        }
        if (is_string($raw_var) && strlen(trim($raw_var)) == 0) {
            // Just for simplify the logic
        } else {
            $return_var = $raw_var;
            break;
        }
    }

    return $return_var;
} // get_var($var_name, $scope = 'A', $default = false)

/**
 * This function should never be called directly
 */
function _get_var(&$container, $var_name) {
    $return_var = '';
    if (isset($container[$var_name])) {
        if (is_bool($container[$var_name])) {
            $return_var = $container[$var_name];
        } else {
            if (strlen(trim(strval($container[$var_name]))) > 0) {
                $return_var = $container[$var_name];
            }
        }
    }
    return $return_var;
} // _get_var(&$container, $var_name)

/**
 * Set manual variables
 *
 * @param string $var_name The name of the new variable
 * @param string $val_val The name of the new variable
 */
function set_var($var_name, $var_val) {
    $_SESSION[MYHOST]['_$M'][$var_name] = $var_val;
} // set_var($var_name, $var_val)

/**
 * Set session variables
 *
 * @param string $var_name The name of the new variable
 * @param string $val_val The name of the new variable
 */
function set_session($var_name, $var_val) {
    $_SESSION[MYHOST][$var_name] = $var_val;
} // set_session($var_name, $var_val)

// Variable holder functions

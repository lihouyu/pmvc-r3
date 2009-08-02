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
 * Make plural word
 *
 * @param string $noun The word to be processed
 * @return string
 */
function pluralize($noun) {
    if (preg_match('/(s|sh|ch|x)$/i', $noun)) {
        $pl_noun = $noun.'es';
    } else if (preg_match('/y$/i', $noun)) {
        if (preg_match('/([aeiou]y)$/i', $noun)) {
            $pl_noun = $noun.'s';
        } else {
            $pl_noun = substr($noun, 0, strlen($noun) - 1).'ies';
        }
    } else {
        $pl_noun = $noun.'s';
    }

    return $pl_noun;
} // pluralize($noun)

/**
 * Generate table name according to object name
 *
 * @param string $class_name The class name to be processed
 * @return string
 */
function transform_class_name($class_name) {
    $t_class_name = '';

    // Get upcase letter index
    $up_case_idx = array();

    for ($i = 1; $i < strlen($class_name); $i++) {
        if (ord($class_name[$i]) >= 65 &&
            ord($class_name[$i]) <= 90) {
            $up_case_idx[] = $i;
        }
    }
    //

    if (sizeof($up_case_idx) == 0) {
        $t_class_name = $class_name;
    } else {
        $start_idx = 0;
        for($i = 0; $i < sizeof($up_case_idx); $i++) {
            $t_class_name .= '_'.substr($class_name,
                $start_idx, $up_case_idx[$i] - $start_idx);
            $start_idx = $up_case_idx[$i];
        }
        $t_class_name .= '_'.substr($class_name,
                $start_idx);

        $t_class_name = substr($t_class_name, 1);
    }

    return strtolower($t_class_name);
} // transform_class_name($class_name)

/**
 * Check the value whether it's in a valid number format
 *
 * @param string $var The value to be checked
 * @return bool
 */
function v_is_numeric($var) {
    return is_numeric($var);
} // v_is_numeric($var)

/**
 * Check the string whether it's empty
 * It will strip HTML tags and entities automatically
 *
 * @param string $var The string to be checked
 * @return bool
 */
function v_is_empty($var) {
    $var = strip_tags($var);
    $var = str_replace('&nbsp;', '', $var);
    return v_custom_match('/^\s*$/', $var);
} // v_is_empty($var)

/**
 * Check the value whether it's in a valid email format
 *
 * @param string $var The value to be checked
 * @return bool
 */
function v_is_email($var) {
    $email_parts = explode('@', $var);
    if (sizeof($email_parts) != 2) {
        return false;
    }

    /* check the name part */
    if (preg_match('/^\..*$/', trim($email_parts[0])) ||
        preg_match('/^.*\.$/', trim($email_parts[0])) ||
        preg_match('/^\..*\.$/', trim($email_parts[0]))) {
        return false;
    }
    if (!preg_match('/^[0-9a-zA-Z\!#\$%\*\/\?\|\^\{\}`~&\'\+\-=_\.]+$/', trim($email_parts[0]))) {
        return false;
    }

    /* check the domain part */
    if (preg_match('/\.\./', trim($email_parts[1]))) {
        return false;
    }
    $domain_parts = explode('.', $email_parts[1]);
    if (sizeof($domain_parts) < 2) {
        return false;
    }
    foreach ($domain_parts as $s) {
        $s = trim($s);
        if (preg_match('/^\-.*$/', $s) ||
            preg_match('/^.*\-$/', $s) ||
            preg_match('/^\-.*\-$/', $s)) {
            return false;
        }
        if (!preg_match('/^[0-9a-zA-Z\-]+$/', $s)) {
            return false;
        }
    }

    return true;
} // v_is_email($var)

/**
 * Check the string according to the given pattern
 *
 * @param string $regexp The pattern you want to test on the input string
 * @param string $var The input string
 * @return bool
 */
function v_custom_match($regexp, $var) {
    return preg_match($regexp, $var);
} // v_custom_match($regexp, $var)

<?php
if (!defined('PMVCR3')) die('Access violation error!');

/**
 * Inherited MySQL connection object, provides singleton object.
 */
final class MyDb extends mysqli {
    /**
     * $_INSTANCE holds the singleton object
     *
     * @access private
     * @static
     * @var array
     */
    private static $_INSTANCE = array();

    /**
     * $_prefix is the prefix of data tables
     *
     * @access private
     * @var string
     */
    private $_prefix;

    /**
     * The input parameter place holder in SQL
     *
     * @var constant
     */
    const PLACE_HOLDER = '?';

    /**
     * The private constructor used only in singleton process
     *
     * @access private
     * @param string $db_host MySQL server hostname
     * @param string $db_user User for connecting MySQL server
     * @param string $db_passwd User password
     * @param string $db_database The database to be used
     * @param string $tbl_prefix The prefix of data tables
     */
    private function __construct($db_host, $db_user, $db_passwd, $db_database, $tbl_prefix) {
        parent::__construct($db_host, $db_user, $db_passwd, $db_database);
        $this->_prefix = $tbl_prefix;
    } // MyDb::__construct($db_host, $db_user, $db_passwd, $db_database, $tbl_prefix)

    /**
     * Get the instance name of MySQLi connection which is marked as the default one
     *
     * @access private
     * @static
     */
    private static function _get_default_instance_name() {
        $global_db_configs =& $GLOBALS['db_configs'];
        if (sizeof($global_db_configs) < 1) {
            return false;
        }

        $i = 0;
        $default_instance_name = false;
        foreach ($global_db_configs as $instance_name => $db_config) {
            if ($i == 0) {
                $default_instance_name = $instance_name;
            }
            if (isset($db_config['default']) && $db_config['default']) {
                $default_instance_name = $instance_name;
                break;
            }
            $i++;
        }

        return $default_instance_name;
    } // MyDb::_get_default_instance_name()

    /**
     * The the configuration of the given instance name
     *
     * @access private
     * @static
     * @param string $instance_name The name of MySQLi connection instance
     */
    private static function &_get_instance_config($instance_name) {
        $global_db_configs =& $GLOBALS['db_configs'];
        if (!isset($global_db_configs[$instance_name])) {
            return false;
        }

        $instance_config = $global_db_configs[$instance_name];
        return $instance_config;
    } // & MyDb::_get_instance_config($instance_name)

    /**
     * Return the reference of the singleton'ed object instance
     *
     * @access public
     * @static
     * @param string $instance_name The name of MySQLi connection instance
     * @return reference The reference of the established MySQLi connection object
     */
    public static function &get_instance($instance_name = '_default') {
        if ($instance_name == '_default') {
            $instance_name = self::_get_default_instance_name();
            if (!$instance_name) {
                return false;
            }
        }
        $instance_config =& self::_get_instance_config($instance_name);
        if (!$instance_config) {
            return false;
        }
        if (!isset(self::$_INSTANCE[$instance_name]) ||
            !is_object(self::$_INSTANCE[$instance_name])) {
            $me = __CLASS__;
            self::$_INSTANCE[$instance_name] = new $me($instance_config['host'],
                                                       $instance_config['user'],
                                                       $instance_config['passwd'],
                                                       $instance_config['database'],
                                                       $instance_config['prefix']);
            self::$_INSTANCE[$instance_name]->set_charset($instance_config['charset']);
        }
        return self::$_INSTANCE[$instance_name];
    } // & MyDb::get_instance($instance_name = '_default')

    /**
     * Close all MySQLi database connection instance
     *
     * @access public
     * @static
     */
    public static function close_all() {
        if (sizeof(self::$_INSTANCE) > 0) {
            foreach(self::$_INSTANCE as $instance) {
                @$instance->close();
            }
        }
    } // MyDb::close_all()

    /**
     * Replace place holder in sql with escaped parameters
     *
     * @access private
     * @param string $sql The SQL with place holders
     * @param array $params Parameters
     * @param string $quote_mask
     * @return string A good SQL
     */
    private function _make_sql($sql, $params = array(), $quote_mask = '') {
        if (!is_array($params)) {
            return false;
        }
        if (!is_string($quote_mask)) {
            $quote_mask = '';
        }
        $sql_parts = explode(self::PLACE_HOLDER, $sql);
        $n_parts = sizeof($sql_parts);
        if ($n_parts == 1) {
            return $sql;
        }
        if ($n_parts - 1 != sizeof($params)) {
            return false;
        }
        if (sizeof($params) != strlen($quote_mask)) {
            $quote_mask = '';
        }

        $full_sql = $sql_parts[0];
        for ($i = 1; $i < $n_parts; $i++) {
            if (strlen($quote_mask) == 0 || $quote_mask[$i - 1] == '1') {
                $full_sql .= "'".$this->real_escape_string($params[$i - 1])."'".$sql_parts[$i];
            } else {
                $full_sql .= $this->real_escape_string($params[$i - 1]).$sql_parts[$i];
            }
        }

        return $full_sql;
    } // MyDb->_make_sql($sql, $params = array(), $quote_mask = '')

    /**
     * The query method according to mysqli::query
     *
     * @access public
     * @param string $sql The SQL with place holders
     * @param array $params Parameters
     * @param string $quote_mask
     * @return mix Query or update result
     */
    public function exec_query($sql, $params = array(), $quote_mask = '') {
        if (sizeof($params) == 0) {
            $good_sql = $sql;
        } else {
            $good_sql = $this->_make_sql($sql, $params, $quote_mask);
        }

        if (!$good_sql) {
            return false;
        }

        return parent::query($good_sql);
    } // MyDb->exec_query($sql, $params = array(), $quote_mask = '')

    /**
     * Get the prefix of data tables
     *
     * @access public
     * @return string The prefix of data tables
     */
    public function get_prefix() {
        return $this->_prefix;
    } // MyDb->get_prefix()
} // MyDb

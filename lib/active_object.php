<?php
if (!defined('PMVCR3')) die('Access violation error!');

/**
 * The Object-Relation Mapping(ORM) class
 */
class ActiveObject {
    /**
     * The MySQLi connection instance holder
     *
     * @access protected
     * @var object
     */
    protected $mydb = false;

    /**
     * $prefix is the prefix of data tables
     *
     * @access protected
     * @var string
     */
    protected $prefix;

    /**
     * Data table name represented in database
     *
     * @access protected
     * @var string
     */
    protected $table_name = false;

    /**
     * Record table object
     *
     * @access protected
     * @var object
     */
    protected $table_object = false;

    /**
     * Indicate whether this object is new created
     *
     * @access protected
     * @var bool
     */
    protected $stat_new = false;

    /**
     * The auto_increment field name
     *
     * @access protected
     * @var string
     */
    protected $aikey = false;

    /**
     * The class name for the current object
     *
     * @access protected
     * @var string
     */
    protected $class_name;

    /**
     * Validations require true
     *
     * @access protected
     * @var array
     */
    protected $yes_validate = array();

    /**
     * Validations require false
     *
     * @access protected
     * @var array
     */
    protected $no_validate = array();

    /**
     * Error message holder
     *
     * @access public
     * @var string
     */
    public $errmsg;

    /**
     * ActiveObject constructor
     *
     * @param int $aikey_val The auto_increment key value for loading an existing ActiveObject
     * @param bool $stat_new Whether the RecordObject is newly created or loaded from an existing record
     */
    public function __construct($aikey_val = false, $stat_new = true) {
        $sys_configs =& $GLOBALS['sys_configs'];
        $this->class_name = get_class($this);

        /* Get default MySQLi connection instance */
        $this->mydb =& MyDB::get_instance();
        if (!$this->mydb) return false;
        $this->prefix = $this->mydb->get_prefix();

        /* Generate table name if no custom table name given */
        if (!$this->table_name) {
            $this->table_name = pluralize(transform_class_name($this->class_name));
        }

        /* Load table info */
        //$this->table_object =& $this->_getRecordTable($this->_table_name);
        $table_object_cache_file = TABLE_CACHE_DIR.DS.$this->table_name.'.cache';
        if ($sys_configs['enable_table_cache']) {
            if (file_exists($table_object_cache_file)) {
                $table_object_cache = file_get_contents($table_object_cache_file);
                $this->table_object = unserialize($table_object_cache);
            }
        }
        if (!$this->table_object) {
            $rs_table = $this->mydb->exec_query("DESCRIBE `{$this->prefix}{$this->table_name}`");
            if (!$rs_table) return false;
            $this->table_object = new stdClass();
            $this->table_object->fields = array();
            $this->table_object->pkeys = array();
            $this->table_object->aikey = false;
            while ($field = $rs_table->fetch_object()) {
                $this->table_object->fields[] = $field;
                if (strpos(strtolower($field->Key), 'pri') !== false) {
                    $this->table_object->pkeys[] = $field->Field;
                }
                if (strpos(strtolower($field->Extra), 'auto_increment') !== false) {
                    $this->table_object->aikey = $field->Field;
                }
            }
            if (!$this->table_object->aikey && $this->aikey) {
                $this->table_object->aikey = $this->aikey;
            }
            $rs_table->free();
        }
        if (!$this->table_object) return false;
        if ($sys_configs['enable_table_cache'] &&
            is_writable($table_object_cache_file)) {
            file_put_contents($table_object_cache_file, serialize($this->table_object));
        }
        //

        /* Set default object status */
        $this->stat_new = $stat_new;

        /* load object data if the $aikey_val is not false */
        if ($aikey_val !== false) {
            if (!$this->table_object->aikey) {
                return false;
            } else {
                $sql = "SELECT * FROM `{$this->prefix}{$this->table_name}` "
                    ."WHERE `{$this->table_object->aikey}`=?";
                $rs = $this->mydb->exec_query($sql, array($aikey_val));
                if (!$rs) return false;
                $row = $rs->fetch_assoc();
                if (NULL != $row) {
                    $this->set($row);
                    $this->stat_new = false;
                }
                $rs->free();
            }
        }
    } // ActiveObject::__construct($aikey_val = false, $stat_new = true)

    /**
     * Set object data
     *
     * @access public
     * @param array $params Parameters in key=>value pairs while key represents the property of the object
     */
    public function set($params) {
        if ($this->stat_new) {
            foreach ($this->table_object->fields as $field) {
                $attr_name = $field->Field;
                if (isset($params[$attr_name])) {
                    $this->$attr_name = $params[$attr_name];
                } else {
                    $this->$attr_name = $field->Default;
                }
            }
        } else {
            foreach ($params as $attr_name => $value) {
                $this->$attr_name = $value;
            }
        }
    } // ActiveObject->set($params)

    /**
     * Insert new object data
     *
     * @access protected
     * @return bool
     */
    protected function insert() {
        $fields = "";
        $value_place_holders = "";
        $values = array();

        foreach ($this->table_object->fields as $field) {
            if ($field->Field != $this->table_object->aikey) {
                $attr_name = $field->Field;
                $fields .= ", `$attr_name`";
                $value_place_holders .= ", ?";
                if (!isset($this->$attr_name)) {
                    $values[] = $field->Default;
                } else {
                    $values[] = $this->$attr_name;
                }
            }
        }
        $fields = substr($fields, 2);
        $value_place_holders = substr($value_place_holders, 2);

        $sql = "INSERT INTO `{$this->prefix}{$this->table_name}` ($fields) VALUES "
            ."($value_place_holders)";
        $rs = $this->mydb->exec_query($sql, $values);
        if ($rs) {
            if ($this->table_object->aikey) {
                $insert_id = $this->mydb->insert_id();
                $ai_attr_name = $this->table_object->aikey;
                $this->$ai_attr_name = $insert_id;
            }

            $this->stat_new = false;
        }

        return $rs;
    } // ActiveObject->insert()

    /**
     * Update object data
     *
     * @access protected
     * @return bool
     */
    protected function update() {
        if (!$this->table_object->aikey) {
            return false;
        } else {
            $set_fields = "";
            $values = array();

            foreach ($this->table_object->fields as $field) {
                if ($field->Field != $this->table_object->aikey) {
                    $attr_name = $field->Field;
                    $set_fields .= ", `$attr_name`=?";
                    $values[] = $this->$attr_name;
                }
            }
            $set_fields = substr($set_fields, 2);

            $ai_attr_name = $this->table_object->aikey;
            $where = "`$ai_attr_name`=?";
            $values[] = $this->$ai_attr_name;

            $sql = "UPDATE `{$this->prefix}{$this->table_name}` SET $set_fields "
                ."WHERE $where";
            $rs = $this->mydb->exec_query($sql, $values);
            return $rs;
        }
    } // ActiveObject->update()

    /**
     * Insert or save object data
     *
     * @access public
     * @return bool
     */
    public function save() {
        /* validate data required */
        // TODO : how to check whether the object has the specified attribute.
        $error_msg = '';
        if (sizeof($this->yes_validate) >= 1) {
            foreach ($this->yes_validate as $validate => $attr_array) {
                if (sizeof($attr_array) == 0) {
                    continue;
                }
                if ($validate == '_regexp_') {
                    foreach ($attr_array as $attr) {
                        $attr_name = $attr[1];
                        if (!v_custom_match($attr[0], $this->$attr_name)) {
                            $error_msg .= $attr[2]."\n";
                        }
                    }
                } else {
                    foreach ($attr_array as $attr) {
                        $attr_name = $attr[0];
                        $validator_name = 'v_'.$validate;
                        if (!$validator_name($this->$attr_name)) {
                            $error_msg .= $attr[1]."\n";
                        }
                    }
                }
            }
        }
        if (sizeof($this->no_validate) >= 1) {
            foreach ($this->no_validate as $validate => $attr_array) {
                if (sizeof($attr_array) == 0) {
                    continue;
                }
                if ($validate == '_regexp_') {
                    foreach ($attr_array as $attr) {
                        $attr_name = $attr[1];
                        if (v_custom_match($attr[0], $this->$attr_name)) {
                            $error_msg .= $attr[2]."\n";
                        }
                    }
                } else {
                    foreach ($attr_array as $attr) {
                        $attr_name = $attr[0];
                        $validator_name = 'v_'.$validate;
                        if ($validator_name($this->$attr_name)) {
                            $error_msg .= $attr[1]."\n";
                        }
                    }
                }
            }
        }
        if (!empty($error_msg)) {
            $this->errmsg = $error_msg;
            return false;
        }

        /* execute update */
        if ($this->stat_new) {
            return $this->insert();
        } else {
            return $this->update();
        }
    } // ActiveObject->save()

    /**
     * Delete current object record
     *
     * @access public
     * @return bool
     */
    public function delete() {
        $pkey_size = sizeof($this->table_object->pkeys);

        if (!$this->table_object->aikey && $pkey_size == 0) {
            return false;
        } else {
            if (!$this->stat_new) {
                $where = "";
                $params = array();
                if ($this->table_object->aikey) {
                    $attr_name = $this->table_object->aikey;
                    $where .= " AND `$attr_name`=?";
                    $params[] = $this->$attr_name;
                }
                if ($pkey_size >= 1) {
                    for ($i = 0; $i < $pkey_size; $i++) {
                        $attr_name = $this->table_object->pkeys[$i];
                        $where .= " AND `$attr_name`=?";
                        $params[] = $this->$attr_name;
                    }
                }
                $where = substr($where, 4);

                $sql = "DELETE FROM `{$this->prefix}{$this->table_name}` WHERE $where";
                $rs = $this->mydb->exec_query($sql, $params);
                return $rs;
            } else {
                return false;
            }
        }
    } // ActiveObject->delete()

    /* Static data accessor */
    /**
     * The MySQLi connection instance holder for static accessors
     *
     * @access private
     * @var object
     */
    private static $_mydb = false;

    /**
     * $prefix is the prefix of data tables for static accessors
     *
     * @access private
     * @var string
     */
    private static $_prefix = false;

    /**
     * Transfer class name to table name
     *
     * @access private
     * @static
     * @param string $class_name The name of requested class
     * @return string The translated table name
     */
    private static function _get_table_name($class_name) {
        return pluralize(transform_class_name($class_name));
    } // ActiveObject::_get_table_name($class_name)

    /**
     * Find the first matched record
     * Only first record is returned
     *
     * @access public
     * @final
     * @static
     * @param string $class_name The name of requested class
     * @param string $where The WHERE SQL used for filtering records
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @param string $more_sql Additional SQL conditions to sort, group or limit records selection
     * @return object
     */
    final public static function find($class_name, $where = false, $params = array(), $more_sql = false) {
        if (!self::$_mydb) self::$_mydb = MyDB::get_instance();
        if (!self::$_prefix) self::$_prefix = self::$_mydb->get_prefix();

        $table_name = self::_get_table_name($class_name);
        $sql = "SELECT * FROM `".self::$_prefix."$table_name`";
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        if ($more_sql !== false) {
            $sql .= " $more_sql";
        }
        $rs = self::$_mydb->exec_query($sql, $params);
        if (!$rs) return false;
        $object = $rs->fetch_object($class_name,
            array(false, false));
        $rs->free();
        if (NULL != $object) {
            return $object;
        } else {
            return false;
        }
    } // ActiveObject::find($class_name, where = false, $params = array(), $more_sql = false)

    /**
     * Find all matched record
     *
     * @access public
     * @final
     * @static
     * @param string $class_name The name of requested class
     * @param string $where The WHERE SQL used for filtering records
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @param string $more_sql Additional SQL conditions to sort, group or limit records selection
     * @return array
     */
    final public static function find_all($class_name, $where = false, $params = array(), $more_sql = false) {
        if (!self::$_mydb) self::$_mydb = MyDB::get_instance();
        if (!self::$_prefix) self::$_prefix = self::$_mydb->get_prefix();

        $objects = array();

        $table_name = self::_get_table_name($class_name);
        $sql = "SELECT * FROM `".self::$_prefix."$table_name`";
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        if ($more_sql !== false) {
            $sql .= " $more_sql";
        }
        $rs = self::$_mydb->exec_query($sql, $params);
        if (!$rs) return false;
        while ($row = $rs->fetch_object($class_name, array(false, false))) {
            $objects[] = $row;
        }
        $rs->free();
        if (sizeof($objects) > 0) {
            return $objects;
        } else {
            return false;
        }
    } // ActiveObject::find_all($class_name, $where = false, $params = array(), $more_sql = false)

    /**
     * Count record
     *
     * @access public
     * @static
     * @final
     * @param string $class_name The name of requested class
     * @param string $where The WHERE SQL used for counting records
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @return int
     */
    final public static function count($class_name, $where = false, $params = array()) {
        if (!self::$_mydb) self::$_mydb = MyDB::get_instance();
        if (!self::$_prefix) self::$_prefix = self::$_mydb->get_prefix();

        $table_name = self::_get_table_name($class_name);
        $sql = "SELECT COUNT(*) FROM `".self::$_prefix."$table_name`";
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        $rs = self::$_mydb->exec_query($sql, $params);
        if (!$rs) return 0;
        $row = $rs->fetch_row();
        $rs->free();
        return $row[0];
    } // ActiveObject::count($class_name, $where = false, $params = array())
    // Static data accessor
}

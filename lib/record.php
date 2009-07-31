<?php
/**
 * The Object-Relation Mapping(ORM) related class
 * 
 * @package record
 * @author Li HouYu <karadog@gmail.com>
 * @copyright Copyright (c) 2007, pMVC Reloaded Team
 * 
 * This file is part of pMVC Reloaded.
 *
 * pMVC Reloaded is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * pMVC Reloaded is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */ 

if (!defined('IN_CONTEXT')) die('access violation error!');

/**
 * Constant CACHE_DIR
 * The directory for saving RecordTable cache file
 */
if (!defined('CACHE_DIR')) {
    $cache_path = dirname(__FILE__).'/cache';
    if (!file_exists($cache_path) && !@mkdir($cache_path, 0777)) {
        trigger_error('record table cache dir cannot be created. '
            .'thus record table cache will not be available!', 
            E_USER_WARNING);
    }
    define('CACHE_DIR', $cache_path);
}

/**
 * Constant REL_BOTH
 * Tell function loadRelatedObjects() to load all related record objects
 */
if (!defined('REL_BOTH')) define ('REL_BOTH', 610);

/**
 * Constant REL_CHILDREN
 * Tell function loadRelatedObjects() to load children record objects
 * defined in $has_one, $has_many
 */
if (!defined('REL_CHILDREN')) define('REL_CHILDREN', 611);

/**
 * Constant REL_PARENT
 * Tell function loadRelatedObjects() to load parent record objects
 * defined in $belong_to, $belong_to_many
 */
if (!defined('REL_PARENT')) define('REL_PARENT', 612);

/**
 * The table object for storing table structure
 *
 * @package record
 */
class RecordTable {
    /**
     * Table fields info
     *
     * @access public
     * @var array
     */
    public $fields = array();

    /**
     * Table primary keys
     *
     * @access public
     * @var array
     */
    public $pkeys = array();

    /**
     * Table auto increment key
     * Only one auto_increment key is stored here
     * The last one has the most precedence
     *
     * @access public
     * @var string
     */
    public $aikey;

    /**
     * RecordTable contructor
     *
     * @param string $table_name The table name
     */
    public function __construct($table_name) {
        $db =& MysqlConnection::get();
        try {
            $rs =& $db->query("DESCRIBE `$table_name`");
    
            while ($field =& $rs->fetchObject()) {
                $this->fields[] = $field;
                if (strpos(strtolower($field->Key), 'pri') !== false) {
                    $this->pkeys[] = $field->Field;
                }
                if (strpos(strtolower($field->Extra), 'auto_increment') !== false) {
                    $this->aikey = $field->Field;
                }
            }
    
            $rs->free();
    
            $this->_cacheRecordTable($table_name);
        } catch (MysqlException $ex) {
            throw new RecordException($ex->getMessage());
        }
    }

    /**
     * Cache record table object when get it
     *
     * @access private
     * @param string $table_name The table name
     */
    private function _cacheRecordTable($table_name) {
        $record_table_cache_file = CACHE_DIR.'/'.$table_name.'.cache';

        $fp = @fopen($record_table_cache_file, 'w');
        @fwrite($fp, serialize($this));
        @fclose($fp);
    }
}

/**
 * The Object-Relation Mapping(ORM) class
 *
 * @package record
 */
class RecordObject {
    /**
     * The class name for the current object
     *
     * @access protected
     * @var string
     */
    protected $_class_name;

    /**
     * Data table name represented in database
     *
     * @access protected
     * @var string
     */
    protected $_table_name;

    /**
     * Record table object
     *
     * @access protected
     * @var object
     */
    protected $_table;

    /**
     * Indicate whether this object is new created
     *
     * @access protected
     * @var bool
     */
    protected $_stat_new;

    /**
     * 1 -> 1 children relation objects
     *
     * @access public
     * @var array
     */
    public $has_one = array();

    /**
     * 1 -> many children relation objects
     *
     * @access public
     * @var array
     */
    public $has_many = array();

    /**
     * 1 -> 1 parent relation objects
     *
     * @access public
     * @var array
     */
    public $belong_to = array();

    /**
     * 1 -> many parent relation objects
     *
     * @access public
     * @var array
     */
    public $belong_to_many = array();

    /**
     * Array for holding parent objects
     *
     * @access public
     * @var array
     */
    public $masters = array();

    /**
     * Array for holding children objects
     *
     * @access public
     * @var array
     */
    public $slaves = array();
    
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
     * RecordObject constructor
     *
     * @param int $aikey_val The auto_increment key value for loading an existing RecordObject
     * @param bool $_stat_new Whether the RecordObject is newly created or loaded from an existing record
     */
    public function __construct($aikey_val = false, $_stat_new = true) {
        /* set the class name */
        $this->_class_name = get_class($this);

        /* generate table name if no custom table name given */
        if ($this->_table_name == null) {
            $this->_table_name = Config::$tbl_prefix.Toolkit::pluralize(
                Toolkit::transformClassName($this->_class_name));
        }

        try {
            /* load table info */
            $this->_table =& $this->_getRecordTable($this->_table_name);
    
            /* set default object status */
            $this->_stat_new = $_stat_new;
    
            /* load object data if the $aikey_val is not false */
            if ($aikey_val !== false) {
                if (empty($this->_table->aikey)) {
                    throw new RecordException('No auto_increment key defined in table!'."\n");
                } else {
                    $sql = "SELECT * FROM `{$this->_table_name}` "
                        ."WHERE `{$this->_table->aikey}`=?";
                    $db =& MysqlConnection::get();
                    $rs =& $db->query($sql, array($aikey_val));
                    $row =& $rs->fetchRow();
                    if ($row !== false) {
                        $this->set($row);
                        $this->_stat_new = false;
                    }
                    $rs->free();
                }
            }
        } catch (RecordException $ex) {
            throw new RecordException($ex->getMessage());
        } catch (MysqlException $ex) {
            throw new RecordException('Failed loading record!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * Get record table object from cache or from new instance
     *
     * @access protected
     * @param string $table_name The table name
     * @return object
     */
    protected function &_getRecordTable($table_name) {
        $record_table_cache_file = CACHE_DIR.'/'.$table_name.'.cache';
        $record_table = false;

        try {
            if (file_exists($record_table_cache_file)) {
                $fp = @fopen($record_table_cache_file, 'r');
                $record_table_str = @fread($fp, filesize($record_table_cache_file));
                @fclose($fp);
                $record_table =& unserialize($record_table_str);
            } else {
                $record_table =& new RecordTable($table_name);
            }
            return $record_table;
        } catch (RecordException $ex) {
            throw new RecordException('Failed getting record table!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * Find the first matched record
     * Only first record is returned
     *
     * @access public
     * @param string $where The WHERE SQL used for filtering records
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @param string $more_sql Additional SQL conditions to sort, group or limit records selection
     * @return object
     */
    public function &find($where = false, $params = false, $more_sql = false) {
        $sql = "SELECT * FROM `{$this->_table_name}`";
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        if ($more_sql !== false) {
            $sql .= " $more_sql";
        }
        $db =& MysqlConnection::get();
        try {
            $rs =& $db->query($sql, $params);
            $object =& $rs->fetchObject($this->_class_name, 
                array(false, false));
            $rs->free();
            return $object;
        } catch (MysqlException $ex) {
            throw new RecordException('Failed loading records!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * Find all matched record
     *
     * @access public
     * @param string $where The WHERE SQL used for filtering records
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @param string $more_sql Additional SQL conditions to sort, group or limit records selection
     * @return array
     */
    public function &findAll($where = false, $params = false, $more_sql = false) {
        $sql = "SELECT * FROM `{$this->_table_name}`";
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        if ($more_sql !== false) {
            $sql .= " $more_sql";
        }
        $db =& MysqlConnection::get();
        try {
            $rs =& $db->query($sql, $params);
            $objects =& $rs->fetchObjects($this->_class_name, 
                array(false, false));
            $rs->free();
            return $objects;
        } catch (MysqlException $ex) {
            throw new RecordException('Failed loading records!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * Find all record according to given fields and values
     *
     * @access public
     * @param string|array $mix_field Field(s) that used for filtering records
     * @param string|array $mix_value Value(s) according to field(s)
     * @param string $more_sql Additional SQL conditions to sort, group or limit records selection
     * @return array
     */
    public function &findBy($mix_field, $mix_value, $more_sql = false) {
        if (is_array($mix_field) && !is_array($mix_value)) {
            throw new RecordException('Parameter type not match!'."\n");
        }

        $where = "";
        $params = array();
        if (is_array($mix_field)) {
            for ($i = 0; $i < sizeof($mix_field); $i++) {
                $where .= " AND {$mix_field[$i]}=?";
            }
            $where = substr($where, 4);
            $params = $mix_value;
        } else {
            $where = " $mix_field=?";
            $params = array($mix_value);
        }
        try {
            $objects =& $this->findAll($where, $params, $more_sql);
            return $objects;
        } catch (RecordException $ex) {
            throw new RecordException($ex->getMessage());
        }
    }

    /**
     * Count record
     *
     * @access public
     * @param string $where The WHERE SQL used for counting records
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @return int
     */
    public function count($where = false, $params = false) {
        $sql = "SELECT COUNT(*) FROM `{$this->_table_name}`";
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        $db =& MysqlConnection::get();
        try {
        $rs =& $db->query($sql, $params);
        $row =& $rs->fetchRow(RSTYPE_NUM);
        $rs->free();
        return $row[0];
        } catch (MysqlException $ex) {
            throw new RecordException('Failed loading data!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * Insert new object data
     *
     * @access public
     * @return bool
     */
    protected function _insert() {
        $fields = "";
        $value_place_holders = "";
        $values = array();

        foreach ($this->_table->fields as $field) {
            if ($field->Field != $this->_table->aikey) {
                $attr_name = $field->Field;
                $fields .= ", `$attr_name`";
                $value_place_holders .= ", ?";
                $values[] = $this->$attr_name;
            }
        }
        $fields = substr($fields, 2);
        $value_place_holders = substr($value_place_holders, 2);

        $sql = "INSERT INTO `{$this->_table_name}` ($fields) VALUES "
            ."($value_place_holders)";
        $db =& MysqlConnection::get();
        try {
            $rs = $db->query($sql, $values);
    
            if ($rs) {
                if (!empty($this->_table->aikey)) {
                    $insert_id = $db->getInsertId();
                    $ai_attr_name = $this->_table->aikey;
                    $this->$ai_attr_name = $insert_id;
                }
    
                $this->_stat_new = false;
            }
    
            return $rs;
        } catch (MysqlException $ex) {
            throw new RecordException('Failed executing update!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * Update object data
     *
     * @access public
     * @return bool
     */
    protected function _update() {
        $rs = false;

        if (empty($this->_table->aikey)) {
            throw new RecordException('AUTO_INCREMENT key not defined in table. '
                .'No record updated!'."\n");
        } else {
            $set_fields = "";
            $values = array();

            foreach ($this->_table->fields as $field) {
                if ($field->Field != $this->_table->aikey) {
                    $attr_name = $field->Field;
                    $set_fields .= ", `$attr_name`=?";
                    $values[] = $this->$attr_name;
                }
            }
            $set_fields = substr($set_fields, 2);

            $ai_attr_name = $this->_table->aikey;
            $where = "`$ai_attr_name`=?";
            $values[] = $this->$ai_attr_name;

            $sql = "UPDATE `{$this->_table_name}` SET $set_fields "
                ."WHERE $where";
            $db =& MysqlConnection::get();
            try {
                $rs = $db->query($sql, $values);
                return $rs;
            } catch (MysqlException $ex) {
                throw new RecordException('Failed executing update!'."\n"
                                          .$ex->getMessage());
            }
        }
    }

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
                        if (!DataValidator::customMatch($attr[0], $this->$attr_name)) {
                            $error_msg .= $attr[2]."\n";
                        }
                    }
                } else {
                    foreach ($attr_array as $attr) {
                        $attr_name = $attr[0];
                        if (!DataValidator::$validate($this->$attr_name)) {
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
                        if (DataValidator::customMatch($attr[0], $this->$attr_name)) {
                            $error_msg .= $attr[2]."\n";
                        }
                    }
                } else {
                    foreach ($attr_array as $attr) {
                        $attr_name = $attr[0];
                        if (DataValidator::$validate($this->$attr_name)) {
                            $error_msg .= $attr[1]."\n";
                        }
                    }
                }
            }
        }
        if (!empty($error_msg)) {
            throw new RecordException($error_msg);
        }

        /* execute update */
        try {
            if ($this->_stat_new) {
                return $this->_insert();
            } else {
                return $this->_update();
            }
        } catch (RecordException $ex) {
            throw new RecordException($ex->getMessage());
        }
    }

    /**
     * Delete current object record
     *
     * @access public
     * @return bool
     */
    public function delete() {
        $pkey_size = sizeof($this->_table->pkeys);

        $rs = false;

        if (empty($this->_table->aikey) && $pkey_size == 0) {
            throw new RecordException('Neither auto_increment key nor '
                .'primary key defined in table. '
                .'No record deleted!'."\n");
        } else {
            if (!$this->_stat_new) {
                $where = "";
                $params = array();
                if (!empty($this->_table->aikey)) {
                    $attr_name = $this->_table->aikey;
                    $where .= " AND `$attr_name`=?";
                    $params[] = $this->$attr_name;
                }
                if ($pkey_size >= 1) {
                    for ($i = 0; $i < $pkey_size; $i++) {
                        $attr_name = $this->_table->pkeys[$i];
                        $where .= " AND `$attr_name`=?";
                        $params[] = $this->$attr_name;
                    }
                }
                $where = substr($where, 4);

                $sql = "DELETE FROM `{$this->_table_name}` WHERE $where";
                $db =& MysqlConnection::get();
                try {
                    $rs = $db->query($sql, $params);
                    return $rs;
                } catch (MysqlException $ex) {
                    throw new RecordException('Failed executing update!'."\n"
                                              .$ex->getMessage());
                }
            }
        }
    }

    /**
     * Set object data
     *
     * @access public
     * @param array $params Parameters in key=>value pairs while key represents the property of the object
     */
    public function set($params) {
        if ($this->_stat_new) {
            foreach ($this->_table->fields as $field) {
                $attr_name = $field->Field;
                $this->$attr_name = $params[$attr_name];
            }
        } else {
            foreach ($params as $attr_name => $value) {
                $this->$attr_name = $value;
            }
        }
    }

    /**
     * Load related objects
     *
     * @access public
     * @param int $direct The relation of objects to be loaded
     * @param array $target_objs Only load objects defined in this array
     */
    public function loadRelatedObjects($direct = REL_BOTH, $target_objs = array()) {
        /* relation fallback to REL_BOTH */
        if ($direct != REL_BOTH && $direct != REL_PARENT && 
            $direct != REL_CHILDREN) {
            $direct = REL_BOTH;
        }

        $db =& MysqlConnection::get();

        try {
            if ($direct == REL_BOTH || $direct == REL_CHILDREN) {
                /* the has_one relation */
                if (sizeof($this->has_one) >= 1) {
                    foreach ($this->has_one as $class_name) {
                        if (!empty($target_objs) && 
                            !in_array($class_name, $target_objs)) {
                            continue;
                        }
                        $object =& new $class_name();
                        if (in_array($this->_class_name, $object->belong_to)) {
                            $t_class_name = 
                                Toolkit::transformClassName($this->_class_name);
                            $ai_attr_name = $this->_table->aikey;
                            $this->slaves[$class_name] =& 
                                $object->find("{$t_class_name}_id=?", 
                                array($this->$ai_attr_name));
                        } else {
                            throw new RecordException('Broken relation: '.$this->_class_name
                                .' has_one '.$class_name.'!'."\n");
                        }
                        unset($object);
                    }
                }
    
                /* the has_many relation */
                if (sizeof($this->has_many) >= 1) {
                    foreach ($this->has_many as $class_name) {
                        if (!empty($target_objs) && 
                            !in_array($class_name, $target_objs)) {
                            continue;
                        }
                        $object =& new $class_name();
                        if (in_array($this->_class_name, $object->belong_to)) {
                            $t_class_name = 
                                Toolkit::transformClassName($this->_class_name);
                            $ai_attr_name = $this->_table->aikey;
                            $this->slaves[$class_name] =& 
                                $object->findAll("{$t_class_name}_id=?", 
                                array($this->$ai_attr_name));
                        } else if (in_array($this->_class_name, 
                            $object->belong_to_many)) {
                                $t_class_name = 
                                    Toolkit::transformClassName($this->_class_name);
                                $t_class_name_s = 
                                    Toolkit::transformClassName($class_name);
                                $ai_attr_name = $this->_table->aikey;
                                $ai_attr_name_s = $object->_table->aikey;
                                $table_name_s = $object->_table_name;
                                $table_name_r = $t_class_name.'_'.$t_class_name_s;
                                $id_r = $t_class_name.'_id';
                                $id_r_s = $t_class_name_s.'_id';
                                $sql = "SELECT `$table_name_s`.* FROM `$table_name_s`, "
                                    ."`$table_name_r` WHERE "
                                    ."`$table_name_s`.`$ai_attr_name_s`=`$table_name_r`.`$id_r_s` "
                                    ."AND `$table_name_r`.`$id_r`=?";
    
                                $rs =& $db->query($sql, array($this->$ai_attr_name));
                                $this->slaves[$class_name] =& 
                                    $rs->fetchObjects($class_name, 
                                    array(false, false));
                                $rs->free();
                                unset($rs);
                            } else {
                                throw new RecordException('Broken relation: '.$this->_class_name
                                    .' has_many '.$class_name.'!'."\n");
                            }
                        unset($object);
                    }
                }
            }
    
            if ($direct == REL_BOTH || $direct == REL_PARENT) {
                /* the belong_to relation */
                if (sizeof($this->belong_to) >= 1) {
                    foreach ($this->belong_to as $class_name) {
                        if (!empty($target_objs) && 
                            !in_array($class_name, $target_objs)) {
                            continue;
                        }
                        $object =& new $class_name();
                        if (in_array($this->_class_name, $object->has_one) || 
                            in_array($this->_class_name, $object->has_many)) {
                                $t_class_name_m = 
                                    Toolkit::transformClassName($class_name);
                                $id_r_m = $t_class_name_m.'_id';
                                $ai_attr_name_m = $object->_table->aikey;
                                $this->masters[$class_name] =& 
                                    $object->find("$ai_attr_name_m=?", 
                                    array($this->$id_r_m));
                            } else {
                                throw new RecordException('Broken relation: '.$this->_class_name
                                    .' belong_to '.$class_name.'!'."\n");
                            }
                        unset($object);
                    }
                }
    
                /* the belong_to_many relation */
                if (sizeof($this->belong_to_many) >= 1) {
                    foreach ($this->belong_to_many as $class_name) {
                        if (!empty($target_objs) && 
                            !in_array($class_name, $target_objs)) {
                            continue;
                        }
                        $object =& new $class_name();
                        if (in_array($this->_class_name, $object->has_many)) {
                            $t_class_name = 
                                Toolkit::transformClassName($this->_class_name);
                            $t_class_name_m = 
                                Toolkit::transformClassName($class_name);
                            $ai_attr_name = $this->_table->aikey;
                            $ai_attr_name_m = $object->_table->aikey;
                            $table_name_m = $object->_table_name;
                            $table_name_r = $t_class_name_m.'_'.$t_class_name;
                            $id_r = $t_class_name.'_id';
                            $id_r_m = $t_class_name_m.'_id';
                            $sql = "SELECT `$table_name_m`.* FROM `$table_name_m`, "
                                ."`$table_name_r` WHERE "
                                ."`$table_name_m`.`$ai_attr_name_m`=`$table_name_r`.`$id_r_m` "
                                ."AND `$table_name_r`.`$id_r`=?";
    
                            $rs =& $db->query($sql, array($this->$ai_attr_name));
                            $this->masters[$class_name] =& 
                                $rs->fetchObjects($class_name, 
                                array(false, false));
                            $rs->free();
                            unset($rs);
                        } else {
                            throw new RecordException('Broken relation: '.$this->_class_name
                                .' belong_to_many '.$class_name.'!'."\n");
                        }
                        unset($object);
                    }
                }
            }
        } catch (RecordException $ex) {
            throw new RecordException($ex->getMessage());
        } catch (MysqlException $ex) {
            throw new RecordException('Failed loading records!'."\n"
                                      .$ex->getMessage());
        }
    }

    /**
     * TODO: To be finished
     * Check whether current object has child objects according to the given object name
     *
     * @access public
     * @param string $object_name The name of child object
     * @param string $where The WHERE SQL used for looking for children
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     * @return bool
     */
    public function hasChildren($object_name, $where = false, $params = false) {
        
    }

    /**
     * TODO: To be finished
     * Delete child objects according to the given object name
     *
     * @access public
     * @param string $object_name The name of child object
     * @param string $where The WHERE SQL used for looking for children
     * @param array $params Parameters used for replacing place holders in the WHERE SQL
     */
    public function deleteChildren($object_name, $where = false, $params = false) {
        
    }

    /**
     * TODO: To be finished
     * Delete all child objects
     *
     * @access public
     */
    public function deleteAllChildren() {
        
    }
}

/**
 * Exception class for handling exceptions in record object
 *
 * @package record
 */
class RecordException extends Exception {
}
?>
<?php

/**
 * @revision       $Id: class.database.php 94 2010-08-16 18:18:47Z aleks $
 * @version        1.0.0 proxy plugin $
 * @package        myorangehost ispmgr plugins
 * @copyright      Copyright © 2016 - All rights reserved.
 * @license        GNU/GPL
 * @author         Alexey Gordeyev IK
 * @author mail    aleksej@gordejev.lv
 * @website        http://www.gordejev.lv/
 *
 */
require_once 'class.logger.php';


Class DataBase extends Logger
{
    var $_dbfile = null;
    var $_sql = null;
    var $_limit = null;
    var $_offset = null;
    var $_db = null;
    var $_timeout = 5000;
    var $_cursor = null;
    var $_quoted = null;
    var $_nameQuote = null;
    var $_hasQuoted = null;
    var $_errorNum = array();
    var $_errorMsg = array();

    /**
     * DataBase constructor.
     * @param string $dbfile
     */
    function __construct($dbfile)
    {
        $this->_dbfile = $dbfile;
        $this->_db = new SQLite3($dbfile);
        $this->_db->busyTimeout($this->_timeout);
    }

    /**
     * Sets the SQL query string for later execution.
     *
     * This function replaces a string identifier <var>$prefix</var> with the
     * string held is the <var>_table_prefix</var> class variable.
     *
     * @method setQuery
     * @access public
     * @param string The SQL query
     * @param string The offset to start selection
     * @param string The number of results to return
     */
    function setQuery($sql, $offset = 0, $limit = 0)
    {
        $this->_sql = $sql;
        $this->_limit = (int)$limit;
        $this->_offset = (int)$offset;
    }

    /**
     * Get the active query
     * @method getQuery
     * @access public
     * @return string The current value of the internal SQL vairable
     */
    function getQuery()
    {
        return $this->_sql;
    }

    /**
     * Execute the query
     *
     * @method query
     * @access public
     * @return mixed A database resource if successful, FALSE if not.
     */
    function query()
    {
        if (!$this->_db) {
            return false;
        }

        // Take a local copy so that we don't modify the original query and cause issues later
        $sql = $this->_sql;
        if ($this->_limit > 0 || $this->_offset > 0) {
            $sql .= ' LIMIT ' . $this->_offset . ', ' . $this->_limit;
        }
        if ($this->_debug) {
            $this->writeLog($sql);
        }
        $this->_cursor = $this->_db->query($sql);
        if (!$this->_cursor) {
            return false;
        }
        return $this->_cursor;
    }

    /**
     * Get a database escaped string
     *
     * @param     string    The string to be escaped
     * @param     boolean   Optional parameter to provide extra escaping
     * @return    string
     * @access    public
     * @abstract
     */
    function getEscaped($text)
    {
        return $this->_db->escapeString($text);
    }

    /**
     * Description
     *
     * @access    public
     * @return int The number of affected rows in the previous operation
     * @since 1.0.5
     */
    function getAffectedRows()
    {
        return $this->_db->changes();
    }

    /**
     * Description
     *
     * @access    public
     * @return int The number of rows returned from the most recent query.
     */
    function getNumRows($cur = null)
    {
        if (!$cur) {
            $this->_offset = 0;
            $this->_limit = 0;
            $this->query();
        }
        // TODO: need implement
        return 0;
    }


    /**
     * This method loads the first field of the first row returned by the query.
     *
     * @access    public
     * @return The value returned in the query or null if the query failed.
     */
    function loadResult()
    {
        if (!($cur = $this->query())) {
            return null;
        }
        $ret = null;
        if ($rows = $cur->fetchArray(SQLITE3_ASSOC)) {
            $ret = $rows[0];
        }
        return $ret;
    }

    /**
     * Load an array of single field results into an array
     *
     * @access    public
     */
    function loadResultArray($numinarray = 0)
    {
        if (!($cur = $this->query())) {
            return null;
        }
        $array = array();
        while ($rows = $cur->fetchArray(SQLITE3_ASSOC)) {
            $array[] = $rows[$numinarray];
        }
        return $array;
    }


    /**
     * Fetch a result row as an associative array
     *
     * @access    public
     * @return array
     */
    function loadAssoc()
    {
        if (!($cur = $this->query())) {
            return null;
        }
        $ret = null;
        if ($array = $cur->fetchArray(SQLITE3_ASSOC)) {
            $ret = $array;
        }
        return $ret;
    }

    /**
     * Load a assoc list of database rows
     *
     * @access    public
     * @param string The field name of a primary key
     * @return array If <var>key</var> is empty as sequential list of returned records.
     */
    function loadAssocList($key = '')
    {
        if (!($cur = $this->query())) {
            return null;
        }
        $array = array();
        while ($row = $cur->fetchArray(SQLITE3_ASSOC)) {
            if ($key) {
                $array[$row[$key]] = $row;
            } else {
                $array[] = $row;
            }
        }
        return $array;
    }

    /**
     * Inserts a row into a table based on an objects properties
     *
     * @access public
     * @param string    The name of the table
     * @param object    An object whose properties match table fields
     * @param string    The name of the primary key. If provided the object property is updated.
     */
    function insertObject($table, &$object, $keyName = NULL)
    {
        $fmtsql = 'INSERT INTO ' . $this->nameQuote($table) . ' ( %s ) VALUES ( %s ) ';
        $fields = array();
        foreach (get_object_vars($object) as $k => $v) {
            if (is_array($v) or is_object($v) or $v === NULL) {
                continue;
            }
            if ($k[0] == '_') { // internal field
                continue;
            }
            $fields[] = $this->nameQuote($k);
            $values[] = $this->isQuoted($k) ? $this->Quote($v) : (int)$v;
        }
        $this->setQuery(sprintf($fmtsql, implode(",", $fields), implode(",", $values)));
        if (!$this->query()) {
            return false;
        }
        $id = $this->insertid();
        if ($keyName && $id) {
            $object->$keyName = $id;
        }
        return true;
    }

    /**
     * Description
     *
     * @access public
     * @param [type] $updateNulls
     */
    function updateObject($table, &$object, $keyName, $updateNulls = true)
    {
        $fmtsql = 'UPDATE ' . $this->nameQuote($table) . ' SET %s WHERE %s';
        $tmp = array();
        foreach (get_object_vars($object) as $k => $v) {
            if (is_array($v) or is_object($v) or $k[0] == '_') { // internal or NA field
                continue;
            }
            if ($k == $keyName) { // PK not to be updated
                $where = $keyName . '=' . $this->Quote($v);
                continue;
            }
            if ($v === null) {
                if ($updateNulls) {
                    $val = 'NULL';
                } else {
                    continue;
                }
            } else {
                $val = $this->isQuoted($k) ? $this->Quote($v) : (int)$v;
            }
            $tmp[] = $this->nameQuote($k) . '=' . $val;
        }
        $this->setQuery(sprintf($fmtsql, implode(",", $tmp), $where));
        return $this->query();
    }


    /**
     * Description
     *
     * @access public
     * @param [type] $updateNulls
     */
    function findAndUpdateObject($table, &$object, $keyName, $updateNulls = true)
    {
        $fmtsql = 'UPDATE ' . $this->nameQuote($table) . ' SET %s WHERE %s';
        $tmp = array();
        foreach (get_object_vars($object) as $k => $v) {
            if (is_array($v) or is_object($v) or $k[0] == '_') { // internal or NA field
                continue;
            }
            if ($k == $keyName) { // PK not to be updated
                $where = $keyName . '=' . $this->Quote($v);
                continue;
            }
            if ($v === null) {
                if ($updateNulls) {
                    $val = 'NULL';
                } else {
                    continue;
                }
            } else {
                $val = $this->isQuoted($k) ? $this->Quote($v) : (int)$v;
            }
            $tmp[] = $this->nameQuote($k) . '=' . $val;
        }
        $this->setQuery(sprintf($fmtsql, implode(",", $tmp), $where));
        return $this->query();
    }

    /**
     * Description
     *
     * @access public
     */
    function insertid()
    {
        return $this->_db->lastInsertRowID();
    }

    /**
     * Description
     *
     * @access    public
     * @return array A list of all the tables in the database
     */
    function getTableList()
    {
        $this->setQuery('SHOW TABLES');
        return $this->loadResultArray();
    }

    /**
     * Shows the CREATE TABLE statement that creates the given tables
     *
     * @access    public
     * @param    array|string A table name or a list of table names
     * @return    array A list the create SQL for the tables
     */
    function getTableCreate($tables)
    {
        settype($tables, 'array'); //force to array
        $result = array();

        foreach ($tables as $tblval) {
            $this->setQuery('SHOW CREATE table ' . $this->getEscaped($tblval));
            $rows = $this->loadRowList();
            foreach ($rows as $row) {
                $result[$tblval] = $row[1];
            }
        }

        return $result;
    }

    /**
     * Retrieves information about the given tables
     *
     * @access    public
     * @param    array|string A table name or a list of table names
     * @param    boolean            Only return field types, default true
     * @return    array An array of fields by table
     */
    function getTableFields($tables, $typeonly = true)
    {
        settype($tables, 'array'); //force to array
        $result = array();

        foreach ($tables as $tblval) {
            $this->setQuery('SHOW FIELDS FROM ' . $tblval);
            $fields = $this->loadObjectList();

            if ($typeonly) {
                foreach ($fields as $field) {
                    $result[$tblval][$field->Field] = preg_replace("/[(0-9)]/", '', $field->Type);
                }
            } else {
                foreach ($fields as $field) {
                    $result[$tblval][$field->Field] = $field;
                }
            }
        }

        return $result;
    }

    /**
     * Adds a field or array of field names to the list that are to be quoted
     *
     * @access public
     * @param mixed Field name or array of names
     * @since 1.5
     */
    function addQuoted($quoted)
    {
        if (is_string($quoted)) {
            $this->_quoted[] = $quoted;
        } else {
            $this->_quoted = array_merge($this->_quoted, (array)$quoted);
        }
        $this->_hasQuoted = true;
    }

    /**
     * Checks if field name needs to be quoted
     *
     * @access public
     * @param string The field name
     * @return bool
     */
    function isQuoted($fieldName)
    {
        if ($this->_hasQuoted) {
            return in_array($fieldName, $this->_quoted);
        } else {
            return true;
        }
    }

    /**
     * Quote an identifier name (field, table, etc)
     *
     * @access    public
     * @param    string    The name
     * @return    string    The quoted name
     */
    function nameQuote($s)
    {
        // Only quote if the name is not using dot-notation
        if (strpos($s, '.') === false) {
            $q = $this->_nameQuote;
            if (strlen($q) == 1) {
                return $q . $s . $q;
            } else {
                return $q{0} . $s . $q{1};
            }
        } else {
            return $s;
        }
    }

    /**
     * Get a quoted database escaped string
     *
     * @param string    A string
     * @param boolean    Default true to escape string, false to leave the string unchanged
     * @return string
     * @access public
     */
    function Quote($text, $escaped = true)
    {
        return '\'' . ($escaped ? $this->getEscaped($text) : $text) . '\'';
    }

    /**
     * ADODB compatability function
     *
     * @access  public
     * @param string SQL
     */
    function GetCol($query)
    {
        $this->setQuery($query);
        return $this->loadResultArray();
    }

    /**
     * ADODB compatability function
     *
     * @access public
     * @param string SQL
     * @return array
     */
    function GetRow($query)
    {
        $this->setQuery($query);
        $result = $this->loadRowList();
        return $result[0];
    }

    /**
     * ADODB compatability function
     *
     * @access public
     * @param string SQL
     * @return mixed
     */
    function GetOne($query)
    {
        $this->setQuery($query);
        $result = $this->loadResult();
        return $result;
    }

    /**
     * ADODB compatability function
     *
     */
    function ErrorMsg()
    {
        return $this->_db->lastErrorMsg();
    }

    /**
     * ADODB compatability function
     *
     */
    function ErrorNo()
    {
        return $this->_db->lastErrorCode();
    }


    function __destruct()
    {


    }

}

?>

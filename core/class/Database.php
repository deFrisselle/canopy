<?php
/**
 * A database class
 *
 * @version $Id$
 * @author  Matt McNaney <matt at tux dot appstate dot edu>
 * @package Core
 */
require_once 'DB.php';

define ('DEFAULT_MODE', DB_FETCHMODE_ASSOC);

class PHPWS_DB {

  var $table       = NULL;
  var $where       = array();
  var $order       = array();
  var $values      = array();
  var $mode        = DEFAULT_MODE;
  var $limit       = NULL;
  var $index       = NULL;
  var $column      = NULL;
  var $qwhere      = NULL;
  var $indexby     = NULL;
  var $groupby     = NULL;
  var $_allColumns = NULL;
  var $_columnInfo = NULL;
  var $_DB         = array();
  var $_lock       = FALSE;
  var $_sql        = NULL;
  var $_distinct   = FALSE;

  function PHPWS_DB($table=NULL){
    PHPWS_DB::touchDB();
    if (isset($table)){
      $result = $this->setTable($table);

      if (PEAR::isError($result))
	PHPWS_Error::log($result);
    }
    $this->setMode('assoc');
    $type = $GLOBALS['PEAR_DB']->dbsyntax;

    $result = PHPWS_Core::initCoreClass('DB/' . $type .'.php');
    if ($result == FALSE) {
      PHPWS_Error::log(PHPWS_FILE_NOT_FOUND, 'core', 'PHPWS_DB::PHPWS_DB', 
		       PHPWS_SOURCE_DIR . 'core/class/DB/' . $type . '.php');
      PHPWS_Core::errorPage();
    }
    $this->_sql = & new PHPWS_SQL;
  }
  
  function addDB($db){
    if (!is_object($db))
      return PHPWS_Error::get(PHPWS_DB_NOT_OBJECT, 'core', 'PHPWS_DB::addJoin', gettype($db));

    if (strtoupper(get_class($db)) != 'PHPWS_DB')
      return PHPWS_Error::get(PHPWS_WRONG_CLASS, 'core', 'PHPWS_DB::addJoin', get_class($db));

    if (empty($db->table))
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::addJoin');

    $this->_DB[$db->table] = $db;
  }

  function lock()
  {
    
  }

  function unlock()
  {

  }

  function touchDB(){
    if (!PHPWS_DB::isConnected())
      PHPWS_DB::loadDB();
  }

  function isConnected(){
    if (isset($GLOBALS['PEAR_DB']))
      return TRUE;
    else
      return FALSE;
  }

  function loadDB($dsn=NULL){
    if (PHPWS_DB::isConnected())
      PHPWS_DB::disconnect();

    if (isset($dsn))
      $GLOBALS['PEAR_DB'] = DB::connect($dsn);
    else
      $GLOBALS['PEAR_DB'] = DB::connect(PHPWS_DSN);


    if (PEAR::isError($GLOBALS['PEAR_DB'])){
      PHPWS_Error::log($GLOBALS['PEAR_DB']);
      PHPWS_Core::errorPage();
    }

    if (defined(TABLE_PREFIX))
      PHPWS_DB::setPrefix(TABLE_PREFIX);
    else
      PHPWS_DB::setPrefix(NULL);

    return TRUE;
  }

  function query($sql){
    PHPWS_DB::touchDB();
    return $GLOBALS['PEAR_DB']->query($sql);
  }

  function getColumnInfo($col_name, $parsed=FALSE){
    if (!isset($this->_columnInfo))
      $this->getTableColumns();

    if (isset($this->_columnInfo[$col_name])) {
      if ($parsed == TRUE)
	return $this->parsePearCol($this->_columnInfo[$col_name], TRUE);
      else
	return $this->_columnInfo[$col_name];
    }
    else
      return NULL;
  }

  function getTableColumns($fullInfo=FALSE){
    if (isset($this->_allColumns) && $fullInfo == FALSE) {
      return $this->_allColumns;
    } elseif (isset($this->_columnInfo) && $fullInfo == TRUE) {
      return $this->_columnInfo;
    }

    $table = & $this->getTable();
    if (!isset($table))
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::isTableColumn');

    $columns =  $GLOBALS['PEAR_DB']->tableInfo($table);
    if (PEAR::isError($columns))
      return $columns;

    foreach ($columns as $colInfo) {
      $this->_columnInfo[$colInfo['name']] = $colInfo;
      $this->_allColumns[] = $colInfo['name'];
    }

    if ($fullInfo == TRUE)
      return $this->_columnInfo;
    else
      return $this->_allColumns;
  }

  function isTableColumn($columnName){
    $columns = $this->getTableColumns();

    if (PEAR::isError($columns))
      return $columns;

    return in_array($columnName, $columns);
  }

  function setMode($mode){
    switch (strtolower($mode)){
    case 'ordered':
      $this->mode = DB_FETCHMODE_ORDERED;
      break;

    case 'object':
      $this->mode = DB_FETCHMODE_OBJECT;
      break;

    case 'assoc':
      $this->mode = DB_FETCHMODE_ASSOC;
      break;
    }

  }

  function getMode(){
    return $this->mode;
  }

  function isTable($tableName){
    static $tables;

    if (count($tables) < 1){
      PHPWS_DB::touchDB();
      $tables = PHPWS_DB::listTables();
    }
    return in_array(PHPWS_DB::getPrefix() . $tableName, $tables);
  }

  function listTables(){
    return $GLOBALS['PEAR_DB']->getlistOf('tables');
  }

  function listDatabases(){
    return $GLOBALS['PEAR_DB']->getlistOf('databases');
  }


  function setPrefix($prefix){
    $GLOBALS['PEAR_DB']->prefix = $prefix;
  }

  function getPrefix(){
    return $GLOBALS['PEAR_DB']->prefix;
  }

  function setTable($table){
    if (PHPWS_DB::allowed($table))
      $this->table = $table;
    else
      return PHPWS_Error::get(PHPWS_DB_BAD_TABLE_NAME, 'core', 'PHPWS_DB::setTable', $table);
  }

  function setIndex($index){
    $this->index = $index;
  }

  function getIndex(){
    if (isset($this->index))
      return $this->index;
    
    $columns =  $GLOBALS['PEAR_DB']->tableInfo($this->getTable());
    
    if (PEAR::isError($columns))
      return $columns;
    
    foreach ($columns as $colInfo)
      if ($colInfo['name'] == 'id' && preg_match('/primary/', $colInfo['flags']) && preg_match('/int/', $colInfo['type']))
	return $colInfo['name'];

    return NULL;
  }

  function getTable($prefix=TRUE){
    if ($prefix == TRUE)
      return $this->getPrefix() . $this->table;
    else
      return $this->table;
  }

  function resetTable(){
    $this->table = NULL;
  }

  function addGroup($group, $conj){
    $this->where[$group]['conj'] = $conj;
  }

  function addGroupBy($group_by){
    if (PHPWS_DB::allowed($group_by))
      $this->groupBy[] = $group_by;
  }

  function getGroupBy($dbReady=FALSE){
    if ((bool)$dbReady == TRUE){
      if (!isset($this->groupBy))
	return NULL;
      else
	return 'GROUP BY ' . implode(', ', $this->groupBy);
    }
    return $this->groupBy;
  }

  function addJoinWhere($base_column, $match_column, $operator=NULL, $conj=NULL, $group=NULL){
    $this->addWhere($base_column, $match_column, $operator, $conj, $group, TRUE);
  }

  function addWhere($column, $value, $operator=NULL, $conj=NULL, $group=NULL, $join=FALSE){
    if (isset($operator)) {
      $operator = strtoupper($operator);
    }

    if (is_array($value)){
      if (empty($operator)) {
	$operator = 'IN';
      }
      
      if ($operator != 'IN' && $operator != 'BETWEEN'){
	foreach ($value as $newVal){
	  $result = $this->addWhere($column, $newVal, $operator, $conj, $group);
	  if (PEAR::isError($result))
	    return $result;
	}

	return;
      }
    }


    if (!isset($operator))
      $operator = '=';
    elseif (!PHPWS_DB::checkOperator($operator))
      return PHPWS_Error::get(PHPWS_DB_BAD_OP, 'core', 'PHPWS_DB::addWhere', _('DB Operator:') . $operator);

    if (!isset($conj))
      $conj = 'AND';

    if (!PHPWS_DB::allowed($column))
      return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addWhere', $column);

    if (is_array($value)) {
      foreach ($value as $temp_val) {
	$temp_val = $GLOBALS['PEAR_DB']->escapeSimple($temp_val);
	$new_value_list[] = $temp_val;
      }

      $value = $new_value_list;
    } else {
      $value = $GLOBALS['PEAR_DB']->escapeSimple($value);
    }

    if (isset($group))
      $this->where[$group]['values'][] = array('column'=>$column, 'value'=>$value, 'operator'=>$operator, 'conj'=>$conj, 'join'=>$join);
    else
      $this->where[0]['values'][] = array('column'=>$column, 'value'=>$value, 'operator'=>$operator, 'conj'=>$conj, 'join'=>$join);
  }

  function checkOperator($operator){
    $allowed = array('>',
		     '>=',
		     '<',
		     '<=',
		     '=',
		     '!=',
		     '<>',
		     '<=>',
		     'LIKE',
		     'REGEXP',
		     'IN',
		     'BETWEEN');

    return in_array(strtoupper($operator), $allowed);
  }

  function setQWhere($where){
    $where = preg_replace('/where/i', '', $where);
    $this->qwhere = $where;
  }

  function getWhere($dbReady=FALSE, $addWhere=TRUE){
    $extra = FALSE;
    $where = NULL;

    if (empty($this->where)){
      if (isset($this->qwhere)) {
	return 'WHERE ' . $this->qwhere;
      }
      return NULL;
    }

    $startMain = FALSE;
    if ($dbReady){
      foreach ($this->where as $groups){
	if (!isset($groups['values']))
	  continue;
	$startSub = FALSE;

	if ($startMain == TRUE) $sql[] = $groups['conj'];
	$sql[] = '(';


	foreach ($groups['values'] as $whereValues){
	  extract($whereValues);

	  if (strstr($column, '.') == FALSE)
	    $column = $this->getTable() . '.' . $column;

	  if ($startSub == TRUE) $sql[] = $conj;

	  if (is_array($value)) {
	    switch ($operator){
	    case 'IN':

	      foreach ($value as $temp_val){
		$temp_val_list[] = "'$temp_val'";
	      }
	      $value = '(' . implode(', ', $temp_val_list) . ')';

	      break;

	    case 'BETWEEN':
	      $value = "'{$value[0]}' AND '{$value[1]}'";
	      break;
	    }

	    $sql[] = "$column $operator $value";
	  } else {
	    if ($join)
	      $sql[] = "$column $operator $value";
	    else
	      $sql[] = "$column $operator '$value'";
	  }

	  $startSub = TRUE;
	}

	$sql[] = ')';
	$startMain = TRUE;
      }

      if (isset($this->qwhere))
	$sql[] = ' AND (' . $this->qwhere . ')';

      if (isset($sql)){
	if ($addWhere)
	  $where = 'WHERE ' . implode(' ', $sql);
	else
	  $where = implode(' ', $sql);
      }

      return $where;
    } else
      return $this->where;

  }

  function resetWhere(){
    $this->where = array();
  }

  function isDistinct(){
    $this->_distinct = TRUE;
  }

  function notDistinct(){
    $this->_distinct = FALSE;
  }

  function addColumn($column, $distinct=FALSE){
    if (!PHPWS_DB::allowed($column))
      return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addColumn', $column);

    if ((bool)$distinct == TRUE)
      $column = 'DISTINCT ' .  $column;

    $this->column[] = $column;
  }

  function getColumn(){
    return $this->column;
  }

  function setIndexBy($indexby){
    $this->indexby = $indexby;
  }

  function getIndexBy(){
    return $this->indexby;
  }


  function addOrder($order){
    if (is_array($order)){
      foreach ($order as $value){
	$this->order[] = preg_replace('/[^\w\s]/', '', $value);
      }
    }
    else
      $this->order[] = preg_replace('/[^\w\s]/', '', $order);
  }

  function getOrder($dbReady=FALSE){
    if (!count($this->order))
      return NULL;

    if ($dbReady)
      return 'ORDER BY ' . implode(', ', $this->order);
    else
      return $this->order;
  }

  function resetOrder(){
    $this->order = array();
  }

  function addValue($column, $value=NULL){
    if (is_array($column)){
      foreach ($column as $colKey=>$colVal){
	$result = $this->addValue($colKey, $colVal);
	if (PEAR::isError($result))
	  return $result;
      }
    } else {
      if (!PHPWS_DB::allowed($column))
	return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addValue', $column);

      $this->values[$column] = $value;
    }
  }

  function getValue($column){
    if (!count($this->values) || !isset($this->values[$column]))
      return NULL;

    return $this->values[$column];
  }

  function resetValues(){
    $this->values = array();
  }

  function getAllValues(){
    if (!isset($this->values) || !count($this->values))
      return NULL;

    return $this->values;
  }


  function setLimit($limit, $offset=NULL){
    unset($this->limit);

    if (is_array($limit)) {
      $_limit = $limit[0];
      $_offset = $limit[1];
    }
    elseif (preg_match('/,/', $limit)) {
      $split = explode(',', $limit);
      $_limit = trim($split[0]);
      $_offset = trim($split[1]);
    }
    else {
      $_limit = $limit;
      $_offset = $offset;
    }

    $this->limit['total'] = preg_replace('/[^\d\s]/', '', $_limit);

    if (isset($_offset))
      $this->limit['offset'] = preg_replace('/[^\d\s]/', '', $_offset);

    return TRUE;
  }

  function getLimit($dbReady=FALSE){
    if (empty($this->limit))
      return NULL;
    
    if ($dbReady) {
      return $this->_sql->getLimit($this->limit);
    }
    else {
      return $this->limit;
    }
  }

  function resetLimit(){
    $this->limit = '';
  }

  function resetColumns(){
    $this->column = NULL;
  }


  function affectedRows(){
    $query =  PHPWS_DB::lastQuery();
    $process = strtolower(substr($query, 0, strpos($query, ' ')));

    if ($process == 'select'){
      $rows = $GLOBALS['PEAR_DB']->num_rows; 
      return array_pop($rows);
    }
    else
      return $GLOBALS['PEAR_DB']->affectedRows();
  }

  function reset(){
    $this->resetWhere();
    $this->resetValues();
    $this->resetLimit();
    $this->resetOrder();
    $this->resetColumns();
    $this->indexby = NULL;
    $this->qwhere  = NULL;
  }

  function lastQuery(){
    return $GLOBALS['PEAR_DB']->last_query;
  }

  function insert(){
    $maxID = TRUE;
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::insert');

    $values = $this->getAllValues();

    if (!isset($values))
      return PHPWS_Error::get(PHPWS_DB_NO_VALUES, 'core', 'PHPWS_DB::insert');

    $idColumn = $this->getIndex();

    if (PEAR::isError($idColumn))
      return $idColumn;
    elseif(isset($idColumn)) {
      $maxID = $GLOBALS['PEAR_DB']->nextId($table);
      $values[$idColumn] = $maxID;
    }

    foreach ($values as $index=>$entry){
      $columns[] = $index;
      $set[] = PHPWS_DB::dbReady($entry);
    }

    $query = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $set) . ')';

    $result = PHPWS_DB::query($query);

    if (DB::isError($result))
      return $result;
    else
      return $maxID;
  }

  function update(){
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::update');

    $values = $this->getAllValues();
    $where = $this->getWhere(TRUE);

    foreach ($values as $index=>$data)
      $columns[] = $index . ' = ' . PHPWS_DB::dbReady($data);

    $query = "UPDATE $table SET " . implode(', ', $columns) ." $where";

    $result = PHPWS_DB::query($query);

    if (DB::isError($result))
      return $result;
    else
      return TRUE;
  }

  function addTableNames(&$values, $table){
    
    foreach ($values as $val){
      if (stristr($val, 'count'))
	$newValue[] = $val;
      else
	$newValue[] = $table . '.' . $val;
    }

    $values = $newValue;
  }

  function count(){

    return $this->select('one');
  }

  function getSelectSQL($type){
    $columnList = $this->getColumn();

    if (isset($columnList))
      PHPWS_DB::addTableNames($columnList, $this->table);
      
    if (!empty($this->_DB)){
      $tables[] = $this->getTable();
      foreach ($this->_DB as $altDB){
	$tables[] = $altDB->getTable();
	$extraWhere[] = $altDB->getWhere(TRUE, FALSE);
	$columns = $altDB->getColumn();
	PHPWS_DB::addTableNames($columns, $altDB->table);
	if (isset($columnList))
	  $columnList = array_merge($columnList, $columns);
	else
	  $columnList = $columns;
      }

      $table = implode(', ', $tables);
    } else
      $table = $this->getTable();


    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::select');

    $where   = $this->getWhere(TRUE);
    if (isset($extraWhere))
      $where .= ' AND ' . implode(' AND ', $extraWhere);

    $order   = $this->getOrder(TRUE);
    $limit   = $this->getLimit(TRUE);
    $groupby = $this->getGroupBy(TRUE);

    if (isset($columnList)){
      if (isset($indexby) && !in_array($indexby, $columnList))
	$columnList[] = $indexby;
      if ($type == 'max' || $type == 'min') {
	$columns = implode('', array($type . '(', array_shift($columnList), ')'));
      }
      else {
	$columns = implode(', ', $columnList);
      }

    }
    else {
      $columns = '*';
    }

    $sql_array['columns'] = &$columns;
    $sql_array['table']   = &$table;
    $sql_array['where']   = &$where;
    $sql_array['groupby'] = &$groupby;
    $sql_array['order']   = &$order;
    $sql_array['limit']   = &$limit;

    return $sql_array;

    $sql = "SELECT $columns FROM $table $where $groupby $order $limit";
    return $sql;
  }

  function select($type=NULL, $sql=NULL){
    PHPWS_DB::touchDB();
    if (isset($type))
      $type = strtolower($type);

    $mode = $this->getMode();
    $indexby = $this->getIndexBy();

    if (!isset($sql)){
      $sql_array = $this->getSelectSQL($type);
      extract($sql_array);

      if ($type == 'count') {
	if ($columns == "*") {
	  $columns = 'COUNT(*)';
	} else {
	  $add_group = $columns;
	  $columns .= ', COUNT(*)';	
	  if (empty($groupby)) {
	    $groupby = "GROUP BY $add_group";
	  } else {
	    $groupby .= ", $addgroup";
	  }
	}

	$type = 'all';
      }

      $sql = "SELECT $columns FROM $table $where $groupby $order $limit";
    } else
      $mode = DB_FETCHMODE_ASSOC;

    // assoc does odd things if the resultant return is two items or less
    // not sure why it is coded that way. Use the default instead

    switch ($type){
    case 'assoc':
      return PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getAssoc($sql, NULL,NULL, $mode), $type);
      break;

    case 'col':
      if (empty($this->column))
	return PHPWS_Error::get(PHPWS_DB_NO_COLUMN_SET, 'core', 'PHPWS_DB::select');

      if (isset($indexby)){
	$result = PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getAll($sql, NULL, $mode), $type);
	if (PEAR::isError($result))
	  return $result;

	return PHPWS_DB::_indexBy($result, $indexby, TRUE);
      }

      return PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getCol($sql), $type);
      break;

    case 'min':
    case 'max':
      $result = $GLOBALS['PEAR_DB']->query($sql);
      if (DB::isError($result))
	return $result;
      elseif ($result && $this->affectedRows()){
	$result->fetchInto($row);
	return $row[0];
      }
      break;

    case 'one':
      $value = $GLOBALS['PEAR_DB']->getOne($sql, NULL, $mode);
      db_trim($value);
      return $value;
      break;

    case 'row':
      return PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getRow($sql, array(), $mode), $type);
      break;

    case 'count':

      break;

    case 'all':
    default:
      $result = PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getAll($sql, NULL, $mode), $type);
      if (PEAR::isError($result))
	return $result;

      if (isset($indexby))
	return PHPWS_DB::_indexBy($result, $indexby);

      return $result;
      break;
    }
  }

  function getRow($sql){
    return $this->select('row', $sql);
  }

  function getCol($sql){
    return $this->select('col', $sql);
  }

  function getAll($sql){
    return $this->select('all', $sql);
  }

  function _indexBy($sql, $indexby, $colMode=FALSE){
    if (!is_array($sql))
      return $sql;
    $stacked = FALSE;

    foreach ($sql as $item){
      if (!isset($item[(string)$indexby]))
	return $sql;

      if ($colMode){
	$col = $this->getColumn();
	PHPWS_DB::_expandIndex($rows, $item[$indexby], $item[$col[0]], $stacked);
      } else
	PHPWS_DB::_expandIndex($rows, $item[$indexby], $item, $stacked);
    }

    return $rows;
  }

  function _expandIndex(&$rows, $index, $item, &$stacked){
    if (isset($rows[$index])) {
      if (!$stacked) {
	$hold = $rows[$index];
	$rows[$index] = array();
	$rows[$index][] = $hold;
	$stacked = TRUE;
      }
	$rows[$index][] = $item;
    } else {
      $rows[$index] = $item;
    }
  }


  function delete(){
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::delete');

    $where = $this->getWhere(TRUE);

    $sql = "DELETE FROM $table $where";
    return PHPWS_DB::query($sql);
  }
  

  function dropTable(){
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::dropTable');

    $sql = "DROP TABLE $table";

    return PHPWS_DB::query($sql);
  }

  function createTable(){
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::createTable');

    $values = $this->getAllValues();

    foreach ($values as $column=>$value)
      $parameters[] = $column . ' ' . $value;


    $sql = "CREATE TABLE $table ( " . implode(', ', $parameters) . ' )';
    return PHPWS_DB::query($sql);
  }

  function addTableColumn($column, $parameter, $after=NULL, $indexed=FALSE){
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::addColumn');

    if (!PHPWS_DB::allowed($column))
      return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addTableColumn', $column);

    if (isset($after)){
      if (strtolower($after) == 'first')
	$location = 'FIRST';
      else
	$location = "AFTER $after";
    } else
      $location = NULL;

    $sql = "ALTER TABLE $table ADD $column $parameter $location";

    $result = PHPWS_DB::query($sql);
    if (PEAR::isError($result))
      return $result;

    if ($indexed == TRUE){
      $indexSql = "CREATE INDEX $column on $table($column)";
      $result = PHPWS_DB::query($indexSql);
      if (PEAR::isError($result))
	return $result;
    }
    return TRUE;
  }


  function dropTableColumn($column){
    $table = $this->getTable();
    if (!$table)
      return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::dropColumn');

    if (!PHPWS_DB::allowed($column))
      return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::dropTableColumn', $column);

    $sql = "ALTER TABLE $table DROP $column";

    return PHPWS_DB::query($sql);
  }


  function getDBType(){
    return $GLOBALS['PEAR_DB']->phptype;
  }


  function disconnect(){
    if (PHPWS_DB::isConnected())
      $GLOBALS['PEAR_DB']->disconnect();
  }


  function import($text, $report_errors=TRUE){
    PHPWS_DB::touchDB();

    $prefix = PHPWS_DB::getPrefix();
    $sqlArray = PHPWS_Text::sentence($text);

    foreach ($sqlArray as $sqlRow){
      if (empty($sqlRow) || preg_match("/^[^\w\d\s\\(\)]/i", $sqlRow))
	continue;

      $sqlCommand[] = $sqlRow;

      if (preg_match("/;$/", $sqlRow)){
	$query = implode(' ', $sqlCommand);

	if (isset($prefix)){
	  $tableName = PHPWS_DB::extractTableName($query);
	  $query = str_replace($tableName, $prefix . $tableName, $query);
	}
	$sqlCommand = array();
	PHPWS_DB::homogenize($query);

	$result = PHPWS_DB::query($query);
	if (DB::isError($result))
	  $errors[] = $result;
      }
    }

    if (isset($errors)) {
      if ($report_errors) {
	return $errors;
      } else {
	foreach ($errors as $_error){
	  PHPWS_Error::log($_error);
	}
	return FALSE;
      }
    }
    else
      return TRUE;
  }


  function homogenize(&$query){
    $from = array('/int\(\d+\)/iU',
		  '/mediumtext/'
		  );
    $to = array('int',
		'text'
		);
    $query = preg_replace($from, $to, $query);

    $this->_sql->readyImport($query);
  }


  function parsePearCol($info, $strip_name=FALSE){
    $setting = $this->_sql->export($info);

    if (isset($info['flags'])){
      if (stristr($info['flags'], 'multiple_key')){
	$column_info['index'] = 'CREATE INDEX ' .  $info['name'] . ' on ' . $info['table'] 
	  . '(' . $info['name'] . ')';
	$info['flags'] = str_replace(' multiple_key', '', $info['flags']);
      }
      $preFlag = array('/not_null/', '/primary_key/', '/default_(.*)?/', '/blob/');
      $postFlag = array('NOT NULL', 'PRIMARY KEY', "DEFAULT '\\1'", '');
      $multipleFlag = array('multiple_key', '');
      $flags = ' ' . preg_replace($preFlag, $postFlag, $info['flags']);
    }
    else
      $flags = NULL;
    
    if ($strip_name == TRUE) {
      $column_info['parameters'] = $setting . $flags; 
    }
    else {
      $column_info['parameters'] = $info['name'] . " $setting" . $flags; 
    }

    return $column_info;
  }

  function parseColumns($columns){
    foreach ($columns as $info){
      if (!is_array($info))
	continue;

      $result = $this->parsePearCol($info);
      if (isset($result['index']))
	$column_info['index'][] = $result['index'];

      $column_info['parameters'][] = $result['parameters'];
    }

    return $column_info;
  }

  function export($structure=TRUE, $contents=TRUE){
    PHPWS_DB::touchDB();

    if ($structure == TRUE){      
      $columns =  $GLOBALS['PEAR_DB']->tableInfo($this->table);

      $column_info = $this->parseColumns($columns);
      $index = $this->getIndex();

      if ($prefix = PHPWS_DB::getPrefix())
	$tableName = str_replace('', $prefix, $tableName);

      $sql[] = "CREATE TABLE $tableName ( " .  implode(', ', $column_info['parameters']) .' );';
      if (isset($column_info['index']))
	$sql = array_merge($sql, $column_info['index']);
    }

    if ($contents == TRUE){
      if ($rows = $this->select()){
	if (PEAR::isError($rows))
	  return $rows;
	foreach ($rows as $dataRow){
	  foreach ($dataRow as $key=>$value){
	    $allKeys[] = $key;
	    $allValues[] = PHPWS_DB::quote($value);
	  }
	  
	  $sql[] = "INSERT INTO $tableName (" . implode(', ', $allKeys) . ') VALUES (' . implode(', ', $allValues) . ');';
	  $allKeys = $allValues = array();
	}
      }
    }

    return implode("\n", $sql);
  }

  function quote($text){
    return $GLOBALS['PEAR_DB']->quote($text);
  }

  function extractTableName($sql_value){
    require_once PHPWS_SOURCE_DIR . 'core/Array.php';
    $temp = explode(' ', trim($sql_value));
    PHPWS_Array::dropNulls($temp);
    if (!is_array($temp))
      return NULL;
    foreach ($temp as $whatever)
      $format[] = $whatever;

      switch (trim(strtolower($format[0]))) {
      case 'insert':
      if (stristr($format[1], 'into'))
	return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[2]));
      else
	return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[1]));
      break;
	
      case 'update':
	return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[1]));
      break;
      
      case 'select':
      case 'show':
	return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[3]));
      break;

      case 'drop':
      case 'alter':
	return preg_replace('/;/', '', str_replace('`', '', $format[2]));
      break;

      default:
	return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[2]));
      break;
      }
  }// END FUNC extractTableName


  /**
   * Prepares a value for database writing or reading
   *
   * @author Matt McNaney <matt at NOSPAM dot tux dot appstate dot edu>
   * @param  mixed $value The value to prepare for the database.
   * @return mixed $value The prepared value
   * @access public
   */
  function dbReady($value=NULL) {
    if (is_array($value) || is_object($value))
      return PHPWS_DB::dbReady(serialize($value));
    elseif (is_string($value))
      return "'" . $GLOBALS['PEAR_DB']->escapeSimple($value) . "'";
    elseif (is_null($value))
      return 'NULL';
    elseif (is_bool($value))
      return ($value ? 1 : 0);
    else
      return $value;
  }// END FUNC dbReady()

  function loadObject(&$object){
    if (!is_object($object))
      return PHPWS_Error::get(PHPWS_DB_NOT_OBJECT, 'core', 'PHPWS_DB::loadObject');

    $variables = $this->select('row');

    if (PEAR::isError($variables))
      return $variables;
    elseif (empty($variables))
      return FALSE;

    return PHPWS_Core::plugObject($object, $variables);
  }

  function getObjects($className){
    if (!class_exists($className))
      return PHPWS_Error::get(PHPWS_CLASS_NOT_EXIST, 'core', 'PHPWS_DB::getObjects');

    $items = NULL;
    $result = $this->select();

    if (PEAR::isError($result) || !isset($result))
      return $result;

    foreach ($result as $indexby => $itemResult){
      $genClass = & new $className;
      PHPWS_Core::plugObject($genClass, $itemResult);

      if (isset($indexby))
	$items[$indexby] = $genClass;
      else
	$items[] = $genClass;
    }

    return $items;
  }

  function saveObject(&$object, $stripChar=FALSE){

    if (!is_object($object))
      return PHPWS_Error::get(PHPWS_WRONG_TYPE, 'core', 'PHPWS_DB::saveObject', _('Type') . ': ' . gettype($object));

    $object_vars = get_object_vars($object);

    if (!is_array($object_vars))
      return PHPWS_Error::get(PHPWS_DB_NO_OBJ_VARS, 'core', 'PHPWS_DB::saveObject');

    foreach ($object_vars as $column => $value){
      if ($stripChar == TRUE)
	$column = substr($column, 1);
      if (!$this->isTableColumn($column))
	continue;

      $this->addValue($column, $value);
    }

    if (isset($this->qwhere) || ((isset($this->where) && count($this->where)))) {
      $result = $this->update();
    }
    else {
      $result = $this->insert();
      if (is_numeric($result)){
	if (array_key_exists('id', $object_vars))
	  $object->id = (int)$result;
	elseif (array_key_exists('_id', $object_vars))
	  $object->_id = (int)$result;
      }
    }

    $this->resetValues();

    return $result;
  }

  
  function allowed($value){
    if (!is_string($value))
      return FALSE;

    $reserved = array('ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'AUTO_INCREMENT', 'BDB',
		      'BERKELEYDB', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'BTREE', 'BY', 'CASCADE',
		      'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'COLLATE', 'COLUMN', 'COLUMNS', 'CONSTRAINT', 'CREATE',
		      'CROSS', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'DATABASE', 'DATABASES',
		      'DAY_HOUR', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DEFAULT',
		      'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DISTINCT', 'DISTINCTROW',
		      'DOUBLE', 'DROP', 'ELSE', 'ENCLOSED', 'ERRORS', 'ESCAPED', 'EXISTS', 'EXPLAIN', 'FIELDS',
		      'FLOAT', 'FOR', 'FOREIGN', 'FROM', 'FULLTEXT', 'FUNCTION', 'GEOMETRY', 'GRANT', 'GROUP',
		      'HASH', 'HAVING', 'HELP', 'HIGH_PRIORITY', 'HOUR_MINUTE', 'HOUR_SECOND',
		      'IF', 'IGNORE', 'IN', 'INDEX', 'INFILE', 'INNER', 'INNODB', 'INSERT', 'INT',
		      'INTEGER', 'INTERVAL', 'INTO', 'IS', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LEADING',
		      'LEFT', 'LIKE', 'LIMIT', 'LINES', 'LOAD', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT',
		      'LOW_PRIORITY', 'MASTER_SERVER_ID', 'MATCH', 'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT', 
		      'MIDDLEINT', 'MINUTE_SECOND', 'MRG_MYISAM', 'NATURAL', 'NOT', 'NULL', 'NUMERIC', 'ON', 'OPTIMIZE',
		      'OPTION', 'OPTIONALLY', 'OR', 'ORDER', 'OUTER', 'OUTFILE', 'PRECISION', 'PRIMARY', 'PRIVILEGES',
		      'PROCEDURE', 'PURGE', 'READ', 'REAL', 'REFERENCES', 'REGEXP', 'RENAME', 'REPLACE', 'REQUIRE',
		      'RESTRICT', 'RETURNS', 'REVOKE', 'RIGHT', 'RLIKE', 'RTREE', 'SELECT', 'SET', 'SHOW',
		      'SMALLINT', 'SONAME', 'SPATIAL', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT',
		      'SSL', 'STARTING', 'STRAIGHT_JOIN', 'STRIPED', 'TABLE', 'TABLES', 'TERMINATED', 'THEN', 'TINYBLOB',
		      'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TYPES', 'UNION', 'UNIQUE', 'UNLOCK', 'UNSIGNED',
		      'UPDATE', 'USAGE', 'USE', 'USER_RESOURCES', 'USING', 'VALUES', 'VARBINARY', 'VARCHAR', 'VARYING',
		      'WARNINGS', 'WHEN', 'WHERE', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL');

    if(in_array(strtoupper($value), $reserved))
      return FALSE;

    if(preg_match('/[^\w\*\.]/', $value)) 
      return FALSE;

    return TRUE;
  }



  function autoTrim($sql, $type){
    if (PEAR::isError($sql) || !is_array($sql))
      return $sql;

    if (!count($sql))
      return NULL;

    if ($GLOBALS['PEAR_DB']->phptype != 'pgsql')
      return $sql;

    switch ($type){
    case 'col':
      array_walk($sql, 'db_trim');
      break;

    default:
      array_walk($sql, 'db_trim');
      break;
    }

    return $sql;
  }

}

function db_trim(&$value){
  if (PEAR::isError($value) || !isset($value))
    return;

  if (is_array($value)){
    array_walk($value, 'db_trim');
    return;
  }

  $value = rtrim($value);
}

?>
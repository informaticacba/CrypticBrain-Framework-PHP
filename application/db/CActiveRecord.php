<?php
/**
 * CActiveRecord base class for classes that represent relational data.
 * It implements the Active Record design pattern.
 *
 * PUBLIC:					PROTECTED:					PRIVATE:
 * ----------			   ----------				  ----------
 * __construct			  _relations				  _createObjectFromTable
 * __set				  _customFields			      _getRelations
 * __get				  _beforeSave				  _getCustomFields
 * __unset				  _afterSave				  _addCustomFields
 *						  _beforeDelete			      _removeCustomFields
 * set					  _beforeDelete
 * get
 * getError
 * getErrorMessage
 * primaryKey
 * getPrimaryKey
 * getTableName
 * getFieldsAsArray
 * isNewRecord
 * getTranslations
 * saveTranslations
 *
 * find
 * findByPk
 * findByAttributes
 * findAll
 * findPk
 *
 * save
 * clearPkValue
 *
 * delete
 * deleteByPk
 * deleteAll
 *
 * exists
 * count
 * countByAttributes
 * max
 *
 *
 * STATIC:
 * ---------------------------------------------------------------
 * model
 *
 */

abstract class CActiveRecord
{
    /** @var Database */
    protected $_db;
    /**	@var boolean */
    protected $_error;
    /**	@var string */
    protected $_errorMessage;

    /* class name => model */
    private static $_models = array();

    /**	@var string */
    protected $_table = '';
    /**	@var string */
    protected $_tableTranslation = '';
    /**	@var */
    protected $_columns = array();

    /**	@var */
    private $_columnTypes = array();
    /**	@var */
    private $_pkValue = 0;
    /**	@var */
    private $_primaryKey;
    /**	@var */
    private $_isNewRecord = false;

    /**	@var */
    private static $_joinTypes = array(
        'INNER JOIN',
        'OUTER JOIN',
        'LEFT JOIN',
        'LEFT OUTER JOIN',
        'RIGHT JOIN',
        'RIGHT OUTER JOIN',
        'JOIN'
    );

    /** many-to-one */
    const BELONGS_TO = 1;
    /** one-to-one */
    const HAS_ONE = 2;
    /** one-to-many */
    const HAS_MANY = 3;
    /** many-to-many */
    const MANY_MANY = 4;

    const INNER_JOIN = 'INNER JOIN';
    const OUTER_JOIN = 'OUTER JOIN';
    const LEFT_JOIN = 'LEFT JOIN';
    const LEFT_OUTER_JOIN = 'LEFT OUTER JOIN';
    const RIGHT_JOIN = 'RIGHT JOIN';
    const RIGHT_OUTER_JOIN = 'RIGHT OUTER JOIN';
    const JOIN = 'JOIN';


    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_db = CDatabase::init();

        if(!empty($this->_table)){
            $this->_createObjectFromTable();
            $this->_pkValue = 0;
        }

        $this->_error = CDatabase::getError();
        $this->_errorMessage = CDatabase::getErrorMessage();
    }

    /**
     * Returns the static model of the specified AR class
     * @param string $className
     *
     * EVERY derived AR class must override this method in following way,
     * <pre>
     * public static function model($className = __CLASS__)
     * {
     *	 return parent::model($className);
     * }
     * </pre>
     */
    public static function model($className = __CLASS__)
    {
        if(isset(self::$_models[$className])){
            return self::$_models[$className];
        }else{
            return self::$_models[$className] = new $className(null);
        }
    }

    /**
     * Setter
     * @param $index
     * @param $value
     */
    public function __set($index, $value)
    {
        $this->_columns[$index] = $value;
    }

    /**
     * Getter
     * @param $index
     * @return string
     */
    public function __get($index)
    {
        return isset($this->_columns[$index]) ? $this->_columns[$index] : '';
    }

    /**
     * Sets a active record property to be null
     * @param $index
     */
    public function __unset($index)
    {
        if(isset($this->_columns[$index])) unset($this->_columns[$index]);
    }

    /**
     * Setter
     * @param $index
     * @param $value
     */
    public function set($index, $value)
    {
        $this->_columns[$index] = $value;
    }

    /**
     * Getter
     * @param $index
     * @return string
     */
    public function get($index)
    {
        if(isset($this->_columns[$index])){
            return $this->_columns[$index];
        }else{
            return '';
        }
    }

    /**
     * Get error status
     * @return boolean
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Get error message
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    /**
     * Returns the primary key of the associated database table
     * @return string
     */
    public function primaryKey()
    {
        return $this->_primaryKey;
    }

    /**
     * Returns the primary key value
     * @return mixed
     */
    public function getPrimaryKey()
    {
        return $this->_pkValue;
    }

    /**
     * Returns the table name value
     * @return string
     */
    public function getTableName()
    {
        return $this->_table;
    }

    /**
     * Returns fields as array
     * @return array
     */
    public function getFieldsAsArray()
    {
        return $this->_columns;
    }

    /**
     * Returns if current operation is on new record or not
     * @return bool
     */
    public function isNewRecord()
    {
        return $this->_isNewRecord;
    }

    /**
     * Returns array of translation fields
     * @param array $params
     * @return array
     */
    public function getTranslations($params = array())
    {
        $key = isset($params['key']) ? $params['key'] : '';
        $value = isset($params['value']) ? $params['value'] : '';
        $fields = isset($params['fields']) ? $params['fields'] : array();
        $resultArray = array();

        $result = $this->_db->select(
            'SELECT * FROM '.CConfig::get('db.prefix').$this->_tableTranslation.' WHERE '.$key.' = :'.$key,
            array(':'.$key => $value)
        );
        foreach($result as $res){
            foreach($fields as $field){
                $resultArray[$res['language_code']][$field] = $res[$field];
            }
        }
        return $resultArray;
    }

    /**
     * Saves array of translation fields
     * @param array $params
     * @return array
     */
    public function saveTranslations($params = array())
    {
        $key = isset($params['key']) ? $params['key'] : '';
        $value = isset($params['value']) ? $params['value'] : '';
        $fields = isset($params['fields']) ? $params['fields'] : array();
        $paramsTranslation = array();

        foreach($fields as $lang => $langInfo){
            foreach($langInfo as $langField => $langFieldValue){
                $paramsTranslation[$langField] = $langFieldValue;
            }
            if($this->isNewRecord()){
                $paramsTranslation[$key] = $value;
                $paramsTranslation['language_code'] = $lang;
                $this->_db->insert($this->_tableTranslation, $paramsTranslation);
            }else{
                $this->_db->update($this->_tableTranslation, $paramsTranslation, $key.'="'.$value.'" AND language_code="'.$lang.'"');
            }
        }
    }

    /**
     * Create empty object from table
     * @return bool
     */
    private function _createObjectFromTable()
    {
        if(is_null($this->_table)){
            return false;
        }

        $cols = $this->_db->showColumns($this->_table);
        if(!is_array($cols)) return false;

        foreach($cols as $array){
            $this->_columns[$array[0]] = ($array[4] != '') ? $array[4] : '';
            $arrayParts = explode('(', $array[1]);
            $this->_columnTypes[$array[0]] = array_shift($arrayParts);
            if($array[3] == 'PRI'){
                $this->_primaryKey = $array[0];
            }
        }
        $this->_addCustomFields();

        if($this->_primaryKey == ''){
            $this->_primaryKey = 'id';
        }
        return true;
    }

    /**
     * This method queries your database to find first related object
     * Ex.: find('postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param mixed $conditions
     * @param array|string $params
     * @return mixed
     */
    public function find($conditions = '', $params = '')
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
            $order = isset($conditions['order']) ? $conditions['order'] : '';
        }else{
            $where = $conditions;
            $order = '';
        }
        $whereClause = !empty($where) ? ' WHERE '.$where : '';
        $orderBy = !empty($order) ? ' ORDER BY '.$order : '';
        $relations = $this->_getRelations();

        $sql = 'SELECT
					`'.CConfig::get('db.prefix').$this->_table.'`.*
					'.$relations['fields'].'
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				'.$whereClause.'
				'.$orderBy.'
				LIMIT 1';
        return $this->_db->select($sql, $params);
    }

    /**
     * This method queries your database to find related objects by PK
     * Ex.: findByPk($pk, 'postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param string $pk
     * @param mixed $conditions
     * @param array|string $params
     * @return boolean
     */
    public function findByPk($pk, $conditions = '', $params = '')
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
            $order = isset($conditions['order']) ? $conditions['order'] : '';
        }else{
            $where = $conditions;
            $order = '';
        }
        $whereClause = !empty($where) ? ' AND '.$where : '';
        $orderBy = !empty($order) ? ' ORDER BY '.$order : '';
        $relations = $this->_getRelations();
        $customFields = $this->_getCustomFields();

        $sql = 'SELECT
					`'.CConfig::get('db.prefix').$this->_table.'`.*
					'.$customFields.'
					'.$relations['fields'].'
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				WHERE `'.CConfig::get('db.prefix').$this->_table.'`.'.$this->_primaryKey.' = '.(int)$pk.'
				'.$whereClause.'
				'.$orderBy.'
				LIMIT 1';
        $result = $this->_db->select($sql, $params);
        if(isset($result[0]) && is_array($result[0])){
            foreach($result[0] as $key => $val){
                $this->$key = $val;
            }
            $this->_pkValue = $pk;
            return $this;
        }else{
            return null;
        }
    }

    /**
     * This method queries your database to find related objects by attributes
     * Ex.: findByAttributes($attributes, 'postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * Ex.: findByAttributes($attributes, array('condition'=>'postID = :postID AND isActive = :isActive', 'order'=>'id DESC'), 'params'=>array(':postID'=>10, 'isActive'=>1)));
     * Ex.: $attributes = array('first_name'=>$firstName, 'last_name'=>$lastName);
     * @param array $attributes
     * @param mixed $conditions
     * @param array|string $params
     * @return $this|null
     */
    public function findByAttributes($attributes, $conditions = '', $params = '')
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
            $order = isset($conditions['order']) ? $conditions['order'] : '';
        }else{
            $where = $conditions;
            $order = '';
        }
        $whereClause = !empty($where) ? ' AND '.$where : '';
        $orderBy = !empty($order) ? ' ORDER BY '.$order : '';
        $relations = $this->_getRelations();

        $attributes_clause = '';
        foreach($attributes as $key => $value){
            $attributes_clause .= ' AND '.$key." = '".$value."'";
        }

        $sql = 'SELECT
					`'.CConfig::get('db.prefix').$this->_table.'`.*
					'.$relations['fields'].'
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				WHERE 1 = 1
					'.$attributes_clause.'
				'.$whereClause.'
				'.$orderBy.'
			   LIMIT 1';

        $result = $this->_db->select($sql, $params);
        if(isset($result[0]) && is_array($result[0])){
            foreach($result[0] as $key => $val){
                $this->$key = $val;
            }

            if(isset($result[0][$this->_primaryKey])){
                $this->_pkValue = $result[0][$this->_primaryKey];
            }
            return $this;
        }else{
            return null;
        }
    }

    /**
     * This method queries your database to find all related objects
     * Ex.: findAll('post_id = :postID AND is_active = :isActive', array(':postID'=>10, ':isActive'=>1));
     * Ex.: findAll(array('condition'=>'post_id = :postID AND is_active = :isActive', 'order'=>'id DESC', 'limit'=>'0, 10', 'cacheId'=>''), array(':postID'=>10, ':isActive'=>1));
     * @param mixed $conditions
     * @param array|string $params
     * @param int $fetchMode
     * @return mixed
     */
    public function findAll($conditions = '', $params = '', $fetchMode = PDO::FETCH_ASSOC)
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
            $order = isset($conditions['order']) ? $conditions['order'] : '';
            $limit = isset($conditions['limit']) ? $conditions['limit'] : '';
            $cacheId = isset($conditions['cacheId']) ? $conditions['cacheId'] : '';
        }else{
            $where = $conditions;
            $order = '';
            $limit = '';
            $cacheId = '';
        }
        $whereClause = !empty($where) ? ' WHERE '.$where : '';
        $orderBy = !empty($order) ? ' ORDER BY '.$order : '';
        $limit = !empty($limit) ? ' LIMIT '.$limit : '';

        $relations = $this->_getRelations();
        $customFields = $this->_getCustomFields();

        $sql = 'SELECT
					`'.CConfig::get('db.prefix').$this->_table.'`.*
					'.$customFields.'
					'.$relations['fields'].'
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				'.$whereClause.'
				'.$orderBy.'
				'.$limit;

        return $this->_db->select($sql, $params, $fetchMode, $cacheId);
    }

    /**
     * This method queries your database to find first related record primary key
     * Ex.: findPk('postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param mixed $conditions
     * @param array|string $params
     * @return int
     */
    public function findPk($conditions = '', $params = '')
    {
        $result = $this->find($conditions, $params);
        return isset($result[0][$this->_primaryKey]) ? $result[0][$this->_primaryKey] : '';
    }

    /**
     * Save data
     * @return boolean
     */
    public function save()
    {
        $data = array();

        $this->_removeCustomFields();

        if($this->_beforeSave($this->_pkValue)){
            foreach($this->_columns as $column => $val){
                $relations = $this->_getRelations();
                if($column != 'id' && $column != $this->_primaryKey && !in_array($column, $relations['fieldsArray'])){
                    $data[$column] = $this->$column;
                }
            }

            if($this->_pkValue > 0){
                $result = $this->_db->update($this->_table, $data, $this->_primaryKey.' = '.(int)$this->_pkValue);
            }else{
                $data[$this->_primaryKey] = $this->_columns[$this->_primaryKey];
                $result = $this->_db->insert($this->_table, $data);
                $this->_isNewRecord = true;
                $this->_pkValue = (int)$result;
            }

            if($result){
                $this->_afterSave($this->_pkValue);
                return true;
            }
        }else{
        }
        return false;
    }

    /**
     * Clear primary key
     * @return boolean
     */
    public function clearPkValue()
    {
        $this->_pkValue = 0;
    }

    /**
     * Remove the row from database if AR instance has been populated with this row
     * Ex.: $post = PostModel::model()->findByPk(10);
     *	  $post->delete();
     * @return boolean
     */
    public function delete()
    {
        if(!empty($this->_pkValue) && $this->deleteByPk($this->_pkValue)){
            return true;
        }
        return false;
    }

    /**
     * Remove the rows matching the specified condition and primary key(s)
     * Ex.: deleteByPk(10, 'postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param string $pk
     * @param mixed $conditions
     * @param array|string $params
     * @return boolean
     */
    public function deleteByPk($pk, $conditions = '', $params = '')
    {
        if($this->_beforeDelete($pk)){
            if(is_array($conditions)){
                $where = isset($conditions['condition']) ? $conditions['condition'] : '';
            }else{
                $where = $conditions;
            }
            $whereClause = !empty($where) ? ' WHERE '.$where : '';

            $result = $this->_db->delete($this->_table, $this->_primaryKey.' = '.(int)$pk.$whereClause, $params);
            if($result){
                $this->_afterDelete($pk);
                return true;
            }
        }else{
        }
        return false;
    }

    /**
     * Remove the rows matching the specified condition
     * Ex.: deleteAll('postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param mixed $conditions
     * @param array|string $params
     * @return boolean
     */
    public function deleteAll($conditions = '', $params = '')
    {
        if($this->_beforeDelete()){
            if(is_array($conditions)){
                $where = isset($conditions['condition']) ? $conditions['condition'] : '';
            }else{
                $where = $conditions;
            }
            $whereClause = !empty($where) ? ' WHERE '.$where : '';

            $result = $this->_db->delete($this->_table, $whereClause, $params);
            if($result){
                $this->_afterDelete();
                return true;
            }
        }else{
        }
        return false;
    }

    /**
     * This method check if there is at least one row satisfying the specified condition
     * Ex.: exists('postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param mixed $conditions
     * @param array|string $params
     * @return boolean
     */
    public function exists($conditions = '', $params = '')
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
        }else{
            $where = $conditions;
        }
        $whereClause = !empty($where) ? ' WHERE '.$where : '';

        $sql = 'SELECT * FROM `'.CConfig::get('db.prefix').$this->_table.'` '.$whereClause.' LIMIT 1';
        $result = $this->_db->select($sql, $params);
        return ($result) ? true : false;
    }

    /**
     * Finds the number of rows satisfying the specified query condition
     * Ex.: count('postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param mixed $conditions
     * @param array|string $params
     * @return integer
     */
    public function count($conditions = '', $params = '')
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
        }else{
            $where = $conditions;
        }
        $whereClause = !empty($where) ? ' WHERE '.$where : '';
        $relations = $this->_getRelations();

        $sql = 'SELECT
					COUNT(*) as cnt
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				'.$whereClause.'
				LIMIT 1';
        $result = $this->_db->select($sql, $params);
        return (isset($result[0]['cnt'])) ? $result[0]['cnt'] : 0;
    }

    /**
     * Finds the number of rows related objects by attributes
     * Ex.: countByAttributes($attributes, $conditions $params);
     * Ex.: $attributes = array('first_name'=>$firstName, 'last_name'=>$lastName);
     * Ex.: $params = array(':postID'=>10, 'isActive'=>1);
     * @param array $attributes
     * @param mixed $conditions
     * @param array|string $params
     * @return $this|null
     */
    public function countByAttributes($attributes, $conditions = '', $params = '')
    {
        $whereClause = !empty($conditions) ? ' AND '.$conditions : '';
        $relations = $this->_getRelations();

        foreach($attributes as $key => $value){
            $whereClause .= ' AND '.$key." = '".$value."'";
        }

        $sql = 'SELECT
					COUNT(*) as cnt
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				WHERE 1 = 1
				'.$whereClause.'
				LIMIT 1';

        $result = $this->_db->select($sql, $params);
        return (isset($result[0]['cnt'])) ? (int)$result[0]['cnt'] : 0;
    }

    /**
     * Finds a maximum value of the specified column
     * Ex.: max('id', 'postID = :postID AND isActive = :isActive', array(':postID'=>10, 'isActive'=>1));
     * @param string $column
     * @param mixed $conditions
     * @param array|string $params
     * @return integer
     */
    public function max($column = '', $conditions = '', $params = '')
    {
        if(is_array($conditions)){
            $where = isset($conditions['condition']) ? $conditions['condition'] : '';
        }else{
            $where = $conditions;
        }
        $whereClause = !empty($where) ? ' WHERE '.$where : '';
        $relations = $this->_getRelations();

        $sql = 'SELECT
					MAX('.$column.') as column_max
				FROM `'.CConfig::get('db.prefix').$this->_table.'`
					'.$relations['tables'].'
				'.$whereClause.'
				LIMIT 1';
        $result = $this->_db->select($sql, $params);
        return (isset($result[0]['column_max'])) ? $result[0]['column_max'] : 0;
    }

    /**
     * Used to define relations between different tables in database and current $_table
     * This method should be overridden
     */
    protected function _relations()
    {
        return array();
    }

    /**
     * Used to define custom fields
     * This method should be overridden
     * Usage: 'CONCAT(first_name, " ", last_name)' => 'fullname'
     *		'(SELECT COUNT(*) FROM '.CConfig::get('db.prefix').$this->_tableTranslation.')' => 'records_count'
     */
    protected function _customFields()
    {
        return array();
    }

    /**
     * This method is invoked before saving a record (after validation, if any)
     * You may override this method
     * @param string $pk
     * @return boolean
     */
    protected function _beforeSave($pk = '')
    {
        return true;
    }

    /**
     * This method is invoked after saving a record successfully
     * @param string $pk
     * You may override this method
     */
    protected function _afterSave($pk = '')
    {
    }

    /**
     * This method is invoked before deleting a record (after validation, if any)
     * You may override this method
     * @param string $pk
     * @return boolean
     */
    protected function _beforeDelete($pk = '')
    {
        return true;
    }

    /**
     * This method is invoked after deleting a record successfully
     * @param string $pk
     * You may override this method
     */
    protected function _afterDelete($pk = '')
    {
    }

    /**
     * Prepares custom fields for query
     * @return string
     */
    private function _getCustomFields()
    {
        $result = '';
        $fields = $this->_customFields();
        if(is_array($fields)){
            foreach($fields as $key => $val){
                $result .= ', '.$key.' as '.$val;
            }
        }
        return $result;
    }

    /**
     * Add custom fields for query
     */
    private function _addCustomFields()
    {
        $fields = $this->_customFields();
        if(is_array($fields)){
            foreach($fields as $key => $val){
                $this->_columns[$val] = '';
                $this->_columnTypes[$val] = 'varchar';
            }
        }
    }

    /**
     * Remove custom fields for query
     */
    private function _removeCustomFields()
    {
        $fields = $this->_customFields();
        if(is_array($fields)){
            foreach($fields as $key => $val){
                unset($this->_columns[$val]);
                unset($this->_columnTypes[$val]);
            }
        }
    }

    /**
     * Prepares relations for query
     * @return string
     */
    private function _getRelations()
    {
        $result = array('fields'=>'', 'tables'=>'', 'fieldsArray'=>array());
        $rel = $this->_relations();
        if(!is_array($rel)) return $result;
        $defaultJoinType = self::LEFT_OUTER_JOIN;
        $nl = "\n";

        foreach($rel as $key => $val){
            $key = isset($val['parent_key']) ? $val['parent_key'] : $key;
            $relationType = isset($val[0]) ? $val[0] : '';
            $relatedTable = isset($val[1]) ? $val[1] : '';
            $relatedTableKey = isset($val[2]) ? $val[2] : '';
            $joinType = (isset($val['joinType']) && in_array($val['joinType'], self::$_joinTypes)) ? $val['joinType'] : $defaultJoinType;
            $condition = isset($val['condition']) ? $val['condition'] : '';

            if(
                $relationType == self::HAS_ONE ||
                $relationType == self::BELONGS_TO ||
                $relationType == self::HAS_MANY ||
                $relationType == self::MANY_MANY
            ){
                if(isset($val['fields']) && is_array($val['fields'])){
                    foreach($val['fields'] as $field => $fieldAlias){
                        if(is_numeric($field)){
                            $field = $fieldAlias;
                            $fieldAlias = '';
                        }
                        $result['fields'] .= ', `'.CConfig::get('db.prefix').$relatedTable.'`.'.$field.(!empty($fieldAlias) ? ' as '.$fieldAlias : '');
                        $result['fieldsArray'][] = (!empty($fieldAlias) ? $fieldAlias : $field);
                    }
                }else{
                    $result['fields'] .= ', `'.CConfig::get('db.prefix').$relatedTable.'`.*';
                }
                $result['tables'] .= $joinType.' `'.CConfig::get('db.prefix').$relatedTable.'` ON `'.CConfig::get('db.prefix').$this->_table.'`.'.$key.' = `'.CConfig::get('db.prefix').$relatedTable.'`.'.$relatedTableKey;
                $result['tables'] .= (($condition != '') ? ' AND '.$condition : '').$nl;
            }
        }

        return $result;
    }
}
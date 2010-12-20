<?php
class DAO_Document extends C4_ORMHelper {
	const ID = 'id';
	const TITLE = 'title';
	const FILE_NAME = 'file_name';
	const ORG_ID = 'org_id';
	const CREATE_DATE = 'create_date';
    
    static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$db->Execute("INSERT INTO document () VALUES ()");
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
    
    static function update($ids, $fields) {
		parent::_update($ids, 'document', $fields);
	}
    
    static function updateWhere($fields, $where) {
		parent::_updateWhere('document', $fields, $where);
	}
    
    static function getWhere($where=null, $sortBy='create_date', $sortAsc=false, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, title, file_name, org_id, create_date ".
			"FROM document ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

    static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}

    static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_Document();
			$object->id = intval($row['id']);
			$object->title = $row['title'];
			$object->file_name = $row['file_name'];
			$object->org_id = $row['org_id'];
			$object->create_date = intval($row['create_date']);
			
			$objects[$object->id] = $object;
		}
		
		return $objects;
	}
    
    static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		// Delete files
		$documents = self::getWhere( "id IN (".$ids_list.")" );
		foreach( $documents as $document ) {
			$file_name = PiDocumentsPage::UPLOAD_DIRECTORY.$document->org_id.'/'.$document->file_name;
			if( file_exists($file_name) )
				unlink( $file_name );
		}
		
		// Delete database entries
		$db->Execute(sprintf("DELETE FROM document WHERE id IN (%s)", $ids_list));
		
		return true;
	}
    
    public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Document::getFields();
		
		// Sanitize
		if(!isset($fields[$sortBy]) || '*'==substr($sortBy,0,1))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"document.id as %s, ".
			"document.title as %s, ".
			"document.file_name as %s, ".
			"document.org_id as %s, ".
			"document.create_date as %s ",
			    SearchFields_Document::ID,
			    SearchFields_Document::TITLE,
				SearchFields_Document::FILE_NAME,
				SearchFields_Document::ORG_ID,
				SearchFields_Document::CREATE_DATE
			 );
			
		$join_sql = "FROM document ";
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'document.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
		
		// Virtuals
		foreach($params as $param) {
			$param_key = $param->field;
			settype($param_key, 'string');
			switch($param_key) {
				case SearchFields_Document::VIRTUAL_WORKERS:
					$has_multiple_values = true;
					if(empty($param->value)) { // empty
						$join_sql .= "LEFT JOIN context_link AS context_owner ON (context_owner.from_context = 'net.pixelinstrument.contexts.document' AND context_owner.from_context_id = document.id AND context_owner.to_context = 'cerberusweb.contexts.worker') ";
						$where_sql .= "AND context_owner.to_context_id IS NULL ";
					} else {
						$join_sql .= sprintf("INNER JOIN context_link AS context_owner ON (context_owner.from_context = 'net.pixelinstrument.contexts.document' AND context_owner.from_context_id = document.id AND context_owner.to_context = 'cerberusweb.contexts.worker' AND context_owner.to_context_id IN (%s)) ",
							implode(',', $param->value)
						);
					}
					break;
			}
		}
		
		$result = array(
			'primary_table' => 'comment',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
    
    static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql = 
			$select_sql.
			$join_sql.
			$where_sql.
			//($has_multiple_values ? 'GROUP BY document.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
    		$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
		    $rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
            $total = mysql_num_rows($rs);
		}
		
		$results = array();
		$total = -1;
		
		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_Document::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql = 
				($has_multiple_values ? "SELECT COUNT(DISTINCT document.id) " : "SELECT COUNT(document.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);
		
		return array($results,$total);
	}

    
    
}



class SearchFields_Document implements IDevblocksSearchFields {
	const ID = 'd_id';
	const TITLE = 'd_title';
	const FILE_NAME = 'd_file_name';
	const ORG_ID = 'd_org_id';
	const CREATE_DATE = 'd_create_date';
	
	const VIRTUAL_WORKERS = '*_workers';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'document', 'id', $translate->_('common.id')),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'document', 'title', $translate->_('net.pixelinstrument.documents.title')),
			self::FILE_NAME => new DevblocksSearchField(self::FILE_NAME, 'document', 'file_name', $translate->_('net.pixelinstrument.documents.file_name')),
			self::VIRTUAL_WORKERS => new DevblocksSearchField(self::VIRTUAL_WORKERS, '*', 'workers', $translate->_('common.owners')),
			self::ORG_ID => new DevblocksSearchField(self::ORG_ID, 'document', 'org_id', $translate->_('net.pixelinstrument.documents.organization')),
			self::CREATE_DATE => new DevblocksSearchField(self::CREATE_DATE, 'document', 'create_date', $translate->_('net.pixelinstrument.documents.create_date')),
		);
		
		// Custom Fields
		$fields = DAO_CustomField::getByContext(Model_Document::CUSTOM_DOCUMENTS);

		if(is_array($fields))
		foreach($fields as $field_id => $field) {
			$key = 'cf_'.$field_id;
			$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name);
		}
		
		// Sort by label (translation-conscious)
		uasort($columns, create_function('$a, $b', "return strcasecmp(\$a->db_label,\$b->db_label);\n"));

		return $columns;	
	}
};

class Model_Document {
	public $id;
	public $title;
	public $file_name;
	public $org_id;
	public $create_date;
	
	const CUSTOM_DOCUMENTS = 'net.pixelinstrument.contexts.document';
};

class View_Document extends C4_AbstractView {
	const DEFAULT_ID = 'documents';
	const ORG_DEFAULT_ID = 'org_documents';
	const DEFAULT_TITLE = 'Documents';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = self::DEFAULT_TITLE;
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Document::CREATE_DATE;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Document::FILE_NAME,
			SearchFields_Document::ORG_ID,
			SearchFields_Document::CREATE_DATE,
		);
		$this->addColumnsHidden(array(
			SearchFields_Document::ID,
			SearchFields_Document::VIRTUAL_WORKERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_Document::ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		return $this->_objects = DAO_Document::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Document', $size);
	}

	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);
		
		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);

		$organizations = DAO_ContactOrg::getWhere();
		$tpl->assign('organizations', $organizations);

		$tpl->assign('timestamp_now', time());
		
		// Pull the results so we can do some row introspection
		$results = $this->getData();
		$tpl->assign('results', $results);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(Model_Document::CUSTOM_DOCUMENTS);
		
		$tpl->assign('custom_fields', $custom_fields);
		
		$tpl->display('devblocks:net.pixelinstrument.documents::view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		
		switch($field) {
			case SearchFields_Document::TITLE:
			case SearchFields_Document::FILE_NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Document::CREATE_DATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			
			case SearchFields_Document::VIRTUAL_WORKERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
					
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}
	
	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_DOCUMENT::VIRTUAL_WORKERS:
				if(empty($param->value)) {
					echo "Owners <b>are not assigned</b>";
					
				} elseif(is_array($param->value)) {
					$workers = DAO_Worker::getAll();
					$strings = array();
					
					foreach($param->value as $worker_id) {
						if(isset($workers[$worker_id]))
							$strings[] = '<b>'.$workers[$worker_id]->getName().'</b>';
					}
					
					echo sprintf("Owner is %s", implode(' or ', $strings));
				}
				break;
		}
	}
	
	function renderCriteriaParam($param) {
		$field = !is_null($param_key) ? $param_key : $param->field;
		$translate = DevblocksPlatform::getTranslationService();
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Document::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Document::TITLE:
				// force wildcards if none used on a LIKE
				if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
				&& false === (strpos($value,'*'))) {
					$value = $value.'*';
				}
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
				
			case SearchFields_Document::CREATE_DATE:
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');

				if(empty($from)) $from = 0;
				if(empty($to)) $to = 'today';

				$criteria = new DevblocksSearchCriteria($field,$oper,array($from,$to));
				break;
			
			case SearchFields_Document::VIRTUAL_WORKERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,'in', $worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
	
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // [TODO] Temp!
	  
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				case 'org_id':
					$change_fields[DAO_Document::ORG_ID] = $v;
					break;
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
			}
		}
		
		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_Document::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Document::ID,
				true,
				false
			);
			 
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			DAO_Document::update($batch_ids, $change_fields);
			
			// Custom Fields
			self::_doBulkSetCustomFields(Model_Document::CUSTOM_DOCUMENTS, $custom_fields, $batch_ids);
			
			// Owners
			if(isset($do['owner']) && is_array($do['owner'])) {
				$owner_params = $do['owner'];
				foreach($batch_ids as $batch_id) {
					if(isset($owner_params['add']) && is_array($owner_params['add']))
						CerberusContexts::addWorkers(Model_Document::CUSTOM_DOCUMENTS, $batch_id, $owner_params['add']);
					if(isset($owner_params['remove']) && is_array($owner_params['remove']))
						CerberusContexts::removeWorkers(Model_Document::CUSTOM_DOCUMENTS, $batch_id, $owner_params['remove']);
				}
			}
			
			unset($batch_ids);
		}

		unset($ids);
	}	
};

?>

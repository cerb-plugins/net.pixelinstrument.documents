<?php
define( 'DOCUMENTS_DIRECTORY', substr( dirname(dirname(dirname(dirname(dirname(__FILE__))))), strlen(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))) ) . "/storage/documents/" );
define( 'UPLOAD_DIRECTORY', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . DOCUMENTS_DIRECTORY );

abstract class Extension_DocumentsTab extends DevblocksExtension {
	function __construct($manifest) {
		$this->DevblocksExtension($manifest);
	}
	
	function showTab() {}
	function saveTab() {}
};

class PiDocumentsPage extends CerberusPageExtension {
	const DOCUMENTS_DIRECTORY = DOCUMENTS_DIRECTORY;
	const UPLOAD_DIRECTORY = UPLOAD_DIRECTORY;

	function __construct($manifest) {
		parent::__construct($manifest);
	}
		
	function isVisible() {
		// check login
		$visit = CerberusApplication::getVisit();
		
		if(empty($visit)) {
			return false;
		} else {
			return true;
		}
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$response = DevblocksPlatform::getHttpResponse();
		$tpl->assign('request_path', implode('/',$response->path));

		// Remember the last tab/URL
		$visit = CerberusApplication::getVisit();
		if(null == ($selected_tab = @$response->path[1])) {
			$selected_tab = $visit->get(CerberusVisit::KEY_ACTIVITY_TAB, '');
		}
		$tpl->assign('selected_tab', $selected_tab);
		
		// active worker
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl->assign('active_worker', $active_worker);

		// Path
		$stack = $response->path;
		array_shift($stack); // activity
		
		$tab_manifests = DevblocksPlatform::getExtensions('net.pixelinstrument.documents.tab', false);
		uasort($tab_manifests, create_function('$a, $b', "return strcasecmp(\$a->name,\$b->name);\n"));
		$tpl->assign('tab_manifests', $tab_manifests);
		
		$tpl->display('devblocks:net.pixelinstrument.documents::index.tpl');
	}
	
	// Ajax
	function showTabAction() {
		@$ext_id = DevblocksPlatform::importGPC($_REQUEST['ext_id'],'string','');
		
		if(null != ($tab_mft = DevblocksPlatform::getExtension($ext_id)) 
			&& null != ($inst = $tab_mft->createInstance()) 
			&& $inst instanceof Extension_DocumentsTab) {
			$inst->showTab();
		}
	}
	
	function showDocumentPeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer','');
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		@$upload = DevblocksPlatform::importGPC($_REQUEST['upload'],'integer',0);
		@$org_id = DevblocksPlatform::importGPC($_REQUEST['org_id'],'integer',0);
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl_path = dirname(dirname(__FILE__)) . '/templates/';
		$tpl->assign('path', $tpl_path);
		
		// Upload?
		$tpl->assign('upload', $upload);
		
		if( $org_id ) {
			$organization = DAO_ContactOrg::get($org_id);

			$tpl->assign('org_id', $org_id);
			$tpl->assign('organization', $organization);
		}
		
		if( !empty($id) ) {
			 // Document
			$document = DAO_Document::get($id);
			$tpl->assign('document', $document);
		}

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(Model_Document::CUSTOM_DOCUMENTS); 
		$tpl->assign('custom_fields', $custom_fields);

		$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(Model_Document::CUSTOM_DOCUMENTS, $id);
		if(isset($custom_field_values[$id]))
			$tpl->assign('custom_field_values', $custom_field_values[$id]);
		
		$types = Model_CustomField::getTypes();
		$tpl->assign('types', $types);

		// Workers
		$context_workers = CerberusContexts::getWorkers(Model_Document::CUSTOM_DOCUMENTS, $id);
		$tpl->assign('context_workers', $context_workers);
		
		// Organization
		list($organizations,$null) = DAO_ContactOrg::search(
			array(),
			array(),
			0,
			0,
			SearchFields_ContactOrg::NAME,
			true,
			false
		);
		$tpl->assign('organizations', $organizations);
		
		// View
		$tpl->assign('id', $id);
		$tpl->assign('view_id', $view_id);
		$tpl->display('devblocks:net.pixelinstrument.documents::peek.tpl');
	}
	
	function saveDocumentPeekAction() {			
		$active_worker = CerberusApplication::getActiveWorker();
		@$upload = DevblocksPlatform::importGPC($_REQUEST['upload'],'integer',0);
		
		// read form parameters
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$org_id = DevblocksPlatform::importGPC($_REQUEST['org_id'],'integer',0);
		@$title = DevblocksPlatform::importGPC($_REQUEST['title'],'string','');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);
			
		// check if the document should be deleted
		if(!empty($id) && !empty($do_delete)) { // delete
			if( $active_worker->hasPriv('net.pixelinstrument.documents.acl.delete') ) {
				DAO_Document::delete($id);
			}
			
			return;
		}
		
		$userHasPermission = ( $upload ? $active_worker->hasPriv('net.pixelinstrument.documents.acl.upload') : $active_worker->hasPriv('net.pixelinstrument.documents.acl.update') );
		
		if( $userHasPermission ) {
			// check the organization
			if( !$org_id ) {
				return;
			}
			
			// is an upload?
			if( $upload ) {
				// set variables
				$upload_dir = PiDocumentsPage::UPLOAD_DIRECTORY;
				$tmpfile = $_FILES['file']['tmp_name'];
				$file_name = $_FILES['file']['name'];
				
				// move the uploaded file
				if( !file_exists( $upload_dir . $org_id ) ) {
					if( !mkdir( $upload_dir . $org_id, 0777, true ) )
						return;
				}
				
				$file_name = str_replace( " ", "_", $file_name ); // replace spaces in file name
				
				$orig_name = $file_name;
				$count = 0;
				while( file_exists( $upload_dir . $org_id . "/" .	$file_name ) ) {
					$count++;
					$ext_begin = strrpos( $orig_name, "." );
					$file_name = substr( $orig_name, 0, $ext_begin ) . "_" . $count . substr( $orig_name, $ext_begin );
				}
				
				move_uploaded_file( $tmpfile, $upload_dir . $org_id . "/" .	$file_name );
				
				$document = array(
					DAO_Document::TITLE => $title,
					DAO_Document::FILE_NAME => $file_name,
					DAO_Document::ORG_ID => $org_id,
					DAO_Document::CREATE_DATE => time()
				);
				
				$id = DAO_Document::create( $document );
			} else {
				// edit
				$document = array(
					DAO_Document::TITLE => $title,
					DAO_Document::ORG_ID => $org_id
				);
				
				DAO_Document::update( $id, $document );
			}
			
			if( $id ) {
				// Workers
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				CerberusContexts::setWorkers(Model_Document::CUSTOM_DOCUMENTS, $id, $worker_ids);
				
				// Custom field saves
				@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', array());
				DAO_CustomFieldValue::handleFormPost(Model_Document::CUSTOM_DOCUMENTS, $id, $field_ids);
			}
		}
	}
	
	
	function showDocumentsBulkPanelAction() {
		@$ids = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);

	    if(!empty($ids)) {
	        $id_list = DevblocksPlatform::parseCsvString($ids);
	        $tpl->assign('ids', implode(',', $id_list));
	    }
		
	    $workers = DAO_Worker::getAllActive();
	    $tpl->assign('workers', $workers);
		
		// Organization
		list($organizations,$null) = DAO_ContactOrg::search(
			array(),
			array(),
			0,
			0,
			SearchFields_ContactOrg::NAME,
			true,
			false
		);
		$tpl->assign('organizations', $organizations);
	    
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(Model_Document::CUSTOM_DOCUMENTS);
		$tpl->assign('custom_fields', $custom_fields);
		
		$tpl->display('devblocks:net.pixelinstrument.documents::bulk.tpl');
	}
	
	function doDocumentsBulkUpdateAction() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		if( $active_worker->hasPriv('net.pixelinstrument.documents.acl.update_all') ) {
			// Filter: whole list or check
			@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
			$ids = array();
			
			// View
			@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
			$view = C4_AbstractViewLoader::getView($view_id);
			
			// Task fields
			$org_id = trim(DevblocksPlatform::importGPC($_POST['org_id'],'string',''));
	
			$do = array();
			
			// Do: Organization
			if($org_id)
				$do['org_id'] = $org_id;
				
			// Owners
			$owner_params = array();
			
			@$owner_add_ids = DevblocksPlatform::importGPC($_REQUEST['do_owner_add_ids'],'array',array());
			if(!empty($owner_add_ids))
				$owner_params['add'] = $owner_add_ids;
				
			@$owner_remove_ids = DevblocksPlatform::importGPC($_REQUEST['do_owner_remove_ids'],'array',array());
			if(!empty($owner_remove_ids))
				$owner_params['remove'] = $owner_remove_ids;
			
			if(!empty($owner_params))
				$do['owner'] = $owner_params;
				
			// Do: Custom fields
			$do = DAO_CustomFieldValue::handleBulkPost($do);
	
			switch($filter) {
				// Checked rows
				case 'checks':
					@$ids_str = DevblocksPlatform::importGPC($_REQUEST['ids'],'string');
					$ids = DevblocksPlatform::parseCsvString($ids_str);
					break;
				case 'sample':
					@$sample_size = min(DevblocksPlatform::importGPC($_REQUEST['filter_sample_size'],'integer',0),9999);
					$filter = 'checks';
					$ids = $view->getDataSample($sample_size);
					break;
				default:
					break;
			}
			
			$view->doBulkUpdate($filter, $do, $ids);
			
			$view->render();
		}
		return;
	}
	// ajax
	function showViewRssAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		@$source = DevblocksPlatform::importGPC($_REQUEST['source'],'string','');
		
		$view = C4_AbstractViewLoader::getView($view_id);
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		$tpl->assign('view', $view);
		$tpl->assign('source', $source);
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/view_rss_builder.tpl');
	}
	
	// post
	// [TODO] Move to 'internal'
	function viewBuildRssAction() {
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id']);
		@$source = DevblocksPlatform::importGPC($_POST['source']);
		@$title = DevblocksPlatform::importGPC($_POST['title']);
		$active_worker = CerberusApplication::getActiveWorker();

		$view = C4_AbstractViewLoader::getView($view_id);
		
		$hash = md5($title.$view_id.$active_worker->id.time());
		
	    // Restrict to current worker groups
		$active_worker = CerberusApplication::getActiveWorker();
		
		$params = array(
			'params' => $view->getParams(),
			'sort_by' => $view->renderSortBy,
			'sort_asc' => $view->renderSortAsc
		);
		
		$fields = array(
			DAO_ViewRss::TITLE => $title, 
			DAO_ViewRss::HASH => $hash, 
			DAO_ViewRss::CREATED => time(),
			DAO_ViewRss::WORKER_ID => $active_worker->id,
			DAO_ViewRss::SOURCE_EXTENSION => $source, 
			DAO_ViewRss::PARAMS => serialize($params),
		);
		$feed_id = DAO_ViewRss::create($fields);
				
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('preferences','rss')));
	}
};


class PiDocumentsTab extends Extension_DocumentsTab {
	function __construct($manifest) {
		parent::__construct($manifest);
	}
	
	function showTab() {
		// Remember the tab
		$visit = CerberusApplication::getVisit();
		$visit->set(CerberusVisit::KEY_ACTIVITY_TAB, 'documents');
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		$translate = DevblocksPlatform::getTranslationService();
		
		// [TODO] Convert to $defaults
		
		if(null == ($view = C4_AbstractViewLoader::getView(View_Document::DEFAULT_ID))) {
			$view = new View_Document();
			$view->id = View_Document::DEFAULT_ID;
			$view->renderSortBy = SearchFields_Document::CREATE_DATE;
			$view->renderSortAsc = 1;
			
			$view->name = "Documents";
			
			C4_AbstractViewLoader::setView($view->id, $view);
		}

		$tpl->assign('view', $view);
		
		$tpl->assign('documents_directory', PiDocumentsPage::DOCUMENTS_DIRECTORY);
		
		$tpl->display('devblocks:net.pixelinstrument.documents::documents.tpl');		
	}
}

class PiDocumentsOrgTab extends Extension_OrgTab {
	function showTab() {
		@$org_id = DevblocksPlatform::importGPC($_REQUEST['org_id'],'integer',0);
		
		$tpl = DevblocksPlatform::getTemplateService();

		$org = DAO_ContactOrg::get($org_id);
		$tpl->assign('org_id', $org_id);
		
		if(null == ($view = C4_AbstractViewLoader::getView(View_Document::ORG_DEFAULT_ID))) {
			$view = new View_Document();
			$view->id = View_Document::ORG_DEFAULT_ID;
			$view->renderSortBy = SearchFields_Document::CREATE_DATE;
			$view->renderSortAsc = 1;
			
			$view->name = "Documents";
		}
		
		$view->addParams(array(
			SearchFields_Document::ORG_ID => new DevblocksSearchCriteria(SearchFields_Document::ORG_ID,'=',$org_id) 
		), true);
		
		C4_AbstractViewLoader::setView($view->id, $view);

		$tpl->assign('view', $view);
		
		$tpl->assign('documents_directory', PiDocumentsPage::DOCUMENTS_DIRECTORY);
		
		$tpl->display('devblocks:net.pixelinstrument.documents::org_documents.tpl');
	}
}
class PiRssSource_Document extends Extension_RssSource {
	function getSourceName() {
		return "Documents";
	}
	
	function getFeedAsRss($feed) {
        $xmlstr = <<<XML
		<rss version='2.0' xmlns:atom='http://www.w3.org/2005/Atom'>
		</rss>
XML;

        $xml = new SimpleXMLElement($xmlstr);
        $translate = DevblocksPlatform::getTranslationService();
		$url = DevblocksPlatform::getUrlService();

        // Channel
        $channel = $xml->addChild('channel');
        $channel->addChild('title', $feed->title);
        $channel->addChild('link', $url->write('',true));
        $channel->addChild('description', '');
        
        // View
        $view = new View_Document();
        $view->name = $feed->title;
        $view->addParams($feed->params['params'], true);
        $view->renderLimit = 100;
        $view->renderSortBy = $feed->params['sort_by'];
        $view->renderSortAsc = $feed->params['sort_asc'];

        // Results
        list($results, $count) = $view->getData();
        
        foreach($results as $document) {
        	$created = intval($document[SearchFields_DOCUMENT::CREATE_DATE]);
            if(empty($created)) $created = time();

            $eItem = $channel->addChild('item');
            
            $escapedSubject = htmlspecialchars($document[SearchFields_Document::TITLE],null,LANG_CHARSET_CODE);
            $escapedSubject = mb_convert_encoding($escapedSubject, 'utf-8', LANG_CHARSET_CODE);
            $eTitle = $eItem->addChild('title', $escapedSubject);

            $eDesc = $eItem->addChild('description', $document[SearchFields_Document::FILE_NAME]);

            $link = $url->write('/storage/documents/'.$document[SearchFields_Document::ORG_ID].'/'.$document[SearchFields_Document::FILE_NAME], true);
            $eLink = $eItem->addChild('link', $link);
            	
            $eDate = $eItem->addChild('pubDate', gmdate('D, d M Y H:i:s T', $created));
            
            $eGuid = $eItem->addChild('guid', md5($escapedSubject . $link . $created));
            $eGuid->addAttribute('isPermaLink', "false");
        }

        return $xml->asXML();
	}
};
?>

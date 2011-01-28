<?php
$db = DevblocksPlatform::getDatabaseService();

$tables = $db->metaTables();

// ***** Application

if(!isset($tables['document'])) {
	$sql ="
		CREATE TABLE IF NOT EXISTS document (
			id INT NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) DEFAULT '' NOT NULL,
			file_name VARCHAR(250) DEFAULT '' NOT NULL,
			worker_id INT DEFAULT 0 NOT NULL,
			org_id INT DEFAULT 0 NOT NULL,
			create_date INT(32) DEFAULT 0 NOT NULL,
			PRIMARY KEY (id)
		) ENGINE=MyISAM;
	";
	$db->Execute($sql);
	
	$tables['documents'] = 'documents';
}

return TRUE;

?>
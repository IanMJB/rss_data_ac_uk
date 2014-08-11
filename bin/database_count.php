#!/usr/bin/php

<?php

include __DIR__.'/DB_Utilities.php';

$config		= parse_ini_file(__DIR__.'/../secrets.ini', true);
$db_version	= $config['db'];
$config		= $config[$db_version];

$db_host	= $config['db_host'];
$db_name	= $config['db_name'];
$db_charset	= $config['db_charset'];
$db_user	= $config['db_user'];
$db_password	= $config['db_password'];

$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset", $db_user, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

$table_names = array(
			'institutions',
			'feeds',
			'posts'
		    );

echo date('Y-m-d H:i:s'), "\n";
get_counts_for_tables($db, $table_names);

function get_counts_for_tables($db, $table_names)
{
	foreach($table_names as $table_name)
	{
		$select_count_stmt = DB_Utilities::create_select_count($db, $table_name);
		$select_count_stmt->execute();
		$result = $select_count_stmt->fetchAll();
		echo $table_name, ',', $result[0][0], "\n";
	}
}

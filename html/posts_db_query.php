<?php

$db;
$config = parse_ini_file(__DIR__.'/../secrets.ini');

function get_posts_with_terms($term)
{
	global $db;
	global $config;

	if(!isset($db))
	{
		$db_host = $config['db_host'];
		$db_name = $config['db_name'];
		$db_charset = $config['db_charset'];
		$db_user = $config['db_user'];
		$db_password = $config['db_password'];

		$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset", $db_user, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

	$sql_select = "SELECT DISTINCT post_title, post_desc, post_date, post_url, title_url_hash
			FROM posts
			WHERE
			MATCH(post_title, post_desc)
			AGAINST(:term1 IN NATURAL LANGUAGE MODE)
			ORDER BY
			MATCH(post_title, post_desc)
			AGAINST(:term2 IN NATURAL LANGUAGE MODE)
			DESC
			LIMIT 20;";

	$sql_select_stmt = $db->prepare($sql_select);
	$sql_select_stmt->bindValue(':term1', $term);
	$sql_select_stmt->bindValue(':term2', $term);
	$sql_select_stmt->execute();

	return $sql_select_stmt->fetchAll();
}

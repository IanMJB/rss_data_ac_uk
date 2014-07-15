#!/usr/bin/php
<?php

include '../lib/lastRSS.php';

#Database variables.
$db_host = "localhost";
$db_name = "newsthing";
$db_charset = "utf8";
$db_user = "ian";
$db_password = "password";

#Loads data into json file.
$json_data = json_decode(file_get_contents('http://observatory.data.ac.uk/data/observations/latest.json'), true);

#Instantiates lastRSS.
$rss = new lastRSS;
$rss->cache_dir = '../temp';
$rss->cache_time = 1200;

#Connects to database.
$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset", $db_user, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

$relevant_data = process_json($json_data);

process_institutions($relevant_data);

#Creates an array of institution_pdomain => (rss_array, crawl_date).
#Means institution pdomains without RSS feeds ARE NOT added to the institutions table.
function process_json($json_data)
{
	$relevant_data = [];
	foreach($json_data as $name => $details)
	{
		if(!empty($details['site_profile']['rss']))
		{
			$final_rss = [];
			#Auto-generates a full RSS URL for short RSS names using the site URL.
			foreach($details['site_profile']['rss'] as $prelim_rss)
			{
				if(substr($prelim_rss, 0, 7) != 'http://')
				{
					$final_rss[] = $details['site_url'].substr($prelim_rss, 2);
				}
				else
				{
					$final_rss[] = $prelim_rss;
				}
			}
			$relevant_data[$name] = [$final_rss, $details['crawl']['crawl_timestamp']];
		}
	}
	
	return $relevant_data;
}

function process_institutions($relevant_data)
{	
	$inst_insert_stmt = NULL;
	foreach($relevant_data as $inst_pdomain => $details)
	{
		$institution = create_institution_from_data('placeholder', $inst_pdomain);
		if(!$inst_insert_stmt)
		{
			$inst_insert_stmt = create_insert('institutions', $institution);
		}
		$inst_insert_stmt->execute(array_values($institution));
		process_feeds($inst_pdomain, $details);
	}
}

#$name = inst_url
function process_feeds($inst_pdomain, $details)
{
	$inst_id_select_stmt = create_select('institutions', array('inst_id', 'inst_pdomain'));
	$inst_id_select_stmt->execute(array($inst_pdomain));
	$inst_id_array = $inst_id_select_stmt->fetchAll();
	$inst_id = $inst_id_array[0]['inst_id'];
	foreach($details[0] as $rss_url)
	{
		process_single_feed($inst_id, $rss_url, $details[1]);
	}
}

function process_single_feed($inst_id, $rss_url, $crawl_date)
{
	global $rss;

	$feed_insert_stmt = NULL;
	if($rs = $rss->get($rss_url))
	{
		$feed = create_feed_from_rss($rs);
		if(!$feed)
		{
			return;
		}
		$feed['inst_id'] = $inst_id;

		if(!$feed_insert_stmt)
		{
			$feed_insert_stmt = create_insert('feeds', $feed);
		}
		$feed_insert_stmt->execute(array_values($feed));

		$feed_id_select_stmt = create_select('feeds', array('feed_id', 'feed_url'));
		$feed_id_select_stmt->execute(array($feed['feed_url']));
		$feed_id_array = $feed_id_select_stmt->fetchAll();
		$feed_id = $feed_id_array[0]['feed_id'];
	
		foreach($rs['items'] as $item)
		{
			process_post($feed_id, $item);
		}
	}
}

function process_post($feed_id, $item)
{
	$post_insert_stmt = NULL;
	$post = create_post_from_rss($item);
	if(!$post)
	{
		return;
	}
	$post['feed_id'] = $feed_id;

	if(!$post_insert_stmt)
	{
		$post_insert_stmt = create_insert('posts', $post);
	}
	$post_insert_stmt->execute(array_values($post));
}

function create_insert($table_name, $data_array)
{
	global $db;

	$column_names = array();
	$question_marks = array();

	foreach(array_keys($data_array) as $col_name)
	{
		$column_names[] = "`$col_name`";
		$question_marks[] = '?';
	}

	$sql_insert = "INSERT IGNORE INTO $table_name(".implode(',', $column_names).') VALUES ('.implode(',', $question_marks).')';
	return $db->prepare($sql_insert);
}

#TODO
#Maybe make more sanitised/uniform (custom data structure for data_array).
function create_select($table_name, $data_array)
{
	global $db;

	$retrieve_column_name = $data_array[0];
	$match_column_name = $data_array[1];

	$sql_select = "SELECT $retrieve_column_name FROM $table_name WHERE $match_column_name = ?";

	return $db->prepare($sql_select); 
}

function create_institution_from_data($inst_name, $inst_pdomain)
{
	$institution = array('inst_name' => $inst_name, 'inst_pdomain' => $inst_pdomain);
	return $institution;
}

function create_feed_from_rss($rss_feed)
{
	#Maps RSS value key to MySQL column title-required status pair.
	$field_config = array(
		'title' => array('db_col_name' => 'feed_title', 'required' => false),
		'description' => array('db_col_name' => 'feed_desc', 'required' => false),
		'link' => array('db_col_name' => 'feed_url', 'required' => true)
		);

	#Createsd the MySQL item, failing and returning false if a required field is missing.
	$sql_item;
	foreach($field_config as $rss_id => $f_cfg)
	{
		$value = NULL;
		if(!array_key_exists($rss_id, $rss_feed) && $f_cfg['required'])
		{
			error_log("Missing required field $rss_id.\n");
			return false;
		}
		else
		{
			$value = $rss_feed[$rss_id];
		}
		$sql_item[$f_cfg['db_col_name']] = $value;
	}

	return $sql_item;
}

function create_post_from_rss($rss_item)
{
	#Maps RSS value key to MySQL column title-required status pair.
	$field_config = array(
		'title' => array('db_col_name' => 'post_title', 'required' => false),
		'description' => array('db_col_name' => 'post_desc', 'required' => false),
		'pubDate' => array('db_col_name' => 'post_date', 'required' => true),
		'link' => array('db_col_name' => 'post_url', 'required' => true)
		);

	#Createsd the MySQL item, failing and returning false if a required field is missing.
	$sql_item;
	foreach($field_config as $rss_id => $f_cfg)
	{
		$value = NULL;
		if(!array_key_exists($rss_id, $rss_item) && $f_cfg['required'])
		{
			error_log("Missing required field $rss_id.\n");
			return false;
		}
		else
		{
			$value = $rss_item[$rss_id];
		}
		$sql_item[$f_cfg['db_col_name']] = $value;
	}

	#Post-processing (currently just formatting date).
	$sql_item['post_date'] = rss_date_to_mysql_date($sql_item['post_date']);

	return $sql_item;
}

function rss_date_to_mysql_date($rss_date)
{
	$time_from_epoch = strtotime($rss_date);
	return date('Y-m-d H:i:s', $time_from_epoch);
}

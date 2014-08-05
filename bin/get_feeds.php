#!/usr/bin/php

<?php

include __DIR__.'/../lib/lastRSS.php';
include __DIR__.'/DB_Utilities.php';

$config = parse_ini_file(__DIR__.'/../secrets.ini', true);
$db_version = $config['db'];
$config = $config[$db_version];

#Database variables.
$db_host = $config['db_host'];
$db_name = $config['db_name'];
$db_charset = $config['db_charset'];
$db_user = $config['db_user'];
$db_password = $config['db_password'];

#Loads data into json file.
$json_data = json_decode(file_get_contents('http://observatory.data.ac.uk/data/observations/latest.json'), true);

echo 'JSON data acquired.', "\n";

#Instantiates lastRSS.
$rss = new lastRSS;
$rss->cache_dir = '../tmp';
$rss->cache_time = 1200;

#TODO
#Unhack global.
#Connects to database.
$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset", $db_user, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

echo 'Database connected.', "\n";

$relevant_data = process_json($json_data);

echo 'JSON processed.', "\n";

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
	#TODO
	#Un-hack $db.
	global $db;
	
	$inst_insert_stmt = NULL;
	foreach($relevant_data as $inst_pdomain => $details)
	{
		$institution = create_institution_from_data('placeholder', $inst_pdomain);
		if(!$inst_insert_stmt)
		{
			$inst_insert_stmt = DB_Utilities::create_insert($db, 'institutions', $institution);
		}
		$inst_insert_stmt->execute(array_values($institution));
		echo $institution['inst_pdomain'], ' added.', "\n";
		process_feeds($inst_pdomain, $details);
	}
}

function process_feeds($inst_pdomain, $details)
{
	#TODO
	#Un-hack $db.
	global $db;
	
	$inst_id_select_stmt = DB_Utilities::create_select($db, 'institutions', array('inst_id', 'inst_pdomain'));
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
	#TODO
	#Un-hack $db/$rss.
	global $db;
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
			$feed_insert_stmt = DB_Utilities::create_insert($db, 'feeds', $feed);
		}
		$feed_insert_stmt->execute(array_values($feed));
#		echo $feed['feed_url'], ' added.', "\n";

		$feed_id_select_stmt = DB_Utilities::create_select($db, 'feeds', array('feed_id', 'feed_url'));
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
	#TODO
	#Un-hack $db.
	global $db;

	$post_insert_stmt = NULL;
	$post = create_post_from_rss($item);
	if(!$post)
	{
		return;
	}
	$post['feed_id'] = $feed_id;

	if(!$post_insert_stmt)
	{
		$post_insert_stmt = DB_Utilities::create_insert($db, 'posts', $post);
	}
	$post_insert_stmt->execute(array_values($post));
#	echo $post['post_url'], ' added.', "\n";
}

function create_institution_from_data($inst_name, $inst_pdomain)
{
	#TODO
	#Check if fixes - forces to UTF-8.
	$inst_name = iconv(mb_detect_encoding($inst_name, mb_detect_order(), true), 'UTF-8', $inst_name);
	$inst_pdomain = iconv(mb_detect_encoding($inst_pdomain, mb_detect_order(), true), 'UTF-8', $inst_pdomain);
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
			#TODO
			#Check if fixes - forces to UTF-8.
			$rss_feed[$rss_id] = iconv(mb_detect_encoding($rss_feed[$rss_id], mb_detect_order(), true), 'UTF-8', $rss_feed[$rss_id]);
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

	#Creates the MySQL item, failing and returning false if a required field is missing.
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
			if($rss_id != 'pubDate')
			{
				$rss_item[$rss_id] = str_replace('<![CDATA[', '', $rss_item[$rss_id]);
				$rss_item[$rss_id] = str_replace(']]>', '', $rss_item[$rss_id]);
			}
			#TODO
			#Check if fixes - forces to UTF-8.
			$rss_item[$rss_id] = iconv(mb_detect_encoding($rss_item[$rss_id], mb_detect_order(), true), 'UTF-8', $rss_item[$rss_id]);
			$value = $rss_item[$rss_id];
		}
		$sql_item[$f_cfg['db_col_name']] = $value;
	}

	#Post-processing (currently just formatting date).
	$sql_item['post_date'] = DB_Utilities::rss_date_to_mysql_date($sql_item['post_date']);

	return $sql_item;
}

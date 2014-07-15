#!/usr/bin/php
<?php 

include "../lib/lastRSS.php";

$raw_data = json_decode(file_get_contents("/home/ian/Documents/newsthing/docs/example_data_1.json"), true);
#$raw_data = json_decode(file_get_contents("http://observatory.data.ac.uk/data/observations/latest.json"), true);

#Testing lastRSS:
$rss = new lastRSS;
$rss->cache_dir = '../temp';
$rss->cache_time = 1200;


#Creates an array of site-name => (rss-array, date).
#Means institution pdomains without RSS feeds ARE NOT added to the institutions table.
foreach($raw_data as $k => $v)
{
	if(!empty($v["site_profile"]["rss"]))
	{
		$final_rss = [];
		#Auto-generates a full RSS URL for short RSS names using the site URL.
		foreach($v["site_profile"]["rss"] as $prelim_rss)
		{
			if(substr($prelim_rss, 0, 7) != "http://")
			{
				$final_rss[] = $v["site_url"].substr($prelim_rss, 2);
			}
			else
			{
				$final_rss[] = $prelim_rss;
			}
		}
		$relevant_data[$k] = [$final_rss, $v["crawl"]["crawl_timestamp"]];
	}
}

$db = new PDO("mysql:host=localhost;dbname=newsthing;charset=utf8", "ian", "password", array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$inst_insert_stmt = $db->prepare("INSERT IGNORE INTO institutions(inst_name, inst_pdomain) VALUES(?, ?)");
$inst_id_select_stmt = $db->prepare("SELECT inst_id FROM institutions WHERE inst_pdomain = ?");
$feed_insert_stmt = $db->prepare("INSERT IGNORE INTO feeds(feed_title, feed_desc, feed_url, inst_id) VALUES(?, ?, ?, ?)");
$feed_id_select_stmt = $db->prepare("SELECT feed_id FROM feeds WHERE feed_url = ?");
#$post_insert_stmt = $db->prepare("INSERT INTO posts(post_title, post_desc, post_date, post_url, feed_id) VALUES(?, ?, ?, ?, ?)");

foreach($relevant_data as $k => $v)
{
	#Inserts each institution into the institutions table.
	$inst_insert_stmt->execute(array('placeholder', $k));

	foreach($v[0] as $rss_url)
	{
		process_rss($k, $v[1], $rss_url);
	}
}

function create_insert($table_name, $data_array)
{
	global $db;

	$column_names = array();
	$question_marks = array();
	foreach (array_keys($data_array) as $col_name)
	{
		$column_names[] = "`$col_name`";
		$question_marks[] = '?';
	}

	$sql = "INSERT IGNORE INTO $table_name(" . implode(',', $column_names) . ') VALUES (' . implode(',', $question_marks) . ')';

	return $db->prepare($sql);
}

function rss_date_to_mysql_date($rss_date)
{	
	$time_from_epoch = strtotime($rss_date);
	return date('Y-m-d H:i:s', $time_from_epoch);
}

function create_item_from_rs($rss_item)
{
	#Maps RSS value key to MySQL column title-required status pair.
	$field_config = array(
		'title' => array('db_col_name' => 'post_title', 'required' => false),
		'description' => array('db_col_name' => 'post_desc', 'required' => false),
		'pubDate' => array('db_col_name' => 'post_date', 'required' => true),
		'link' => array('db_col_name' => 'post_url', 'required' => true)
	);

	#Creates the MySQL item, failing and returning false if a requried field is missing.
	$sql_item;
	foreach($field_config as $rss_id => $f_cfg)
	{
		$value = NULL;
		if (
			!array_key_exists($rss_id, $rss_item) #check, it might have args in the wrong way
			&& $f_cfg['required']
		)
		{
			error_log("Missing required field $rss_id\n");
			return false;
		}
		else
		{
			$value = $rss_item[$rss_id];
		}

		$sql_item[$f_cfg['db_col_name']] = $value;

	}

	#Post-processing.
	$sql_item['post_date'] = rss_date_to_mysql_date($sql_item['post_date']);

	return $sql_item;
}

function process_rss($source_url, $crawl_date, $rss_url)
{
	global $db;
	global $rss;
	global $inst_id_select_stmt;
	global $feed_insert_stmt;
	global $feed_id_select_stmt;
	global $post_insert_stmt;
	
	if($rs = $rss->get($rss_url))
	{
		$feed_url = $rs["link"];
		if(!empty($feed_url))
		{		
			#Get the inst_id associated with the feed for use as FK in feeds table.
			$inst_id_select_stmt->execute(array($source_url));
			$inst_id_array = $inst_id_select_stmt->fetchAll();
			$inst_id = $inst_id_array[0]["inst_id"];

			#Inserts each feed into the feeds table if it is alive.
			$feed_insert_stmt->execute(array($rs["title"], $rs["description"], $feed_url, $inst_id));

			#Get the feed_id associated with the post for use as FK in the posts table.
			$feed_id_select_stmt->execute(array($feed_url));
			$feed_id_array = $feed_id_select_stmt->fetchAll();
			$feed_id = $feed_id_array[0]["feed_id"];

			$insert_stmt = NULL;
			foreach($rs["items"] as $item)
			{
				$validated_item = create_item_from_rs($item);
				if (!$validated_item)
				{
					continue;
				}
				$validated_item['feed_id'] = $feed_id;

				if(!$insert_stmt)
				{
					$insert_stmt = create_insert('posts', $validated_item);
				}
				$insert_stmt->execute(array_values($validated_item));
#				$post_url = $item["link"];
#				if(!empty($post_url))
#				{	
					#Inserts each post into the posts table.
#					$post_insert_stmt->execute(array($item["title"], $item["description"], $date->format("Y-m-d H:i:s"), $post_url, $feed_id));
#					echo "inst_url: $source_url\n";
#					echo "feed_title: $rs[title]\n";
#					echo "feed_desc: $rs[description]\n";
#					echo "feed_url: $rs[link]\n";
#					echo "post_title: $item[title]\n";
#					echo "post_desc: $item[description]\n";
#					echo "post_date: ".$date->format('Y-m-d H:i:s')."\n";
#					echo "post_url: $item[link]\n\n";
#				}
			}
		}
	}
}

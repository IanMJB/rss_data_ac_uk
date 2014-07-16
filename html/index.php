<?php /*<!DOCTYPE html>
<!--[if lt IE 7]><html class="ie ie6" lang="en"><![endif]-->
<!--[if IE 7]><html class="ie ie7" lang="en"><![endif]-->
<!--[if IE 8]><html class="ie ie8" lang="en"><![endif]-->
<!--[if (gte IE 9)|!(IE)><!-->
<html lang="en">
<!--<![endif]-->
<head>

<!-- Basic Page Needs
  ================================================== -->
<meta charset="utf-8">
      <title>UK University Web Observatory: RSS </title>
<meta name="author" content="Ian Barker">

</head>
<body>
	<h1>Search UK University RSS Feeds</h1>
	<form name="search" method="post" action="">
		Search for: <input type="text" name="query" />
		<input type="submit" name="search" value="Search" />
	</form>
</body>

*/?>
<?php

foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*Interface.php') as $filename)
{
	include_once $filename;
}

foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*.php') as $filename)
{
	include_once $filename;
}

use \Suin\RSSWriter\Feed;
use \Suin\RSSWriter\Channel;
use \Suin\RSSWriter\Item;

$config = parse_ini_file(__DIR__.'/../secrets.ini');

$db_host = $config['db_host'];
$db_name = $config['db_name'];
$db_charset = $config['db_charset'];
$db_user = $config['db_user'];
$db_password = $config['db_password'];

if(isset($_POST['query']))
{
	$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset", $db_user, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	
	#$posts_select_stmt = create_select('posts', array('inst_pdomain', 'inst_id'));

#	echo '<h2>Results:</h2>';
	if($_POST['query'] == "")
	{
		echo '<p>No search term entered.</p>';
	}
	else
	{
		$feed = new Feed();
		$channel = new Channel();
		$channel
			->title('Search Results')
			->appendTo($feed);
		$posts = get_posts_with_terms($_POST['query']);
		foreach($posts as $post)
		{
#			echo '<p>','<a href="',$post['post_url'],'">',$post['post_title'],'</a>','</p>';
			$item = new Item();
			$item
				->title($post['post_title'])
				->description($post['post_desc'])
				->url($post['post_url'])
				->pubDate(strtotime($post['post_date']))
				->appendTo($channel);
		}

		header('Content-type: text/xml');	
	
		echo $feed;
	}
}

function get_posts_with_terms($term)
{
	global $db;

#	$term = "%$term%";

	$sql_select = "SELECT DISTINCT post_title, post_desc, post_date, post_url
			FROM posts
			WHERE
			MATCH(post_title, post_desc)
			AGAINST(:term1 IN NATURAL LANGUAGE MODE)
			ORDER BY
			MATCH(post_title, post_desc)
			AGAINST(:term2 IN NATURAL LANGUAGE MODE)
			DESC
			LIMIT 20;";

#	$sql_select = "SELECT DISTINCT post_title, post_desc, post_date, post_url
#			FROM posts
#			WHERE (post_title LIKE :term1 OR post_desc LIKE :term2) AND post_date < NOW()
#			ORDER BY post_date DESC
#			LIMIT 10;";

	$sql_select_stmt = $db->prepare($sql_select);
	$sql_select_stmt->bindValue(':term1', $term);
	$sql_select_stmt->bindValue(':term2', $term);
	$sql_select_stmt->execute();

	return $sql_select_stmt->fetchAll();
}

#function create_select($table_name, $data_array)
#{
#	global $db;
#
#	$retrieve_column_name = $data_array[0];
#	$match_column_name = $data_array[1];
#
#	$sql_select = "SELECT $retrieve_column_name FROM $table_name WHERE $match_column_name = ?";
#
#	return $db->prepare($sql_select);
#}

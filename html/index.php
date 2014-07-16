<!DOCTYPE html>
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
	<form name="search" method="get" action="">
		Search for: <input type="text" name="query" />
		<input type="submit" name="search" value="Search" />
	</form>
</body>
<?php

#session_start();

foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*Interface.php') as $filename)
{
	include_once $filename;
}
foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*.php') as $filename)
{
	include_once $filename;
}
include_once __DIR__.'/posts_db_query.php';

use \Suin\RSSWriter\Feed;
use \Suin\RSSWriter\Channel;
use \Suin\RSSWriter\Item;

if(isset($_REQUEST['query']))
{
	if($_REQUEST['query'] == "")
	{
		echo '<h2>Results:</h2>';
		echo '<p>No search term entered.</p>';
	}
	else
	{
		$rss_url = "rss.php?";
		foreach($_REQUEST as $key => $value)
		{
			$rss_url .= urlencode($key)."=".urlencode($value)."&";
		}
		echo '<h2>Results as RSS:</h2>';
		echo '<a href="'.$rss_url.'" target="_blank">Click here for results as RSS.</a>';
		echo '<h2>Results:</h2>';

		$posts = get_posts_with_terms($_REQUEST['query']);
		foreach($posts as $post)
		{
			echo '<p>','<a href="',$post['post_url'],'">',$post['post_title'],'</a>','</p>';
		}
	}
}

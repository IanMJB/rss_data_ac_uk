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

<!-- Mobile Specific Metas
  ================================================== -->
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

<link rel="stylesheet" href="http://network-bar.data.ac.uk/subsite.css" type="text/css">

<style type="text/css">
	.post{margin-top: 1em;}
	.rss_link{float: right;}
</style>

</head>
<body>
	<div class="container">
		<div class="sixteen columns padding_top_30 padding_bottom_20">
			<h1>Search UK University RSS Feeds</h1>
			<form name="search" method="get" action="">
				Search for: <input type="text" name="query" />
				<input type="submit" name="search" value="Search" />
			</form>
<?php

#Changes the default number of recent posts displayed.
$no_recent_posts = 20;

/*foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*Interface.php') as $filename)
{
	include_once $filename;
}
foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*.php') as $filename)
{
	include_once $filename;
}*/
include_once __DIR__.'/../lib/posts_db_query.php';

#use \Suin\RSSWriter\Feed;
#use \Suin\RSSWriter\Channel;
#use \Suin\RSSWriter\Item;

$rss_url = "rss.php?";
foreach($_REQUEST as $key => $value)
{
	$rss_url .= urlencode($key)."=".urlencode($value)."&";
}
echo '<h2 class="rss_link">','<a href="'.$rss_url.'" target="_blank">Results as RSS</a>','</h2>';

if(isset($_REQUEST['query']) && $_REQUEST['query'] != '')
{
	echo '<h2>Results:</h2>';

	$posts = get_posts_with_terms($_REQUEST['query']);

	foreach($posts as $post)
	{
		echo '<div class="post">';
		echo '<h3>','<a href="',$post['post_url'],'">',$post['post_title'],'</a>','</h3>';
		echo '<div class="news_description">',html_entity_decode($post['post_desc']),'</div>';
		echo '</div>';
	}
}
else
{
	echo '<h2>Latest '.$no_recent_posts.' Results:</h2>';

	$posts = get_posts_last_x($no_recent_posts);
	foreach($posts as $post)
	{
		echo '<div class="post">';
		echo '<h3>','<a href="',$post['post_url'],'">',$post['post_title'],'</a>','</h3>';
		echo '<div class="news_description">',html_entity_decode($post['post_desc']),'</div>';
		echo '</div>';
	}
}
?>
		</div>
	</div>
</body>
</html>

<script type="text/javascript" src="//network-bar.data.ac.uk/network-bar.js"></script>

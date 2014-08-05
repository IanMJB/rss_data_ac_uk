<?php 

foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*Interface.php') as $filename)
{
	include_once $filename;
}
foreach(glob(__DIR__.'/../lib/php-rss-writer-master/Source/Suin/RSSWriter/*.php') as $filename)
{
	include_once $filename;
}
include_once __DIR__.'/../lib/posts_db_query.php';

use \Suin\RSSWriter\Feed;
use \Suin\RSSWriter\Channel;
use \Suin\RSSWriter\Item;

#Changes the default number of recent posts displayed.
$no_recent_posts = 20;

header('Content-type: text/xml');

$feed = new Feed();
$channel = new Channel();


if(isset($_REQUEST['query']) && $_REQUEST['query'] != '')
{
	$channel
		->title('Search results for: '.$query)
		->description('search results for: '.$query)
		->url('https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'])
		->language('en-GB')
		->copyright('')
		->pubDate(time())
		->lastBuildDate(time())
		->ttl(60)
		->appendTo($feed);

	$posts = get_posts_with_terms($query);
	foreach($posts as $post)
	{
		$item = new Item();
		$item
			->title($post['post_title'])
			->description(html_entity_decode($post['post_desc']))
			->url($post['post_url'])
			->pubDate(strtotime($post['post_date']))
			->appendTo($channel);
	}

	echo $feed;
}
else
{
	$channel
		->title('Last '.$no_recent_posts.' search results.')
		->description('last '.$no_recent_posts.' search results')
		->url('https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'])
		->language('en-GB')
		->copyright('')
		->pubDate(time())
		->lastBuildDate(time())
		->ttl(60)
		->appendTo($feed);

	$posts = get_posts_last_x($no_recent_posts);
	foreach($posts as $post)
	{
		$item = new Item();
		$item
			->title($post['post_title'])
			->description(html_entity_decode($post['post_desc']))
			->url($post['post_url'])
			->pubDate(strtotime($post['post_date']))
			->appendTo($channel);
	}

	echo $feed;
}

<?php 

session_start();

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

header('Content-type: text/xml');

$feed = new Feed();
$channel = new Channel();
$channel
	->title('Search results for: '.$_REQUEST['query'])
	->appendTo($feed);

$posts = get_posts_with_terms($_REQUEST['query']);
foreach($posts as $post)
{
	$item = new Item();
	$item
		->title($post['post_title'])
		->description($post['post_desc'])
		->url($post['post_url'])
		->pubDate(strtotime($post['post_date']))
		->appendTo($channel);
}

echo $feed;

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

use \Suin\RSSWriter\Feed;
use \Suin\RSSWriter\Channel;
use \Suin\RSSWriter\Item;

header('Content-type: text/xml');

$feed = $_SESSION['feed'];

$test = unserialize($feed);

echo $test;

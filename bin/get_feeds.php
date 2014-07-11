#!/usr/bin/php
<?php 

include "../lib/lastRSS.php";

$raw_data = json_decode(file_get_contents("/home/ian/Documents/newsthing/docs/example_data_1.json"), true);
#$raw_data = json_decode(file_get_contents("http://observatory.data.ac.uk/data/observations/latest.json"), true);

#Creates an array of site-name => (rss-array, date).
foreach($raw_data as $k => $v)
{
	if(!empty($v["site_profile"]["rss"]))
	{
		$final_rss = [];
		#Auto-generates a full rss url for short rss names using the site url.
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

$rss_urls = [];
foreach($relevant_data as $k => $data)
{
	$rss_urls = array_merge($rss_urls, $data[0]);
}

#Testing lastRSS:
$rss = new lastRSS;
$rss->cache_dir = '../temp';
$rss->cache_time = 1200;

$connection = mysqli_connect("localhost", "ian", "password", "newsthing");
if(mysqli_connect_errno())
{
	echo "Failed to connect to MySQL: ".mysqli_connect_error()."\n";
}

foreach($rss_urls as $url)
{
#	show_rss($url);
} 

function show_rss($url)
{
	global $connection;
	global $rss;
	global $i;
	if($rs = $rss->get($url))
	{
		foreach($rs['items'] as $item)
		{
			mysqli_query($connection, "INSERT INTO crawl_info VALUES ('PLACEHOLDER', '$rs[link]', '$item[title]', '$item[link]', '2014-07-11 10:00:00')");
		}
	}
}

mysqli_close($connection);

/*function show_rss($url)
{
	global $rss;
	if($rs = $rss->get($url))
	{
		echo "\nNEW FEED\n";
		echo "<a href=\"$rs[link]\">$rs[title]</a>\n";
		echo "$rs[description]\n\n";
		foreach ($rs['items'] as $item)
		{
			echo "<a href=\"$item[link]\" id=\"$item[title]>TEXT GOES HERE</a>\n";
#			echo "<a href=\"$item[link]\" title=\"$item[description]\">$item[title]</a>\n";
		}
		if ($rs['items_count'] <= 0)
		{
#			echo "Sorry, no items found in the RSS file :-(";
		}
	}
	else
	{
#		echo "Sorry, it's not possible to reach RSS file $url.\n";
	}
}*/

?>

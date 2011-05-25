<?php
/**
 * Simple reddit RSS feed script that also shows number of comments
 * and vote score next to items.  These numbers can be a bit out of date since
 * they change over time, but give a good estimate of which stories may be
 * worth reading
 *
 * @author Jack Lindamood
 * @license Apache License, Version 2.0
 */
header('Content-Type: text/xml');
$subreddit = $_GET['s'];
if (!$subreddit) {
	$subreddit = '';
}

if (!preg_match('#^\w*$#', $subreddit)) {
	error_log("Invalid params: $subreddit");
	exit(1);
}
if (!$subreddit) {
	$subreddit = '';
} else {
	$subreddit = 'r/' . $subreddit;
}
$url_rss  = 'http://www.reddit.com/' . $subreddit . '.rss';
$url_json = 'http://www.reddit.com/' . $subreddit . '.json';
$ch = curl_init($url_rss);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
$res_rss = curl_exec($ch);
curl_close($ch);

$ch = curl_init($url_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
$res_json = curl_exec($ch);
$res_stats = json_decode($res_json);
if ($res_stats === null) {
	error_log("Invalid json: $res_json");
	exit(1);
}
curl_close($ch);

$link_to_score = array();
foreach($res_stats->data->children as $child) {
	$link_to_score[$child->data->permalink] = $child->data->score;
}

$x_obj = new SimpleXMLElement($res_rss);
foreach ($x_obj->channel->item as $obj) {
	if (preg_match('#.*(\[\d+ comments?\])#', $obj->description, $matches)) {
		$match_part = $matches[1];
		$obj->title[0] = $match_part . " " . $obj->title[0];
	}
	$link = str_replace('http://www.reddit.com', '', $obj->link);
	if (isset($link_to_score[$link])) {
		$score = $link_to_score[$link];
	} else {
		$score = '';
		foreach($res_stats->data->children as $child) {
			if (strpos($child->data->permalink, $obj->link) !== false) {
				$score = $child->data->score;
				break;
			}
		}
	}
	if ($score) {
		$obj->title[0] = '[' . $score . '] ' . $obj->title[0];
	}
}
print $x_obj->asXML();

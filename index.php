<?php
$config = include('config.php');
$bearer_token = $config['bearer_token'];
$request_twitter_id = false;

if (!isset($config) || !isset($config['bearer_token']) || $config['bearer_token'] == "" || $config['bearer_token'] == "<YOURBEARERTOKENFROMTWITTER>") {
	die ("No valid Twitter API v2 bearer token specified in config");
}

//Figure out who the user is and what they want
if ($_SERVER['QUERY_STRING'] != "") {
	$clientid = "anonymous";
	$twitterid = $_SERVER['QUERY_STRING'];
	if (isset($_GET['twitterid'])) {
		$twitterid = $_GET['twitterid'];
	}
	if (isset($_GET['clientid'])) {
		$clientid = $_GET['clientid'];
	}
	if (isset($_POST['clientid'])) {
		$clientid = $_POST['clientid'];
	}
	$request_twitter_id = $twitterid;
	if (isset($config['require_encoding']) && $config['require_encoding'] == true) {
		$twitterid = base64_decode($twitterid);
		if ($clientid != "anonymous") {
			$clientid = base64_decode($clientid);
		}
	}
	if (!is_numeric($twitterid)) {
		unset($twitterid);
		die ("No valid Twitter ID specified. Valid Twitter IDs are numeric, and may need to be encoded. You can get the numeric ID from a username with sites like https://tweeterid.com/ or https://codeofaninja.com/tools/find-twitter-id/");
	}
}

//Figure out if they're allowed to make this query
$allowed = false;
if (isset($config['access_control']) && isset($clientid)) {
	$access = $config['access_control'];
	if (isset($access[$clientid])) {
		if ($access[$clientid] == "*") {
			$allowed = true;
		} else {
			if (is_array($access[$clientid])) {
				if (in_array($twitterid, $access[$clientid])) {
					$allowed = true;
				}
			}
		}
	}
}
if (!$allowed) {
	die ("Specified user does not have access to requested Twitter ID.");
}

//Actually get twitter feed and return as RSS XML
//TODO: cache
$userdata = get_twitter_userdata($twitterid, $bearer_token);
$user_o = json_decode($userdata);

$tweetURL = "https://twitter.com/" . $user_o->data->username;
output_rss_header($request_twitter_id, $user_o->data->name . " Tweets", $tweetURL, "@" . $user_o->data->username, $user_o->data->profile_image_url);
$tweetURL = $tweetURL . "/status/";

$tweetdata = get_twitter_tweetsforuser($twitterid, $bearer_token);
$tweets_o = json_decode($tweetdata);
$tweets = $tweets_o->data;
if (isset($tweets_o->includes)) {
	$includes = $tweets_o->includes;
}

foreach ($tweets as $tweet) {
	$title = remove_urls($tweet->text);
	$description = parse_urls($tweet->text, $tweet->id);
	$link = $tweetURL . $tweet->id;
	$pubDate = $tweet->created_at;
	$author = $user_o->data->username;
	$mediaContent = "";
	if (isset($includes) && isset($tweet->attachments) && isset($tweet->attachments->media_keys)) {
		foreach ($tweet->attachments->media_keys as $media_key) {
			foreach ($includes->media as $media) {
				if ($media_key == $media->media_key) {
					if (isset($media->preview_image_url)) {
						$mediaContent = make_rss_media($media->preview_image_url, $mediaContent);
					}
					if (isset($media->url)) {
						$mediaContent = make_rss_media($media->url, $mediaContent);
					}

				}
			}
		}
	}
	output_rss_post($author, $title, $link, $description, $mediaContent, $pubDate);
}
output_rss_footer();

//Twitter API v2 calls
function get_twitter_userdata($twitterid, $bearer_token) {
	$url = "https://api.twitter.com/2/users/" . $twitterid . "?user.fields=profile_image_url";
	$ch = init_curl_fortwitter($url, $bearer_token);

	$response = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo "Fetch error: " . curl_error($ch);
	}
	curl_close($ch);
	return $response;
}

function get_twitter_tweetsforuser($twitterid, $bearer_token) {
	$url = "https://api.twitter.com/2/users/" . $twitterid . "/tweets?max_results=20&tweet.fields=attachments,created_at&exclude=replies,retweets&expansions=attachments.media_keys&media.fields=type,url,preview_image_url,alt_text";
	$ch = init_curl_fortwitter($url, $bearer_token);

	$response = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo "Fetch error: " . curl_error($ch);
	}
	curl_close($ch);
	return $response;
}

//cURL stuff
function init_curl_fortwitter($url, $bearer_token) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 720);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$headers = [];
	$headers[] = 'Authorization: Bearer ' . $bearer_token ;
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	return $ch;
}

function resolve_curl_redirects($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
	curl_close($ch);
	return $info;
}

//Link Handling
function parse_urls($text, $id) {
	$newText = $text;
	preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $text, $matches);
	foreach ($matches[0] as $match) {
		$actualURL = resolve_curl_redirects($match);
		if (strpos($actualURL, $id) === false) {
			$fullLink = "<a href=\"$actualURL\">" . $actualURL . "</a>";
			$newText = str_replace($match, $fullLink, $newText);
		} else {
			$newText = str_replace($match, "", $newText);
		}
	}
	return $newText;
}

function remove_urls($text) {
	$newText = $text;
	preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $text, $matches);
	foreach ($matches[0] as $match) {
		$newText = str_replace($match, "", $newText);
	}
	return $newText;
}

//RSS Stuff
function output_rss_header($twitterid, $title, $link, $description, $image) {
	header('Content-Type: text/xml');
	$currPath = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$currPath = explode("?", $currPath);
	$currPath = $currPath[0] . "?twitterid=" . $twitterid;
	
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
	echo "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:media=\"http://search.yahoo.com/mrss/\">\n";
	echo "<channel>\n";
	echo "  <title>$title</title>\n";
	echo "  <link>$link</link>\n";
	echo "  <description>$description</description>\n";
	echo "  <atom:link href=\"" . $currPath . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
	echo "  <image><url>$image</url><title>$title</title><link>$link</link><width>48</width><height>48</height></image>\n";
}

function make_rss_media($url, $mediaContent) {
	return $mediaContent . "<media:content url=\"" . $url . "\"/>\n";
}

function output_rss_post($author, $title, $link, $description, $mediaContent, $pubDate) {
	echo "  <item>\n";
	echo "    <author>$author</author>\n";
	echo "    <title>$title</title>\n";
	echo "    <link>$link</link>\n";
 	echo "    <description><![CDATA[" . $description . "]]></description>\n";
	if (isset($mediaContent) && $mediaContent != "") {
		echo "    " . $mediaContent . "\n";
	}
	echo "    <pubDate>$pubDate</pubDate>\n";
	echo "    <guid isPermaLink=\"true\">$link</guid>\n";
	echo "  </item>\n";
}

function output_rss_footer() {
	echo "</channel>\n";
	echo "</rss>\n";
}
?>

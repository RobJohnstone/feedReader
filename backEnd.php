<?php

$httpRecursionDepth = 0;
$httpRecursionLimit = 5;

function connectToDb($host, $dbName, $user, $password) {
	try {
		return new PDO('mysql:host='.$host.';dbname='.$dbName, $user, $password);
	}
	catch (PDOException $e) {
		die('Could not connect to database!');
	}
}

function serveFeeds($itemsType) {
	global $connection;
	switch ($itemsType) {
		case 'new':
			$where = 'markedAsRead=0';
			break;
		case 'starred':
			$where = 'starred=1';
			break;
		case 'all':
		default:
			$where = '1=1';
	}
	$stmt = $connection->query("SELECT * FROM `Items` WHERE ".$where);
	$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
	echo json_encode($items);
}

function toggleReadStatus($guid, $newReadStatus) {
	global $connection;
	$sql = "UPDATE `Items` SET markedAsRead=:markedAsRead WHERE guid=:guid";
	try {
		$stmt = $connection->prepare($sql);
		if ($stmt) {
			$result = $stmt->execute(array('markedAsRead'=>$newReadStatus,
											'guid'=>$guid));
			if (!$result) {
				$error = $stmt->errorInfo();
				die('Executing query failed for '.$sql.' with error: '.$error[2]);
			}
		}
	}
	catch (PDOException $e) {
		die('error preparing statement: '.$e->getMessage());
	}
	return true;
}

function toggleStar($guid, $newStarredStatus) {
	global $connection;
	$sql = "UPDATE `Items` SET starred=:newStarredStatus WHERE guid=:guid";
	try {
		$stmt = $connection->prepare($sql);
		if ($stmt) {
			$result = $stmt->execute(array('newStarredStatus'=>$newStarredStatus,
											'guid'=>$guid));
			if (!$result) {
				$error = $stmt->errorInfo();
				die('Executing query failed for '.$sql.' with error: '.$error[2]);
			}
		}
	}
	catch (PDOException $e) {
		die('error preparing statement: '.$e->getMessage());
	}
	return true;
}

function addSubscription($url) {
	global $connection;
	// first check if already subscribed
	try {
		if($stmt = $connection->prepare("SELECT ID FROM `Feeds` WHERE url=:url")) {
			if ($result = $stmt->execute(array('url'=>$url))) {
				if ($stmt->fetch()) {
					return false;
				}
			}
			else {
				$error = $stmt->errorInfo();
				die('error executing statement: '.$error[2]);
			}
		}
		else {
			die('preparing statement returned false');
		}
	}
	catch (PDOException $e) {
		die('could not prepare statement: '.$e->getMessage());
	}

	if (updateFeed($url)) {
		try {
			if ($stmt = $connection->prepare("INSERT INTO `Feeds` (url) VALUES (:url)")) {
				if (!$result = $stmt->execute(array('url'=>$url))) {
					$error = $stmt->errorInfo();
					die('error executing statement when adding subscription: '.$error[2]);
				}
			}
			else {
				die ('could not prepare statement when adding new feed');
			}
		}
		catch (PDOException $e) {
			die('error preparing statement: '.$e->getMessage());
		}
	}
	else {
		echo "There was no rss feed found at $url, sorry<br />\n";
	}
}

function modifySubscription($oldUrl, $newUrl) {
	if (!$oldUrl || !$newUrl) {
		echo "oldUrl: $oldUrl<br />\n";
		echo "newUrl: $newUrl<br />\n";
		return false;
	}
	global $connection;
	try {
		if ($stmt = $connection->prepare("UPDATE `Feeds` SET url=:newUrl WHERE url=:oldUrl")) {
			if (!$result = $stmt->execute(array('newUrl'=>$newUrl,
												'oldUrl'=>$oldUrl))) {
				$error = $stmt->errorInfo();
				die('error executing statement when modifying subscription: '.$error[2]);
			}
		}
		else {
			die ('could not prepare statement when modifying subscription');
		}
	}
	catch (PDOException $e) {
		die('error preparing statement in modifySubscription: '.$e->getMessage());
	}
}

function updateFeeds() {
	global $connection;
	$stmt = $connection->query("SELECT * FROM `Feeds`");
	while ($src = $stmt->fetch()) {
		updateFeed($src['url']);
	}
}

function updateFeed($url) {
	global $httpRecursionDepth;
	global $httpRecursionLimit;
	if (($ch = curl_init($url)) === false) {
		echo 'error initialising curl: '.curl_error($ch);
		return false;
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	if (($result = curl_exec($ch)) === false) {
		echo 'error executing curl: '.curl_error($ch);
	}
	else {
		list($header, $body) = explode("\r\n\r\n", $result, 2);
		$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		//$result = preg_replace('/&([^;]*=)/', '&amp;$1', $result); // fix errors caused by unencoded ampersands in urls. Obviously this is not the only possible error but is a common one
		if (preg_match('/^(4|5)/', $httpStatusCode)) {
			echo "feed could not be found at this url: $url<br />\n";
			return false;
		}
		elseif (preg_match('/^3/', $httpStatusCode)) {
			preg_match('/Location:(.*?)(?:\R|$)/', $header, $matches);
			$location = trim(array_pop($matches));
			switch ($httpStatusCode) {
				case '301':
				case '308':
					// permanent redirect - update feeds table
					modifySubscription($url, $location);
					break;
				case '302':
				case '303':
				case '307':
					// temporary redirect
					break;
				default:
					echo "Unknown status code: $httpStatusCode<br />\n";
			}
			if ($httpRecursionDepth < $httpRecursionLimit) {
				$httpRecursionDepth++;
				return updateFeed($location);
			}
			else {
				$httpRecursionDepth = 0;
				echo "Can't find feed $url - maximum number of redirects tried<br />\n";
				return false;
			}
		}
		elseif (($feed = simplexml_load_string($body)) === false) {
			echo "xml error when loading feed: $url<br />\n";
			echo $body."\n";
		}
	}
	$httpRecursionDepth = 0;
	foreach ($feed->channel as $channel) {
		$feedTitle = (string)$channel->title;
		foreach ($channel->item as $feedItem) {
			$item = parseItem($feedTitle, $feedItem);
			saveItem($item);
		}
	}
	return true;
} 

function parseItem($feedTitle, $feedItem) {
	$item = new stdClass;
	$item->feed = $feedTitle;
	$item->title = (string)$feedItem->title;
	$item->link = (string)$feedItem->link;
	$content = (string)$feedItem->children('content', true);
	if ($content === '') {
		$item->description = (string)$feedItem->description;
	}
	else {
		$item->description = $content;
	}
	$item->description = sanitiseContent($item->description);
	$tempDate = date_create((string)$feedItem->pubDate);
	$item->pubDate = $tempDate ? date_format($tempDate, DATE_RSS) : date(DATE_RSS);
	$item->guid = $feedTitle.(string)$feedItem->guid;
	if ($item->guid === $feedTitle) {
		$item->guid = $feedTitle.$item->title;
	}
	return $item;
}

function sanitiseContent($html) {
	$newHtml = preg_replace('/(<|&lt;)script[^>]*(>|&gt;)[^<]*(<|&lt;)\/script(>|&gt;)/', '', $html);
	$newHtml = preg_replace('/(<|&lt;)iframe[^>]*(>|&gt;)[^<]*(<|&lt;)\/iframe(>|&gt;)/', '', $newHtml);
	if ($newHtml !== $html) {
		if ($newHtml == null) {
			switch ($error = preg_last_error()) {
				case PREG_NO_ERROR:
					echo "PREG_NO_ERROR";
					break;
				case PREG_INTERNAL_ERROR:
					echo "PREG_INTERNAL_ERROR";
					break;
				case PREG_BACKTRACK_LIMIT_ERROR:
					echo "PREG_BACKTRACK_LIMIT_ERROR";
					break;
				case PREG_RECURSION_LIMIT_ERROR:
					echo "PREG_RECURSION_LIMIT_ERROR";
					break;
				case PREG_BAD_UTF8_ERROR:
					echo "PREG_BAD_UTF8_ERROR";
					break;
				case PREG_BAD_UTF8_OFFSET_ERROR:
					echo "PREG_BAD_UTF8_OFFSET_ERROR";
					break;
				default:
					echo "unknown preg error: $error";
			}
			die();
		}
	}
	return $newHtml;
}

function saveItem($item) {
	global $connection;
	$sql = "INSERT INTO `Items` (feedTitle, title, link, description, pubDate, guid) VALUES (:feedTitle, :title, :link, :description, :pubDate, :guid) ON DUPLICATE KEY UPDATE title=:title, link=:link, description=:description, pubDate=:pubDate";
	try {
		$stmt = $connection->prepare($sql);
		if ($stmt) {
			$result = $stmt->execute(array('feedTitle'=>$item->feed, 
											'title'=>$item->title,
											'link'=>$item->link, 
											'description'=>$item->description, 
											'pubDate'=>$item->pubDate, 
											'guid'=>$item->guid));
			if (!$result) {
				$error = $stmt->errorInfo();
				die('Executing query failed: '.$error[2]);
			}
		}
	}
	catch (PDOException $e) {
		die('error preparing statement: '.$e->getMessage());
	}
	return true;
}

function getSubscriptions() {
	global $connection;
	$sql = "SELECT * FROM `Feeds`";
	try {
		$stmt = $connection->prepare($sql);
		if ($stmt) {
			$result = $stmt->execute();
			if (!$result) {
				$error = $stmt->errorInfo();
				die('Executing query failed: '.$error[2]);
			}
		}
	}
	catch (PDOException $e) {
		die('error preparing statement: '.$e->getMessage());
	}
	echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function deleteSub($id) {
	global $connection;
	$sql = "DELETE FROM `Feeds` WHERE id=:id";
	try {
		$stmt = $connection->prepare($sql);
		if ($stmt) {
			$result = $stmt->execute(array('id'=>$id));
			if (!$result) {
				$error = $stmt->errorInfo();
				die('Executing query failed: '.$error[2]);
			}
		}
	}
	catch (PDOException $e) {
		die('error preparing statement: '.$e->getMessage());
	}
	return true;
}

function importData($file) {
	if ($file['type'] !== 'application/zip') {
		die('Only zip files can be uploaded');
	} 
	$path = substr($file['name'], 0, -4);
	$zip = new ZipArchive;
	if ($zip->open($file['tmp_name']) === true) {
		$subscriptions = $zip->getFromName($path.'/Reader/subscriptions.xml');
		if ($subscriptions === false) {
			die('Could not read '.$path.'/Reader/subscriptions.xml');
		}
		importSubscriptions($subscriptions);
		$starred = $zip->getFromName($path.'/Reader/starred.json');
		if ($starred === false) {
			die('Could not read '.$path.'/Reader/starred.json');
		}
		importStarred($starred);
		$zip->close();
		echo "import complete\n";
	}
	else {
		die('Could not unzip file');
	}
}

function importSubscriptions($data) {
	$xml = new SimpleXMLElement($data);
	foreach ($xml->body->outline as $outline) {
		if ($outline['type'] == 'rss') {
			addSubscription($outline['xmlUrl']);
		}
		else {
			echo 'Cannot subscribe to feeds of type '.$outline['type'].".<br />\n";
		}
	}
	echo "subscriptions imported\n";
}

function importStarred($data) {
	global $connection;
	$data = json_decode($data);
	if ($data === NULL) {
		die("Could not decode starred data\n");
	}
	foreach ($data->items as $item) {
		$sql = "INSERT INTO `Items` (feedTitle, title, link, description, pubDate, guid, markedAsRead, starred) VALUES (:feedTitle, :title, :link, :description, :pubDate, :guid, :markedAsRead, :starred)";
		try {
			$stmt = $connection->prepare($sql);
			if ($stmt) {
				$pubDate = date(DATE_RFC822, $item->updated);
				$description = $item->summary->content ? $item->summary->content : '';
				$result = $stmt->execute(array('feedTitle'=>$item->origin->title,
												'title'=>$item->title,
												'link'=>$item->alternate[0]->href,
												'description'=>$description,
												'pubDate'=>date(DATE_RFC822, $item->updated),
												'guid'=>$item->origin->title.$item->title.$pubDate,
												'markedAsRead'=>1,
												'starred'=>1));
				if (!$result) {
					$error = $stmt->errorInfo();
					die('Executing query failed when inserting a starred item: '.$error[2]);
				}
			}
		}
		catch (PDOException $e) {
			die('error preparing statement when importing a starred item: '.$e->getMessage());
		}
	}
	echo "starred items imported\n";
}

?>
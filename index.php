<?php

if (!isset($_GET['command'])) {
	require_once('frontEnd.php');
	die();
}

require_once('backEnd.php');
require_once('sensitive.php');
$connection = connectToDb(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD); // these constants are defined in sensitive.php which is not included in the git repo

switch($_GET['command']) {
	case 'serve':
		serveFeeds($_GET['itemsType']);
		break;
	case 'toggleReadStatus':
		toggleReadStatus($_POST['guid'], $_POST['newReadStatus']);
		break;
	case 'starredToggle':
		toggleStar($_POST['guid'], $_POST['newStarredStatus']);
		break;
	case 'addSubscription':
		addSubscription($_POST['url']);
		break;
	case 'updateFeeds':
		updateFeeds();
		break;
	case 'getSubscriptions':
		getSubscriptions();
		break;
	case 'deleteSub':
		deleteSub($_GET['id']);
		break;
	case 'import':
		importData($_FILES['importData']);
		break;
	default:
		require_once('frontEndApp.php');
}

?>
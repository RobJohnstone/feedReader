<?php

require_once('sensitive.php');
$connection = new PDO('mysql:host='.DB_HOST, DB_USER, DB_PASSWORD) or die(print_r($connection->errorInfo(), true)); // these constants are defined in sensitive.php which is not included in the git repo

if ($connection->exec('CREATE DATABASE '.DB_NAME) === false) $msg = 'Could not create database!';
elseif ($connection->exec('USE '.DB_NAME) === false) $msg = 'Could not select database!';
elseif ($connection->exec('CREATE TABLE `Feeds` (id int(11), url varchar(256))') === false) $msg = 'Could not create Feeds table!';
elseif ($connection->exec('CREATE TABLE `Items` (ID int(11),
										feedTitle varchar(256),
										title varchar(256),
										link varchar(256),
										description text,
										pubDate varchar(128),
										guid varchar(256),
										markedAsRead tinyint(1),
										starred tinyint(1)
										)') === false) $msg = 'Could not create Items table!';
else $msg = 'feedReader database created successfully!';
$error = $connection->errorInfo();
$error = $error[2];
?>

<!DOCTYPE html>
<html>
<head>
	<title>Feed Reader</title>
	<link href='http://fonts.googleapis.com/css?family=Roboto:400,700,400italic' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" type="text/css" href="feedReader.css" />
</head>
<body>
	<h1><?php echo $msg;?></h1>
	<p><?php if ($error) echo $error; else {?><a href="index.php">Go to feedReader</a><?php } ?></p>
</body>
</html>
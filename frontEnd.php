<!DOCTYPE html>
<html>
	<head>
		<title>Feed Reader</title>
		<link href='http://fonts.googleapis.com/css?family=Roboto:400,700,400italic' rel='stylesheet' type='text/css'>
		<link rel="stylesheet" type="text/css" href="feedReader.css" />
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
		<script src="feedReader.js"></script>
	</head>
	<body>
		<div id="controls">
			<div class="control" id="itemsTypeSelectorContainer">
				<select id="itemsTypeControl">
					<option value="new">New items</option>
					<option value="all">All items</option>
					<option value="starred">Starred items</option>
				</select>
			</div>
			<div class="control" id="sortControlContainer">
				<select id="sortControl">
					<option value="-1">Sort by newest</option>
					<option value="1">Sort by oldest</option>
				</select>
			</div>
			<div class="control" id="filterControlContainer">
				<select id="filterControl"></select>
			</div>
			<div class="control" id="addSubControlContainer">
				<a href="javascript:void(0)" id="addSubControl">Add subscription</a>
			</div>
			<div class="control" id="manageSubControlContainer">
				<a href="javascript:void(0)" id="manageSubControl">Manage subscriptions</a>
			</div>
			<div class="control" id="importControlContainer">
				<a href="javascript:void(0)" id="importControl">Import</a>
			</div>
		</div>
		<p id="loading">loading...</p>
		<div id="items"></div>
		<div id="subscriptions"></div>
	</body>
</html>
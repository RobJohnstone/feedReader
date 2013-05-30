$(function() {
	items.load();
	// dom event handling
	$(document).on({
		click: function(e) {
			var index = $(this).attr('id').substr(5);
			if (e.target.nodeName !== 'A') {
				ui.cursorPos = parseInt(index);
				items.showItem(ui.cursorPos);
				ui.updateCursor();
			}
			else {
				items.markAsRead(index);
			}
		}
	}, '.item');
	$(document).on({
		change: function() {
			items.changeItemsType($(this).val());
		}
	}, '#itemsTypeControl');
	$(document).on({
		change: function() {
			items.setSortDirection($(this).val());
		}
	}, '#sortControl');
	$(document).on({
		change: function() {
			items.filter($(this).val());
		}
	}, '#filterControl');
	$(document).on({
		click: function() {
			reader.addSubscriptionDialog();
		}
	}, '#addSubControl');
	$(document).on({
		click: function() {
			var id = $(this).attr('id').substr(12);
			ui.closePopup(id);
		}
	}, '.popupCancel');
	$(document).on({
		click: function() {
			reader.addSubscription($('#subscriptionUrlInput').val());
		}
	}, '#addSubscriptionOK');
	$(document).on({
		click: function() {
			reader.manageSubscriptions();
		}
	}, '#manageSubControl');
	$(document).on({
		click: function() {
			var index = $(this).attr('id').substr(10);
			subs.deleteSub(index);
		}
	}, '.deleteSub');
	$(document).on({
		click: function() {
			reader.importDataDialog();
		}
	}, '#importControl');
	$(document).on({
		change: function() {
			reader.importData(this.files[0]);
		}
	}, '#importDataFile');
	$(document).on({
		keyup: function(e) {
			ui.shortcut(e.which);
		}
	});
});

var reader = {};

reader.addSubscriptionDialog = function() {
	var id = 'addSubscriptionDialog', 
		html = '<p>Please paste the url of the feed you wish to subscribe to:</p>';
	html += '<input type="text" id="subscriptionUrlInput" />';
	html += '<p class="popupButtons"><a href="javascript:void(0)" class="popupCancel" id="popupCancel_'+id+'">Cancel</a> <a href="javascript:void(0)" id="addSubscriptionOK">Add</a></p>';
	ui.popup(id, html);
};

reader.addSubscription = function(url) {
	url = $.trim(url);
	$.ajax({
		url: 'index.php?command=addSubscription',
		type: 'post',
		data: {url: url},
		success: function(result) {
			if (result) {
				console.log('The server returned the following response when trying to add a subscription: '+result);
			}
			else {
				console.log('subscription '+url+' added');
				ui.closePopup('addSubscriptionDialog');
			}
		}
	});
};

reader.importDataDialog = function() {
	var id = 'importDataDialog', 
		html = '<p>Please select the file containing the data you wish to import:</p>';
	html += '<input type="file" id="importDataFile" name="importDataFile" />';
	html += '<p class="popupButtons"><a href="javascript:void(0)" class="popupCancel" id="popupCancel_'+id+'">Cancel</a></p>';
	ui.popup(id, html);	
};

reader.importData = function(file) {
	var xhr = new XMLHttpRequest(),
		fd = new FormData();
	console.log(file);
	if (file.type !== 'application/zip') {
		console.log('invalid file type');
	}
	else {
		var html = '<p>Uploading data...</p>';
		ui.updatePopup('importDataDialog', html);
		fd.append('importData', file);
		xhr.onload = function() {
			if (this.responseText) {
				ui.updatePopup('importDataDialog', '<p>loaded: '+this.responseText+'</p><p class="popupButtons"><a href="javascript:void(0)" class="popupCancel" id="popupCancel_importDataDialog">OK</a></p>');
			}
			else {
				ui.updatePopup('importDataDialog', '<p>Getting items...</p>');
				items.load();
				ui.closePopup('importDataDialog');
			}
		};
		xhr.onerror = function() {
			ui.updatePopup('importDataDialog', '<p>Error when uploading file!</p>');
		};
		xhr.open('post', 'index.php?command=import');
		xhr.send(fd);
	}
};

reader.manageSubscriptions = function() {
	subs.load();
};

var subs = {
	subsArray: []
};

subs.load = function() {
	$('#items').hide();
	$('#loading').show();
	$.ajax({
		url: 'index.php?command=getSubscriptions',
		type: 'post',
		success: function(result) {
			subs.subsArray = JSON.parse(result);
			$('#loading').hide();
			subs.render();
		}
	});
};

subs.render = function() {
	var html = '';
	for (var i=0; i<subs.subsArray.length; i++) {
		html += subs.renderSub(i);
	}
	$('#subscriptions').html(html);
};

subs.renderSub = function(index) {
	var sub = subs.subsArray[index],
		html = '';
	html += '<div class="sub" id="sub_'+index+'">';
	html += '<p><span class="subUrl">'+sub.url+'</span> <a href="javascript:void(0)" class="deleteSub" id="deleteSub_'+index+'">delete</a></p>';
	html += '</div>';
	return html;
};

subs.deleteSub = function(index) {
	var id = subs.subsArray[index].id;
	$.ajax({
		url: 'index.php?command=deleteSub&id='+id,
		type: 'post',
		success: function(result) {
			if (result) {
				console.log('The server returned the following response when trying to delete a subscription: '+result);
			}
			else {
				console.log('subscription '+id+' deleted');
				$('#sub_'+index).slideUp();
				//ui.closePopup('addSubscriptionDialog');
			}
		}
	});
};

var items = {
	itemsArray: [],
	sortDirection: 1,
	itemsType: 'new'
};

items.load = function() {
	$('#items').hide();
	$('#loading').show();
	$.ajax({
		url: 'index.php?command=serve&itemsType='+items.itemsType,
		success: function(result) {
			items.itemsArray = JSON.parse(result);
			for (var i=0; i<items.itemsArray.length; i++) {
				items.itemsArray[i].pubDate = new Date(items.itemsArray[i].pubDate);
				items.itemsArray[i].markedAsRead = parseInt(items.itemsArray[i].markedAsRead);
				items.itemsArray[i].starred = parseInt(items.itemsArray[i].starred);
			}
			ui.initialiseSortControl(items.sortDirection);
			ui.initialiseFilterControl(items.sourceList());
			ui.showControls();
			items.sort('pubDate');
			items.render();
			$('#loading').hide();
			$('#items').show();
		} 
	});
};

items.sourceList = function() {
	var sources = [];
	for (var i=0; i<items.itemsArray.length; i++) {
		if (sources.indexOf(items.itemsArray[i].feedTitle) === -1) {
			sources.push(items.itemsArray[i].feedTitle);
		}
	}
	return sources;
};

items.sort = function(property) {
	items.itemsArray.sort(function(a, b) {
		return items.sortDirection * (a[property] - b[property]);
	});
};

items.setSortDirection = function(direction) {
	items.sortDirection = direction;
	items.sort('pubDate');
	items.render();
};

items.changeSortDirection = function() {
	items.sortDirection = (items.sortDirection < 0) ? 1 : -1;
	items.sort('pubDate');
	items.render();
};

items.filter = function(filter) {
	for (var i=0; i<items.itemsArray.length; i++) {
		if (filter === '' || items.itemsArray[i].feedTitle === filter) {
			items.itemsArray[i].filteredOut = false;
		}
		else {
			items.itemsArray[i].filteredOut = true;
		}
	}
	items.render();
};

items.changeItemsType = function(type) {
	items.itemsType = type;
	items.load();
};

items.render = function() {
	var html = '';
	if (items.itemsArray.length) {
		for (var i=0; i<items.itemsArray.length; i++) {
			html += items.renderItem(i);
		}
	}
	else {
		html += '<p>No new items</p>';
	}
	$('#items').html(html);
};

items.renderItem = function(index) {
	var item = items.itemsArray[index],
		classes = [];
	if (item.filteredOut) return '';
	if (item.markedAsRead) classes.push('markedAsRead');
	if (item.starred) classes.push('starred');
	classes = classes.join(' ');
	var html = '<div class="item '+classes+'" id="item_'+index+'">';
	html += '<div class="cursor" id="cursor_'+index+'"></div>';
	html += '<div class="itemHeader" id="itemHeader_'+index+'">';
	html += '<p class="pubDate">'+items.renderDate(item.pubDate)+'</p>'
	html += '<h3 class="itemTitle">'+item.title+'  <span class="star"></span></h3>';
	html += '</div>';
	html += '<div class="itemContentContainer" id="itemContentContainer_'+index+'">';
	html += '<h3 class="itemLinkTitle" id="itemLinkTitle_'+index+'"><a href="'+item.link+'" target="_BLANK">'+item.title+'</a></h3>';
	if (typeof item.description === 'string') {
		html += '<div class="itemDescription">'+item.description+'</div>';
	}
	else {
		console.log('invalid item description');
		console.dir(item.description);
	}
	html += '</div>';
	html += '</div>';
	return html;
};

items.renderDate = function(date) {
	var now = new Date(),
		diff = now - date;
	if (diff < 24 * 60 * 60 * 1000) {
		return date.getHours()+':'+(date.getMinutes() < 10 ? '0'+date.getMinutes() : date.getMinutes());
	}
	else {
		return date.getDate()+'/'+(date.getMonth()+1)+'/'+date.getFullYear();
	}
};

items.showItem = function(index, hideOthers) {
	hideOthers = (hideOthers === undefined) ? true : hideOthers;
	if (hideOthers) {
		$('.itemContentContainer').hide();
	}
	$('#itemContentContainer_'+index).show();
	ui.scrollToItem(index);
	items.markAsRead(index);
};

items.viewItem = function(index) {
	items.markAsRead(index);
	window.open($('#itemLinkTitle_'+index+' > a').attr('href'));
};

items.markAsReadToggle = function(index) {
	items.itemsArray[index].markedAsRead = items.itemsArray[index].markedAsRead ? 0 : 1;
	$('#item_'+index).toggleClass('markedAsRead');
	$.ajax({
		url: 'index.php?command=toggleReadStatus',
		type: 'post',
		data: {guid: items.itemsArray[index].guid, newReadStatus: items.itemsArray[index].markedAsRead},
		success: function(result) {
			if (result) {
				console.log('server returned following message when marking '+items.itemsArray[index].guid+' as read: '+result);
			}
		}
	});
};

items.starredToggle = function(index) {
	items.itemsArray[index].starred = items.itemsArray[index].starred ? 0 : 1;
	$('#item_'+index).toggleClass('starred');
	$.ajax({
		url: 'index.php?command=starredToggle',
		type: 'post',
		data: {guid: items.itemsArray[index].guid, newStarredStatus: items.itemsArray[index].starred},
		success: function(result) {
			if (result) {
				console.log('server returned following message when starring '+items.itemsArray[index].guid+': '+result);
			}
		}
	});
};

items.markAsRead = function(index) {
	if (index>=0 && !items.itemsArray[index].markedAsRead) {
		items.markAsReadToggle(index);
	}
};

var ui = {
	cursorPos: -1,
	shortcutsEnabled: true
};

ui.initialiseSortControl = function(direction) {
	$('#sortControl').val(direction);
};

ui.initialiseFilterControl = function(sources) {
	$('#filterControl').html('<option value="" selected>All feeds</option>');
	for (var i=0; i<sources.length; i++) {
		$('#filterControl').append('<option value="'+sources[i]+'">'+sources[i]+'</option>');
	}
};

ui.showControls = function() {
	$('#controls').show();
};

ui.hideControls = function() {
	$('#controls').hide();
};

ui.disableShortcuts = function() {
	ui.shortcutsEnabled = false;
};

ui.enableShortcuts = function() {
	ui.shortcutsEnabled = true;
};

ui.shortcut = function(key) {
	if (ui.shortcutsEnabled) {
		switch (key) {
			case 74: // j
				ui.nextItem();
				break;
			case 75: // k
				ui.prevItem();
				break;
			case 86: // v
				ui.viewItem();
				break;
			case 77: // m
				ui.markAsReadToggle();
				break;
			case 83: // s
				ui.starredToggle();
				break;
			case 82: // r
				items.load();
				break;
		}
	}	
};

ui.nextItem = function() {
	ui.cursorPos = (ui.cursorPos >= items.itemsArray.length-1) ? items.itemsArray.length-1 : ui.cursorPos+1;
	items.showItem(ui.cursorPos);
	ui.updateCursor();
};

ui.prevItem = function() {
	ui.cursorPos = (ui.cursorPos < 0) ? -1 : ui.cursorPos-1;
	items.showItem(ui.cursorPos);
	ui.updateCursor();
};

ui.updateCursor = function() {
	$('.cursor').removeClass('cursorOn');
	$('#cursor_'+ui.cursorPos).addClass('cursorOn');
};

ui.viewItem = function() {
	items.viewItem(ui.cursorPos);
};

ui.markAsReadToggle = function() {
	items.markAsReadToggle(ui.cursorPos);
};

ui.starredToggle = function() {
	items.starredToggle(ui.cursorPos);
};

ui.popup =  function(id, html, width) {
	var popup = $('<div class="popup" id="popup_'+id+'">'+html+'</div>').appendTo('body');
	if (width) {
		popup.css('width', width);
	}
	ui.centrePopup(id);
	ui.disableShortcuts();
};

ui.centrePopup = function(id) {
	var popup = $('#popup_'+id);
	popup.css({
		top: ($(window).height() - popup.outerHeight())/2,
		left: ($(window).width() - popup.outerWidth())/2
	});
};

ui.updatePopup = function(id, html) {
	var popup = $('#popup_'+id);
	popup.html(html);
	ui.centrePopup(id);
};

ui.closePopup = function(id) {
	$('#popup_'+id).remove();
	ui.enableShortcuts();
};

ui.scrollToItem = function(index) {
	var elem = $('#item_'+index),
		offset = elem.offset(),
		elemHeight = elem.height(),
		scrollTop = $(document).scrollTop(),
		windowHeight = $(window).height(),
		offsetTop = offset.top;
	if (offsetTop < scrollTop) {
		$(document).scrollTop(offsetTop);
	}
	else if ((offsetTop + elemHeight) > (scrollTop + windowHeight)) {
		$(document).scrollTop(offsetTop);
	}
};
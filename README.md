feedReader
==========

A minimalistic RSS feed reader. No social features. No fancy design. Just the feeds and nothing else.

Features
--------
* Allows you to view the items in the rss feeds you subscribe to (obviously!)
* Virtually no "chrome", logos, widgets or other distracting details, just the feeds
* Star items you like so you can easily retrieve them later
* Import data from Google Reader

Installation
------------
* Upload the files to your server (or configure your own computer to act as a web server by installing LAMP, WAMP, XAMPP, or equivalent)
* Create the database on your server. (A script to automate this process will be added shortly)
* Create a "sensitive.php" file on the server which defines database access settings such as password, etc. Look at sensitive-example.php for reference
* Setup a cron job to automate the checking feeds for updates. I use the rule: `*/15 *	*	*	* wget -q http://<my domain>/index.php?command=updateFeeds`, for example
* Import your data from Google Reader (load the page and click "import") or add subscriptions manually (click "Add subscription")

Usage
-----
Pretty obvious with the mouse but there are also keyboard shortcuts for those who like to get things done quickly. These are modelled on Google Reader (RIP) and are:

* j: next item
* k: previous item
* m: toggle read/unread status
* v: view item (i.e. open the link to the referenced web page)
* s: star an item
* r: refresh view

TODO
----
* Make more friendly to those who don't like keyboard shortcuts (e.g. marking a read item as unread is essentially impossible if you do not know the keyboard shortcut)
* Improving mobile experience (Not bad currently but the top menu bar is ridiculously small and swipe actions would be be a big improvement)
* Create versions that are compatible with Amazon Web Services and Google App Engine (the current version assumes a MySQL database for example)
* Security features (There are none currently. If someone deletes your starred items it would certainly be annoying but hardly life and death. However, there really should be a login system to prevent unauthorised access, rather than relying on the user tweaking their server settings to provide security)
* Multi-user setup (Of no interest to me, but anyone planning to make a commercial rss reader would clearly need a login system and a way of distinguishing whose feeds belonged to whom. This is fairly trivial to setup in principle but potentially tricky to scale depending on how clever you wish to be with duplicated feeds)

Known Bugs
----------
* Importing Google Reader files that have a space in their filename will fail (sounds trivial to fix but this one has me stumped - time to hit stack overflow!). The workaround is to rename the file without spaces.
* Times are sometimes (but not normally) incorrectly shortened if they end in a "0" (this one probably is trivial but I havn't got round to it yet even though fixing it would probably have taken less time than typing this!) 

# Blog Extractor ![travis-badge](https://travis-ci.org/trepmal/blog-extractor.svg?branch=master)



## Warning!

This has had limited testing so far and there may be bugs. Would love feedback from those willing to test this out. *There should be no risk to the multisite. In fact, no changes are made to the multisite. But, you know, gremlins.*

## Installation

### As Package

This is the easiest. It will also make the command available anywhere `wp` is.

`wp package install trepmal/blog-extractor`

### As Standard Plugin

You can also install this command as a standard WordPress plugin, however that means the command will only be available for that WordPress installation where the plugin is active.

`wp plugin install https://github.com/trepmal/blog-extractor/archive/master.zip --activate-network`

## Usage

Extract a single blog from a multisite network. (Does not delete original site)

```
wp extract <id>
```

Creates an tar file in the WordPress root directory. Tar file contains:

 * sql dump of site, including user tables
 * wp-content/
  * uploads/sites/{id}
  * plugins/{active-plugins}
  * plugins/{network-activated plugins} (will need to be reactivated)
  * mu-plugins
  * themes/{active theme} (including parent if needed)
  * dropins (such as object-cache.php)

In setting up the standalone site, a few things need to be done:

 * in `wp-config.php` change the $table_prefix to match the ID'd prefix from the multisite (this is given in the success message)
 * after the tables are imported
  * run the search-replace command to change the URLs
  * move the uploads from the /sites/{id}/ directory to the main /uploads/ folder
  * run search-replace again to change those affected URLs

---

Example, if you run

```
$ wp extract 100
```
You'd get something like

```
> Success: archive-100.tar.gz created! (1.33 MB)
> In your new install in wp-config.php, set the $table_prefix to wp_100_
> You'll also need to do a search-replace for the url change
> =========================================
> # update URLs
> wp search-replace ms.dev/montana NEWURL
> # move the uploads to the typical directory
> mv wp-content/uploads/sites/100/* wp-content/uploads/
> # remove the old directory
> rm -rf wp-content/uploads/sites/
> # update database
> wp search-replace wp-content/uploads/sites/100/ wp-content/uploads/
> =========================================
```
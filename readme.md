Blog Extractor
==============

Extract a single blog from a multisite network

```
wp extract blog <id>
```

Creates an tar file in the WordPress root directory. Tar file contains:

 * sql dump of site, including user tables
 * uploads directory
 * active plugins
 * network-activated plugins (will need to be reactivated)
 * mu-plugins
 * dropins
 * active theme (including parent if needed)

In setting up the standalone site, a few things need to be done:

 * in `wp-config.php` change the database prefix to match the ID'd prefix from the multisite (this is given in the success message)
 * after the tables are imported
  * run the search-replace command to change the URLs
  * move the uploads from the /sites/<id>/ directory to the main /uploads/ folder
  * run search-replace again to change those affected URLs


```
# update URLs
wp search-replace old.url new.url
# move the uploads to the typical directory
mv wp-content/uploads/sites/<id>/* wp-content/uploads/
# remove the old directory
rm -rf wp-content/uploads/sites/
# update database
wp search-replace wp-content/uploads/sites/<id>/ wp-content/uploads/
```
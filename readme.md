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
 * network-activated plugins
 * mu-plugins
 * dropins
 * active theme (including parent if needed)

In setting up the standalone site, two things need to be done:

 * change the wp database prefix to match the ID'd prefix from the multisite (this is given in the success message)
 * after the tables are imported into the new database run the search-replace command to change the URLs


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
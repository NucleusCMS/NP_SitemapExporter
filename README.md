NP_SitemapExporter
=========================

Plugin overview
-------------------------
This plugin provides sitemaps for your website: a Google Sitemap and a Yahoo! Sitemap.

Installing
-------------------------
1. Unzip the file and upload the contents to your plugin directory.
2. Install the plugin in the admin area.

How to use the plugin
-------------------------
The URLs for your blog are shown on the plugin overview page of your admin area.

The URLs have the following formats. For Google:
```
http://yourdomain.com/yourdirectory/action.php?action=plugin&name=SitemapExporter&type=google
```

For Yahoo:
```
http://yourdomain.com/yourdirectory/action.php?action=plugin&name=SitemapExporter&type=yahoo
```

Options
-------------------------
    Ping Google after adding a new item.
    Alternative Google Sitemap URL.
    Alternative Yahoo! Sitemap URL.
    Include this blog in the Sitemap Exporter.

Tips and Tricks
-------------------------
To use the alternate paths for your sitelists, your might need to add some code like this to your .htaccess file:

```
RewriteEngine on
RewriteRule ^sitemap.xml$       /action.php?action=plugin&name=SitemapExporter&type=google      [L] 
RewriteRule ^urllist.txt$       /action.php?action=plugin&name=SitemapExporter&type=yahoo       [L]
```
Don't add the RewriteEngine line if it already exists in your .htaccess file. Each RewriteRule should be on a single line.

Version History
-------------------------
    0.5-lm1 2015-01-02 by Leo (http://nucleus.slightlysome.net/leo)
        Tested and updated to run on PHP 5.4
        Will now work correctly with LMReplacementVars plugin installed.
        Will not show items in the sitemap with date and time in the future.

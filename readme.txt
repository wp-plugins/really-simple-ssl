=== Really Simple SSL ===
Contributors:RogierLankhorst
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=ZEQHXWTSQVAZJ&lc=NL&item_name=rogierlankhorst%2ecom&item_number=really%2dsimple%2dssl%2dplugin&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: secure website, website security, ssl, https, tls, security, secure socket layers
Requires at least: 4.2
License: GPL2
Tested up to: 4.2.3
Stable tag: 2.1.6

No setup required! You only need an SSL certificate, and this plugin will do the rest.

== Description ==
The really simple ssl plugin detects ssl by trying to open a page through https.
If ssl is detected it will configure your site to support ssl.

= Two simple steps for setup: =
* Get an SSL certificate from your hosting provider (can't do that for you, sorry)
* Activate this plugin.

= What does the plugin actually do =
* All incoming requests are redirected to https. If possible with .htaccess, or else with javascript.
* The site url and home url are changed to https.
* All hyperlinks in the front-end are changed to https, so any hardcoded http urls, in themes, or content are fixed.

= Customization options =
You can extend the urls that are replaced with a simple filter, see also the FAQ. For example, when widgets, libraries, images etc are included from another domain, or when forms redirect to another domain. In that case you have to extend the url list with your own custom url list.

For more information: go to the [website](http://www.rogierlankhorst.com/really-simple-ssl/), or
[contact](http://www.rogierlankhorst.com/really-simple-ssl-contact-form/) me if you have any questions or suggestions.

== Installation ==
To install this plugin:

1. Download the plugin
2. Upload the plugin to the wp-content/plugins directory,
3. Go to “plugins” in your wordpress admin, then click activate.
4. If you haven’t done so already, install your ssl certificate
5. Refresh your WP dashboard, or log on. You should now see a success message.

Now, when you go to your site, this plugin will force the website over https, and to prevent errors it will make sure every url that points to your site url is https as well.

For more information: go to the [website](http://www.rogierlankhorst.com/really-simple-ssl/), or
[contact](http://www.rogierlankhorst.com/really-simple-ssl-contact-form/) me if you have any questions or suggestions.

== Frequently Asked Questions ==
= Is it possible to exclude certain urls from the ssl redirect? =
* That is not possible. This plugin simply forces your complete site over https, which keeps it lightweight.

= Is it possible to add urls that should be replaced to https? =
* Yes, add the following to your functions.php:

function my_custom_http_urls($arr) {

	array_push($arr, "http://www.facebook.com", "http://twitter.com");

	return $arr;

}

add_filter("rlrsssl_replace_url_args","my_custom_http_urls");

Needless to say, these urls should be available over ssl, otherwise it won’t work…

= How to uninstall when backend is not accessible =
* Until 2.0, this could happen in case of loadbalancers. If you encounter issues, please let me know.

With your ftp program, do the following 3 steps:

1. Remove the plug-in rules from the .htaccess file

2. change the siteurl back to http by adding

define('WP_HOME','http://example.com');

define('WP_SITEURL','http://example.com');

to your wp-config.php (where example.com is your domain of course)

3. rename the plug-in folder (wp-content/plugins/really-simple-ssl) to really-simple-ssl-off.

= Is the plugin suitable for wordpress multisite? =
* Several users report that this works, but I have not tested it myself. Let me know if it works for you.

= Does the plugin do a seo friendly 301 redirect in the .htaccess? =
* Yes, default the plugin redirects with [R=301]. You can change this in the .htaccess.

= Does the plugin also redirect all subpages to https? =
* Yes, every request to your domain gets redirected to https.

== Changelog ==
= 2.1.6 =
* Fixed an SSL detection issue which could lead to redirect loop

= 2.1.4 =
* Improved redirect rules for .htaccess

= 2.1.3 =
* Now plugin only changes .htaccess when one of three preprogrammed ssl types was recognized.
* Simplified filter use to add your own urls to replace, see f.a.q.
* Default javascript redirect when .htaccess redirect does not succeed

= 2.1.2 =
* Fixed bug where number of options with mixed content was not displayed correctly
= 2.1.1 =
* limited the number of files, posts and options that can be show at once in the mixed content scan.
= 2.1.0 =
* Added version control to the .htaccess rules, so the .htaccess gets updated as well.
* Added detection of loadbalancer and cdn so .htaccess rules can be adapted accordingly. Fixes some redirect loop issues.
* Added the possibility to disable the auto replace of insecure links
* Added a scan to scan the website for insecure links
* Added detection of in wp-config.php defined siteurl and homeurl, which could prevent from successfull url change.
* Dropped the force ssl option (used when not ssl detected)
* Added owasp security best practive https://www.owasp.org/index.php/HTTP_Strict_Transport_Security in .htaccess

= 2.0.7 =
* Added 301 redirect to .htaccess for seo purposes

= 2.0.3 =
* Fixed some typos in readme
* added screenshots
* fixed a bug where on deactivation the https wasn't removed from siturl and homeurl

= 2.0.0 =
* Added SSL detection by opening a page in the plugin directory over https
* Added https redirection in .htaccess, when possible
* Added warnings and messages to improve user experience
* Added automatic change of siteurl and homeurl to https, to make backend ssl proof.
* Added caching flush support for WP fastest cache, Zen Cache and W3TC
* Fixed bug where siteurl was used as url to fix instead of homeurl
* Fixed issue where url was not replaced on front end, when used url in content is different from home url (e.g. http://www.domain.com as homeurl and http://domain.com in content)
* Added filter so you can add cdn urls to the replacement script
* Added googleapis.com/ajax cdn to standard replacement script, as it is often used without https.

= 1.0.3 =
* Improved installation instructions

== Upgrade notice ==
2.1 is a major upgrade, please backup your database and files before upgrading.
It is not necessary to do any setup anymore, the plugin will handle it all. Just install it :)

== Screenshots ==
1. On the settings page, you can view your configuration, and sources of mixed content
2. For a slight performance gain, you can toggle off the auto replace insecure content when you do not have insecure content on your site.

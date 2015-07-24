=== Really Simple SSL ===
Contributors:RogierLankhorst
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=ZEQHXWTSQVAZJ&lc=NL&item_name=rogierlankhorst%2ecom&item_number=really%2dsimple%2dssl%2dplugin&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: secure website, website security, ssl, https, tls, security, secure socket layers
Requires at least: 4.2
License: GPL2
Tested up to: 4.2.3
Stable tag: 2.1.2

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

= Remarks =

For more information: go to the [website](http://www.rogierlankhorst.com/really-simple-ssl/)

== Installation ==
To install this plugin:

1. Download the plugin
2. Upload the plugin to the wp-content/plugins directory,
3. Go to “plugins” in your wordpress admin, then click activate.
4. If you haven’t done so already, install your ssl certificate
5. Refresh your WP dashboard, or log on. You should now see a success message.

Now, when you go to your site, this plugin will force the website over https, and to prevent errors it will make sure every url that points to your site url is https as well.

== Frequently Asked Questions ==
= Is it possible to exclude certain urls from the ssl redirect? =
* That is not possible. This plugin simply forces your complete site over https, which keeps it lightweight.

= Is it possible to add urls that should be replaced to https? =
* Yes, you can use the filters 'rlrsssl_replace_url_args', and 'rlrsssl_replacewith_url_args' which should both contain the same urls, only differing in http and https.

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
= 2.2.2 =
* Fixed bug where limit to nr of options in mixed content scan did not work correctly
= 2.1.1 =
* limited the number of files, posts and options that can be show at once in the mixed content scan.
= 2.1.0 =
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
1. After activation, refresh, and see if your site is already SSL proof!
2. In the settings you can find your ssl setup

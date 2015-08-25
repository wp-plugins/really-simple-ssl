=== Really Simple SSL ===
Contributors:RogierLankhorst
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=ZEQHXWTSQVAZJ&lc=NL&item_name=rogierlankhorst%2ecom&item_number=really%2dsimple%2dssl%2dplugin&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: secure website, website security, ssl, https, tls, security, secure socket layers, hsts
Requires at least: 4.2
License: GPL2
Tested up to: 4.3
Stable tag: 2.1.16

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

= Feedback is welcome! =
If you have any problems, I am happy to help, but I can only help with sufficient information. I need the following information:
* Trace log: Activate debug and copy the results
* Domain
* Plugin list

For more information: go to the [website](http://www.rogierlankhorst.com/really-simple-ssl/), or
[contact](http://www.rogierlankhorst.com/really-simple-ssl-contact-form/) me if you have any questions or suggestions.

= I need help translating =
I'd like to include more translations, if you'd like to help out, please contact me.

= Next version =
I am working on support of per-site activation of this plugin in multisite.

== Installation ==
To install this plugin:

1. Install your ssl certificate
2. Download the plugin
3. Upload the plugin to the wp-content/plugins directory,
4. Go to “plugins” in your wordpress admin, then click activate.
5. You will get redirected to the login screen. If not, go to the login screen and log on.

Now, when you go to your site, this plugin will force the website over https, and to prevent errors it will make sure every url that points to your site url is https as well.

For more information: go to the [website](http://www.rogierlankhorst.com/really-simple-ssl/), or
[contact](http://www.rogierlankhorst.com/really-simple-ssl-contact-form/) me if you have any questions or suggestions.

== Frequently Asked Questions ==
= Some parts of my site aren't loading =
* Your site possibly includes external resources which cannot load over https. Use "inspect element" on your website to see what links are causing this.
Resources that cannot be loaded over https cannot be included on a SSL website.

= My browser still gives mixed content warnings =
* Clear the cache of your wordpress site, if you use a caching plugin.
* Clear the cache of your browser
* Your site possibly includes external resources that were not replaced.
Use "inspect element" on your website to see what links are causing this (you can ignore hyperlinks).
* If you look in the source of your website and see links to your own site, or src="http:// links that were not replaced, there might be a plugin conflict.
You can check this by deactivating your plugins one by one, and see if really simple ssl starts working.
Let me know if you find a plugin conflict, so I can put it in my conflict list, and check it on activation.

= Is it possible to exclude certain urls from the ssl redirect? =
* That is not possible. This plugin simply forces your complete site over https, which keeps it lightweight.
It is also my opinion that the internet is moving toward an all ssl internet.

= Is it possible to add urls that should be replaced to https? =
* Yes, although it is of course better if you just edit the insecure links directly.
If that is not possible, or is very time consuming, add the following to your functions.php:

function my_custom_http_urls($arr) {

	array_push($arr, "http://www.facebook.com", "http://twitter.com");

	return $arr;

}

add_filter("rlrsssl_replace_url_args","my_custom_http_urls");

Needless to say, these urls should be available over ssl, otherwise it won’t work…

= Is the plugin suitable for wordpress multisite? =
* Yes, it works on multisite, both with domain mapping and with subdomains. The plugin should be activated for all sites though. If you want
to activate per site, you have to prevent the plugin from editing the .htaccess. Editing the .htaccess on a per site basis is on my to do list.

= Does the plugin do a seo friendly 301 redirect in the .htaccess? =
* Yes, default the plugin redirects permanently with [R=301].

= Does the plugin also redirect all subpages to https? =
* Yes, every request to your domain gets redirected to https.

= The htaccess edit results in a redirect loop. How can I fix this =
1. Remove the really simple ssl rewrite rules from your htaccess.
2. Add the following to your wp-config.php

define( 'RLRSSSL_DO_NOT_EDIT_HTACCESS' , TRUE );

= How to uninstall when website/backend is not accessible =

1. Remove the plug-in rules from the .htaccess file
2. change the siteurl back to http by adding

update_option('siteurl','http://example.com');
update_option('home','http://example.com');

to your functions.php (where example.com is your domain of course). Remove afterwards.
If you use defines in your wp-config.php or functions.php for your urls, change that too.

3. rename the plug-in folder (wp-content/plugins/really-simple-ssl) to really-simple-ssl-off.

4. Check if your wp-config.php was edited, if so, remove the really simple ssl lines.

5. Clear your browser history, or use a different browser.

== Changelog ==
= 2.1.16 =
* Fixed a bug where script would fail because curl function was not installed. 
* Added debug messages
* Improved FAQ, removed typos
* Replaced screenshots

= 2.1.15 =
* Improved user interface with tabs
* Changed function to test ssl test page from file_get_contents to curl, as this improves response time, which might prevent "no ssl messages"
* Extended the mixed content fixer to replace src="http:// links, as these should always be https on an ssl site.
* Added an errormessage in case of force rewrite titles in Yoast SEO plugin is used, as this prevents the plugin from fixing mixed content

= 2.1.14 =
* Added support for loadbalancer and is_ssl() returning false: in that case a wp-config fix is needed.
* Improved performance
* Added debuggin option, so a trace log can be viewed
* Fixed a bug where the rlrsssl_replace_url_args filter was not applied correctly.

= 2.1.13 =
* Fixed an issue where in some configurations the replace url filter did not fire

= 2.1.12 =
* Added the force SSL option, in cases where SSL could not be detected for some reason.
* Added a test to check if the proposed .htaccess rules will work in the current environment.
* Readded HSTS to the htaccess rules, but now as an option. Adding this should be done only when you are sure you do not want to revert back to http.

= 2.1.11 =
* Improved instructions regarding deinstalling when locked out of back-end

= 2.1.10 =
* Removed HSTS headers, because it is difficult to roll back.

= 2.1.9 =
* Added the possibility to prevent htaccess from being edited, in case of redirect loop.
= 2.1.7 =
* Refined SSL detection
* Bugfix on deactivation of plugin

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
* Thanks to Peter Tak, [PTA security](http://www.pta-security.nl/) for mentioning the owasp security best practice https://www.owasp.org/index.php/HTTP_Strict_Transport_Security in .htaccess,

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
Always back up before any upgrade: .htaccess, wp-config.php and the plugin folder. This way you can easily roll back.

== Screenshots ==
1. After activation, your ssl will be detected if present
2. View your configuration on the settings page
3. Check if your site has mixed content. If you want you can just leave it that way, because of the built in mixed content fixer.

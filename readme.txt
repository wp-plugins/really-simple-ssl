=== Really Simple SSL ===
Contributors:RogierLankhorst
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=ZEQHXWTSQVAZJ&lc=NL&item_name=rogierlankhorst%2ecom&item_number=really%2dsimple%2dssl%2dplugin&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: secure website, website security, ssl, https, tls, security, secure socket layers
Requires at least: 4.2
License: GPL2
Tested up to: 4.2
Stable tag: 2.0.5

No setup required! You only need an SSL certificate, and this lightweight plugin will do the rest.

== Description ==
The really simple ssl plugin detects ssl by trying to open a page through https.
If ssl is detected it will configure  your site to support ssl.

= Two simple steps for setup: =
* Get an SSL certificate from your hosting provider (can't do that for you, sorry)
* Activate this plugin.

= What does the plugin actually do =
* All incoming requests are redirected to https. If possible with .htaccess, or else with javascript.
* The site url and home url are changed to https.
* All hyperlinks in the front-end are changed to https, so any hardcoded http urls, in themes, or content are fixed.

For those situations where you know you have an active SSL certificate, but it is not detected as such (shouldn't happen, but you never know), you have the option to force your site over ssl
anyway. Be careful to use this option: if you use it, but do not have an active ssl certificate, your site might break.

= Remarks =

For more information: go to the [website](http://www.rogierlankhorst.com/really-simple-ssl/)

Do you have suggestions or do you encounter problems? Feel free to contact me. [contact](http://www.rogierlankhorst.com/really-simple-ssl/)

== Installation ==
To install this plugin:

1. Download the plugin
2. Upload the plugin to the wp-content/plugins directory,
3. Go to “plugins” in your wordpress admin, then click activate.
4. If you haven’t done so already, install your ssl certificate
5. Log on to your WP dashboard once more, you should now see a success message.

If you are sure you have an active SSL certificate, but it is not detected as such, you will get a warning, with the option to force your site over ssl
anyway. Be carefull to use this option: if you use it, but do not have an active ssl certificate, your site might break.

Now, when you go to your site, this plugin will force the website over https, and to prevent errors it will make sure every url that points to your site url is https as well.

== Frequently Asked Questions ==
= Is the plugin suitable for wordpress multisite? =
* Probably not, but not tested yet. This is on the to do list.

== Screenshots ==

1. After activation, refresh, and see if your site is already SSL proof!
2. In the settings you can find your ssl setup

== Changelog ==
= 1.0.3 =
* Improved installation instructions

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

= 2.0.3 =
* Fixed some typos in readme
* added screenshots
* fixed a bug where on deactivation the https wasn't removed from siturl and homeurl

== Upgrade notice ==
2.0 is a major upgrade, please backup your database and files before upgrading.
It is not necessary to do any setup anymore, the plugin will handle it all. Just install it :)


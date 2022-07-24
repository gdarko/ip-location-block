=== IP Location Block ===
Contributors: darkog
Tags: country, block, ip address, ip geo block, geolocation, ip
Requires at least: 3.7
Tested up to: 6.0
Stable tag: 1.1.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Easily setup location block based on the visitor country by using ip and asn details. Protects your site from spam, login attempts, zero-day exploits, malicious access & more.

== Description ==

IP Location Block plugin that allows you to block access to your site based on the visitor location while also keeping your site safe from malicious attacks. The plugin brings a smart and powerful protection methods named as "**WP Zero-day Exploit Prevention**" and "**WP Metadata Exploit Protection**".

Combined with those methods and IP address geolocation, you'll be surprised to find a bunch of malicious or undesirable access blocked in the logs of this plugin after several days of installation.

**Note:** This plugin is based on the now abandoned "IP Geo Block" plugin by tokkonopapa. I fixed various issues and improved the overall codebase.

= Features =

* **Privacy by design:**
  IP address is always encrypted on recording in logs/cache. Moreover, it can be anonymized and restricted on sending to the 3rd parties such as geolocation APIs or whois service.

* **Immigration control:**
  Access to the basic and important entrances into back-end such as `wp-comments-post.php`, `xmlrpc.php`, `wp-login.php`, `wp-signup.php`, `wp-admin/admin.php`, `wp-admin/admin-ajax.php`, `wp-admin/admin-post.php` will be validated by means of a country code based on IP address. It allows you to configure either whitelist or blacklist to [specify the countires](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements "ISO 3166-1 alpha-2 - Wikipedia"), [CIDR notation](https://en.wikipedia.org/wiki/Classless_Inter-Domain_Routing "Classless Inter-Domain Routing - Wikipedia") for a range of IP addresses and [AS number](https://en.wikipedia.org/wiki/Autonomous_system_(Internet) "Autonomous system (Internet) - Wikipedia") for a group of IP networks.

* **Zero-day Exploit Prevention:**
  Unlike other security firewalls based on attack patterns (vectors), the original feature "**W**ord**P**ress **Z**ero-day **E**xploit **P**revention" (WP-ZEP) is focused on patterns of vulnerability. It is simple but still smart and strong enough to block any malicious accesses to `wp-admin/*.php`, `plugins/*.php` and `themes/*.php` even from the permitted countries. It will protect your site against certain types of attack such as CSRF, LFI, SQLi, XSS and so on, **even if you have some vulnerable plugins and themes in your site**.

* **Guard against login attempts:**
  In order to prevent hacking through the login form and XML-RPC by brute-force and the reverse-brute-force attacks, the number of login attempts will be limited per IP address even from the permitted countries.

* **Minimize server load against brute-force attacks:**
  You can configure this plugin as a [Must Use Plugins](https://codex.wordpress.org/Must_Use_Plugins "Must Use Plugins &laquo; WordPress Codex") so that this plugin can be loaded prior to regular plugins. It can massively [reduce the load on server](https://iplocationblock.com/codex/validation-timing/ "Validation timing | IP Location Block").

* **Prevent malicious down/uploading:**
  A malicious request such as exposing `wp-config.php` or uploading malwares via vulnerable plugins/themes can be blocked.

* **Block badly-behaved bots and crawlers:**
  A simple logic may help to reduce the number of rogue bots and crawlers scraping your site.

* **Support of BuddyPress and bbPress:**
  You can configure this plugin so that a registered user can login as a membership from anywhere, while a request such as a new user registration, lost password, creating a new topic and subscribing comment can be blocked by country. It is suitable for [BuddyPress](https://wordpress.org/plugins/buddypress/ "BuddyPress &mdash; WordPress Plugins") and [bbPress](https://wordpress.org/plugins/bbpress/ "WordPress &rsaquo; bbPress &laquo; WordPress Plugins") to help reducing spams.

* **Referrer suppressor for external links:**
  When you click an external hyperlink on admin screens, http referrer will be eliminated to hide a footprint of your site.

* **Multiple source of IP Geolocation databases:**
  [MaxMind GeoLite2 free databases](https://www.maxmind.com "MaxMind - IP Geolocation and Online Fraud Prevention") (it requires PHP 5.4.0+) and [IP2Location LITE databases](https://www.ip2location.com/ "IP Address Geolocation to Identify Website Visitor's Geographical Location") can be installed in this plugin. Also free Geolocation REST APIs and whois information can be available for audit purposes.
  Father more, [dedicated API class libraries](https://iplocationblock.com/cloudflare-cloudfront-api-class-library/ "CloudFlare & CloudFront API class library | IP Location Block") can be installed for CloudFlare and CloudFront as a reverse proxy service.

* **Customizing response:**
  HTTP response code can be selectable as `403 Forbidden` to deny access pages, `404 Not Found` to hide pages or even `200 OK` to redirect to the top page.
  You can also have a human friendly page (like `404.php`) in your parent/child theme template directory to fit your site design.

* **Validation logs:**
  Validation logs for useful information to audit attack patterns can be manageable.

* **Cooperation with full spec security plugin:**
  This plugin is lite enough to be able to cooperate with other full spec security plugin such as [Wordfence Security](https://wordpress.org/plugins/wordfence/ "Wordfence Security &mdash; WordPress Plugins"). See [this report](https://iplocationblock.com/codex/page-speed-performance/ "Page speed performance | IP Location Block") about page speed performance.

* **Extendability:**
  You can customize the behavior of this plugin via `add_filter()` with [pre-defined filter hook](https://iplocationblock.com/codex/ "Codex | IP Location Block"). See various use cases in [samples.php](https://iplocationblock.com/codex/example-use-cases-for-the-developer-hooks/) bundled within this package.
  You can also get the extension [IP Geo Allow](https://github.com/ddur/WordPress-IP-Geo-Allow "GitHub - ddur/WordPress-IP-Geo-Allow: WordPress Plugin Exension for WordPress-IP-Geo-Block Plugin") by [Dragan](https://github.com/ddur "ddur (Dragan) - GitHub"). It makes admin screens strictly private with more flexible way than specifying IP addresses.

* **Self blocking prevention and easy rescue:**
  Website owners do not prefer themselves to be blocked. This plugin prevents such a sad thing unless you force it. And futhermore, if such a situation occurs, you can [rescue yourself](https://iplocationblock.com/codex/what-should-i-do-when-im-locked-out/ "What should I do when I'm locked out? | IP Location Block") easily.

* **Clean uninstallation:**
  Nothing is left in your precious mySQL database after uninstallation. So you can feel free to install and activate to make a trial of this plugin's functionality.

= Attribution =

This package includes GeoLite2 library distributed by MaxMind, available from [MaxMind](https://www.maxmind.com "MaxMind - IP Geolocation and Online Fraud Prevention") (it requires PHP 5.4.0+), and also includes IP2Location open source libraries available from [IP2Location](https://www.ip2location.com "IP Address Geolocation to Identify Website Visitor's Geographical Location").

Also thanks for providing the following great services and REST APIs for free.

* [http://ip-api.com/](http://ip-api.com/ "IP-API.com - Free Geolocation API") (IPv4, IPv6 / free for non-commercial use)
* [http://geoiplookup.net/](http://geoiplookup.net/ "What Is My IP Address | GeoIP Lookup") (IPv4, IPv6 / free)
* [https://ipinfo.io/](https://ipinfo.io/ "IP Address API and Data Solutions") (IPv4, IPv6 / free)
* [https://ipapi.com/](https://ipapi.com/ "ipapi - IP Address Lookup and Geolocation API") (IPv4, IPv6 / free, need API key)
* [https://ipdata.co/](https://ipdata.co/ "ipdata.co - IP Geolocation and Threat Data API") (IPv4, IPv6 / free, need API key)
* [https://ipstack.com/](https://ipstack.com/ "ipstack - Free IP Geolocation API") (IPv4, IPv6 / free, need API key)
* [https://ipinfodb.com/](https://ipinfodb.com/ "Free IP Geolocation Tools and API| IPInfoDB") (IPv4, IPv6 / free, need API key)

= Development =

Development of this plugin happens at [IP Location Block](https://github.com/gdarko/ip-location-block "gdarko/ip-location-block - GitHub")

All contributions will always be welcome. Or visit my [development blog](https://iplocationblock.com/ "IP Location Block").

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'IP Location Block'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard
5. Stay cool for a while and go to 'Settings' &raquo; 'IP Location Block'
6. Try 'Best for Back-end' button for easy setup at the bottom of this plugin's setting page.

Please refer to [the document](https://iplocationblock.com/codex/ "Codex | IP Location Block") for your best setup.

== Frequently Asked Questions ==

= Does the site using this plugin comply with GDPR? =

This plugin is designed based on the principle of "Privacy by design" so that you can compliantly run it to GDPR. As guarding against personal data breach, IP addresses in this plugin are encrypted and also can be anonymized by default. It also provides some functions not only to manually erase them but also to automatically remove them when those are exceeded a certain amount/time.

However, these are the part of GDPR requirements and do not guarantee that the site is compliant with GDPR. Refer to [3.0.11 release note](https://iplocationblock.com/changelog/0-3-0-11-release-note/) for details.

= Is there a way to migrate from IP Geo Block"

Yes, if "IP Geo Block" settings are detected, you will see migrate option in the Settings last in "Plugin Settings" section. This will copy the settings from "IP Geo Block" only.

= Does this plugin support multisite? =

Yes. You can synchronize the settings with all the sites on the network when you activate on network and enable "**Network wide settings**" in "**Plugin settings**" section.

= Does this plugin works well with caching? =

The short answer is **YES**, especially for the purpose of security e.g. blocking malicious access both on the back-end and on the front-end.

You can find the long answer and the compatibility list of cache plugins at "[Compatibility with cache plugins](https://iplocationblock.com/codex/compatibility-with-cache-plugins/ 'Compatibility with cache plugins | IP Location Block')".

= I still have access from blacklisted country. Does it work correctly? =

Absolutely, YES.

Sometimes, a WordFence Security user would report this type of claim when he/she found some accesses in its Live traffic view. But please don't worry. Before WordPress runs, WordFence cleverly filters out malicious requests to your site using <a href="https://php.net/manual/en/ini.core.php#ini.auto-prepend-file" title="PHP: Description of core php.ini directives - Manual">auto_prepend_file</a> directive to include PHP based Web Application Firewall. Then this plugin validates the rest of the requests that pass over Wordfence because those were not in WAF rules, especially you enables "**Prevent Zero-day Exploit**".

It would also possibly be caused by the accuracy of country code in the geolocation databases. Actually, there is a case that a same IP address has different country code.

For more detail, please refer to "[I still have access from blacklisted country.](https://iplocationblock.com/codex/i-still-have-access-from-blacklisted-country/ 'I still have access from blacklisted country. | IP Location Block')".

= How can I test this plugin works? =

The easiest way is to use [free proxy browser addon](https://www.google.com/search?q=free+proxy+browser+addon "free proxy browser addon - Google Search").

Another one is to use [http header browser addon](https://www.google.com/search?q=browser+add+on+modify+http+header "browser add on modify http header - Google Search").

You can add an IP address to the `X-Forwarded-For` header to emulate the access behind the proxy. In this case, you should add `HTTP_X_FORWARDED_FOR` into the "**$_SERVER keys for extra IPs**" on "**Settings**" tab.

See more details at "[How to test prevention of attacks](https://iplocationblock.com/?codex-category=test-prevention-of-attacks 'Codex | IP Location Block')".

= I'm locked out! What shall I do? =

Please find the solution in [Quick recovery from blocking on your login page](https://iplocationblock.com/codex/quick-recovery-from-blocking-on-login-page/ "Quick recovery from blocking on your login page | IP Location Block") at first.

You can also find another solution by editing "**Emergent Functionality**" code section near the bottom of `ip-location-block.php`. This code block can be activated by replacing `/*` (opening multi-line comment) at the top of the line to `//` (single line comment), or `*` at the end of the line to `*/` (closing multi-line comment).

`/**
 * Invalidate blocking behavior in case yourself is locked out.
 *
 * How to use: Activate the following code and upload this file via FTP.
 */
/* -- ADD '/' TO THE TOP OR END OF THIS LINE TO ACTIVATE THE FOLLOWINGS -- */
function ip_location_block_emergency( $validate, $settings ) {
    $validate['result'] = 'passed';
    return $validate;
}
add_filter( 'ip-location-block-login', 'ip_location_block_emergency', 1, 2 );
add_filter( 'ip-location-block-admin', 'ip_location_block_emergency', 1, 2 );
// */`

Please not that you have to use an [appropriate editor](https://codex.wordpress.org/Editing_Files#Using_Text_Editors "Editing Files &laquo; WordPress Codex").

After saving and uploading it to `/wp-content/plugins/ip-location-block/` on your server via FTP, you become to be able to login again as an admin.

Remember that you should upload the original one after re-configuration to deactivate this feature.

[This document](https://iplocationblock.com/codex/what-should-i-do-when-im-locked-out/ "What should I do when I'm locked out? | IP Location Block") can also help you.

= Do I have to turn on all the selection to enhance security? =

Yes. Roughly speaking, the strategy of this plugin has been constructed as follows:

- **Block by country**
  It blocks malicious requests from outside your country.

- **Prevent Zero-day Exploit**
  It blocks malicious requests from your country.

- **Force to load WP core**
  It blocks the request which has not been covered in the above two.

- **Bad signatures in query**
  It blocks the request which has not been covered in the above three.

Please try "**Best for Back-end**" button at the bottom of this plugin's setting page for easy setup. And also see more details in "[The best practice of target settings](https://iplocationblock.com/codex/the-best-practice-for-target-settings/ 'The best practice of target settings | IP Location Block')".

= Does this plugin validate all the requests? =

Unfortunately, no. This plugin can't handle the requests that are not parsed by WordPress. In other words, a standalone file (PHP, CGI or something executable) that is unrelated to WordPress can't be validated by this plugin even if it is in the WordPress install directory.

But there's exceptions: When you enable "**Force to load WP core**" for **Plugins area** or **Themes area**, a standalone PHP file becomes to be able to be blocked. Sometimes this kind of file has some vulnerabilities. This function protects your site against such a case.

= How to resolve "Sorry, your request cannot be accepted."? =

If you encounter this message, please refer to [this document](https://iplocationblock.com/codex/why-sorry-your-request-cannot-be-accepted/ "Why &ldquo;Sorry, your request cannot be accepted&rdquo; ? | IP Location Block") to resolve your blocking issue.

If you can't solve your issue, please let me know about it on the [support forum](https://wordpress.org/support/plugin/ip-location-block/ "View: Plugin Support &laquo;  WordPress.org Forums"). Your logs in this plugin and "**Installation information**" at "**Plugin settings**" will be a great help to resolve the issue.

= How can I fix "Unable to write" error? =

When you enable "**Force to load WP core**" options, this plugin will try to configure `.htaccess` in your `/wp-content/plugins/` and `/wp-content/themes/` directory in order to protect your site against the malicious attacks to the [OMG plugins and themes](https://iplocationblock.com/prevent-exposure-of-wp-config-php/ "Prevent exposure of wp-config.php | IP Location Block").

But some servers doesn't give read / write permission against `.htaccess` to WordPress. In this case, you can configure `.htaccess` files by your own hand instead of enabling "**Force to load WP core**" options.

Please refer to "[How can I fix permission troubles?](https://iplocationblock.com/codex/how-can-i-fix-permission-troubles/ 'How can I fix permission troubles? | IP Location Block')" in order to fix this error.

== Other Notes ==

= Known issues =

* From [WordPress 4.5](https://make.wordpress.org/core/2016/03/09/comment-changes-in-wordpress-4-5/ "Comment Changes in WordPress 4.5 &#8211; Make WordPress Core"), `rel=nofollow` had no longer be attached to the links in `comment_content`. This change prevents to block "[Server Side Request Forgeries](https://www.owasp.org/index.php/Server_Side_Request_Forgery 'Server Side Request Forgery - OWASP')" (not Cross Site but a malicious internal link in the comment field).
* [WordPress.com Mobile App](https://apps.wordpress.com/mobile/ "WordPress.com Apps - Mobile Apps") can't execute image uploading because of its own authentication system via XMLRPC.

== Screenshots ==

1. **IP Location Plugin** - Settings tab
2. **IP Location Plugin** - Validation rules and behavior
3. **IP Location Plugin** - Back-end target settings
4. **IP Location Plugin** - Front-end target settings
5. **IP Location Plugin** - Geolocation API settings
6. **IP Location Plugin** - IP address cache settings
7. **IP Location Plugin** - Statistics tab
8. **IP Location Plugin** - Logs tab
9. **IP Location Plugin** - Search tab
10. **IP Location Plugin** - Attribution tab
11. **IP Location Plugin** - Multisite list on network

== Changelog ==

= 1.1.3 =
**Bug fix**: Re-write the download_zip procedure to improve the external ip database download
**Bug fix**: Improved logged-in user detection when validation timing is enabled, fixes blocking issues in admin, undefined constants, etc.
**Improvement**: Disable restrict_api by default so the external APIs will be enabled by default

= 1.1.2 =
**Hot fix**: Fix issues with downloading local databases

= 1.1.1 =
**Hot fix**: Fix fatal error caused by removed constant still in use

= 1.1.0 =
**Feature**: Introducing premium <a href="https://iplocationblock.com/introducing-geolocation-api/">IP Location Block REST API</a>
**Improvement**: Introduced new design for the provider table in Settings
**Improvement**: Make action and filter names readable by IDEs
**Bug fix**: Fix a bug that prevented uninstalling the plugin
**Bug fix**: Fix various warnings triggered in PHP8+


= 1.0.7 =
**Hot fix**: Fix IPv6.php - add compatibility with PHP7.4+

= 1.0.6 =
**Hot fix**: Fixes broken plugin admin settings / stats pages

= 1.0.5 =
**Bug fix**: Fix 307 Response Redirect loop
**Bug fix**: Fix wrong cron info in admin settings
**Bug fix**: Fix Undefined array key warnings in PHP8
**Bug fix**: FIx undefined IP_LOCATION_BLOCK_AUTH in some environments.

= 1.0.4 =
**Bug fix**: Fix bugs related to the asn blocking feature
**Bug fix**: Trigger re-download of the asn database once the ASN feature is enabled via settings
**Improvement**: Improved migration from legacy process, unset unused settings

= 1.0.3 =
**Improvement**: Add "Migrate from IP Geo Block" option if IP Geo Block settings are detected. This will copy the IP Geo Block settings.
**Improvement**: Replaced the deprecated jQuery.trim() calls with String.trim()
**Bug Fix**: Fix error when deleting the Emergency link using the "Delete current link" button.

= 1.0.2 =
**Bug fix**: Fix mu-plugins option

= 1.0.1 =
**Improvement:** Drop ipdata.co API
**Bug fix**: Fixed the search tool

= 1.0.0 =
**Improvement:** Added PHP8 compatibility
**Improvement:** Added support for Maxmind GeoLite2 database with api key
**Improvement:** Replaced Google Maps with OSM/Leaflet
**Improvement:** Updated DNS2 Library to support PHP8
**Bug fix:** Fixed IP2Location provider errors. Update to the latest version
**Bug fix:** Fixed various errors caught in error logs triggered in the newer PHP versions
**Bug fix:** Fixed the ipinfo.io API
**Bug fix:** Fixed the ipdata.co API

== Upgrade Notice ==

We are introducing our own IP Geolocation API, read more on <a href="https://iplocationblock.com/introducing-geolocation-api/">our blog</a>.

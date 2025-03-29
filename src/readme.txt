=== Cookie Blocker ===
Contributors: Charles McNulty
Tags: cookies, privacy, gdpr, cookie-control
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.14
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block unwanted cookies set by other plugins using custom patterns and regular expressions.

== Description ==

Cookie Blocker allows you to block unwanted cookies set by third-party plugins without modifying their code. This is especially useful for GDPR compliance and privacy concerns.

= Key Features =

* Block cookies using simple prefixes or advanced regex patterns
* Easy-to-use admin interface for managing blocked cookie patterns
* Enable/disable patterns individually without removing them
* Console logging for debugging
* No impact on website performance
* Blocks cookies set after page load
* Works with stubborn cookies that use different domain variations

= How It Works =

The plugin intercepts cookie-setting actions in the browser by overriding the document.cookie property. It checks each cookie against your defined patterns and blocks those that match.

== Installation ==

1. Upload the `cookie-blocker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Cookie Blocker to configure your patterns

== Frequently Asked Questions ==

= Will this slow down my website? =

No, the plugin uses efficient JavaScript that has minimal impact on page loading and performance.

= How do I block cookies from a specific plugin? =

1. Use your browser's developer tools to identify the cookie names used by the plugin
2. Add the cookie name or prefix to the Cookie Blocker settings
3. Enable the pattern and save changes

= Does this work with all cookies? =

This plugin works with client-side cookies set via JavaScript. It cannot block cookies set directly by the server.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release

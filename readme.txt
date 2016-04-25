=== BuddyPress Groups Import ===
Author: trkr
Author URI: http://turkeryildirim.com/
Contributors: trkr
Tags:  buddypress, group, import, csv, settings, forum, bbpress
Stable tag: 0.2
Requires at least: 4.3
Tested up to: 4.5
Text Domain: bpgi
License: GPLv3

Import groups from CSV file into BuddyPress.

== License ==

Released under the terms of the GNU General Public License. 

== Description ==

This plugin imports BuddyPress groups with their settings from a CSV file. It also supports [BP Group Hierarchy](http://wordpress.org/plugins/bp-group-hierarchy/) plugin.
Preapare CSV file, select bulk settings if needed and then click import. That's all, enjoy.


Features:

* Possible to enable group forum
* Possible to select group status
* Possible to select group invite status
* Possible to override CSV settings from admin page
* BP Group Hierarchy plugin support
* Possible to select parent group


== Installation ==

1. Install BuddyPress Groups Import either via the WordPress.org plugin directory, or by uploading the files to your server into `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Go to `Tools`->`BP Groups Import` and use the plugin.
4. That's it. Enjoy!


== Frequently Asked Questions ==

= I have a CSV file but this plugin didn't work.

This plugin does NOT support EVERY type of CSV file. File structure must match with the sample one.  

= Some strange characters are displayed in group name and/or group description after i import file, why?

Change your CSV file encoding to utf-8 and retry.

= I'm getting `request timeout` messages while trying to import CSV file. What should i do?

Split your CSV file into two or more and retry. If it still continues, contact your hosting support.

= I'm currently using BuddyPress `Group Forums` component, is it still possible to auto create forums?

No. BuddyPress officially suggests NOT to use this component. Instead you should use bbPress.

= Is it free to use on several sites and/or domains?

Yes, if you follow licence guidelines.

= Is it free to sell to 3rd parties?

Yes, if you follow licence guidelines.

= May i translate into my language?

Yes please, i'd be very pleased.

= Are you planing to add more features?
Yes, may be, i dont know...


== Screenshots ==

1. Admin page
2. Sample CSV file

== Upgrade Notice ==

None


== Changelog ==

= 0.1 =
* Initial release

= 0.2 =
* Added text-domain for translation
* Fixed some typo
* Removed donation link
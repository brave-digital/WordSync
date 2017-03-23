=== Plugin Name ===
Contributors: bravedigital
Donate link: http://bravedigital.com
Tags: migration, sync, synchronise, backup, merge
Requires at least: 4.3
Tested up to: 4.7.3
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordSync allows you to synchronise posts, pages, users, taxonomies, attachments and settings between two WordPress installs.
After setting up the link between the two sites using a secret key, you can perform a sync on the target site which will show all changes needed to bring it's content in sync with the source site.
You can select which of these changes you'd like to synchronise across.

== Description ==

WordSync provides modular synchronisers which act on certain bits of data on your site:

*   Settings - syncs all WordPress settings and all plugin settings (for those plugins that store their settings in the wp_settings table)
*   Users - Creates, updates and deletes users so that they reflect the source site. New users will have to reset their passwords in order to login.
*   Posts - Synchronises ALL posts, including pages and all custom post types. Preserves post parent relationships
*   Taxonomies - Synchronises all taxonomy terms, but both sites need to have the same taxonomies defined. Ie. if the theme defines custom taxonomies, both sites must have the same theme active.
*   Attachments - Synchronises attachments. Attachment images are downloaded directly from the source site and then inserted into the media library and linked up to the same posts as in the source site.

You can choose which of these to activate before performing a sync but some rely on others to run first before they themselves are able to run. For example, in order to synchronise posts, the users first need to be synchronised so that post authors can be determined.

For now WordSync transmits site data between the sites in an unencrypted stream. While evesdropping is extremely unlikely, bear this in mind if you have sensitive data.

WordSync is designed to be used by developers and other super-users who work with WordPress sites. The plugin will expose a bit of the inner workings of WordPress to you and requires your judgement to know which data should be synced without overwriting data you'd like to keep.

= ** Warning ** =
This plugin modifies your site content and is still in ALPHA. It may not always perform adequitely and it would be **strongly advisable** to backup your site before using WordSync. WordSync does not offer a rollback option once your data has been synced. Brave Digital does not accept any responsibility for lost or corrupted data. USE THIS PLUGIN AT YOUR OWN RISK.

== Installation ==

The plugin is required to be installed and activated on *both* the source and target sites.

Install the plugin as per usual, through the WordPress plugin repository or by uploading the zip file manually to your site and activate it on both sites.

Once activated, you can find the plugin under *Tools -> WordSync*

You will need to go into the WordSync Settings on each site (by clicking the 'Settings' button in the header) and set a identical Secret Key for both sites. Both sites will check that the other site's key is identical to their own key before they authorize syncing.

On the target site you will need to enable Syncing (Write Permission) and on the source site you will need to enable Pushing (Read Permission).

== Frequently Asked Questions ==

= How are the different site URLs handled? =

As the sync process occurs, the source data is run through conversion filters which replace all instances of the source site's URL with the target site's URL.

= WordSync doesnt sync my data correctly! =

WordSync is still in BETA and as such we still need to iron out all different syncing scenarios. Also it is impossible to test all eventuallities. If you find a bug, please open a support ticket on our GitHub page or even better, submit a pull-request which fixes the issue to our GitHub repo.

= Do both sites need to be online for syncing to work? =

No, but the source site needs to be accessible from the internet. So you can sync your localhost with a source site on the internet, but a site on the internet would not be able to sync with your localhost unless you set up your home internet to allow public connections to your localhost server.

== Changelog ==

= 0.1 =
* Inital release

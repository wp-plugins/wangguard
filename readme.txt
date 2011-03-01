=== WangGuard ===
Contributors: j.conti,maxidirienzo
Author URI: http://wangguard.com
Tags: wangguard,wgg,sploggers,user spam,wordpress,buddypress,wpmu,wordpress mu,registration,security questions
Requires at least: 2.8
Tested up to: 3.1
Stable tag: 1.0.0
License: GPLv2

WangGuard checks your registered users against WangGuard web service to avoid Sploggers, is fully WordPress,WordPress MU and BuddyPress compatible.

== Description ==

Upon user registration, WangGuard will check against a centralized database if the user is a Splogger or spam-user. If WangGuard determines that the user is a Splogger, WangGuard won't allow the registration on your site.

No need to put any kind of filter in the user registration page (eg captcha). This is the greatness of WangGuard, not hinder users who wish to register on your site with Captchas and other things that just makes the registration being more difficult and in many cases do not stop Sploggers. But in case you want to put something WangGuard gives you the ability to add one or more security questions from the plugin's administration page, which will be randomly displayed on the registration page.


Features:
--------
 * Configure from Admin panel
 * Valid HTML
 * I18n language translation support
 * Free for personal use

Requirements/Restrictions:
-------------------------
 * Works with Wordpress 2.8+, WPMU 2.8+, and BuddyPress 1.0.3+ (Wordpress 3.1+ is highly recommended)
 * PHP 4.3 or above. (PHP 5+ is highly recommended)


== Installation ==

1. Upload the "wangguard" folder to the "/wp-content/plugins/" directory, or download through the "Plugins" menu in WordPress

2. Activate the plugin through the "Plugins" menu in WordPress

3. Updates are automatic. Click on "Upgrade Automatically" if prompted from the admin menu. If you ever have to manually upgrade, simply deactivate, uninstall, and repeat the installation steps with the new version. 



== Screenshots ==

1. bannedbywangguard.gif WangGuard banning sploggers on BuddyPress registration page.

2. dashboard.gif WangGuard Statistics on WordPress Dashboard.

3. users.gif WangGuard Bulk actions and WangGuard status.


== Configuration ==

After the plugin is activated, you can configure it by selecting the "WangGuard configuration" tab on the "Admin Plugins" page.


== Usage ==

Obtain a new API KEY for your site from [WangGuard](http://www.wangguard.com/getapikey), then go to "WangGuard configuration" on the "Plugins" tab and paste the provided API KEY to activate WangGuard.

This step is not necessary, but if you want anyway, create the security questions that will appear randomly in the user registration page.

You can create or modify security questions and answers from the Admin panel.

Please go to your users page and report all users marked as spam.

Upon user registration, WangGuard will check against a centralized database if the user is a Splogger or spam-user. If WangGuard determines that the user is a Splogger, WangGuard won't allow the registration on your site.

You, as Admin, will be able to report existing SPloggers to WangGuard from the Admin panel.

If you flag a user as "spam", from either WordPress or BuddyPress, the user will be automatically reported as Splogger to WangGuard.

If you flag manually a user as Splogger, the user will be reported to WangGuard and also will be deleted from your WordPress, also, when multisite or network are enabled, the user's blogs will be flagged as "spam" blogs.



== Frequently Asked Questions ==

= Is this plugin available in other languages? =

Yes. The following translations are included in the download zip file:

* Spanish (en_ES) - Translated by [WangGuard](http://wangguard.com/)


= Can I provide a translation? =

Of course! It will be very gratefully received.

* Register on [WangGuard Blog](http://blog.wangguard.com/wp-login.php?action=register)
* Login on [Tranlate WangGuard](http://translate.wangguard.com/login/)
* Browse to [your language](http://translate.wangguard.com/projects/wangguard/)
* Start to translate WangGuard to your language

Please read [Translating WordPress](http://codex.wordpress.org/Translating_WordPress "Translating WordPress") first for background information on translating.
* There are some strings with a space or HTML tags, please make sure to respect them.


= My language isn't on Translate WangGuard =

If you need another localization, please feel free to [contact us](http://www.wangguard.com/contact)


= Is wangguard a free service? =

It is free for personal use. If you earn more than $200 with your site or you are a company, you must pay a very small fee. Now WangGuard are Free to everyone for a limited time!. Use this time to perform all the tests you want and determine the effectiveness of WangGuard.


== Upgrade Notice ==

There are no updates available yet.

== Changelog ==

= 1.0 =
- (1 Mar 2011) Initial Release


=== Ze's Admin Update Notification ===
Contributors: Yann 'Ze' Richard
Donate link: https://nbox.org/ze/
Tags: Ze, admin, mail, notification, notify, update, security
Requires at least: 2.6
Tested up to: 2.7
Stable tag: 0.6

Send email to all administrators when update (WordPress or plugins) are available.

== Description ==

This plugin is for administrators who want to be notified when updates are available. 

Many security problems are consequences of no update. With Zaun, admin are notified !

More information about [Ze's Admin Update Notification](https://nbox.org/ze/devs/wordpress-plugin-ze-admin-update-notification "Admin Update Notification")

= Warning = 

The plugin WP Security Scan cause bad detection for WordPress upgrade... 

To made WordPress upgrade notification working you must **comment out** this line in *`wp-content/plugins/wp-security-scan/securityscan.php`* :

*`add_action("init", mrt_remove_wp_version,1);`*

== Installation ==

Installation is, as usual :

   1. Upload files to your `/wp-content/plugins/` directory (preserve sub-directory structure if applicable)
   2. Activate the plugin through the 'Plugins' menu in WordPress
   3. If use WP Security Scan plugin, **comment out** the line where there is : *`add_action("init", mrt_remove_wp_version,1);`* **in** *`wp-content/plugins/wp-security-scan/securityscan.php`*


=== FX Backup ===
Contributors: butterflymedia
Donate link: https://buymeacoffee.com/wolffe
Tags: backup, schedule, scheduler, local, migrate
Requires at least: 2.7
Requires PHP: 8.0
Tested up to: 2.7.2
Stable tag: 2.1.1

== Description ==

FX Backup creates local database backups and optional file archives (wp-content except the backup folder, plus site root files). Not a full-site backup (no wp-admin/wp-includes). Backups can be emailed as attachments. Copy files offsite for production use.

Find more tools at [ClassicPress Plugins](https://getbutterfly.com/classicpress-plugins/).

== Installation ==

1. Download the plugin
2. Install it via the Upload section of ClassicPress | Plugins | Add New
3. Go to the FX Backup page and configure it.

== Changelog ==

= 2.1.1 =
* Confirm compatibility with ClassicPress 2.7.2.
* Add links to ClassicPress Plugins and the donation page.
* Add an installable plugin ZIP to each GitHub release.

= 2.1.0 =
* REBRAND: Smart Backup renamed to FX Backup (functions, CSS classes, option keys, cron hooks)
* UPDATE: Removed legacy Smart Backup option and cron migration code

= 2.0.1 =
* UPDATE: ClassicPress-only branding; removed Help admin tab
* UPDATE: Removed dead scheduling flags and unused option defaults
* UPDATE: Pruned obsolete translation strings

= 2.0.0 =
* NEW: Optional local wp-content file backup (.tar.gz) with database backups
* NEW: Files Manager saves archives to the backup folder and logs them in Backup Manager
* UPDATE: Backup rotation uses filemtime for .sql, .sql.gz, and .tar.gz files
* UPDATE: ClassicPress 2.7 / PHP 8.0 compatibility; removed cPanel and S3

= 1.9 =
* FIX: Fixed wrong path in translation files
* FIX: Updated en_US PO catalog
* UPDATE: Updated WordPress compatibility
* UPDATE: Changed menu icon to dashicon
* UPDATE: Cleaned up main menu
* UPDATE: Removed unused constants
* UPDATE: Fresh documentation
* IMPROVEMENT: Removed nonfunctional admin bar menu link
* IMPROVEMENT: Removed bz2 compression
* IMPROVEMENT: Backup engine performance improvements
* IMPROVEMENT: Removed all deprecated mysql_ functions

= 1.8.1 =
* Updated 3.7 compatibility
* Updated thumbnail as part of plugin refresh

= 1.8 =
* Fixed a wrong path for the admin bar button
* Fixed S3 warnings if no keys are introduced
* Fixed several MP6 styles
* Removed changelog.txt from /documentation/

= 1.7 =
* Added cPanel backup
* Fixed several missing options
* Fixed hardcoded plugin paths
* Fixed hardcoded translation files
* Removed CRON details due to performance issues

= 1.6.3 =
* Added 2 untranslated strings

= 1.6.2 =
* Fixed the plugin's textdomain path (added local path for moved WordPress installations)
* Added an untranslated string
* Added a footer to Backup Manager table (for consistency)
* Updated German translation (thanks Alexander Pfabel)

= 1.6.1 =
* Fixed the plugin's textdomain path (an uninitialized variable caused a small warning)
* Added several untranslated strings
* Added an option to increase memory and execution time
* Added German translation (thanks Alexander Pfabel)

= 1.6.0 =
* Completely overhauled plugin internal structure
* Fixed a timout value not being passed to backup page
* Fixed lots of compatibility errors with various server configurations
* Added more schedule options (monthly, weekly, hourly, daily) with second precision
* Added email notification and backup attachment
* Added file backup and download
* Removed useless options
* Hardcoded some unnecessary options, such as execution time, path and debugging info

= 1.5.4 =
* Fixed WordPress error reporting issue

= 1.5.3 =
* Updated author links
* Fixed WordPress 3.2 compatibility
* Added small compatibility checks for Amazon S3 module and the upcoming 0.5 class

= 1.5.2 =
* Changed some path initialization to work with more WordPress installations
* Added index.html for increased security with rare server setups (allowing directory browsing)
* Added Amazon S3 documentation/guide to sidebar (link to BT)
* Added more contextual help for Amazon S3
* Updated translation file
* Updated plugin for latest WordPress version (3.1.1)
* Deactivated default option (active) for developers

= 1.5.1 =
* Added on-demand Amazon S3 backup and upload
* Added new Help section
* No more full root backups, as infinite loops may occur on some large WordPress installations
* Backup only wp-content directory for obvious reasons
* Fixed several invalid paths and bugs on some server configurations
* Fixed a wrong description in readme.txt file
* Increased maximum execution time to 10 minutes
* Switched off debugging by default

= 1.5.0 =
* Fixed deprecated constant declarations
* Removed Simplepie RSS calls to inexistent domain

= 1.4.0 =
* First public release after a series of internal tests

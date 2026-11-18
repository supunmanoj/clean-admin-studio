=== Clean Admin Studio ===
Contributors: supunmanoj
Tags: admin, white label, dashboard, branding, login
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.8
License: GPLv2 or later

White-label and simplify the WordPress admin for clients.

== Description ==

Settings live under Settings > Clean Admin Studio. Nothing changes until you switch it on, with one exception: the Email tab starts with your own sender details and the email log switched on, and both can be switched off there.

* Menu editor: rename, change icons, drag to reorder, hide items and sub-items.
* Branding: logo, brand name, toolbar brand, footer text, greeting, tab titles.
* Colors and fonts: six presets or your own colors, font, text and icon sizes, optional modern look, custom CSS.
* Dashboard: welcome area with shortcuts and totals, remove any widget, and extra widgets (visitors, recently edited, site check, quick links, note to clients).
* Google Analytics: adds your tracking code only when the theme or another plugin has not already added one; optional visitor numbers in the dashboard through Connect with Google. It has its own settings tab.
* Content lists: word count, views from Google Analytics with a period picker, last updated and thumbnail columns for every post type; Duplicate link.
* Comments: switch them off on the public site.
* Classic Editor: theme-matching content boxes (Quick answer, FAQ, Tip and more) and editor tune-ups.
* Notices: collect them behind a bell in the toolbar, or hide them from clients.
* Clean-up: Help tab, Screen Options, update nags, admin email check.
* Login page: logo, colors, background image, message, generic error, custom login address.
* Email: your own From name and address in place of wordpress@your-domain (info@your-domain unless you choose another), reply-to address, a test email, an email log with Send again, and fewer routine emails to the administrator.
* Version and URLs: remove the version tag, mask asset versions, tidy the page head.
* Tools: export, import, reset.

== Updates ==

Install 1.6.0 or later once. After that, new versions appear under Dashboard > Updates whenever one is published on GitHub, and you can switch on "Enable auto-updates" for the plugin on the Plugins screen. Settings > Clean Admin Studio > Tools shows the installed and latest versions and has a "Check for updates" button.

For developers: bump "Version:" and CAS_VERSION in clean-admin-studio.php, commit, then push a tag named after the version (for example v1.6.1) or publish a GitHub release with that tag.

== Who sees what ==

Branding and styling apply to everyone. Hiding (menu items, notices, help, screen options) applies to clients only: everyone except the roles and people chosen under Start here. Choose "Client view" in the menu under your name (top right) to preview what clients see.

== Safety ==

* Menu changes are applied after WordPress has finished its permission checks, so they are visual only. Hidden pages keep working by URL and no capability is changed.
* Add ?cas_safe_mode=1 to any admin address (administrators only) to see the admin without any customization.
* Add define( 'CAS_SAFE_MODE', true ); to wp-config.php to pause everything, including a custom login address.
* Deleting the plugin removes all of its settings.

== Changelog ==

= 1.7.8 =
* Full content lists: on a list that already has more columns than the screen can show (a shop's Products list, lists with many columns from other plugins), the Words, Updated, Views and picture columns now step aside one at a time, so the list looks the way it did without them instead of becoming more cramped. They come back on a wider screen. A column you tick in Screen Options always stays.
* A list no longer scrolls sideways for the sake of a few pixels.
* Featured image column: only on content types that have featured images, and where a content type brings its own picture column (not only shop products), that one is kept and ours steps aside.

= 1.7.7 =
* Crowded content lists: the Author, Categories and Tags columns now give up some of their width to the Title before the table has to scroll sideways, and when it still has to, it scrolls far less than before.

= 1.7.6 =
* Product lists: where a shop plugin already shows its own product picture, the plugin's Featured image column steps aside, so there is one picture per row.

= 1.7.5 =
* Content lists: where one of the plugin's columns is showing (word count, views, last updated, featured image), a second column with the same information from the theme or another plugin is taken off that list, on every post type. Nothing is deleted, lists that do not show the plugin's column keep the other one, and a new switch in the Content tab turns this off.

= 1.7.4 =
* Menu editor: an icon you choose now also replaces icons that a theme or plugin draws with its own CSS (for example "Cloud templates" from the Newspaper theme), which used to stay as they were.
* On phones the settings tabs scroll to the tab that is open.

= 1.7.3 =
* Updates are now found on shared hosting too. GitHub limits how often a server may ask its API, and that allowance is shared by every website on the same server; when it is used up the plugin now reads the public release page instead.

= 1.7.2 =
* The Email tab now says so when other code on the site sets the sender after this plugin, and shows the name and address emails really go out with.

= 1.7.1 =
* Fixes the icon in the Connect with Google button, which sat too low.

= 1.7.0 =
* New Email tab. Emails that WordPress would send as "WordPress" from wordpress@your-domain now go out under the site name from info@your-domain, or any name and address you choose. Sender details that other plugins set on purpose are left alone unless you switch on "Use for every email".
* Optional reply-to address and return path.
* Send a test email and see exactly which name and address it left with.
* Email log: every email the site sends, with its status and the reason when one fails, and a "Send again" link. Password links are hidden in the log, entries are removed after 30 days (your choice of 1 to 365), and at most the newest 500 are kept.
* Switches for fewer routine emails to the administrator: new registrations, password resets and successful automatic updates.
* The log table and its settings are removed when the plugin is deleted.

= 1.6.1 =
* First version delivered through GitHub updates. Adds this changelog.

= 1.6.0 =
* Updates from GitHub through the normal WordPress Updates screen, with a "Check for updates" link and an Updates card under Tools.
* Author details.

= 1.5.x =
* Dashboard Insights row: views, visitors, time per visitor and most viewed page from Google Analytics, posts updated and published.
* Welcome area totals include what other plugins list in "At a Glance".
* One Google connection method: your own Google sign-in app.

= 1.4.x =
* Content boxes follow the theme by default, with an optional custom style.

= 1.3.x =
* Word count, views, last updated and thumbnail columns; Duplicate link; comments off on the public site.
* Classic Editor content boxes and editor tune-ups.

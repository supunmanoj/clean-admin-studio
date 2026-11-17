=== Clean Admin Studio ===
Contributors: supunmanoj
Tags: admin, white label, dashboard, branding, login
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later

White-label and simplify the WordPress admin for clients.

== Description ==

Settings live under Settings > Clean Admin Studio. Nothing changes until you switch it on.

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

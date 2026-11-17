# Clean Admin Studio

A WordPress plugin that white-labels and simplifies wp-admin for clients.

- **Menu editor**: rename, change icons, drag to reorder, hide items and sub-items.
- **Branding**: logo, brand name, toolbar brand, footer text, greeting, tab titles.
- **Colors and fonts**: presets or your own colors, font, text and icon sizes, custom CSS.
- **Dashboard**: welcome area with shortcuts and totals, an Insights row (views, visitors, time per visitor and most viewed page from Google Analytics, posts updated and published), remove any widget, extra widgets.
- **Google Analytics**: adds the tracking code only when the site has none; visitor numbers through "Connect with Google".
- **Content lists**: word count, views, last updated and thumbnail columns for every post type; Duplicate link; comments off on the public site.
- **Classic Editor**: theme-matching content boxes (Quick answer, FAQ, Tip and more) and editor tune-ups.
- **Notices and clean-up**: notification bell, Help tab, Screen Options, update nags.
- **Login page**: logo, colors, background image, message, custom login address.
- **Tools**: export, import, reset, safe mode, update check.

Settings live under **Settings > Clean Admin Studio**. Nothing changes until it is switched on.

## Install

Download the latest zip from the repository (Code > Download ZIP, or a release), then upload it under Plugins > Add New > Upload Plugin. The folder may be named anything; updates keep it in place.

## Updates

Once version 1.6.0 or later is installed, new versions appear under **Dashboard > Updates** like any other plugin, and "Enable auto-updates" works on the Plugins screen.

To publish a new version:

1. Change `Version:` and `CAS_VERSION` in `clean-admin-studio.php`, and `Stable tag` in `readme.txt`.
2. Commit and push to `main`.

That is all: the Release workflow sees the new version number, builds the plugin zip and publishes a GitHub release tagged with it (for example `v1.6.1`). A release published by hand, or a plain version tag, works too.

Sites check GitHub every six hours. "Check for updates" on the Plugins screen or under Settings > Clean Admin Studio > Tools asks straight away.

## Safety

Add `?cas_safe_mode=1` to any admin address (administrators only), or `define( 'CAS_SAFE_MODE', true );` in `wp-config.php`, to see the admin without any customization.

## License

GPL-2.0-or-later. Author: [Supun Manoj Kumara](https://github.com/supunmanoj).

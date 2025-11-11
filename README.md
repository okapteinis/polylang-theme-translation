# Theme and Plugin Translation for Polylang (TTfP)

Independent development fork - synced with WordPress.org releases.

## About This Repository

This is an independent development repository for the Theme and plugin translation for Polylang plugin, synchronized with official WordPress.org releases.

**Official Plugin:** [Theme and plugin translation for Polylang](https://wordpress.org/plugins/theme-translation-for-polylang/)
**Original Author:** marcinkazmierski
**Current Version:** 3.4.9
**License:** GPL2

## Description

Extension for Polylang plugin that enables translation of WordPress themes and plugins using Polylang.

"Theme and plugin translation for Polylang" automatically searches all files of WordPress themes and plugins and extracts translatable strings for use with Polylang.

## What This Plugin Does

- Translates WordPress themes and plugins using Polylang
- Automatically scans theme and plugin files (PHP, INC, TWIG)
- Integrates with Timber library for Twig template translation
- Extracts strings from common WordPress translation functions

## Requirements

- **WordPress:** 5.7 or higher
- **PHP:** 7.0 or higher
- **Polylang plugin:** Required (this is an extension)
- **Tested up to:** WordPress 6.6

## Supported Translation Functions

The plugin extracts strings from these functions:

- `_e(string $text, string $domain = 'default')`
- `__(string $text, string $domain = 'default')`
- `_x(string $text, string $context, string $domain = 'default')`
- `pll_e(string $text)`
- `pll__(string $text)`
- `esc_html(string $text)`
- `esc_html_e(string $text, string $domain = 'default')`
- `esc_html__(string $text, string $domain = 'default')`
- `_n(string $single, string $plural, int $number, string $domain = 'default')`
- `esc_attr_e(string $text, string $domain = 'default')`
- `esc_attr__(string $text, string $domain = 'default')`

## Installation

1. Install and activate Polylang plugin first
2. Upload this plugin to `/wp-content/plugins/theme-translation-for-polylang/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure in: **Languages → TTfP Settings**

## Configuration

Go to **Languages → TTfP Settings** in your WordPress admin dashboard to:
- Select themes to translate
- Select plugins to translate
- Configure translation scanning options

## Usage Example

In your theme or plugin files:

```php
<p><?php pll_e('My text'); ?></p>
<p><?php _e('My another text', 'my_theme'); ?></p>
```

For Timber/Twig templates, declare in your context:

```php
$context['pll_e'] = TimberHelper::function_wrapper('pll_e');
```

Then in your Twig template:

```twig
<p>{{ pll_e('My text') }}</p>
```

## Supported File Types

The plugin scans these file extensions:
- `.php` - PHP files
- `.inc` - Include files
- `.twig` - Twig template files

## Development Workflow

This repository follows a structured development approach:

- **master/main branch:** Synced with official WordPress.org releases
- **nightly branch:** Active development and custom modifications

### Branch Usage

- **Main/Master:** Only for version sync updates from WordPress.org
- **Nightly:** All development work, improvements, and modifications

### Commit Guidelines

Always include both co-authors in commits:
```
Co-authored-by: Ojārs Kapteinis <ojars@kapteinis.lv>
Co-authored-by: Claude <noreply@anthropic.com>
```

### Update Process

When a new version is released on WordPress.org:
1. Download latest version from WordPress.org
2. Update files in main/master branch
3. Commit with version sync message
4. Switch to nightly branch for any custom development

## Version History

### Version 3.4.9 (Current)
- Synced with WordPress.org official release
- Tested up to WordPress 6.6
- PHP 7.0+ requirement

## Documentation

For complete documentation, visit:
- [WordPress.org Plugin Page](https://wordpress.org/plugins/theme-translation-for-polylang/)

## License

GPL2 - Same as the original plugin

See LICENSE file for details.

## Credits

- Original plugin by marcinkazmierski
- Repository maintained by Ojārs Kapteinis
- Co-developed with Claude AI assistance

## Support

For official plugin support, visit the [WordPress.org support forum](https://wordpress.org/support/plugin/theme-translation-for-polylang/).

For issues specific to this fork, use the GitHub issue tracker.

## Note

This plugin is an **extension for Polylang** and requires Polylang to be installed and activated. Without Polylang, this plugin will not function.

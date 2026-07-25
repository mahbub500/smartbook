# SmartBook

**SmartBook** is a modern WordPress plugin for organizing book collections — built for individuals, schools, offices, and book enthusiasts who want a smarter way to catalog, track, and manage their books directly from the WordPress admin.

It's built as a clean, scalable, production-ready plugin following modern WordPress development standards: modular architecture, SOLID principles, and dependency injection, rather than the monolithic style common in most WordPress plugins.

## Features

- 📖 Manage your entire book collection as a dedicated custom post type, with genre, author, publisher, series, language, shelf, and collection taxonomies
- 🏷️ Generate and print QR code labels for every book
- 📱 Scan a QR code or barcode to instantly pull up a book's details
- 🔍 Advanced search, sorting, and filtering across the books list
- 📊 A dashboard with reading statistics and at-a-glance analytics
- 👥 Borrow and return management, with due-date reminders
- 📥 Import and export your catalog as CSV
- 🔐 Per-book capabilities, so multi-author sites can scope who can manage which books

## Requirements

- WordPress 6.4+
- PHP 8.2+
- [Composer](https://getcomposer.org/) (to install runtime dependencies)

## Installation

1. Copy (or clone) this plugin into `wp-content/plugins/smartbook`.
2. Install PHP dependencies:

   ```bash
   composer install --no-dev
   ```

3. Activate **SmartBook** from the WordPress admin's Plugins screen.

## Development

This plugin follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) (WPCS), enforced via PHP_CodeSniffer.

```bash
composer install          # installs dev dependencies (PHPCS, WPCS, PHPCompatibility)
composer run lint         # check coding standards
composer run lint:fix     # auto-fix what can be auto-fixed
```

### Architecture

SmartBook is organized under `app/` using PSR-4 autoloading, with each concern in its own namespace:

- `Core/` — the service container, plugin bootstrap, activation/deactivation lifecycle
- `PostTypes/` and `Taxonomies/` — the `sb_book` post type and its taxonomies
- `MetaBoxes/` — the book-details, QR code, and barcode meta boxes
- `Admin/` — admin pages (books list, dashboard, statistics, import/export, settings, label printing)
- `Services/` — QR code and barcode generation/management, logging
- `Frontend/` — public-facing book content display
- `Settings/` — plugin settings storage

Each module is wired up through a small dependency-injection container (`Core\Container\Container`) via service providers, rather than relying on global state.

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Contributors

- [mahbub500](https://github.com/mahbub500)
- [alkesh7](https://github.com/alkesh7)

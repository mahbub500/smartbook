=== SmartBook ===
Contributors: mahbub500, alkesh7
Tags: books, library, catalog, qr-code, barcode
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize your book collection in WordPress with QR/barcode labels, borrow tracking, and reading statistics.

== Description ==

SmartBook is a modular, production-ready WordPress plugin for individuals, schools, offices, and book enthusiasts who want a smarter way to organize their book collections, built on modern WordPress development standards: SOLID principles, PSR-4 autoloading, and dependency injection.

**Features:**

* Manage your entire book collection as a dedicated custom post type
* Organize books by genre, author, publisher, series, language, shelf, and collection
* Generate and print QR code labels for every book
* Scan a QR code or barcode to instantly view a book's details
* Advanced search, sorting, and filtering across your catalog
* A dashboard with reading statistics and analytics
* Borrow and return management, with due-date reminders
* Import and export your catalog as CSV
* Per-book capabilities, so multi-author sites can scope who manages which books

**Under the hood:**

* PHP 8.2+
* Modern OOP architecture following SOLID principles
* PSR-4 autoloading via Composer
* Dependency injection container
* WordPress Coding Standards compliant
* Secure, modular architecture — every input sanitized, every output escaped, every mutating action nonce- and capability-checked

This project is under active development. Feedback and feature suggestions are welcome.

== Installation ==

1. Upload the `smartbook` folder to `/wp-content/plugins/`, or install the plugin zip through the WordPress admin's "Add New Plugin" screen.
2. Run `composer install --no-dev` inside the plugin directory if you installed from source (not required for a packaged release zip).
3. Activate the plugin through the "Plugins" screen in WordPress.
4. A new "SmartBook" menu will appear in your admin sidebar.

== Frequently Asked Questions ==

= Do I need Composer to use this plugin? =

Only if you install from the plugin's source repository. A packaged release zip ships with all dependencies already bundled.

= Can I restrict which books a user can manage? =

Yes. SmartBook registers its own set of book-management capabilities, so you can grant a role permission to manage only their own books, or every book on the site.

= Does scanning a QR code or barcode require an app? =

No. Any camera-based QR/barcode scanner (including a phone camera) or a USB barcode scanner acting as a keyboard works, since SmartBook's scan fields are plain text inputs.

== Screenshots ==

1. Books catalog with cover, status, and quick actions.
2. Dashboard with reading statistics.
3. QR and barcode label printing.

== Changelog ==

= 1.1.0 =
* Security: Fixed a book-record capability check gap in CSV import that could let a user with limited "edit books" access overwrite any post on the site.
* Security: Scoped book listing, CSV export, and label printing to a user's own books when they lack the "edit others' books" capability.
* Security: Added nonce protection to the label-printing flow, validated bulk-edit taxonomy terms against real terms, and hardened CSV upload file-type validation.
* Update: Confirmed compatibility with WordPress 7.0.
* Update: General WordPress Coding Standards cleanup across the codebase.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Includes several security hardening fixes (CSV import, book listing/export, label printing) — upgrading is recommended for all sites.

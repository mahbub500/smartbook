<?php
/**
 * Uninstall handler for SmartBook.
 *
 * Fired by WordPress when the plugin is deleted from the Plugins screen
 * (never on simple deactivation). Must be safe to run standalone.
 *
 * @package SmartBook
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$sb_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $sb_autoloader ) ) {
	require_once $sb_autoloader;

	\SmartBook\Core\Uninstaller::uninstall();

	return;
}

// Fallback cleanup if the Composer autoloader is unavailable.
delete_option( 'sb_options' );
delete_option( 'sb_db_version' );

<?php
/**
 * The SmartBook settings page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\Core\Contracts\Hookable;
use SmartBook\Settings\Settings;

/**
 * Registers the Settings API sections/fields for the plugin's options
 * and renders the settings form. Persistence, sanitization, and the
 * nonce/capability check on save are all handled by options.php via
 * register_setting() (see Settings\SettingsServiceProvider); this class
 * only supplies the field markup and the page shell.
 */
final class SettingsPage implements Hookable {

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'sb_settings';

	/**
	 * Settings section id.
	 */
	private const SECTION_ID = 'sb_settings_general';

	/**
	 * @param Settings $settings Settings repository, used to read current values for the form.
	 */
	public function __construct( private readonly Settings $settings ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Register the Settings API section and fields.
	 */
	public function register_fields(): void {
		add_settings_section(
			self::SECTION_ID,
			__( 'General', 'smartbook' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'sb_enable_logging',
			__( 'Enable Logging', 'smartbook' ),
			array( $this, 'render_enable_logging_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID
		);

		add_settings_field(
			'sb_log_level',
			__( 'Log Level', 'smartbook' ),
			array( $this, 'render_log_level_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID
		);
	}

	/**
	 * Render the "Enable Logging" checkbox.
	 */
	public function render_enable_logging_field(): void {
		$value = (bool) $this->settings->get( 'enable_logging', true );

		printf(
			'<label><input type="checkbox" name="%1$s[enable_logging]" value="1" %2$s /> %3$s</label>',
			esc_attr( Settings::option_name() ),
			checked( true, $value, false ),
			esc_html__( 'Write plugin log entries to disk.', 'smartbook' )
		);
	}

	/**
	 * Render the "Log Level" select.
	 */
	public function render_log_level_field(): void {
		$value  = (string) $this->settings->get( 'log_level', 'error' );
		$levels = array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' );

		printf( '<select name="%s[log_level]">', esc_attr( Settings::option_name() ) );

		foreach ( $levels as $level ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $level ),
				selected( $value, $level, false ),
				esc_html( ucfirst( $level ) )
			);
		}

		echo '</select>';
		printf( '<p class="description">%s</p>', esc_html__( 'Only entries at or above this severity are written to the log file.', 'smartbook' ) );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'SmartBook Settings', 'smartbook' ) );

		echo '<form method="post" action="options.php">';
		settings_fields( Settings::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';

		echo '</div>';
	}
}

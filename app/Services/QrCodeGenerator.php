<?php
/**
 * QR code SVG generator.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRCodeOutputException;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Thin wrapper around chillerlan/php-qrcode configured for this
 * plugin's needs: SVG output (no GD/Imagick dependency, scales
 * perfectly for print), and "H" (30%) error correction, the level
 * recommended for codes that will be printed on physical labels and
 * may pick up smudges or scratches.
 */
final class QrCodeGenerator {

	/**
	 * Render a QR code encoding $data as raw SVG markup.
	 *
	 * @param string $data Data to encode.
	 *
	 * @throws QRCodeOutputException If rendering fails.
	 */
	public function generate_svg( string $data ): string {
		$qrcode = new QRCode( $this->options() );

		return (string) $qrcode->render( $data );
	}

	/**
	 * Render a QR code encoding $data as raw SVG markup, also writing it
	 * to $file_path.
	 *
	 * @param string $data      Data to encode.
	 * @param string $file_path Absolute path to write the SVG file to.
	 *
	 * @throws QRCodeOutputException If rendering or writing the file fails.
	 */
	public function generate_svg_file( string $data, string $file_path ): string {
		$qrcode = new QRCode( $this->options() );

		return (string) $qrcode->render( $data, $file_path );
	}

	/**
	 * Shared QRCode rendering options.
	 */
	private function options(): QROptions {
		return new QROptions(
			array(
				'outputInterface' => QRMarkupSVG::class,
				'eccLevel'        => EccLevel::H,
				'addQuietzone'    => true,
				'quietzoneSize'   => 4,
				'outputBase64'    => false,
			)
		);
	}
}

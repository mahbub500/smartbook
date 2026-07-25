<?php
/**
 * Code128 barcode SVG generator.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Picqer\Barcode\BarcodeGenerator as PicqerBarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\Exceptions\BarcodeException;

/**
 * Thin wrapper around picqer/php-barcode-generator configured for this
 * plugin's needs: Code128 (auto subset A/B/C selection, the general-
 * purpose choice for alphanumeric values) rendered as SVG, which scales
 * perfectly for print without a GD/Imagick dependency.
 */
final class BarcodeGenerator {

	/**
	 * Render $value as a Code128 barcode, as raw SVG markup.
	 *
	 * @param string $value Value to encode.
	 *
	 * @throws BarcodeException If the value contains characters Code128 cannot encode.
	 */
	public function generate_svg( string $value ): string {
		$generator = new BarcodeGeneratorSVG();

		return $generator->getBarcode( $value, PicqerBarcodeGenerator::TYPE_CODE_128, 2, 60 );
	}
}

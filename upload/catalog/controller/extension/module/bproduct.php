<?php
/**
 * Quick Product Manager (bproduct) - storefront barcode.
 * Called from catalog/controller/product/product.php through the OCMOD in install.xml.
 */
class ControllerExtensionModuleBproduct extends Controller {
	// Code128 bar patterns, values 0-105 (1 = bar, 0 = space)
	private $patterns = array(
		'11011001100',
		'11001101100',
		'11001100110',
		'10010011000',
		'10010001100',
		'10001001100',
		'10011001000',
		'10011000100',
		'10001100100',
		'11001001000',
		'11001000100',
		'11000100100',
		'10110011100',
		'10011011100',
		'10011001110',
		'10111001100',
		'10011101100',
		'10011100110',
		'11001110010',
		'11001011100',
		'11001001110',
		'11011100100',
		'11001110100',
		'11101101110',
		'11101001100',
		'11100101100',
		'11100100110',
		'11101100100',
		'11100110100',
		'11100110010',
		'11011011000',
		'11011000110',
		'11000110110',
		'10100011000',
		'10001011000',
		'10001000110',
		'10110001000',
		'10001101000',
		'10001100010',
		'11010001000',
		'11000101000',
		'11000100010',
		'10110111000',
		'10110001110',
		'10001101110',
		'10111011000',
		'10111000110',
		'10001110110',
		'11101110110',
		'11010001110',
		'11000101110',
		'11011101000',
		'11011100010',
		'11011101110',
		'11101011000',
		'11101000110',
		'11100010110',
		'11101101000',
		'11101100010',
		'11100011010',
		'11101111010',
		'11001000010',
		'11110001010',
		'10100110000',
		'10100001100',
		'10010110000',
		'10010000110',
		'10000101100',
		'10000100110',
		'10110010000',
		'10110000100',
		'10011010000',
		'10011000010',
		'10000110100',
		'10000110010',
		'11000010010',
		'11001010000',
		'11110111010',
		'11000010100',
		'10001111010',
		'10100111100',
		'10010111100',
		'10010011110',
		'10111100100',
		'10011110100',
		'10011110010',
		'11110100100',
		'11110010100',
		'11110010010',
		'11011011110',
		'11011110110',
		'11110110110',
		'10101111000',
		'10100011110',
		'10001011110',
		'10111101000',
		'10111100010',
		'11110101000',
		'11110100010',
		'10111011110',
		'10111101110',
		'11101011110',
		'11110101110',
		'11010000100',
		'11010010000',
		'11010011100'
	);

	public function barcode($product_info = array()) {
		if (!is_array($product_info) || !$this->config->get('module_bproduct_status') || !$this->config->get('module_bproduct_barcode_status') || !$this->config->get('module_bproduct_barcode_front')) {
			return '';
		}

		$source = $this->config->get('module_bproduct_barcode_source') == 'sku' ? 'sku' : 'model';

		if (empty($product_info[$source])) {
			return '';
		}

		$text = preg_replace('/[^\x20-\x7e]/', '', html_entity_decode($product_info[$source], ENT_QUOTES, 'UTF-8'));

		if (trim($text) === '') {
			return '';
		}

		return $this->svg($text);
	}

	private function svg($text) {
		$sum = 104;
		$bits = $this->patterns[104];

		for ($i = 0, $length = strlen($text); $i < $length; $i++) {
			$value = ord($text[$i]) - 32;
			$bits .= $this->patterns[$value];
			$sum += $value * ($i + 1);
		}

		$bits .= $this->patterns[$sum % 103] . '1100011101011';

		$width = strlen($bits) + 20;
		$rects = '';

		for ($i = 0, $total = strlen($bits); $i < $total;) {
			$j = $i;

			while ($j < $total && $bits[$j] == $bits[$i]) {
				$j++;
			}

			if ($bits[$i] == '1') {
				$rects .= '<rect x="' . ($i + 10) . '" y="0" width="' . ($j - $i) . '" height="60"/>';
			}

			$i = $j;
		}

		$label = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

		return '<svg class="bp-barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' 92" width="' . ($width * 2) . '" height="92" style="max-width:100%;height:auto;background:#fff" role="img" aria-label="' . $label . '"><rect width="' . $width . '" height="92" fill="#fff"/><g fill="#000">' . $rects . '</g><text x="' . ($width / 2) . '" y="82" text-anchor="middle" font-family="monospace" font-size="12" fill="#000">' . $label . '</text></svg>';
	}
}

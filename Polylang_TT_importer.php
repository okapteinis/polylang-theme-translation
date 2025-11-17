<?php
defined('ABSPATH') or die('No script kiddies please!');

class Polylang_TT_importer {

	/**
	 * Sanitize CSV cell to prevent CSV injection attacks.
	 *
	 * @param string $cell CSV cell content
	 * @return string Sanitized cell content
	 */
	protected function sanitize_csv_cell($cell) {
		// Security: Prevent CSV injection by checking if cell starts with dangerous characters
		if (preg_match('/^[=+\-@|%]/', $cell)) {
			// Prepend with single quote to prevent formula execution
			$cell = "'" . $cell;
		}
		return $cell;
	}

	/**
	 * Import translations from CSV file.
	 *
	 * @param string $fileName Path to CSV file
	 *
	 * @return int Number of translations imported
	 */
	public function import($fileName) {
		$counter = 0;
		$rows = 0;

		// Error handling: check if file exists and is readable
		if (!file_exists($fileName) || !is_readable($fileName)) {
			return 0;
		}

		if (PLL() instanceof PLL_Settings) {
			// Error handling: check if file can be opened
			$file = @fopen($fileName, "r");
			if ($file === false) {
				return 0;
			}

			$languages = PLL()->model->get_languages_list();
			$pllMos = [];
			foreach ($languages as $language) {
				$pllMos[$language->locale] = new PLL_MO();
				$pllMos[$language->locale]->import_from_db($language);
			}
			$header = [];
			while (($row = fgetcsv($file)) !== FALSE) {
				if ($rows === 0) { // header
					$header = $row;
				}
				else {
					/** @var PLL_Language $language */
					foreach ($languages as $key => $language) {
						if (isset($header[$key + 2]) && strpos($header[$key + 2], $language->locale) !== FALSE) {
							$original = isset($row[0]) ? $row[0] : '';
							$translation = isset($row[$key + 2]) ? $row[$key + 2] : '';

							if (!empty($translation)) {
								// Security: Apply CSV injection protection
								$translation = $this->sanitize_csv_cell($translation);

								// Apply custom sanitization filter
								$translation = apply_filters('tt_pll_sanitize_string_translation', $translation, $original, $language->slug);

								$pllMos[$language->locale]->add_entry($pllMos[$language->locale]->make_entry($original, $translation));
							}
							$counter++;
						}
					}
				}
				$rows++;
			}

			// Error handling: close file handle
			fclose($file);

			foreach ($languages as $language) {
				$pllMos[$language->locale]->export_to_db($language);
			}
		}

		return $counter;
	}

}

<?php
defined('ABSPATH') or die('No script kiddies please!');

class Polylang_TT_importer {

	/**
	 * @param $fileName
	 *
	 * @return int
	 */
	public function import($fileName) {
		$counter = 0;
		$rows = 0;
		if (PLL() instanceof PLL_Settings) {
			$file = fopen($fileName, "r");
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
							$original = $row[0];
							$translation = $row[$key + 2] ?? '';
							if (!empty($translation)) {
								$translation = apply_filters('tt_pll_sanitize_string_translation', $translation, $original, $language->slug);
								$pllMos[$language->locale]->add_entry($pllMos[$language->locale]->make_entry($original, $translation));
							}
							$counter++;
						}
					}
				}
				$rows++;
			}

			foreach ($languages as $language) {
				$pllMos[$language->locale]->export_to_db($language);
			}
		}

		return $counter;
	}

}

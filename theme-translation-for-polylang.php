<?php
defined('ABSPATH') or die('No script kiddies please!');

include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'theme-and-plugin-support.php';
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'polylang-tt-access.php';
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Polylang_TT_importer.php';
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Polylang_TT_exporter.php';
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Polylang_TT_theme.php';
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Polylang_Theme_Translation_Settings.php';
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Polylang_Theme_Translation_Translator.php';

/**
 * Class Polylang_Theme_Translation.
 */
class Polylang_Theme_Translation {

	const SETTINGS_OPTION = 'custom_pll_settings';

	const SETTINGS_FORCE_TRANSLATE_ADMIN_DASHBOARD = 'custom_pll_settings_force_translate_admin_dashboard'; // 0 - none

	const VALUE_DEFAULT_POLYLANG_LANG = 1; // Translate admin dashboard to default polylang language,

	const VALUE_SELECTED_SLUG_LANG = 2; // Translate admin dashboard to default current user language

	const VALUE_DEFAULT_USER_PROFILE_LANG = 3; // Translate admin dashboard to default current user language

	const CONTEXT_PREFIX = 'TTfP: ';

	protected $plugin_path;

	protected $files_extensions = [
		'php',
		'inc',
		'twig',
	];

	const EXCLUDE_PLUGINS = [
		'polylang',
		'theme-translation-for-polylang',
		'polylang-theme-translation',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_path = __DIR__;
	}

	/**
	 * Run plugin.
	 */
	public function run() {
		$this->run_theme_scanner();
		$this->run_plugin_scanner();
		$this->run_core_scanner();
	}

	public function run_core_scanner() {
		$data = [];
		$settings = Polylang_Theme_Translation_Settings::getInstance();
		if (in_array('default', $settings['domains'])) {
			$data['WordPress: Admin'] = $this->register_stings_from_dir(ABSPATH . DIRECTORY_SEPARATOR . 'wp-admin', 'WordPress: Admin');
			$data['WordPress: Core'] = $this->register_stings_from_dir(ABSPATH . DIRECTORY_SEPARATOR . 'wp-includes', 'WordPress: Core');
		}

		return $data;
	}

	/**
	 * Find strings in themes.
	 *
	 * @return array
	 */
	public function run_theme_scanner() {
		$data = [];
		$themes = wp_get_themes();

		$settings = Polylang_Theme_Translation_Settings::getInstance();

		foreach ($themes as $name => $theme) {
			if (in_array($name, $settings['themes'])) {
				$data[$name] = $this->register_stings_from_dir($theme->get_theme_root() . DIRECTORY_SEPARATOR . $name, $name);
			}
		}

		return $data;
	}

	/**
	 * Find strings in plugins.
	 *
	 * @return array
	 */
	public function run_plugin_scanner() {
		$plugins = wp_get_active_and_valid_plugins();
		if (is_multisite()) {
			$plugins = array_merge($plugins, wp_get_active_network_plugins());
		}
		$data = [];

		$settings = Polylang_Theme_Translation_Settings::getInstance();

		foreach ($plugins as $plugin) {
			$pluginDir = dirname($plugin);
			$pluginName = pathinfo($plugin, PATHINFO_FILENAME);
			if (in_array($pluginName, $settings['plugins'])) {
				if (!in_array($pluginName, self::EXCLUDE_PLUGINS) && $pluginDir !== WP_PLUGIN_DIR) {
					$data[$pluginName] = $this->register_stings_from_dir($pluginDir, $pluginName);
				}
			}
		}

		return $data;
	}

	/**
	 * Register strings from dir in polylang engine using cache.
	 *
	 * @param string $path Directory path to scan
	 * @param string $name Name of the theme/plugin/domain
	 *
	 * @return array Array of strings found
	 */
	protected function register_stings_from_dir($path, $name) {
		//todo: https://wordpress.org/support/topic/allow-custom-directory-to-scan-theme/#post-16736078
		$cacheKey = sprintf("ttfp_cache_strings_from:%s:%s", $name, md5($path));
		$cacheVal = get_transient($cacheKey);
		if (is_array($cacheVal)) {
			$strings = $cacheVal;
		}
		else {
			$files = $this->get_files_from_dir($path);
			$strings = $this->file_scanner($files);

			// Performance: cache for 1 day instead of 1 minute (customizable via filter)
			$cache_duration = apply_filters('ttfp_cache_duration', DAY_IN_SECONDS);
			set_transient($cacheKey, $strings, $cache_duration);
		}
		$this->add_to_polylang_register($strings, $name);
		return $strings;
	}

	/**
	 * Get files from dictionary recursive.
	 *
	 * @param string $dir_name Directory path to scan
	 * @param int $max_depth Maximum recursion depth (default: 10)
	 * @param int $current_depth Current recursion depth
	 * @return array Array of file paths
	 */
	protected function get_files_from_dir($dir_name, $max_depth = 10, $current_depth = 0) {
		$results = [];

		// Security: prevent infinite recursion
		if ($current_depth > $max_depth) {
			return $results;
		}

		// Security: check directory is readable
		if (!is_readable($dir_name)) {
			return $results;
		}

		// Security: excluded directories to prevent scanning sensitive/large directories
		$excluded_dirs = ['.git', 'vendor', 'node_modules', '.svn', '.hg', 'CVS', 'dist', 'build', 'tests'];

		$files = @scandir($dir_name);
		if ($files === false) {
			return $results;
		}

		foreach ($files as $key => $value) {
			// Skip current and parent directory references
			if ($value === '.' || $value === '..') {
				continue;
			}

			// Security: skip excluded directories
			if (in_array($value, $excluded_dirs, true)) {
				continue;
			}

			$path = realpath($dir_name . DIRECTORY_SEPARATOR . $value);

			// Security: verify path is valid and within allowed directory
			if ($path === false || strpos($path, realpath($dir_name)) !== 0) {
				continue;
			}

			if (!is_dir($path)) {
				$path_parts = pathinfo($path);
				if (!empty($path_parts['extension']) && in_array($path_parts['extension'], $this->files_extensions)) {
					$results[] = $path;
				}
			}
			else {
				$temp = $this->get_files_from_dir($path, $max_depth, $current_depth + 1);
				$results = array_merge($results, $temp);
			}
		}
		return $results;
	}

	/**
	 *  Get strings from polylang methods.
	 */
	public function file_scanner($files) {
		$singleQuoteTempPattern = "\'";
		$singleQuoteTempReg = '[\single_quote]';

		$doubleQuoteTempPattern = '\"';
		$doubleQuoteTempReg = '[\double_quote]';

		$strings = [];
		foreach ($files as $file) {
			$content = file_get_contents($file);
			$content = str_replace($singleQuoteTempPattern, $singleQuoteTempReg, $content);
			$content = str_replace($doubleQuoteTempPattern, $doubleQuoteTempReg, $content);
			// find polylang functions
			preg_match_all("/[\s=\(\.]+pll_[_e][\s]*\([\s]*[\'\"](.*?)[\'\"][\s]*\)/s", $content, $matches);
			if (!empty($matches[1])) {
				$strings = array_merge($strings, $matches[1]);
			}

			// find wp functions: __(), _e(), _x()
			preg_match_all("/[\s=\(\.]+_[_ex][\s]*\([\s]*[\'](.*?)[\'][\s]*[,]*[\s]*[\']*(.*?)[\']*[\s]*\)/s", $content, $matches);
			if (!empty($matches[1])) {
				$strings = array_merge($strings, $matches[1]);
			}
			preg_match_all("/[\s=\(\.]+_[_ex][\s]*\([\s]*[\"](.*?)[\"][\s]*[,]*[\s]*[\"]*(.*?)[\"]*[\s]*\)/s", $content, $matches);
			if (!empty($matches[1])) {
				$strings = array_merge($strings, $matches[1]);
			}

			// find wp functions: esc_html_e, esc_html, esc_html__, esc_attr, esc_attr_e, esc_attr__
			preg_match_all("/[\s=\(\.]+(esc_html|esc_attr)[_e]*[\s]*\([\s]*[\'](.*?)[\'][\s]*[,]*[\s]*[\']*(.*?)[\']*[\s]*\)/s", $content, $matches);
			if (!empty($matches[2])) {
				$strings = array_merge($strings, $matches[2]);
			}
			preg_match_all("/[\s=\(\.]+(esc_html|esc_attr)[_e]*[\s]*\([\s]*[\"](.*?)[\"][\s]*[,]*[\s]*[\"]*(.*?)[\"]*[\s]*\)/s", $content, $matches);
			if (!empty($matches[2])) {
				$strings = array_merge($strings, $matches[2]);
			}

			// find wp functions: _n: single + plural
			preg_match_all("/[\s=\(\.]+_n[\s]*\([\s]*[\'\"](.*?)[\'\"][\s]*,[\s]*[\'\"](.*?)[\'\"][\s]*,(.*?)\)/s", $content, $matches);
			if (!empty($matches[1])) {
				$strings = array_merge($strings, $matches[1]);
				$strings = array_merge($strings, $matches[2]);
			}
		}

		foreach ($strings as $key => $value) {
			// inverse quotes:
			$value = str_replace($singleQuoteTempReg, '\'', $value);
			$value = str_replace($doubleQuoteTempReg, "\"", $value);

			$strings[$key] = $value;
		}
		return $strings;
	}

	/**
	 * Add strings to polylang register.
	 */
	protected function add_to_polylang_register($strings, $context) {
		if (!empty($strings)) {
			foreach ($strings as $string) {
				pll_register_string($string, $string, self::CONTEXT_PREFIX . $context);
			}
		}
	}

}

/**
 * Init Polylang Theme Translation plugin.
 */
add_action('init', 'process_polylang_theme_translation');

function process_polylang_theme_translation() {
	if (Polylang_TT_access::get_instance()->is_polylang_page()) {
		if (Polylang_TT_access::get_instance()->check_plugin_access()
			&& apply_filters('ttfp_translation_access', current_user_can('manage_options'))) {
			$plugin_obj = new Polylang_Theme_Translation();
			$plugin_obj->run();
		}
	}
	if (is_admin()) {
		// plugin_action_links
		add_filter('plugin_action_links_' . plugin_basename(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'polylang-theme-translation.php'), 'tt_pll_add_action_links');
	}

}

function tt_pll_add_action_links($actions) {
	$actions[] = '<a href="' . esc_url(get_admin_url(NULL, 'admin.php?page=mlang_import_export_strings')) . '">TTfP Settings</a>';
	return $actions;
}

add_action('wp_loaded', 'process_polylang_theme_translation_wp_loaded');
function process_polylang_theme_translation_wp_loaded() {
	global $pagenow;

	if (current_user_can('manage_options') && $pagenow === 'admin.php') {
		if (isset($_POST['export_strings']) && (int) $_POST['export_strings'] === 1) {
			// CSRF protection: verify nonce
			check_admin_referer('export_strings', '_wpnonce_export_strings');
			$translation = new Polylang_Theme_Translation();
			$exporter = new Polylang_TT_exporter($translation);
			$exporter->export();
		}

		if (isset($_POST["action_import_strings"])) {
			// CSRF protection: verify nonce
			check_admin_referer('import_strings', '_wpnonce_import_strings');

			if (PLL() instanceof PLL_Settings) {
				// File upload security: check for upload errors
				if (!isset($_FILES["import_strings"]) || $_FILES["import_strings"]["error"] !== UPLOAD_ERR_OK) {
					wp_redirect((add_query_arg('_msg', 'translations-import-error', wp_get_referer())));
					exit;
				}

				// File upload security: validate file extension
				$file_name = isset($_FILES["import_strings"]["name"]) ? $_FILES["import_strings"]["name"] : '';
				$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
				if ($file_ext !== 'csv') {
					wp_redirect((add_query_arg('_msg', 'translations-import-error-invalid-type', wp_get_referer())));
					exit;
				}

				// File upload security: enforce file size limit (5MB)
				$max_size = 5 * 1024 * 1024; // 5MB
				if ($_FILES["import_strings"]["size"] > $max_size) {
					wp_redirect((add_query_arg('_msg', 'translations-import-error-size', wp_get_referer())));
					exit;
				}

				// File upload security: validate MIME type
				$fileName = $_FILES["import_strings"]["tmp_name"];
				if (function_exists('mime_content_type')) {
					$mime_type = mime_content_type($fileName);
					$allowed_mimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
					if (!in_array($mime_type, $allowed_mimes, true)) {
						wp_redirect((add_query_arg('_msg', 'translations-import-error-invalid-type', wp_get_referer())));
						exit;
					}
				}

				if ($_FILES["import_strings"]["size"] > 0 && $fileName) {
					$importer = new Polylang_TT_importer();
					$counter = $importer->import($fileName);

					if ($counter > 0) {
						wp_redirect((add_query_arg([
							'_msg' => 'translations-imported',
							'items' => $counter,
						], wp_get_referer())));
						exit;
					}
				}
			}
			wp_redirect((add_query_arg('_msg', 'translations-import-error', wp_get_referer())));
			exit;
		}


		if (isset($_POST['action_settings'])) {
			// CSRF protection: verify nonce
			check_admin_referer('settings', '_wpnonce_settings');

			$settings = [
				'themes' => [],
				'plugins' => [],
				'domains' => [],
				'additional_domains' => [],
			];
			$t = $_POST['themes'] ?? [];
			foreach ($t as $item) {
				$item = sanitize_text_field(strip_tags($item));
				if (in_array($item, pll_get_themes())) {
					$settings['themes'][] = $item;
					$textdomain = pll_get_theme_textdomain($item);
					if ($textdomain !== $item) {
						$settings['additional_domains'][] = $textdomain;
					}
				}
			}

			$t = $_POST['plugins'] ?? [];
			foreach ($t as $item) {
				$item = sanitize_text_field(strip_tags($item));
				if (in_array($item, pll_get_plugins())) {
					$settings['plugins'][] = $item;
					$textdomain = pll_get_plugin_textdomain($item);
					if ($textdomain !== $item) {
						$settings['additional_domains'][] = $textdomain;
					}
				}
			}

			$t = $_POST['domains'] ?? [];
			foreach ($t as $item) {
				$item = sanitize_text_field(strip_tags($item));
				if (in_array($item, pll_get_domains())) {
					$settings['domains'][] = $item;
				}
			}

			if (!add_option(Polylang_Theme_Translation::SETTINGS_OPTION, $settings)) {
				update_option(Polylang_Theme_Translation::SETTINGS_OPTION, $settings);
			}

			// others settings:
			$forceTrans = $_POST['force_translate_admin'] ?? 0;
			if (!add_option(Polylang_Theme_Translation::SETTINGS_FORCE_TRANSLATE_ADMIN_DASHBOARD, (int) $forceTrans)) {
				update_option(Polylang_Theme_Translation::SETTINGS_FORCE_TRANSLATE_ADMIN_DASHBOARD, (int) $forceTrans);
			}

			wp_redirect((add_query_arg(['_msg' => 'settings-saved'], wp_get_referer())));
			exit;
		}
	}
}

add_filter('pll_settings_tabs', 'import_export_strings');
function import_export_strings(array $tabs) {
	$tabs['import_export_strings'] = __("TTfP Settings", 'polylang-tt');
	return $tabs;
}

add_action('pll_settings_active_tab_import_export_strings', 'custom_pll_settings_active_tab_import_export_strings', 10, 0);
function custom_pll_settings_active_tab_import_export_strings() {
	$settings = Polylang_Theme_Translation_Settings::getInstance();

	$data = [
		'settings' => $settings,
		'force_translate_admin' => (int) get_option(Polylang_Theme_Translation::SETTINGS_FORCE_TRANSLATE_ADMIN_DASHBOARD),
		'themes' => pll_get_themes(),
		'plugins' => pll_get_plugins(),
		'domains' => pll_get_domains(),
		'items' => (int) ($_GET['items'] ?? 0),
		'msg' => sanitize_text_field(isset($_GET['_msg']) ? strip_tags($_GET['_msg']) : ''),
	];

	print Polylang_TT_theme::includeTemplates('admin-import-export-page', $data);
}

add_action("pll_language_defined", "pll_language_defined_tt_for_polylang", 99, 2);
function pll_language_defined_tt_for_polylang($slug, $lang) {
	if ($lang instanceof PLL_Language) {
		$translator = new Polylang_Theme_Translation_Translator($lang);
	}
	load_plugin_textdomain('polylang-tt', FALSE, basename(__DIR__) . '/languages');
}

add_filter("pll_admin_current_language", "pll_admin_current_language_tt_for_polylang", 99, 2);

/**
 * @param PLL_Language|false|null $curlang
 * @param PLL_Admin_Base $admin
 *
 * @return false|PLL_Language
 */
function pll_admin_current_language_tt_for_polylang($curlang, $admin) {
	if (is_admin()) {
		$forceTranslateAdmin = (int) get_option(Polylang_Theme_Translation::SETTINGS_FORCE_TRANSLATE_ADMIN_DASHBOARD, 0);
		if ($forceTranslateAdmin == Polylang_Theme_Translation::VALUE_DEFAULT_POLYLANG_LANG) {
			$lang = pll_default_language();
			return PLL()->model->get_language($lang);
		}

		if ($forceTranslateAdmin == Polylang_Theme_Translation::VALUE_SELECTED_SLUG_LANG) {
			$slug = get_user_meta(get_current_user_id(), 'pll_filter_content', TRUE); // filter_lang
			return PLL()->model->get_language($slug);
		}

		if ($forceTranslateAdmin == Polylang_Theme_Translation::VALUE_DEFAULT_USER_PROFILE_LANG) {
			$user_locale = get_user_meta(get_current_user_id(), 'locale', TRUE);
			return PLL()->model->get_language($user_locale);
		}
	}
	return $curlang;
}

// ClassicPress compatibility: Only register WordPress 6.5+ filter if supported
// The wp_plugin_dependencies_slug filter was introduced in WordPress 6.5 and is not available in ClassicPress
if (!function_exists('classicpress_version') && version_compare($GLOBALS['wp_version'], '6.5', '>=')) {
	add_filter('wp_plugin_dependencies_slug', 'convert_pll_to_polylang_pro');
}

function convert_pll_to_polylang_pro($slug) {
	if ('polylang' === $slug) {
		if (is_plugin_active('polylang-pro/polylang-pro.php') || is_plugin_active('polylang-pro/polylang.php')) {
			// Return the slug for 'polylang-pro'.
			return 'polylang-pro';
		}
	}
	return $slug;
}

add_filter('rest_pre_dispatch', 'tt_pll_set_language_rest', 999, 3);

/**
 * Load current language for "Multilingual Contact Form 7 with Polylang" plugin
 * in translate_cf7_messages.
 *
 * @param $result
 * @param $server
 * @param $request
 *
 * @return mixed
 */
function tt_pll_set_language_rest($result, $server, $request) {
	$locale = $request->get_param('_wpcf7_locale');
	if (!empty($locale) && is_string($locale)) {
		$language = PLL()->model->get_language($locale);
		if ($language) {
			PLL()->curlang = $language;
			do_action('pll_language_defined', PLL()->curlang->slug, PLL()->curlang);
		}
	}

	return $result;
}

/**
 * Clear TTfP translation cache.
 *
 * This function removes all cached translation strings to force
 * a fresh scan of themes and plugins.
 */
function ttfp_clear_cache() {
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_ttfp_cache_strings_from:%',
			'_transient_timeout_ttfp_cache_strings_from:%'
		)
	);
}

// Performance: Clear cache when themes are switched
add_action('switch_theme', 'ttfp_clear_cache');

// Performance: Clear cache when plugins are activated/deactivated
add_action('activated_plugin', 'ttfp_clear_cache');
add_action('deactivated_plugin', 'ttfp_clear_cache');

// Performance: Clear cache when plugins are updated
add_action('upgrader_process_complete', 'ttfp_clear_cache_on_update', 10, 2);
function ttfp_clear_cache_on_update($upgrader_object, $options) {
	if ($options['action'] === 'update' && ($options['type'] === 'plugin' || $options['type'] === 'theme')) {
		ttfp_clear_cache();
	}
}

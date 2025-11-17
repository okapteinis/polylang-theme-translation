<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Class Polylang_TT_access.
 */
class Polylang_TT_access
{

    protected $plugin_name = 'Theme and plugin translation for Polylang';

    /** @var Polylang_TT_access|null */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct()
    {
    }

    /**
     * Singleton.
     * @return Polylang_TT_access
     */
    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new Polylang_TT_access();
        }
        return self::$instance;
    }

    /**
     * Check plugin access.
     * @return bool - if plugin have access to work.
     */
    public function check_plugin_access()
    {
        if (!version_compare(phpversion(), '7.0', '>=')) {
            add_action('admin_notices', array($this, 'error_php_version'));
            return false;
        } else if (!defined('POLYLANG_VERSION')) {
            add_action('admin_notices', array($this, 'error_polylang_disable'));
            return false;
        } else {
            return true;
        }
    }

    /**
     * Display PHP version error.
     */
    public function error_php_version()
    {
        $class = "error";
        $message = 'The minimum supported PHP version is 7.0 (Current is: ' . phpversion() . ').';
        printf(
            '<div class="%s"><p>%s: %s</p></div>',
            esc_attr($class),
            esc_html($this->plugin_name),
            esc_html($message)
        );
    }

    /**
     * Display Polylang dependency error.
     */
    public function error_polylang_disable()
    {
        $class = "error";
        $message = sprintf(
            /* translators: %s: link to Polylang plugin */
            __('Please, download and activate %s plugin.', 'polylang-tt'),
            '<a href="https://wordpress.org/plugins/polylang/">Polylang</a>'
        );
        printf(
            '<div class="%s"><p>%s: %s</p></div>',
            esc_attr($class),
            esc_html($this->plugin_name),
            wp_kses_post($message)
        );
    }

    /**
     * Check polylang string settings page.
     * @return bool
     */
    public function is_polylang_page()
    {
        global $pagenow;

        if (is_admin() && isset($_GET['page']) && !empty($pagenow)) {
            // Security: sanitize GET parameters
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

            if ($pagenow === 'options-general.php' && $page === 'mlang' && $tab === 'strings') {
                // wp-admin/options-general.php?page=mlang&tab=strings
                return true;
            } elseif ($pagenow === 'admin.php' && $page === 'mlang_strings') {
                // wp-admin/admin.php?page=mlang_strings
                return true;
            }
        }
        return false;
    }
}
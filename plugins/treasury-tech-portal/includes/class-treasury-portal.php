<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Treasury_Tech_Portal {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('treasury_portal', array($this, 'shortcode_handler'));
    }

    public function enqueue_assets() {
        $plugin_url = TTP_PLUGIN_URL;
        wp_enqueue_style('treasury-portal-css', $plugin_url . 'assets/css/treasury-portal.css', array(), '1.0');
        wp_enqueue_script('treasury-portal-js', $plugin_url . 'assets/js/treasury-portal.js', array(), '1.0', true);
    }

    public function shortcode_handler($atts = array(), $content = null) {
        $this->enqueue_assets();
        ob_start();
        include plugin_dir_path(__FILE__) . 'shortcode.php';
        return ob_get_clean();
    }
}

<?php
/**
 * Plugin Name: Smart Notice Banner
 * Description: Lightweight customizable announcement banner with a dismiss button and shortcode.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Mahmud
 * License: GPL-2.0-or-later
 * Text Domain: smart-notice-banner
 */

if (!defined('ABSPATH')) { exit; }

final class SNB_Smart_Notice_Banner {
    const OPTION = 'snb_options';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_body_open', array(__CLASS__, 'render_auto_banner'));
        add_shortcode('smart_notice_banner', array(__CLASS__, 'shortcode'));
    }

    public static function defaults() {
        return array(
            'enabled' => '1',
            'auto_display' => '1',
            'message' => 'Welcome to our website! Check out our latest updates.',
            'button_text' => 'Learn more',
            'button_url' => home_url('/'),
            'background' => '#173b79',
            'text_color' => '#ffffff',
            'dismissible' => '1',
        );
    }

    public static function options() {
        $saved = get_option(self::OPTION, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), self::defaults());
    }

    public static function sanitize($input) {
        $input = is_array($input) ? $input : array();
        $defaults = self::defaults();
        return array(
            'enabled' => !empty($input['enabled']) ? '1' : '0',
            'auto_display' => !empty($input['auto_display']) ? '1' : '0',
            'message' => sanitize_text_field($input['message'] ?? ''),
            'button_text' => sanitize_text_field($input['button_text'] ?? ''),
            'button_url' => esc_url_raw($input['button_url'] ?? ''),
            'background' => sanitize_hex_color($input['background'] ?? '') ?: $defaults['background'],
            'text_color' => sanitize_hex_color($input['text_color'] ?? '') ?: $defaults['text_color'],
            'dismissible' => !empty($input['dismissible']) ? '1' : '0',
        );
    }

    public static function register_settings() {
        register_setting('snb_group', self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default' => self::defaults(),
        ));
    }

    public static function admin_menu() {
        add_options_page('Smart Notice Banner', 'Smart Notice Banner', 'manage_options', 'smart-notice-banner', array(__CLASS__, 'settings_page'));
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $o = self::options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Smart Notice Banner', 'smart-notice-banner'); ?></h1>
            <p><?php esc_html_e('Configure a lightweight announcement bar. Use [smart_notice_banner] for manual placement.', 'smart-notice-banner'); ?></p>
            <form action="options.php" method="post">
                <?php settings_fields('snb_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Enable banner</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($o['enabled'], '1'); ?>> Show banner on the site</label></td></tr>
                    <tr><th scope="row">Automatic placement</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto_display]" value="1" <?php checked($o['auto_display'], '1'); ?>> Display at top of site using wp_body_open</label><p class="description">Requires theme support for wp_body_open. Turn off when using the shortcode to avoid duplicates.</p></td></tr>
                    <tr><th scope="row"><label for="snb-message">Banner message</label></th><td><input id="snb-message" class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[message]" value="<?php echo esc_attr($o['message']); ?>"></td></tr>
                    <tr><th scope="row"><label for="snb-button">Button text</label></th><td><input id="snb-button" type="text" name="<?php echo esc_attr(self::OPTION); ?>[button_text]" value="<?php echo esc_attr($o['button_text']); ?>"></td></tr>
                    <tr><th scope="row"><label for="snb-url">Button URL</label></th><td><input id="snb-url" class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION); ?>[button_url]" value="<?php echo esc_attr($o['button_url']); ?>"></td></tr>
                    <tr><th scope="row"><label for="snb-bg">Background color</label></th><td><input id="snb-bg" type="color" name="<?php echo esc_attr(self::OPTION); ?>[background]" value="<?php echo esc_attr($o['background']); ?>"></td></tr>
                    <tr><th scope="row"><label for="snb-text">Text color</label></th><td><input id="snb-text" type="color" name="<?php echo esc_attr(self::OPTION); ?>[text_color]" value="<?php echo esc_attr($o['text_color']); ?>"></td></tr>
                    <tr><th scope="row">Dismiss button</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[dismissible]" value="1" <?php checked($o['dismissible'], '1'); ?>> Allow visitors to close the banner</label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function enqueue_assets() {
        if (is_admin()) { return; }
        wp_enqueue_style('snb-style', plugins_url('assets/banner.css', __FILE__), array(), '1.0.0');
        wp_enqueue_script('snb-script', plugins_url('assets/banner.js', __FILE__), array(), '1.0.0', true);
    }

    public static function render_auto_banner() {
        $o = self::options();
        if ($o['auto_display'] === '1') { echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    public static function shortcode() {
        return self::markup();
    }

    private static function markup() {
        $o = self::options();
        if ($o['enabled'] !== '1' || $o['message'] === '') { return ''; }
        $style = '--snb-bg:' . esc_attr($o['background']) . ';--snb-color:' . esc_attr($o['text_color']) . ';';
        $html = '<div class="snb-banner" role="region" aria-label="Website announcement" style="' . $style . '">';
        $html .= '<span class="snb-message">' . esc_html($o['message']) . '</span>';
        if ($o['button_text'] !== '' && $o['button_url'] !== '') {
            $html .= ' <a class="snb-link" href="' . esc_url($o['button_url']) . '">' . esc_html($o['button_text']) . '</a>';
        }
        if ($o['dismissible'] === '1') {
            $html .= '<button type="button" class="snb-close" aria-label="Close announcement">&times;</button>';
        }
        return $html . '</div>';
    }
}

SNB_Smart_Notice_Banner::init();

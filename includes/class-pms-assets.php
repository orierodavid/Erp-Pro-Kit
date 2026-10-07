<?php
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
    }

    /** Only load on our own admin pages — never globally, to avoid clashing with other plugins. */
    public function enqueue_admin(string $hook): void
    {
        if (strpos($hook, 'pms-') === false) {
            return;
        }

        wp_enqueue_style(
            'pms-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
            [],
            null
        );
        wp_enqueue_style('pms-admin', PMS_PLUGIN_URL . 'admin/css/admin.css', ['pms-poppins'], PMS_VERSION);
        wp_add_inline_style('pms-admin', $this->dynamic_color_css());

        wp_enqueue_script('pms-admin', PMS_PLUGIN_URL . 'admin/js/admin.js', ['wp-api-fetch'], PMS_VERSION, true);

        wp_localize_script('pms-admin', 'PMS', [
            'restUrl' => esc_url_raw(rest_url('pms/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        // Only the Settings screen needs the color picker widget itself.
        if (strpos($hook, 'pms-settings') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_add_inline_script(
                'wp-color-picker',
                "jQuery(function ($) { $('.pms-color-picker').wpColorPicker(); });"
            );
        }
    }

    /** Only enqueue on pages that actually contain one of our shortcodes. */
    public function enqueue_frontend(): void
    {
        global $post;

        if (! $post || (! has_shortcode($post->post_content, 'pms_dashboard') && ! has_shortcode($post->post_content, 'pms_login'))) {
            return;
        }

        wp_enqueue_style(
            'pms-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
            [],
            null
        );
        wp_enqueue_style('pms-admin', PMS_PLUGIN_URL . 'admin/css/admin.css', ['dashicons', 'pms-poppins'], PMS_VERSION);
        wp_add_inline_style('pms-admin', $this->dynamic_color_css());

        wp_enqueue_script('pms-admin', PMS_PLUGIN_URL . 'admin/js/admin.js', ['wp-api-fetch'], PMS_VERSION, true);

        wp_localize_script('pms-admin', 'PMS', [
            'restUrl' => esc_url_raw(rest_url('pms/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Builds a small CSS override block from the site owner's chosen
     * primary color, layered on top of admin.css's default pink. This
     * is what makes the plugin genuinely white-label — every company
     * running this plugin can have its own brand color without
     * touching a single CSS file.
     */
    private function dynamic_color_css(): string
    {
        $primary = sanitize_hex_color(get_option('pms_primary_color', '#2563eb')) ?: '#2563eb';
        $secondary = sanitize_hex_color(get_option('pms_secondary_color', '#0f172a')) ?: '#0f172a';
        $primary_dark = PMS_Settings::darken($primary, 0.18);
        $secondary_dark = PMS_Settings::darken($secondary, 0.18);
        $primary_rgb = PMS_Settings::to_rgb($primary);
        $secondary_rgb = PMS_Settings::to_rgb($secondary);

        return ".pms-shell, .pms-wrap {
            --pms-primary: {$primary};
            --pms-primary-dark: {$primary_dark};
            --pms-primary-soft: rgba({$primary_rgb['r']}, {$primary_rgb['g']}, {$primary_rgb['b']}, 0.12);
            --pms-secondary: {$secondary};
            --pms-secondary-dark: {$secondary_dark};
            --pms-secondary-soft: rgba({$secondary_rgb['r']}, {$secondary_rgb['g']}, {$secondary_rgb['b']}, 0.10);
            --pms-glow: 0 8px 22px rgba({$primary_rgb['r']}, {$primary_rgb['g']}, {$primary_rgb['b']}, 0.22);
        }";
    }
}

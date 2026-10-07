<?php
/**
 * Keeps ERP users inside the ERP application area.
 *
 * WordPress remains the authentication layer, but ordinary ERP users do not
 * get access to the WordPress administration dashboard. WordPress
 * Administrators/Super Admins retain normal wp-admin access.
 */
if (! defined('ABSPATH')) {
    exit;
}

class PMS_WP_Access
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'restrict_wp_admin']);
    }

    /**
     * A real WordPress administrator may use wp-admin normally.
     * On multisite, Super Admin is the highest-level WordPress administrator.
     * On single-site WordPress, manage_options identifies the Administrator.
     */
    public static function can_access_wp_dashboard(): bool
    {
        if (is_multisite() && is_super_admin()) {
            return true;
        }

        return current_user_can('manage_options');
    }

    /**
     * Identify users who belong to the ERP permission system.
     */
    public static function is_erp_user(): bool
    {
        if (! is_user_logged_in() || self::can_access_wp_dashboard()) {
            return false;
        }

        if (! class_exists('PMS_Roles')) {
            return false;
        }

        foreach (array_keys(PMS_Roles::capability_labels()) as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Allow the ERP's own admin.php pages, while preventing ERP users from
     * opening unrelated WordPress administration screens.
     *
     * Internal AJAX/admin-post/media upload requests are allowed because
     * ERP features such as the company-logo picker depend on WordPress
     * infrastructure behind the scenes. This does not expose the WP
     * Dashboard or normal admin screens.
     */
    public function restrict_wp_admin(): void
    {
        if (! self::is_erp_user()) {
            return;
        }

        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        $script = isset($_SERVER['SCRIPT_NAME']) ? wp_unslash($_SERVER['SCRIPT_NAME']) : '';
        $script = '/' . ltrim((string) $script, '/');

        if (
            $script === '/wp-admin/admin-post.php' ||
            $script === '/wp-admin/async-upload.php' ||
            $script === '/wp-admin/media-upload.php'
        ) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($script === '/wp-admin/admin.php' && strpos($page, 'pms-') === 0) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=pms-dashboard'));
        exit;
    }
}

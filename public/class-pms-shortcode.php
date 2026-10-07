<?php
/**
 * Two shortcodes that together let Staff use this entirely from the
 * public-facing site, with no wp-admin access needed at all:
 *
 * [pms_login]     — a styled login form. Redirects back to whatever
 *                    page it's placed on (or a custom redirect=X attr)
 *                    once logged in, so it works naturally chained with
 *                    [pms_dashboard] on the same page.
 * [pms_dashboard]  — tasks + clock in/out. If the visitor isn't logged
 *                    in yet, it renders the SAME login form inline
 *                    instead of a dead-end message, so a single
 *                    [pms_dashboard] shortcode is enough on its own.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Shortcode
{
    public function __construct()
    {
        add_shortcode('pms_login', [$this, 'render_login']);
        add_shortcode('pms_dashboard', [$this, 'render_dashboard']);
        add_action('wp_login_failed', [$this, 'redirect_back_on_failed_login']);
    }

    /**
     * wp_login_form() posts to wp-login.php directly, so a failed login
     * would otherwise land the visitor on WordPress's own bare login
     * error page instead of back on our branded page. This sends them
     * back to wherever they came from, with a flag our view uses to
     * show a proper error message.
     */
    public function redirect_back_on_failed_login(): void
    {
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';

        if (! $redirect_to) {
            return;
        }

        wp_safe_redirect(add_query_arg('login', 'failed', $redirect_to));
        exit;
    }

    public function render_login(array $atts = []): string
    {
        if (is_user_logged_in()) {
            return '<p class="pms-already-in">' . esc_html__('You are already logged in.', 'pms') . '</p>';
        }

        $atts = shortcode_atts(['redirect' => ''], $atts, 'pms_login');
        $redirect = $atts['redirect'] ? esc_url_raw($atts['redirect']) : get_permalink();

        ob_start();
        include PMS_PLUGIN_DIR . 'public/views/login.php';
        return ob_get_clean();
    }

    public function render_dashboard(): string
    {
        if (! is_user_logged_in()) {
            return $this->render_login(['redirect' => get_permalink()]);
        }

        if (! current_user_can('pms_clock_in_out') && ! current_user_can('pms_view_assigned_tasks')) {
            return '<p class="pms-frontend-denied">' . esc_html__('Your account does not have access to this dashboard.', 'pms') . '</p>';
        }

        ob_start();
        include PMS_PLUGIN_DIR . 'public/views/dashboard.php';
        return ob_get_clean();
    }
}

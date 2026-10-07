<?php
/**
 * Two things, both about what happens around login:
 *
 * 1. Send PMS users (anyone with a PMS role/capability) straight to our
 *    own dashboard after logging in — not WordPress's default wp-admin
 *    Dashboard. Our dashboard already hides WP's sidebar (see
 *    PMS_Fullscreen), so this is what actually stops them from ever
 *    seeing it: the fix isn't more CSS, it's not sending them to WP's
 *    dashboard in the first place.
 *
 * 2. Re-skin wp-login.php itself to match the plugin's branding
 *    (company name, brand color) instead of the default WordPress
 *    logo/styling — without replacing the login form itself, so
 *    password resets, 2FA plugins, and core security behavior all
 *    keep working exactly as WordPress intends.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Login_Experience
{
    public function __construct()
    {
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
        add_action('login_enqueue_scripts', [$this, 'brand_login_screen']);
        add_filter('login_headerurl', [$this, 'login_logo_url']);
        add_filter('login_headertext', [$this, 'login_logo_text']);
    }

    /**
     * $user is only a valid WP_User on success — on a failed attempt it's
     * a WP_Error instead, so we leave WordPress's own error handling
     * alone and only act once login actually succeeded.
     */
    public function redirect_after_login(string $redirect_to, string $requested_redirect_to, $user): string
    {
        if (! ($user instanceof WP_User)) {
            return $redirect_to;
        }

        // Anyone with either PMS capability set goes to our dashboard.
        if (user_can($user, 'pms_manage_tasks') || user_can($user, 'pms_view_assigned_tasks')) {
            // Respect an explicit, legitimate redirect_to (e.g. they clicked
            // a direct link to a specific PMS page while logged out) —
            // only override the plain "just take me to wp-admin" default.
            if (! $requested_redirect_to || strpos($requested_redirect_to, 'wp-admin') === false || $requested_redirect_to === admin_url()) {
                return admin_url('admin.php?page=pms-dashboard');
            }
        }

        return $redirect_to;
    }

    public function login_logo_url(): string
    {
        return home_url('/');
    }

    public function login_logo_text(): string
    {
        return get_option('pms_company_name', get_bloginfo('name'));
    }

    public function brand_login_screen(): void
    {
        $primary = sanitize_hex_color(get_option('pms_primary_color', '#e91e63')) ?: '#e91e63';
        $dark = class_exists('PMS_Settings') ? PMS_Settings::darken($primary, 0.18) : '#c2185b';
        $company = esc_html(get_option('pms_company_name', get_bloginfo('name')));
        ?>
        <style>
            body.login {
                background: #f4f6fa;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            }
            body.login #login h1 a {
                background-image: none;
                width: auto;
                height: auto;
                text-indent: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                font-size: 18px;
                font-weight: 700;
                color: #1f2937;
                text-decoration: none;
            }
            body.login #login h1 a::before {
                content: "<?php echo esc_js(mb_substr($company, 0, 1)); ?>";
                display: grid;
                place-items: center;
                width: 40px;
                height: 40px;
                border-radius: 11px;
                background: linear-gradient(135deg, <?php echo esc_html($primary); ?>, <?php echo esc_html($dark); ?>);
                color: #fff;
                font-size: 16px;
            }
            body.login form {
                border-radius: 14px;
                box-shadow: 0 12px 28px -6px rgba(31,41,55,.14), 0 4px 10px -4px rgba(31,41,55,.08);
                border: 0;
                padding: 26px 24px;
            }
            body.login form .input,
            body.login input[type="text"],
            body.login input[type="password"] {
                border-radius: 9px;
                border-color: #dce0e5;
            }
            body.login form .input:focus,
            body.login input[type="text"]:focus,
            body.login input[type="password"]:focus {
                border-color: <?php echo esc_html($primary); ?>;
                box-shadow: 0 0 0 3px rgba(<?php echo implode(',', array_values(class_exists('PMS_Settings') ? PMS_Settings::to_rgb($primary) : ['r' => 233, 'g' => 30, 'b' => 99])); ?>,.14);
            }
            body.login .wp-core-ui .button-primary {
                border: 0;
                border-radius: 9px;
                background: linear-gradient(195deg, <?php echo esc_html($primary); ?>, <?php echo esc_html($dark); ?>);
                box-shadow: 0 6px 16px rgba(<?php echo implode(',', array_values(class_exists('PMS_Settings') ? PMS_Settings::to_rgb($primary) : ['r' => 233, 'g' => 30, 'b' => 99])); ?>,.28);
                text-shadow: none;
            }
            body.login #backtoblog,
            body.login #nav {
                text-align: center;
            }
            body.login #backtoblog a,
            body.login #nav a {
                color: #7b809a;
            }
        </style>
        <?php
    }
}

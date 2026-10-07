<?php
/**
 * @var string $redirect set by PMS_Shortcode::render_login()
 *
 * Deliberately uses WordPress's own wp_login_form() rather than a
 * hand-rolled <form> — that keeps password hashing, login throttling,
 * two-factor plugins, and any other security hook fully intact. We
 * only style the wrapper around it.
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="pms-wrap pms-frontend pms-login-wrap">
    <div class="pms-login-card">
        <div class="pms-login-brand">
            <span class="pms-brand-mark"><?php echo esc_html(strtoupper(substr(get_option('pms_company_name', get_bloginfo('name')), 0, 1))); ?></span>
            <strong><?php echo esc_html(get_option('pms_company_name', get_bloginfo('name'))); ?></strong>
        </div>
        <p class="pms-eyebrow" style="text-align:center;margin-bottom:18px;">
            <?php esc_html_e('Sign in to your workspace', 'pms'); ?>
        </p>

        <?php
        if (isset($_GET['login']) && $_GET['login'] === 'failed') {
            echo '<div class="pms-notice pms-notice-error">' . esc_html__('Incorrect username or password.', 'pms') . '</div>';
        }

        wp_login_form([
            'redirect'       => $redirect,
            'label_username' => __('Email or username', 'pms'),
            'label_password' => __('Password', 'pms'),
            'label_remember' => __('Keep me signed in', 'pms'),
            'label_log_in'   => __('Sign in', 'pms'),
        ]);
        ?>

        <p class="pms-login-forgot">
            <a href="<?php echo esc_url(wp_lostpassword_url($redirect)); ?>"><?php esc_html_e('Forgot your password?', 'pms'); ?></a>
        </p>
    </div>
</div>

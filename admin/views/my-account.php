<?php
/**
 * Deliberately does NOT include an email field anywhere on this page —
 * that was an explicit requirement. Email changes still go through
 * WordPress's own Profile screen (which sends a confirmation email
 * before applying the change), not here.
 */
if (! defined('ABSPATH')) {
    exit;
}

$user = wp_get_current_user();
$notice = '';
$error = '';

if (isset($_POST['pms_update_name']) && check_admin_referer('pms_update_name_action', 'pms_name_nonce')) {
    $display_name = sanitize_text_field($_POST['display_name'] ?? '');

    if ($display_name === '') {
        $error = __('Name cannot be empty.', 'pms');
    } else {
        wp_update_user(['ID' => $user->ID, 'display_name' => $display_name]);
        $notice = __('Name updated.', 'pms');
        $user = wp_get_current_user(); // refresh
    }
}

if (isset($_POST['pms_update_password']) && check_admin_referer('pms_update_password_action', 'pms_password_nonce')) {
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (! wp_check_password($current_password, $user->user_pass, $user->ID)) {
        $error = __('Your current password is incorrect.', 'pms');
    } elseif (strlen($new_password) < 8) {
        $error = __('New password must be at least 8 characters.', 'pms');
    } elseif ($new_password !== $confirm_password) {
        $error = __('New password and confirmation do not match.', 'pms');
    } else {
        wp_set_password($new_password, $user->ID);

        // wp_set_password() invalidates the current session's auth cookie
        // (since it depends on the password hash) — re-issue it immediately
        // so the person isn't unexpectedly logged out after changing their
        // own password.
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);

        $notice = __('Password updated.', 'pms');
    }
}

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Account', 'pms'); ?></p>
        <h1><?php esc_html_e('My Account', 'pms'); ?></h1>
    </div>
</div>

<?php if ($notice) : ?>
    <div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div>
<?php endif; ?>
<?php if ($error) : ?>
    <div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div>
<?php endif; ?>

<div class="pms-panel pms-form-panel">
    <h2 style="margin-top:0;"><?php esc_html_e('Name', 'pms'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('pms_update_name_action', 'pms_name_nonce'); ?>
        <div class="pms-form-row">
            <label for="display_name"><?php esc_html_e('Display name', 'pms'); ?></label>
            <input type="text" id="display_name" name="display_name" class="pms-input" required value="<?php echo esc_attr($user->display_name); ?>">
        </div>
        <button type="submit" name="pms_update_name" class="pms-btn-primary"><?php esc_html_e('Save name', 'pms'); ?></button>
    </form>
</div>

<div class="pms-panel pms-form-panel">
    <h2 style="margin-top:0;"><?php esc_html_e('Password', 'pms'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('pms_update_password_action', 'pms_password_nonce'); ?>
        <div class="pms-form-row">
            <label for="current_password"><?php esc_html_e('Current password', 'pms'); ?></label>
            <input type="password" id="current_password" name="current_password" class="pms-input" required autocomplete="current-password">
        </div>
        <div class="pms-form-grid">
            <div class="pms-form-row">
                <label for="new_password"><?php esc_html_e('New password', 'pms'); ?></label>
                <input type="password" id="new_password" name="new_password" class="pms-input" required minlength="8" autocomplete="new-password">
            </div>
            <div class="pms-form-row">
                <label for="confirm_password"><?php esc_html_e('Confirm new password', 'pms'); ?></label>
                <input type="password" id="confirm_password" name="confirm_password" class="pms-input" required minlength="8" autocomplete="new-password">
            </div>
        </div>
        <button type="submit" name="pms_update_password" class="pms-btn-primary"><?php esc_html_e('Update password', 'pms'); ?></button>
    </form>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

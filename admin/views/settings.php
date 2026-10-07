<?php
if (! defined('ABSPATH')) {
    exit;
}

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('System', 'pms'); ?></p>
        <h1><?php esc_html_e('Settings', 'pms'); ?></h1>
    </div>
</div>

<?php if (isset($_GET['settings-updated'])) : ?>
    <div class="pms-notice pms-notice-success"><?php esc_html_e('Settings saved.', 'pms'); ?></div>
<?php endif; ?>

<div class="pms-panel pms-form-panel">
    <form method="post" action="options.php">
        <?php
        settings_fields(PMS_Settings::option_group());
        do_settings_sections('pms-settings');
        submit_button(__('Save settings', 'pms'), 'pms-btn-primary-submit');
        ?>
    </form>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

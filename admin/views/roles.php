<?php
if (! defined('ABSPATH')) {
    exit;
}
include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Access control', 'pms'); ?></p>
        <h1><?php esc_html_e('Roles & Permissions', 'pms'); ?></h1>
        <p class="pms-page-description"><?php esc_html_e('Modules decide what exists in the company. Roles decide which employees can use it.', 'pms'); ?></p>
    </div>
</div>

<?php if ($notice) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>

<div class="pms-panel pms-form-panel">
    <h2><?php esc_html_e('Create custom role', 'pms'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('pms_roles_manage', 'pms_roles_nonce'); ?>
        <input type="hidden" name="pms_role_action" value="create">
        <div class="pms-form-grid">
            <label><span><?php esc_html_e('Role name', 'pms'); ?></span><input type="text" name="role_name" required placeholder="<?php esc_attr_e('e.g. Leasing Coordinator', 'pms'); ?>"></label>
        </div>
        <div class="pms-permission-grid">
            <?php foreach ($catalogue as $group => $caps) : ?>
                <div class="pms-permission-group"><h3><?php echo esc_html($group); ?></h3>
                    <?php foreach ($caps as $cap => $label) : ?>
                        <label><input type="checkbox" name="caps[]" value="<?php echo esc_attr($cap); ?>"> <span><?php echo esc_html($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="pms-btn-primary"><?php esc_html_e('Create role', 'pms'); ?></button>
    </form>
</div>

<?php foreach ($roles as $slug => $role) : ?>
<div class="pms-panel pms-form-panel" style="margin-top:20px;">
    <div class="pms-panel-heading">
        <div><h2><?php echo esc_html($role['label']); ?></h2><small><?php echo esc_html($slug); ?></small></div>
        <?php if (! $role['built_in']) : ?>
        <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Delete this custom role?', 'pms')); ?>');">
            <?php wp_nonce_field('pms_roles_manage', 'pms_roles_nonce'); ?>
            <input type="hidden" name="pms_role_action" value="delete">
            <input type="hidden" name="role_slug" value="<?php echo esc_attr($slug); ?>">
            <button type="submit" class="pms-btn-secondary"><?php esc_html_e('Delete', 'pms'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <form method="post">
        <?php wp_nonce_field('pms_roles_manage', 'pms_roles_nonce'); ?>
        <input type="hidden" name="pms_role_action" value="save">
        <input type="hidden" name="role_slug" value="<?php echo esc_attr($slug); ?>">
        <div class="pms-permission-grid">
            <?php foreach ($catalogue as $group => $caps) : ?>
                <div class="pms-permission-group"><h3><?php echo esc_html($group); ?></h3>
                    <?php foreach ($caps as $cap => $label) : ?>
                        <label><input type="checkbox" name="caps[]" value="<?php echo esc_attr($cap); ?>" <?php checked(! empty($role['caps'][$cap])); ?>> <span><?php echo esc_html($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="pms-btn-primary"><?php esc_html_e('Save permissions', 'pms'); ?></button>
    </form>
</div>
<?php endforeach; ?>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>
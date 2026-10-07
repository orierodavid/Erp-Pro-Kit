<?php
if (! defined('ABSPATH')) {
    exit;
}
include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Organization', 'pms'); ?></p>
        <h1><?php esc_html_e('Add person', 'pms'); ?></h1>
        <p class="pms-page-description"><?php esc_html_e('Create an employee directly inside the ERP workspace. No WordPress default user form is required.', 'pms'); ?></p>
    </div>
    <a href="<?php echo esc_url(admin_url('admin.php?page=pms-users')); ?>" class="pms-btn-secondary"><?php esc_html_e('Back to People', 'pms'); ?></a>
</div>

<?php if ($error) : ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>
<?php if ($success) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($success); ?></div><?php endif; ?>

<div class="pms-panel pms-form-panel">
    <form method="post">
        <?php wp_nonce_field('pms_create_person', 'pms_create_person_nonce'); ?>
        <input type="hidden" name="pms_create_person" value="1">
        <div class="pms-form-grid">
            <label><span><?php esc_html_e('First name', 'pms'); ?></span><input type="text" name="first_name" value="<?php echo esc_attr($values['first_name']); ?>" autocomplete="given-name"></label>
            <label><span><?php esc_html_e('Last name', 'pms'); ?></span><input type="text" name="last_name" value="<?php echo esc_attr($values['last_name']); ?>" autocomplete="family-name"></label>
            <label><span><?php esc_html_e('Username *', 'pms'); ?></span><input type="text" name="username" value="<?php echo esc_attr($values['username']); ?>" required autocomplete="username"></label>
            <label><span><?php esc_html_e('Email *', 'pms'); ?></span><input type="email" name="email" value="<?php echo esc_attr($values['email']); ?>" required autocomplete="email"></label>
            <label><span><?php esc_html_e('Role', 'pms'); ?></span>
                <select name="role">
                    <?php foreach ($roles as $slug => $role) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($values['role'], $slug); ?>><?php echo esc_html($role['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span><?php esc_html_e('Department', 'pms'); ?></span>
                <select name="department_id">
                    <option value=""><?php esc_html_e('No department', 'pms'); ?></option>
                    <?php foreach ($departments as $department) : ?>
                        <option value="<?php echo esc_attr($department->id); ?>" <?php selected((string) $values['department_id'], (string) $department->id); ?>><?php echo esc_html($department->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span><?php esc_html_e('Designation', 'pms'); ?></span><input type="text" name="designation" value="<?php echo esc_attr($values['designation']); ?>" placeholder="<?php esc_attr_e('e.g. Real Estate Agent', 'pms'); ?>"></label>
            <label><span><?php esc_html_e('Password', 'pms'); ?></span><input type="password" name="password" value="" autocomplete="new-password"><small><?php esc_html_e('Leave blank to generate a secure password.', 'pms'); ?></small></label>
        </div>
        <div class="pms-form-actions">
            <button type="submit" class="pms-btn-primary"><?php esc_html_e('Create person', 'pms'); ?></button>
        </div>
    </form>
</div>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>
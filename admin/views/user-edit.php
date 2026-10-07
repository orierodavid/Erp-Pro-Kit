<?php
if (! defined('ABSPATH')) {
    exit;
}

$department_id = PMS_User_Profile_Fields::department_id($user->ID);
$designation = PMS_User_Profile_Fields::designation($user->ID);
$current_role = ! empty($user->roles) ? reset($user->roles) : '';
$attendance = PMS_Modules::is_active('attendance') ? PMS_Attendance::today_for_user($user->ID) : null;

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('HR / Staff', 'pms'); ?></p>
        <h1><?php echo esc_html($user->display_name); ?></h1>
        <p class="pms-page-description"><?php esc_html_e('Manage this employee from the ERP workspace.', 'pms'); ?></p>
    </div>
    <a href="<?php echo esc_url(admin_url('admin.php?page=pms-users')); ?>" class="pms-btn-secondary"><?php esc_html_e('Back to People', 'pms'); ?></a>
</div>

<?php if ($error) : ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>
<?php if ($success) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($success); ?></div><?php endif; ?>

<div class="pms-profile-layout">
    <section class="pms-panel">
        <div class="pms-panel-heading">
            <h2><?php esc_html_e('Employee details', 'pms'); ?></h2>
        </div>
        <form method="post" class="pms-form-grid">
            <?php wp_nonce_field('pms_update_person_' . $user->ID, 'pms_update_person_nonce'); ?>
            <div class="pms-field"><label for="first_name"><?php esc_html_e('First name', 'pms'); ?></label><input id="first_name" name="first_name" type="text" value="<?php echo esc_attr($user->first_name); ?>"></div>
            <div class="pms-field"><label for="last_name"><?php esc_html_e('Last name', 'pms'); ?></label><input id="last_name" name="last_name" type="text" value="<?php echo esc_attr($user->last_name); ?>"></div>
            <div class="pms-field pms-field-full"><label for="email"><?php esc_html_e('Email', 'pms'); ?></label><input id="email" name="email" type="email" value="<?php echo esc_attr($user->user_email); ?>"></div>
            <div class="pms-field"><label for="designation"><?php esc_html_e('Designation', 'pms'); ?></label><input id="designation" name="designation" type="text" value="<?php echo esc_attr($designation); ?>" placeholder="<?php esc_attr_e('e.g. Property Manager', 'pms'); ?>"></div>
            <div class="pms-field"><label for="department_id"><?php esc_html_e('Department', 'pms'); ?></label><select id="department_id" name="department_id"><option value=""><?php esc_html_e('No department', 'pms'); ?></option><?php foreach ($departments as $department) : ?><option value="<?php echo esc_attr($department->id); ?>" <?php selected($department_id, $department->id); ?>><?php echo esc_html($department->name); ?></option><?php endforeach; ?></select></div>
            <div class="pms-field"><label for="role"><?php esc_html_e('ERP role', 'pms'); ?></label><select id="role" name="role"><?php foreach ($roles as $slug => $role) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($current_role, $slug); ?>><?php echo esc_html($role['label']); ?></option><?php endforeach; ?></select></div>
            <div class="pms-field"><label for="password"><?php esc_html_e('New password', 'pms'); ?></label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password"><small><?php esc_html_e('Leave blank to keep the current password.', 'pms'); ?></small></div>
            <div class="pms-form-actions pms-field-full"><button type="submit" name="pms_update_person" class="pms-btn-primary"><?php esc_html_e('Save employee', 'pms'); ?></button></div>
        </form>
    </section>

    <aside class="pms-panel pms-profile-summary">
        <div class="pms-avatar-large"><?php echo get_avatar($user->ID, 72); ?></div>
        <h2><?php echo esc_html($user->display_name); ?></h2>
        <p><?php echo esc_html($designation ?: PMS_Roles::role_label($current_role)); ?></p>
        <div class="pms-profile-meta">
            <span><?php esc_html_e('Username', 'pms'); ?></span><strong><?php echo esc_html($user->user_login); ?></strong>
            <span><?php esc_html_e('Role', 'pms'); ?></span><strong><?php echo esc_html(PMS_Roles::role_label($current_role)); ?></strong>
            <span><?php esc_html_e('Attendance today', 'pms'); ?></span><strong><?php echo esc_html($attendance ? PMS_Constants::label_for(PMS_Constants::attendance_statuses(), $attendance->status) : __('Not available', 'pms')); ?></strong>
        </div>
    </aside>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

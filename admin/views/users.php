<?php
if (! defined('ABSPATH')) {
    exit;
}

$people = PMS_Roles::get_pms_people();
$admin_count = count(array_filter($people, fn ($p) => PMS_Roles::user_is_pms_admin($p)));
$staff_count = count($people) - $admin_count;

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Organization', 'pms'); ?></p>
        <h1><?php esc_html_e('People', 'pms'); ?></h1>
    </div>
    <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="pms-btn-primary">
        <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add new person', 'pms'); ?>
    </a>
</div>

<div class="pms-kpi-grid">
    <div class="pms-kpi">
        <span class="pms-kpi-label"><?php esc_html_e('Total people', 'pms'); ?></span>
        <strong><?php echo esc_html(count($people)); ?></strong>
    </div>
    <div class="pms-kpi">
        <span class="pms-kpi-label"><?php esc_html_e('Admins', 'pms'); ?></span>
        <strong><?php echo esc_html($admin_count); ?></strong>
    </div>
    <div class="pms-kpi">
        <span class="pms-kpi-label"><?php esc_html_e('Staff', 'pms'); ?></span>
        <strong><?php echo esc_html($staff_count); ?></strong>
    </div>
</div>

<p class="pms-hint">
    <?php esc_html_e('Roles are assigned from the standard WordPress Users screen.', 'pms'); ?>
    <a href="<?php echo esc_url(admin_url('users.php')); ?>"><?php esc_html_e('Manage roles →', 'pms'); ?></a>
</p>

<div class="pms-panel">
    <table class="pms-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'pms'); ?></th>
                <th><?php esc_html_e('Designation', 'pms'); ?></th>
                <th><?php esc_html_e('Department', 'pms'); ?></th>
                <th><?php esc_html_e('Role', 'pms'); ?></th>
                <th><?php esc_html_e('Open tasks', 'pms'); ?></th>
                <th><?php esc_html_e('Last activity', 'pms'); ?></th>
                <th><?php esc_html_e('Status', 'pms'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($people)) : ?>
            <tr><td colspan="7" class="pms-empty"><?php esc_html_e('No one has a PMS role yet.', 'pms'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($people as $person) :
            $open_tasks = PMS_DB::open_task_count_for_user($person->ID);
            $last_activity = PMS_DB::last_task_activity_for_user($person->ID);
            $working_now = PMS_DB::has_task_in_progress($person->ID);
            $designation = PMS_User_Profile_Fields::designation($person->ID);
            ?>
            <tr>
                <td>
                    <div class="pms-person-cell">
                        <?php echo get_avatar($person->ID, 28); ?>
                        <div>
                            <div><a href="<?php echo esc_url(get_edit_user_link($person->ID)); ?>"><?php echo esc_html($person->display_name); ?></a></div>
                            <small style="color:var(--pms-muted);"><?php echo esc_html($person->user_email); ?></small>
                        </div>
                    </div>
                </td>
                <td><?php echo esc_html($designation ?: '—'); ?></td>
                <td><?php echo esc_html(PMS_User_Profile_Fields::department_name($person->ID)); ?></td>
                <td>
                    <span class="pms-chip <?php echo PMS_Roles::user_is_pms_admin($person) ? 'pms-role-admin' : 'pms-role-staff'; ?>">
                        <?php echo esc_html(PMS_Roles::user_is_pms_admin($person) ? __('Admin', 'pms') : __('Staff', 'pms')); ?>
                    </span>
                </td>
                <td><?php echo esc_html($open_tasks); ?></td>
                <td><?php echo esc_html($last_activity ? human_time_diff(strtotime($last_activity->started_at), current_time('timestamp')) . ' ' . __('ago', 'pms') : '—'); ?></td>
                <td>
                    <span class="pms-chip <?php echo $working_now ? 'pms-status-in_progress' : 'pms-status-todo'; ?>">
                        <?php echo esc_html($working_now ? __('Working', 'pms') : __('Idle', 'pms')); ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

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
    <a href="<?php echo esc_url(admin_url('admin.php?page=pms-user-new')); ?>" class="pms-btn-primary">
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
    <?php esc_html_e('Employees, departments and roles are managed from this ERP workspace.', 'pms'); ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=pms-roles')); ?>"><?php esc_html_e('Roles & Permissions →', 'pms'); ?></a>
</p>

<div class="pms-toolbar pms-panel">
    <div class="pms-search"><span class="dashicons dashicons-search"></span><input type="search" data-pms-people-search placeholder="<?php esc_attr_e('Search people, role, department or designation…', 'pms'); ?>"></div>
    <a href="<?php echo esc_url(admin_url('admin.php?page=pms-roles')); ?>" class="pms-btn-secondary"><?php esc_html_e('Manage access', 'pms'); ?></a>
</div>

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
            $attendance_today = PMS_Modules::is_active('attendance') ? PMS_Attendance::today_for_user($person->ID) : null;
            $working_now = $attendance_today ? $attendance_today->status === 'clocked_in' : PMS_DB::has_task_in_progress($person->ID);
            $person_role = ! empty($person->roles) ? reset($person->roles) : '';
            $person_role_label = $person_role ? PMS_Roles::role_label($person_role) : __('No role', 'pms');
            $designation = PMS_User_Profile_Fields::designation($person->ID);
            ?>
            <tr data-pms-person-row>
                <td>
                    <div class="pms-person-cell">
                        <?php echo get_avatar($person->ID, 28); ?>
                        <div>
                            <div><a href="<?php echo esc_url(admin_url('admin.php?page=pms-user-edit&user_id=' . (int) $person->ID)); ?>"><?php echo esc_html($person->display_name); ?></a></div>
                            <small style="color:var(--pms-muted);"><?php echo esc_html($person->user_email); ?></small>
                        </div>
                    </div>
                </td>
                <td><?php echo esc_html($designation ?: '—'); ?></td>
                <td><?php echo esc_html(PMS_User_Profile_Fields::department_name($person->ID)); ?></td>
                <td>
                    <span class="pms-chip <?php echo PMS_Roles::user_is_pms_admin($person) ? 'pms-role-admin' : 'pms-role-staff'; ?>">
                        <?php echo esc_html($person_role_label); ?>
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

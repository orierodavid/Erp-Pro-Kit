<?php
/** @var bool $is_admin passed in from PMS_Admin_Menu::render_dashboard() */
if (! defined('ABSPATH')) {
    exit;
}

$task_breakdown = PMS_DB::task_status_breakdown();
$my_tasks = PMS_DB::get_tasks_for_user(get_current_user_id());
$working_now = PMS_DB::has_task_in_progress(get_current_user_id());
$attendance = PMS_Modules::is_active('attendance') && current_user_can('pms_clock_in_out')
    ? PMS_Attendance::today_for_user(get_current_user_id())
    : null;
$attendance_minutes = $attendance ? PMS_Attendance::duration_minutes($attendance) : null;

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Workspace', 'pms'); ?></p>
        <h1><?php echo esc_html(sprintf(__('Welcome back, %s', 'pms'), wp_get_current_user()->display_name)); ?></h1>
    </div>
    <span class="pms-header-date">
        <span class="pms-date-dot"></span>
        <?php echo esc_html(date_i18n(get_option('date_format'))); ?>
    </span>
</div>

<?php if ($is_admin) : ?>
    <div class="pms-kpi-grid">
        <?php foreach (PMS_Constants::task_statuses() as $key => $label) : ?>
            <div class="pms-kpi">
                <span class="pms-kpi-label"><?php echo esc_html($label); ?></span>
                <strong><?php echo esc_html($task_breakdown[$key] ?? 0); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="pms-kpi-grid">
        <div class="pms-kpi">
            <span class="pms-kpi-label"><?php esc_html_e('My tasks', 'pms'); ?></span>
            <strong><?php echo esc_html(count($my_tasks)); ?></strong>
        </div>
        <div class="pms-kpi">
            <span class="pms-kpi-label"><?php esc_html_e('Right now', 'pms'); ?></span>
            <strong><?php echo esc_html($working_now ? __('Working', 'pms') : __('Idle', 'pms')); ?></strong>
        </div>
    </div>

    <?php if ($attendance !== null || (PMS_Modules::is_active('attendance') && current_user_can('pms_clock_in_out'))) : ?>
        <div class="pms-panel pms-attendance-card">
            <div class="pms-panel-heading">
                <h2><?php esc_html_e("Today's attendance", 'pms'); ?></h2>
                <span class="pms-chip"><?php echo $attendance && $attendance->status === 'clocked_in' ? esc_html__('Clocked in', 'pms') : esc_html__('Not clocked in', 'pms'); ?></span>
            </div>
            <div class="pms-attendance-summary">
                <div>
                    <span class="pms-kpi-label"><?php esc_html_e('Clock in', 'pms'); ?></span>
                    <strong><?php echo $attendance && $attendance->clock_in ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_in))) : '—'; ?></strong>
                </div>
                <div>
                    <span class="pms-kpi-label"><?php esc_html_e('Clock out', 'pms'); ?></span>
                    <strong><?php echo $attendance && $attendance->clock_out ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_out))) : '—'; ?></strong>
                </div>
                <div>
                    <span class="pms-kpi-label"><?php esc_html_e('Duration', 'pms'); ?></span>
                    <strong><?php echo $attendance_minutes !== null ? esc_html(sprintf(__('%d min', 'pms'), $attendance_minutes)) : '—'; ?></strong>
                </div>
            </div>
            <div class="pms-form-actions">
                <?php if ($attendance && $attendance->status === 'clocked_in') : ?>
                    <button type="button" class="pms-btn-primary pms-attendance-btn" data-attendance-action="clock-out"><?php esc_html_e('Clock out', 'pms'); ?></button>
                <?php elseif (! $attendance || $attendance->status !== 'clocked_out') : ?>
                    <button type="button" class="pms-btn-primary pms-attendance-btn" data-attendance-action="clock-in"><?php esc_html_e('Clock in', 'pms'); ?></button>
                <?php endif; ?>
                <span class="pms-attendance-note" aria-live="polite"></span>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="pms-panel">
    <div class="pms-panel-heading">
        <h2><?php echo $is_admin ? esc_html__('Recent tasks', 'pms') : esc_html__('My tasks', 'pms'); ?></h2>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . ($is_admin ? 'pms-tasks' : 'pms-my-tasks'))); ?>"><?php esc_html_e('View all →', 'pms'); ?></a>
    </div>
    <table class="pms-table">
        <tbody>
        <?php
        $rows = $is_admin ? array_slice(PMS_DB::get_all_tasks(), 0, 6) : array_slice($my_tasks, 0, 6);
        if (empty($rows)) :
            ?>
            <tr><td class="pms-empty"><?php esc_html_e('Nothing here yet.', 'pms'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $task) : ?>
            <tr>
                <td><?php echo esc_html($task->title); ?></td>
                <td><span class="pms-chip pms-status-<?php echo esc_attr($task->status); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_statuses(), $task->status)); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

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
        <?php
        $attendance_is_live = $attendance && $attendance->status === 'clocked_in';
        $workday_start = get_option('pms_workday_start', '08:00');
        $workday_end = get_option('pms_workday_end', '17:00');
        $workday_minutes = max(1, (int) ((strtotime($workday_end) - strtotime($workday_start)) / 60));
        $attendance_progress = $attendance_is_live && $attendance_minutes !== null
            ? min(100, max(0, ($attendance_minutes / $workday_minutes) * 100))
            : 0;
        ?>
        <section class="pms-attendance-card pms-attendance-command" aria-labelledby="pms-attendance-title">
            <div class="pms-attendance-command-top">
                <div>
                    <div class="pms-attendance-overline">
                        <span class="pms-live-dot <?php echo $attendance_is_live ? 'is-live' : ''; ?>"></span>
                        <span><?php echo $attendance_is_live ? esc_html__('Live workday', 'pms') : esc_html__('Workday status', 'pms'); ?></span>
                    </div>
                    <h2 id="pms-attendance-title"><?php esc_html_e("Today's attendance", 'pms'); ?></h2>
                    <p><?php echo $attendance_is_live ? esc_html__('You are currently on the clock.', 'pms') : esc_html__('Your presence, shift and time are ready when you are.', 'pms'); ?></p>
                </div>
                <span class="pms-attendance-status <?php echo $attendance_is_live ? 'is-live' : ''; ?>">
                    <span><?php echo $attendance_is_live ? esc_html__('ON DUTY', 'pms') : esc_html__('OFF DUTY', 'pms'); ?></span>
                </span>
            </div>

            <div class="pms-attendance-command-grid">
                <div class="pms-attendance-ring-wrap">
                    <div class="pms-attendance-ring" style="--pms-attendance-progress: <?php echo esc_attr($attendance_progress); ?>%;">
                        <div class="pms-attendance-ring-core">
                            <span><?php echo $attendance_minutes !== null ? esc_html($attendance_minutes) : '0'; ?></span>
                            <small><?php esc_html_e('MINUTES', 'pms'); ?></small>
                        </div>
                    </div>
                    <span class="pms-attendance-ring-label"><?php echo esc_html(sprintf(__('%d%% of shift', 'pms'), round($attendance_progress))); ?></span>
                </div>

                <div class="pms-attendance-metrics">
                    <div class="pms-attendance-metric">
                        <span><?php esc_html_e('Started', 'pms'); ?></span>
                        <strong><?php echo $attendance && $attendance->clock_in ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_in))) : '—'; ?></strong>
                    </div>
                    <div class="pms-attendance-metric">
                        <span><?php esc_html_e('Finished', 'pms'); ?></span>
                        <strong><?php echo $attendance && $attendance->clock_out ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_out))) : '—'; ?></strong>
                    </div>
                    <div class="pms-attendance-metric">
                        <span><?php esc_html_e('Shift', 'pms'); ?></span>
                        <strong><?php echo esc_html($workday_start . ' – ' . $workday_end); ?></strong>
                    </div>
                    <div class="pms-attendance-metric">
                        <span><?php esc_html_e('Status', 'pms'); ?></span>
                        <strong><?php echo $attendance ? esc_html(ucwords(str_replace('_', ' ', $attendance->status))) : esc_html__('Not started', 'pms'); ?></strong>
                    </div>
                </div>

                <div class="pms-attendance-action">
                    <div class="pms-attendance-action-copy">
                        <span><?php esc_html_e('Today', 'pms'); ?></span>
                        <strong><?php echo $attendance_is_live ? esc_html__('Your shift is active', 'pms') : esc_html__('Ready to start', 'pms'); ?></strong>
                    </div>
                    <?php if ($attendance_is_live) : ?>
                        <button type="button" class="pms-attendance-primary pms-attendance-btn" data-attendance-action="clock-out">
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Clock out', 'pms'); ?>
                        </button>
                    <?php elseif (! $attendance || $attendance->status !== 'clocked_out') : ?>
                        <button type="button" class="pms-attendance-primary pms-attendance-btn" data-attendance-action="clock-in">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Start workday', 'pms'); ?>
                        </button>
                    <?php endif; ?>
                    <span class="pms-attendance-note" aria-live="polite"></span>
                </div>
            </div>
        </section>
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

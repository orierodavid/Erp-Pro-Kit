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
            <div class="pms-attendance-hero">
                <div class="pms-attendance-hero-copy">
                    <div class="pms-attendance-overline">
                        <span class="pms-live-dot <?php echo $attendance_is_live ? 'is-live' : ''; ?>"></span>
                        <span><?php echo $attendance_is_live ? esc_html__('Live workday', 'pms') : esc_html__('Workday control', 'pms'); ?></span>
                    </div>
                    <h2 id="pms-attendance-title"><?php esc_html_e("Today's attendance", 'pms'); ?></h2>
                    <p><?php echo $attendance_is_live ? esc_html__('Your workday is active. Time is being tracked live.', 'pms') : esc_html__('Start your workday when you are ready. Your attendance stays independent from tasks.', 'pms'); ?></p>
                </div>
                <div class="pms-attendance-hero-status <?php echo $attendance_is_live ? 'is-live' : ''; ?>">
                    <span class="pms-status-orb"></span>
                    <div>
                        <small><?php esc_html_e('Current status', 'pms'); ?></small>
                        <strong><?php echo $attendance_is_live ? esc_html__('On duty', 'pms') : esc_html__('Off duty', 'pms'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="pms-attendance-stage">
                <div class="pms-attendance-time-card">
                    <div class="pms-attendance-time-glow"></div>
                    <div class="pms-attendance-ring" style="--pms-attendance-progress: <?php echo esc_attr($attendance_progress); ?>%;">
                        <div class="pms-attendance-ring-core">
                            <span><?php echo $attendance_minutes !== null ? esc_html($attendance_minutes) : '0'; ?></span>
                            <small><?php esc_html_e('MINUTES', 'pms'); ?></small>
                        </div>
                    </div>
                    <strong class="pms-attendance-time-caption"><?php echo esc_html(sprintf(__('%d%% of shift', 'pms'), round($attendance_progress))); ?></strong>
                    <span class="pms-attendance-time-state"><?php echo $attendance_is_live ? esc_html__('Tracking your workday', 'pms') : esc_html__('Ready when you are', 'pms'); ?></span>
                </div>

                <div class="pms-attendance-details">
                    <div class="pms-attendance-shift-head">
                        <div>
                            <span><?php esc_html_e('Today’s shift', 'pms'); ?></span>
                            <strong><?php echo esc_html($workday_start . ' – ' . $workday_end); ?></strong>
                        </div>
                        <span class="pms-attendance-badge"><?php echo $attendance ? esc_html(ucwords(str_replace('_', ' ', $attendance->status))) : esc_html__('Not started', 'pms'); ?></span>
                    </div>

                    <div class="pms-attendance-timeline">
                        <div class="pms-attendance-track"><span style="width: <?php echo esc_attr($attendance_progress); ?>%;"></span></div>
                        <div class="pms-attendance-timepoint">
                            <div><span class="pms-timepoint-dot"></span><small><?php esc_html_e('Start', 'pms'); ?></small></div>
                            <strong><?php echo $attendance && $attendance->clock_in ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_in))) : '—'; ?></strong>
                        </div>
                        <div class="pms-attendance-timepoint is-end">
                            <div><span class="pms-timepoint-dot"></span><small><?php esc_html_e('Finish', 'pms'); ?></small></div>
                            <strong><?php echo $attendance && $attendance->clock_out ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_out))) : '—'; ?></strong>
                        </div>
                    </div>

                    <div class="pms-attendance-stats">
                        <div><span><?php esc_html_e('Clock in', 'pms'); ?></span><strong><?php echo $attendance && $attendance->clock_in ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_in))) : esc_html__('Not started', 'pms'); ?></strong></div>
                        <div><span><?php esc_html_e('Clock out', 'pms'); ?></span><strong><?php echo $attendance && $attendance->clock_out ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_out))) : esc_html__('Not yet', 'pms'); ?></strong></div>
                        <div><span><?php esc_html_e('Shift length', 'pms'); ?></span><strong><?php echo esc_html(round($workday_minutes / 60, 1) . ' hrs'); ?></strong></div>
                    </div>
                </div>

                <div class="pms-attendance-action">
                    <div class="pms-action-icon"><span class="dashicons dashicons-clock"></span></div>
                    <div class="pms-attendance-action-copy">
                        <span><?php echo $attendance_is_live ? esc_html__('You’re on the clock', 'pms') : esc_html__('Ready to begin', 'pms'); ?></span>
                        <strong><?php echo $attendance_is_live ? esc_html__('Keep your workday moving.', 'pms') : esc_html__('Start your workday.', 'pms'); ?></strong>
                    </div>
                    <?php if ($attendance_is_live) : ?>
                        <button type="button" class="pms-attendance-primary pms-attendance-btn" data-attendance-action="clock-out">
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Clock out', 'pms'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    <?php elseif (! $attendance || $attendance->status !== 'clocked_out') : ?>
                        <button type="button" class="pms-attendance-primary pms-attendance-btn" data-attendance-action="clock-in">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Start workday', 'pms'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    <?php else : ?>
                        <div class="pms-attendance-complete"><?php esc_html_e('Workday complete', 'pms'); ?><span class="dashicons dashicons-yes-alt"></span></div>
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

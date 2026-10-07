<?php
/** @var bool $is_admin passed in from PMS_Admin_Menu::render_dashboard() */
if (! defined('ABSPATH')) {
    exit;
}

$task_breakdown = PMS_DB::task_status_breakdown();
$my_tasks = PMS_DB::get_tasks_for_user(get_current_user_id());
$attendance = PMS_Modules::is_active('attendance') && current_user_can('pms_clock_in_out')
    ? PMS_Attendance::today_for_user(get_current_user_id())
    : null;
$attendance_minutes = $attendance ? PMS_Attendance::duration_minutes($attendance) : null;
$current_user = wp_get_current_user();
$workday_start = get_option('pms_workday_start', '08:00');
$workday_end = get_option('pms_workday_end', '17:00');
$attendance_is_live = $attendance && $attendance->status === 'clocked_in';

$todo_count = $is_admin
    ? (int) ($task_breakdown['todo'] ?? 0)
    : count(array_filter($my_tasks, static fn ($task) => $task->status === 'todo'));
$progress_count = $is_admin
    ? (int) ($task_breakdown['in_progress'] ?? 0)
    : count(array_filter($my_tasks, static fn ($task) => $task->status === 'in_progress'));
$done_count = $is_admin
    ? (int) ($task_breakdown['done'] ?? 0)
    : count(array_filter($my_tasks, static fn ($task) => $task->status === 'done'));

$rows = $is_admin ? array_slice(PMS_DB::get_all_tasks(), 0, 6) : array_slice($my_tasks, 0, 6);
$tasks_url = admin_url('admin.php?page=' . ($is_admin ? 'pms-tasks' : 'pms-my-tasks'));
$attendance_url = admin_url('admin.php?page=pms-attendance');
$profile_url = admin_url('admin.php?page=pms-my-account');
?>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>

<div class="pms-exact-dashboard">
    <div class="pms-exact-dashboard-inner">
        <section class="pms-exact-welcome city-banner-bg">
            <div>
                <div class="pms-exact-kicker"><?php echo $is_admin ? esc_html__('ADMIN DASHBOARD', 'pms') : esc_html__('WELCOME BACK,', 'pms'); ?></div>
                <h2>
                    <?php if ($is_admin) : ?>
                        <?php esc_html_e('Welcome back,', 'pms'); ?>
                        <span><?php echo esc_html($current_user->display_name); ?></span>
                    <?php else : ?>
                        <?php echo esc_html($current_user->display_name); ?> <span aria-hidden="true">👋</span>
                    <?php endif; ?>
                </h2>
                <p><?php echo $is_admin ? esc_html__("Here's what's happening with your workspace today.", 'pms') : esc_html__("Here's what's happening with your work today.", 'pms'); ?></p>
            </div>
            <div class="pms-exact-date">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php echo esc_html(wp_date('l, j M Y')); ?></span>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </div>
        </section>

        <section class="pms-exact-kpis" aria-label="<?php esc_attr_e('Task summary', 'pms'); ?>">
            <a class="pms-exact-kpi pms-exact-kpi-todo" href="<?php echo esc_url($tasks_url); ?>">
                <span class="pms-exact-kpi-icon"><span class="dashicons dashicons-clipboard"></span></span>
                <span class="pms-exact-kpi-copy">
                    <small><?php esc_html_e('TO DO', 'pms'); ?></small>
                    <strong><?php echo esc_html($todo_count); ?></strong>
                </span>
                <span class="pms-exact-kpi-arrow"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
                <span class="pms-exact-kpi-description"><?php esc_html_e('Pending tasks', 'pms'); ?></span>
                <span class="pms-exact-kpi-line"></span>
            </a>

            <a class="pms-exact-kpi pms-exact-kpi-progress" href="<?php echo esc_url($tasks_url); ?>">
                <span class="pms-exact-kpi-icon"><span class="dashicons dashicons-clock"></span></span>
                <span class="pms-exact-kpi-copy">
                    <small><?php esc_html_e('IN PROGRESS', 'pms'); ?></small>
                    <strong><?php echo esc_html($progress_count); ?></strong>
                </span>
                <span class="pms-exact-kpi-arrow"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
                <span class="pms-exact-kpi-description"><?php esc_html_e('Ongoing tasks', 'pms'); ?></span>
                <span class="pms-exact-kpi-line"></span>
            </a>

            <a class="pms-exact-kpi pms-exact-kpi-done" href="<?php echo esc_url($tasks_url); ?>">
                <span class="pms-exact-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></span>
                <span class="pms-exact-kpi-copy">
                    <small><?php esc_html_e('DONE', 'pms'); ?></small>
                    <strong><?php echo esc_html($done_count); ?></strong>
                </span>
                <span class="pms-exact-kpi-arrow"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
                <span class="pms-exact-kpi-description"><?php esc_html_e('Completed tasks', 'pms'); ?></span>
                <span class="pms-exact-kpi-line"></span>
            </a>
        </section>

        <?php if (! $is_admin && PMS_Modules::is_active('attendance') && current_user_can('pms_clock_in_out')) : ?>
            <section class="pms-exact-attendance">
                <div class="pms-exact-section-head">
                    <div class="pms-exact-section-title">
                        <div class="pms-exact-section-icon pms-exact-section-icon-orange"><span class="dashicons dashicons-calendar-alt"></span></div>
                        <div>
                            <h3><?php esc_html_e("Today's attendance", 'pms'); ?></h3>
                            <p><?php esc_html_e('Start your workday when you are ready. Your attendance stays independent from tasks.', 'pms'); ?></p>
                        </div>
                    </div>
                    <div class="pms-exact-attendance-date">
                        <span class="pms-exact-live-dot"></span>
                        <span><?php echo esc_html(wp_date('l, j M Y')); ?></span>
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>

                <div class="pms-exact-attendance-stage">
                    <div class="pms-exact-status-card">
                        <div><span class="dashicons dashicons-clock"></span><span><?php esc_html_e('Current status', 'pms'); ?></span></div>
                        <div class="pms-exact-status-value">
                            <span class="pms-exact-status-dot <?php echo $attendance_is_live ? 'is-live' : ''; ?>"></span>
                            <strong><?php echo $attendance_is_live ? esc_html__('On duty', 'pms') : esc_html__('Off duty', 'pms'); ?></strong>
                        </div>
                        <p><?php echo esc_html(($attendance_minutes ?? 0) . ' ' . __('minutes', 'pms')); ?></p>
                    </div>

                    <div class="pms-exact-clock-wrap">
                        <div class="pms-exact-clock-dial">
                            <div class="pms-exact-clock-ticks"></div>
                            <div>
                                <strong><?php echo $attendance_is_live && $attendance_minutes !== null ? esc_html($attendance_minutes) : esc_html($workday_start); ?></strong>
                                <p><?php echo $attendance_is_live ? esc_html__('minutes worked', 'pms') : esc_html__('Ready to work', 'pms'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="pms-exact-attendance-actions">
                        <button type="button" class="pms-exact-attendance-action pms-exact-clock-in pms-attendance-btn" data-attendance-action="clock-in" <?php disabled($attendance_is_live || ($attendance && $attendance->status === 'clocked_out')); ?>>
                            <span class="pms-exact-action-icon"><span class="dashicons dashicons-controls-play"></span></span>
                            <span><strong><?php esc_html_e('Clock In', 'pms'); ?></strong><small><?php esc_html_e('Start your workday', 'pms'); ?></small></span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                        <button type="button" class="pms-exact-attendance-action pms-exact-clock-out pms-attendance-btn" data-attendance-action="clock-out" <?php disabled(! $attendance_is_live); ?>>
                            <span class="pms-exact-action-icon"><span class="dashicons dashicons-stop"></span></span>
                            <span><strong><?php esc_html_e('Clock Out', 'pms'); ?></strong><small><?php esc_html_e('End your workday', 'pms'); ?></small></span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                        <span class="pms-attendance-note" aria-live="polite"></span>
                    </div>
                </div>

                <div class="pms-exact-attendance-meta">
                    <div>
                        <span class="dashicons dashicons-clock"></span>
                        <span><small><?php esc_html_e("Today's shift", 'pms'); ?></small><strong><?php echo esc_html($workday_start . ' - ' . $workday_end); ?></strong></span>
                    </div>
                    <div>
                        <span class="dashicons dashicons-migrate"></span>
                        <span><small><?php esc_html_e('Clock in / out', 'pms'); ?></small><strong><?php echo $attendance && $attendance->clock_in ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_in))) : esc_html__('Not started yet', 'pms'); ?></strong></span>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="pms-exact-recent">
            <div class="pms-exact-recent-head">
                <div class="pms-exact-section-title">
                    <div class="pms-exact-section-icon pms-exact-section-icon-blue"><span class="dashicons dashicons-clipboard"></span></div>
                    <h3><?php esc_html_e('Recent tasks', 'pms'); ?></h3>
                </div>
                <div class="pms-exact-recent-actions">
                    <a href="<?php echo esc_url($tasks_url); ?>" class="pms-exact-create-task"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Create Task', 'pms'); ?></a>
                    <a href="<?php echo esc_url($tasks_url); ?>" class="pms-exact-view-all"><?php esc_html_e('View all', 'pms'); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></a>
                </div>
            </div>

            <?php if (empty($rows)) : ?>
                <div class="pms-exact-empty">
                    <div class="pms-exact-empty-art">
                        <div class="pms-exact-empty-circle"></div>
                        <div class="pms-exact-clipboard">
                            <span></span><i></i><i></i><i></i>
                        </div>
                        <b class="pms-sparkle pms-sparkle-one"></b>
                        <b class="pms-sparkle pms-sparkle-two"></b>
                        <b class="pms-sparkle pms-sparkle-three"></b>
                    </div>
                    <h4><?php esc_html_e('Nothing here yet.', 'pms'); ?></h4>
                    <p><?php esc_html_e('When you have tasks, they will appear here.', 'pms'); ?></p>
                </div>
            <?php else : ?>
                <div class="pms-exact-task-list">
                    <?php foreach ($rows as $task) : ?>
                        <a class="pms-exact-task-row" href="<?php echo esc_url($tasks_url . ($is_admin ? '&task_id=' : '&id=') . (int) $task->id); ?>">
                            <span class="pms-exact-task-dot pms-exact-task-dot-<?php echo esc_attr($task->status); ?>"></span>
                            <span class="pms-exact-task-title"><?php echo esc_html($task->title); ?></span>
                            <span class="pms-exact-task-status"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_statuses(), $task->status)); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

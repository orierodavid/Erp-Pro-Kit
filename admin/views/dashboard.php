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
$workday_minutes = max(1, (int) ((strtotime($workday_end) - strtotime($workday_start)) / 60));
$attendance_is_live = $attendance && $attendance->status === 'clocked_in';
$attendance_progress = $attendance_is_live && $attendance_minutes !== null
    ? min(100, max(0, ($attendance_minutes / $workday_minutes) * 100))
    : 0;

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-reference-dashboard">
    <section class="pms-dashboard-hero">
        <div class="pms-dashboard-hero-copy">
            <p class="pms-dashboard-kicker"><?php echo $is_admin ? esc_html__('Admin dashboard', 'pms') : esc_html__('Welcome back,', 'pms'); ?></p>
            <h1>
                <?php if ($is_admin) : ?>
                    <?php esc_html_e('Welcome back,', 'pms'); ?>
                    <span><?php echo esc_html($current_user->display_name); ?></span>
                <?php else : ?>
                    <?php echo esc_html($current_user->display_name); ?> <span class="pms-wave" aria-hidden="true">👋</span>
                <?php endif; ?>
            </h1>
            <p><?php echo $is_admin ? esc_html__("Here’s what's happening with your workspace today.", 'pms') : esc_html__("Here's what's happening with your work today.", 'pms'); ?></p>
        </div>

        <div class="pms-dashboard-hero-art" aria-hidden="true">
            <span class="pms-art-sun"></span>
            <span class="pms-art-building pms-art-building-a"></span>
            <span class="pms-art-building pms-art-building-b"></span>
            <span class="pms-art-building pms-art-building-c"></span>
            <span class="pms-art-tree pms-art-tree-a"></span>
            <span class="pms-art-tree pms-art-tree-b"></span>
        </div>

        <div class="pms-dashboard-date">
            <span class="dashicons dashicons-calendar-alt"></span>
            <span><?php echo esc_html(wp_date('l, j M Y')); ?></span>
            <span class="dashicons dashicons-arrow-down-alt2"></span>
        </div>
    </section>

    <section class="pms-dashboard-kpis" aria-label="<?php esc_attr_e('Task summary', 'pms'); ?>">
        <?php
        $kpis = [
            [
                'key' => 'todo',
                'label' => __('To do', 'pms'),
                'count' => $is_admin ? ($task_breakdown['todo'] ?? 0) : count(array_filter($my_tasks, static fn ($task) => $task->status === 'todo')),
                'description' => __('Pending tasks', 'pms'),
                'icon' => 'dashicons-yes-alt',
            ],
            [
                'key' => 'in_progress',
                'label' => __('In progress', 'pms'),
                'count' => $is_admin ? ($task_breakdown['in_progress'] ?? 0) : count(array_filter($my_tasks, static fn ($task) => $task->status === 'in_progress')),
                'description' => __('Ongoing tasks', 'pms'),
                'icon' => 'dashicons-clock',
            ],
            [
                'key' => 'done',
                'label' => __('Done', 'pms'),
                'count' => $is_admin ? ($task_breakdown['done'] ?? 0) : count(array_filter($my_tasks, static fn ($task) => $task->status === 'done')),
                'description' => __('Completed tasks', 'pms'),
                'icon' => 'dashicons-yes',
            ],
        ];
        foreach ($kpis as $kpi) :
            ?>
            <a class="pms-dashboard-kpi pms-dashboard-kpi-<?php echo esc_attr($kpi['key']); ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . ($is_admin ? 'pms-tasks' : 'pms-my-tasks'))); ?>">
                <span class="pms-dashboard-kpi-icon"><span class="dashicons <?php echo esc_attr($kpi['icon']); ?>"></span></span>
                <span class="pms-dashboard-kpi-copy">
                    <small><?php echo esc_html($kpi['label']); ?></small>
                    <strong><?php echo esc_html($kpi['count']); ?></strong>
                    <span><?php echo esc_html($kpi['description']); ?></span>
                </span>
                <span class="pms-dashboard-kpi-arrow"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
                <span class="pms-dashboard-kpi-line"></span>
            </a>
        <?php endforeach; ?>
    </section>

    <?php if (! $is_admin && PMS_Modules::is_active('attendance') && current_user_can('pms_clock_in_out')) : ?>
        <section class="pms-dashboard-attendance" aria-labelledby="pms-dashboard-attendance-title">
            <div class="pms-dashboard-section-head">
                <div class="pms-dashboard-section-icon pms-dashboard-section-icon-orange">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div>
                    <h2 id="pms-dashboard-attendance-title"><?php esc_html_e("Today's attendance", 'pms'); ?></h2>
                    <p><?php esc_html_e('Start your workday when you are ready. Your attendance stays independent from tasks.', 'pms'); ?></p>
                </div>
                <div class="pms-dashboard-attendance-date">
                    <span class="pms-live-dot <?php echo $attendance_is_live ? 'is-live' : ''; ?>"></span>
                    <span><?php echo esc_html(wp_date('l, j M Y')); ?></span>
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
            </div>

            <div class="pms-dashboard-attendance-stage">
                <div class="pms-dashboard-attendance-status">
                    <span class="dashicons dashicons-clock"></span>
                    <small><?php esc_html_e('Current status', 'pms'); ?></small>
                    <strong><?php echo $attendance_is_live ? esc_html__('On duty', 'pms') : esc_html__('Off duty', 'pms'); ?></strong>
                    <span><?php echo esc_html(($attendance_minutes ?? 0) . ' ' . __('minutes', 'pms')); ?></span>
                </div>

                <div class="pms-dashboard-attendance-clock">
                    <div class="pms-dashboard-attendance-clock-ring" style="--pms-attendance-progress: <?php echo esc_attr($attendance_progress); ?>%;">
                        <div>
                            <strong><?php echo $attendance_is_live && $attendance_minutes !== null ? esc_html($attendance_minutes) : esc_html($workday_start); ?></strong>
                            <span><?php echo $attendance_is_live ? esc_html__('minutes worked', 'pms') : esc_html__('Ready to work', 'pms'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="pms-dashboard-attendance-actions">
                    <button type="button" class="pms-dashboard-attendance-action is-clock-in pms-attendance-btn" data-attendance-action="clock-in" <?php disabled($attendance_is_live || ($attendance && $attendance->status === 'clocked_out')); ?>>
                        <span class="dashicons dashicons-controls-play"></span>
                        <span><strong><?php esc_html_e('Clock In', 'pms'); ?></strong><small><?php esc_html_e('Start your workday', 'pms'); ?></small></span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                    <button type="button" class="pms-dashboard-attendance-action is-clock-out pms-attendance-btn" data-attendance-action="clock-out" <?php disabled(! $attendance_is_live); ?>>
                        <span class="dashicons dashicons-stop"></span>
                        <span><strong><?php esc_html_e('Clock Out', 'pms'); ?></strong><small><?php esc_html_e('End your workday', 'pms'); ?></small></span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                    <span class="pms-attendance-note" aria-live="polite"></span>
                </div>
            </div>

            <div class="pms-dashboard-attendance-meta">
                <div>
                    <span class="dashicons dashicons-clock"></span>
                    <span><small><?php esc_html_e("Today's shift", 'pms'); ?></small><strong><?php echo esc_html($workday_start . ' - ' . $workday_end); ?></strong><em><?php echo $attendance ? esc_html__('Started', 'pms') : esc_html__('Not started', 'pms'); ?></em></span>
                </div>
                <div>
                    <span class="dashicons dashicons-migrate"></span>
                    <span><small><?php esc_html_e('Clock in / out', 'pms'); ?></small><strong><?php echo $attendance && $attendance->clock_in ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_in))) : esc_html__('Not started yet', 'pms'); ?></strong><em><?php echo $attendance && $attendance->clock_out ? esc_html(wp_date(get_option('time_format'), strtotime($attendance->clock_out))) : esc_html__('Shift length ' . round($workday_minutes / 60, 1) . ' hrs', 'pms'); ?></em></span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="pms-dashboard-recent">
        <div class="pms-dashboard-section-head pms-dashboard-recent-head">
            <div class="pms-dashboard-section-icon pms-dashboard-section-icon-blue">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div>
                <h2><?php esc_html_e('Recent tasks', 'pms'); ?></h2>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . ($is_admin ? 'pms-tasks' : 'pms-my-tasks'))); ?>"><?php esc_html_e('View all', 'pms'); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></a>
        </div>

        <?php
        $rows = $is_admin ? array_slice(PMS_DB::get_all_tasks(), 0, 6) : array_slice($my_tasks, 0, 6);
        if (empty($rows)) :
            ?>
            <div class="pms-dashboard-empty">
                <div class="pms-dashboard-empty-icon"><span class="dashicons dashicons-clipboard"></span></div>
                <strong><?php esc_html_e('Nothing here yet.', 'pms'); ?></strong>
                <p><?php esc_html_e('When you have tasks, they will appear here.', 'pms'); ?></p>
            </div>
        <?php else : ?>
            <div class="pms-dashboard-task-list">
                <?php foreach ($rows as $task) : ?>
                    <a class="pms-dashboard-task-row" href="<?php echo esc_url(admin_url('admin.php?page=' . ($is_admin ? 'pms-tasks' : 'pms-my-tasks') . ($is_admin ? '&task_id=' : '&id=') . (int) $task->id)); ?>">
                        <span class="pms-dashboard-task-icon"><span class="dashicons dashicons-list-view"></span></span>
                        <span class="pms-dashboard-task-copy"><strong><?php echo esc_html($task->title); ?></strong><small><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_statuses(), $task->status)); ?></small></span>
                        <span class="pms-chip pms-status-<?php echo esc_attr($task->status); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_statuses(), $task->status)); ?></span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

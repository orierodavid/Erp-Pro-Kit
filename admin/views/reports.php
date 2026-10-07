<?php
if (! defined('ABSPATH')) {
    exit;
}

$from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : gmdate('Y-m-d', strtotime('-6 days'));
$to   = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : gmdate('Y-m-d');
$department_id = isset($_GET['department_id']) ? absint($_GET['department_id']) : 0;
$work_mode = isset($_GET['work_mode']) ? sanitize_key($_GET['work_mode']) : '';
$punctuality = isset($_GET['punctuality']) ? sanitize_key($_GET['punctuality']) : '';

// Basic guard against a swapped/garbage range rather than trusting the query string blindly.
if (strtotime($from) === false) {
    $from = gmdate('Y-m-d', strtotime('-6 days'));
}
if (strtotime($to) === false || strtotime($to) < strtotime($from)) {
    $to = gmdate('Y-m-d');
}

$task_rows = PMS_DB::filtered_tasks_report($from, $to, [
    'department_id' => $department_id ?: null,
    'work_mode'     => $work_mode ?: null,
    'punctuality'   => $punctuality ?: null,
]);

$task_breakdown = PMS_DB::task_status_breakdown();
$task_statuses = PMS_Constants::task_statuses();
$work_modes = PMS_Constants::work_modes();
$departments = PMS_DB::get_departments();

$late_count = count(array_filter($task_rows, fn ($r) => $r->punctuality === 'late'));

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('System', 'pms'); ?></p>
        <h1><?php esc_html_e('Reports', 'pms'); ?></h1>
    </div>
</div>

<div class="pms-panel pms-form-panel pms-report-filter">
    <form method="get">
        <input type="hidden" name="page" value="pms-reports">
        <div class="pms-form-grid" style="grid-template-columns:repeat(5,1fr);align-items:end;">
            <div class="pms-form-row">
                <label for="from"><?php esc_html_e('From', 'pms'); ?></label>
                <input type="date" id="from" name="from" class="pms-input" value="<?php echo esc_attr($from); ?>">
            </div>
            <div class="pms-form-row">
                <label for="to"><?php esc_html_e('To', 'pms'); ?></label>
                <input type="date" id="to" name="to" class="pms-input" value="<?php echo esc_attr($to); ?>">
            </div>
            <div class="pms-form-row">
                <label for="department_id"><?php esc_html_e('Department', 'pms'); ?></label>
                <select id="department_id" name="department_id" class="pms-input">
                    <option value=""><?php esc_html_e('All departments', 'pms'); ?></option>
                    <?php foreach ($departments as $dept) : ?>
                        <option value="<?php echo esc_attr($dept->id); ?>" <?php selected($department_id, $dept->id); ?>><?php echo esc_html($dept->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pms-form-row">
                <label for="work_mode"><?php esc_html_e('Work mode', 'pms'); ?></label>
                <select id="work_mode" name="work_mode" class="pms-input">
                    <option value=""><?php esc_html_e('All', 'pms'); ?></option>
                    <?php foreach ($work_modes as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($work_mode, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pms-form-row">
                <label for="punctuality"><?php esc_html_e('Punctuality', 'pms'); ?></label>
                <select id="punctuality" name="punctuality" class="pms-input">
                    <option value=""><?php esc_html_e('Early & Late', 'pms'); ?></option>
                    <option value="early" <?php selected($punctuality, 'early'); ?>><?php esc_html_e('Early only', 'pms'); ?></option>
                    <option value="late" <?php selected($punctuality, 'late'); ?>><?php esc_html_e('Late only', 'pms'); ?></option>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button type="submit" class="pms-btn-primary"><?php esc_html_e('Update report', 'pms'); ?></button>
            <a href="<?php
                echo esc_url(wp_nonce_url(
                    add_query_arg([
                        'action'        => 'pms_export_task_report',
                        'from'          => $from,
                        'to'            => $to,
                        'department_id' => $department_id,
                        'work_mode'     => $work_mode,
                        'punctuality'   => $punctuality,
                    ], admin_url('admin-post.php')),
                    'pms_export_task_report_action'
                ));
            ?>" class="pms-btn-secondary">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'pms'); ?>
            </a>
        </div>
    </form>
</div>

<div class="pms-kpi-grid">
    <?php foreach ($task_statuses as $key => $label) : ?>
        <div class="pms-kpi">
            <span class="pms-kpi-label"><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($task_breakdown[$key] ?? 0); ?></strong>
        </div>
    <?php endforeach; ?>
    <div class="pms-kpi">
        <span class="pms-kpi-label"><?php esc_html_e('Late starts', 'pms'); ?></span>
        <strong><?php echo esc_html($late_count); ?></strong>
    </div>
</div>

<div class="pms-panel">
    <div class="pms-panel-heading">
        <h2><?php esc_html_e('Task punctuality', 'pms'); ?></h2>
        <span><?php echo esc_html(sprintf(__('%1$s to %2$s · %3$d started tasks', 'pms'), $from, $to, count($task_rows))); ?></span>
    </div>
    <table class="pms-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Task', 'pms'); ?></th>
                <th><?php esc_html_e('Person', 'pms'); ?></th>
                <th><?php esc_html_e('Department', 'pms'); ?></th>
                <th><?php esc_html_e('Work mode', 'pms'); ?></th>
                <th><?php esc_html_e('Started', 'pms'); ?></th>
                <th><?php esc_html_e('Duration', 'pms'); ?></th>
                <th><?php esc_html_e('Status', 'pms'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($task_rows)) : ?>
            <tr><td colspan="7" class="pms-empty"><?php esc_html_e('No started tasks match these filters.', 'pms'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($task_rows as $row) : ?>
            <tr>
                <td><?php echo esc_html($row->title); ?></td>
                <td><?php echo esc_html($row->user_name); ?></td>
                <td><?php echo esc_html($row->department_name); ?></td>
                <td><?php echo esc_html(PMS_Constants::label_for($work_modes, $row->work_mode ?: 'remote')); ?></td>
                <td><?php echo esc_html(date_i18n('M j, g:i a', strtotime($row->started_at))); ?></td>
                <td><?php echo esc_html($row->duration_minutes !== null ? number_format($row->duration_minutes / 60, 1) . 'h' : '—'); ?></td>
                <td>
                    <span class="pms-chip <?php echo $row->punctuality === 'late' ? 'pms-priority-high' : 'pms-status-done'; ?>">
                        <?php echo esc_html($row->punctuality === 'late' ? __('Late', 'pms') : __('Early', 'pms')); ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

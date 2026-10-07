<?php
if (! defined('ABSPATH')) {
    exit;
}

$can_view_all = current_user_can('pms_view_all_attendance');
$attendance_from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
$attendance_to = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';
$attendance_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$attendance_user = $can_view_all ? absint($_GET['user_id'] ?? 0) : get_current_user_id();
$all_records = $can_view_all ? PMS_Attendance::recent(1000) : PMS_Attendance::recent_for_user(get_current_user_id(), 1000);
$records = array_values(array_filter($all_records, function ($record) use ($attendance_from, $attendance_to, $attendance_status, $attendance_user) {
    $day = substr((string) $record->clock_in, 0, 10);
    return (!$attendance_user || (int) $record->user_id === $attendance_user)
        && (!$attendance_status || $record->status === $attendance_status)
        && (!$attendance_from || $day >= $attendance_from)
        && (!$attendance_to || $day <= $attendance_to);
}));
$attendance_people = $can_view_all ? PMS_Roles::get_pms_people() : [];

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Workforce', 'pms'); ?></p>
        <h1><?php esc_html_e('Attendance', 'pms'); ?></h1>
        <?php if (! $can_view_all) : ?>
            <p class="pms-page-description"><?php esc_html_e('Your attendance history and today’s workday status.', 'pms'); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="pms-panel pms-form-panel">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <input type="hidden" name="page" value="pms-attendance">
        <div class="pms-form-row"><label for="from"><?php esc_html_e('From', 'pms'); ?></label><input type="date" id="from" name="from" class="pms-input" value="<?php echo esc_attr($attendance_from); ?>"></div>
        <div class="pms-form-row"><label for="to"><?php esc_html_e('To', 'pms'); ?></label><input type="date" id="to" name="to" class="pms-input" value="<?php echo esc_attr($attendance_to); ?>"></div>
        <?php if ($can_view_all) : ?><div class="pms-form-row"><label for="user_id"><?php esc_html_e('Employee', 'pms'); ?></label><select id="user_id" name="user_id" class="pms-input"><option value=""><?php esc_html_e('All employees', 'pms'); ?></option><?php foreach ($attendance_people as $person) : ?><option value="<?php echo esc_attr($person->ID); ?>" <?php selected($attendance_user, $person->ID); ?>><?php echo esc_html($person->display_name); ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="pms-form-row"><label for="status"><?php esc_html_e('Status', 'pms'); ?></label><select id="status" name="status" class="pms-input"><option value=""><?php esc_html_e('All statuses', 'pms'); ?></option><?php foreach (PMS_Constants::attendance_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($attendance_status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="pms-btn-primary"><?php esc_html_e('Filter', 'pms'); ?></button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-attendance')); ?>" class="pms-btn-secondary"><?php esc_html_e('Reset', 'pms'); ?></a>
        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'pms_export_attendance_report','from'=>$attendance_from,'to'=>$attendance_to,'status'=>$attendance_status,'user_id'=>$attendance_user], admin_url('admin-post.php')), 'pms_export_attendance_report_action')); ?>" class="pms-btn-secondary"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'pms'); ?></a>
    </form>
</div>
<div class="pms-panel">
    <table class="pms-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Employee', 'pms'); ?></th>
                <th><?php esc_html_e('Clock in', 'pms'); ?></th>
                <th><?php esc_html_e('Clock out', 'pms'); ?></th>
                <th><?php esc_html_e('Duration', 'pms'); ?></th>
                <th><?php esc_html_e('Status', 'pms'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($records)) : ?>
            <tr><td colspan="5" class="pms-empty"><?php esc_html_e('No attendance records yet.', 'pms'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($records as $record) : ?>
            <?php $duration = PMS_Attendance::duration_minutes($record); ?>
            <tr>
                <td><?php echo esc_html(get_the_author_meta('display_name', (int) $record->user_id) ?: __('Unknown', 'pms')); ?></td>
                <td><?php echo esc_html($record->clock_in ?: '—'); ?></td>
                <td><?php echo esc_html($record->clock_out ?: '—'); ?></td>
                <td><?php echo esc_html($duration === null ? '—' : sprintf('%dh %02dm', intdiv($duration, 60), $duration % 60)); ?></td>
                <td><span class="pms-chip <?php echo esc_attr($record->status === 'clocked_in' ? 'pms-status-in_progress' : 'pms-status-done'); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::attendance_statuses(), $record->status)); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

<?php
if (! defined('ABSPATH')) {
    exit;
}

$can_view_all = current_user_can('pms_view_all_attendance');
$records = $can_view_all ? PMS_Attendance::recent(100) : PMS_Attendance::recent_for_user(get_current_user_id(), 100);

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

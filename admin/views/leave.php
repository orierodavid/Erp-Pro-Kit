<?php
if (! defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$is_manager = current_user_can('pms_manage_leave');
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pms_leave_action'])) {
    check_admin_referer('pms_leave_manage', 'pms_leave_nonce');
    $action = sanitize_key(wp_unslash($_POST['pms_leave_action']));

    if ($action === 'request' && current_user_can('pms_request_leave')) {
        $type = sanitize_key(wp_unslash($_POST['leave_type'] ?? ''));
        $start = sanitize_text_field(wp_unslash($_POST['start_date'] ?? ''));
        $end = sanitize_text_field(wp_unslash($_POST['end_date'] ?? ''));
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
        $types = PMS_DB::leave_types();

        if (! isset($types[$type]) || ! PMS_Leave::valid_date($start) || ! PMS_Leave::valid_date($end)) {
            $error = __('Select a valid leave type and date range.', 'pms');
        } elseif ($end < $start) {
            $error = __('End date cannot be before the start date.', 'pms');
        } else {
            PMS_DB::create_leave_request([
                'user_id' => $user_id,
                'leave_type' => $type,
                'start_date' => $start,
                'end_date' => $end,
                'days' => PMS_Leave::calculate_days($start, $end),
                'reason' => $reason,
            ]);
            $notice = __('Leave request submitted for approval.', 'pms');
        }
    }

    if ($action === 'review' && $is_manager) {
        $id = absint($_POST['request_id'] ?? 0);
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        if (in_array($status, ['approved', 'rejected'], true) && PMS_DB::get_leave_request($id)) {
            PMS_DB::update_leave_status($id, $status, $user_id);
            $notice = $status === 'approved' ? __('Leave request approved.', 'pms') : __('Leave request rejected.', 'pms');
        }
    }
}

$filters = [
    'status' => sanitize_key(wp_unslash($_GET['status'] ?? '')),
    'leave_type' => sanitize_key(wp_unslash($_GET['leave_type'] ?? '')),
    'from' => sanitize_text_field(wp_unslash($_GET['from'] ?? '')),
    'to' => sanitize_text_field(wp_unslash($_GET['to'] ?? '')),
];
if (! $is_manager) {
    $filters['user_id'] = $user_id;
}
$requests = PMS_DB::get_leave_requests($filters);
$types = PMS_DB::leave_types();

$export_url = wp_nonce_url(
    admin_url('admin-post.php?action=pms_export_leave_report&' . http_build_query($filters)),
    'pms_export_leave_report_action'
);

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php echo $is_manager ? esc_html__('HR / Workforce', 'pms') : esc_html__('My workspace', 'pms'); ?></p>
        <h1><?php esc_html_e('Leave', 'pms'); ?></h1>
        <p class="pms-page-description"><?php echo $is_manager ? esc_html__('Review and manage employee leave requests.', 'pms') : esc_html__('Request leave and track your approval status.', 'pms'); ?></p>
    </div>
    <a class="pms-btn-secondary" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'pms'); ?></a>
</div>

<?php if ($notice) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>
<?php if ($error) : ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>

<?php if (current_user_can('pms_request_leave')) : ?>
<div class="pms-panel pms-form-panel">
    <div class="pms-panel-heading"><h2><?php esc_html_e('Request leave', 'pms'); ?></h2></div>
    <form method="post">
        <?php wp_nonce_field('pms_leave_manage', 'pms_leave_nonce'); ?>
        <input type="hidden" name="pms_leave_action" value="request">
        <div class="pms-form-grid">
            <div class="pms-field">
                <label for="leave_type"><?php esc_html_e('Leave type', 'pms'); ?></label>
                <select id="leave_type" name="leave_type" class="pms-input" required>
                    <option value=""><?php esc_html_e('Select type', 'pms'); ?></option>
                    <?php foreach ($types as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="pms-field">
                <label for="start_date"><?php esc_html_e('Start date', 'pms'); ?></label>
                <input id="start_date" name="start_date" type="date" class="pms-input" required>
            </div>
            <div class="pms-field">
                <label for="end_date"><?php esc_html_e('End date', 'pms'); ?></label>
                <input id="end_date" name="end_date" type="date" class="pms-input" required>
            </div>
            <div class="pms-field pms-field-full">
                <label for="reason"><?php esc_html_e('Reason', 'pms'); ?></label>
                <textarea id="reason" name="reason" class="pms-input" rows="4"></textarea>
            </div>
        </div>
        <div class="pms-form-actions"><button class="pms-btn-primary" type="submit"><?php esc_html_e('Submit request', 'pms'); ?></button></div>
    </form>
</div>
<?php endif; ?>

<div class="pms-panel">
    <form method="get" class="pms-toolbar">
        <input type="hidden" name="page" value="pms-leave">
        <select name="status" class="pms-input"><option value=""><?php esc_html_e('All statuses', 'pms'); ?></option><?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'], $key); ?>><?php echo esc_html__($label, 'pms'); ?></option><?php endforeach; ?></select>
        <select name="leave_type" class="pms-input"><option value=""><?php esc_html_e('All leave types', 'pms'); ?></option><?php foreach ($types as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['leave_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
        <input type="date" name="from" class="pms-input" value="<?php echo esc_attr($filters['from']); ?>" aria-label="<?php esc_attr_e('From date', 'pms'); ?>">
        <input type="date" name="to" class="pms-input" value="<?php echo esc_attr($filters['to']); ?>" aria-label="<?php esc_attr_e('To date', 'pms'); ?>">
        <button class="pms-btn-secondary" type="submit"><?php esc_html_e('Filter', 'pms'); ?></button>
        <a class="pms-btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=pms-leave')); ?>"><?php esc_html_e('Reset', 'pms'); ?></a>
    </form>
</div>

<div class="pms-panel">
    <table class="pms-table">
        <thead><tr>
            <?php if ($is_manager) : ?><th><?php esc_html_e('Employee', 'pms'); ?></th><?php endif; ?>
            <th><?php esc_html_e('Type', 'pms'); ?></th><th><?php esc_html_e('Dates', 'pms'); ?></th><th><?php esc_html_e('Days', 'pms'); ?></th><th><?php esc_html_e('Reason', 'pms'); ?></th><th><?php esc_html_e('Status', 'pms'); ?></th><?php if ($is_manager) : ?><th><?php esc_html_e('Action', 'pms'); ?></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if (! $requests) : ?><tr><td colspan="<?php echo $is_manager ? '7' : '5'; ?>" class="pms-empty"><?php esc_html_e('No leave requests found.', 'pms'); ?></td></tr><?php endif; ?>
        <?php foreach ($requests as $request) : ?>
            <tr>
                <?php if ($is_manager) : ?><td><?php echo esc_html($request->user_name); ?></td><?php endif; ?>
                <td><?php echo esc_html($types[$request->leave_type] ?? $request->leave_type); ?></td>
                <td><?php echo esc_html($request->start_date . ' → ' . $request->end_date); ?></td>
                <td><?php echo esc_html(number_format((float) $request->days, 2)); ?></td>
                <td><?php echo esc_html($request->reason ?: '—'); ?></td>
                <td><span class="pms-chip pms-status-<?php echo esc_attr($request->status); ?>"><?php echo esc_html(ucfirst($request->status)); ?></span></td>
                <?php if ($is_manager) : ?><td><?php if ($request->status === 'pending') : ?>
                    <form method="post" style="display:inline-flex;gap:6px;"><?php wp_nonce_field('pms_leave_manage', 'pms_leave_nonce'); ?><input type="hidden" name="pms_leave_action" value="review"><input type="hidden" name="request_id" value="<?php echo (int) $request->id; ?>"><button type="submit" name="status" value="approved" class="pms-btn-primary"><?php esc_html_e('Approve', 'pms'); ?></button><button type="submit" name="status" value="rejected" class="pms-btn-secondary"><?php esc_html_e('Reject', 'pms'); ?></button></form>
                <?php else : ?>—<?php endif; ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

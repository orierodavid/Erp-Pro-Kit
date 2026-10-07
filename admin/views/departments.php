<?php
if (! defined('ABSPATH')) {
    exit;
}

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
$department_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$notice = '';

if (isset($_POST['pms_save_department']) && check_admin_referer('pms_save_department_action', 'pms_department_nonce')) {
    $data = [
        'name'      => sanitize_text_field($_POST['name'] ?? ''),
        'branch_id' => absint($_POST['branch_id'] ?? 0) ?: null,
    ];

    $editing_id = absint($_POST['department_id'] ?? 0);

    if ($editing_id) {
        PMS_DB::update_department($editing_id, $data);
        $notice = __('Department updated.', 'pms');
    } else {
        PMS_DB::create_department($data);
        $notice = __('Department added.', 'pms');
    }

    $action = 'list';
}

if ($action === 'delete' && $department_id && check_admin_referer('pms_delete_department_' . $department_id)) {
    PMS_DB::delete_department($department_id);
    $notice = __('Department deleted.', 'pms');
    $action = 'list';
}

$branches = PMS_DB::get_branches();

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Organization', 'pms'); ?></p>
        <h1><?php esc_html_e('Departments', 'pms'); ?></h1>
    </div>
    <?php if ($action === 'list') : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-departments&action=add')); ?>" class="pms-btn-primary">
            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('New department', 'pms'); ?>
        </a>
    <?php else : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-departments')); ?>" class="pms-btn-secondary"><?php esc_html_e('← Back to list', 'pms'); ?></a>
    <?php endif; ?>
</div>

<?php if ($notice) : ?>
    <div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div>
<?php endif; ?>

<?php if (in_array($action, ['add', 'edit'], true)) :
    $department = $action === 'edit' ? PMS_DB::get_department($department_id) : null;
    if ($action === 'edit' && ! $department) :
        echo '<div class="pms-notice pms-notice-error">' . esc_html__('Department not found.', 'pms') . '</div>';
    else :
        ?>
        <div class="pms-panel pms-form-panel">
            <form method="post">
                <?php wp_nonce_field('pms_save_department_action', 'pms_department_nonce'); ?>
                <input type="hidden" name="department_id" value="<?php echo esc_attr($department->id ?? 0); ?>">

                <div class="pms-form-row">
                    <label for="name"><?php esc_html_e('Name', 'pms'); ?></label>
                    <input type="text" id="name" name="name" required class="pms-input" value="<?php echo esc_attr($department->name ?? ''); ?>">
                </div>

                <div class="pms-form-row">
                    <label for="branch_id"><?php esc_html_e('Branch', 'pms'); ?></label>
                    <select id="branch_id" name="branch_id" class="pms-input">
                        <option value=""><?php esc_html_e('— None —', 'pms'); ?></option>
                        <?php foreach ($branches as $branch) : ?>
                            <option value="<?php echo esc_attr($branch->id); ?>" <?php selected($department->branch_id ?? '', $branch->id); ?>><?php echo esc_html($branch->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="pms_save_department" class="pms-btn-primary">
                    <?php echo $department ? esc_html__('Save changes', 'pms') : esc_html__('Add department', 'pms'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
<?php else :
    $departments = PMS_DB::get_departments();
    ?>
    <div class="pms-panel">
        <table class="pms-table">
            <thead><tr><th><?php esc_html_e('Name', 'pms'); ?></th><th><?php esc_html_e('Branch', 'pms'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if (empty($departments)) : ?>
                <tr><td colspan="3" class="pms-empty"><?php esc_html_e('No departments yet.', 'pms'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($departments as $dept) : ?>
                <tr>
                    <td><strong><?php echo esc_html($dept->name); ?></strong></td>
                    <td><?php echo esc_html(PMS_DB::branch_name($dept->branch_id)); ?></td>
                    <td class="pms-row-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-departments&action=edit&id=' . $dept->id)); ?>"><?php esc_html_e('Edit', 'pms'); ?></a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pms-departments&action=delete&id=' . $dept->id), 'pms_delete_department_' . $dept->id)); ?>"
                           class="pms-link-danger"
                           onclick="return confirm('<?php echo esc_js(__('Delete this department?', 'pms')); ?>');">
                            <?php esc_html_e('Delete', 'pms'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

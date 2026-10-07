<?php
if (! defined('ABSPATH')) {
    exit;
}

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
$branch_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$notice = '';

if (isset($_POST['pms_save_branch']) && check_admin_referer('pms_save_branch_action', 'pms_branch_nonce')) {
    $data = [
        'name'              => sanitize_text_field($_POST['name'] ?? ''),
        'address'           => sanitize_text_field($_POST['address'] ?? ''),
        'latitude'          => $_POST['latitude'] !== '' ? (float) $_POST['latitude'] : '',
        'longitude'         => $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : '',
        'geofence_radius_m' => absint($_POST['geofence_radius_m'] ?? 0),
    ];

    $editing_id = absint($_POST['branch_id'] ?? 0);

    if ($editing_id) {
        PMS_DB::update_branch($editing_id, $data);
        $notice = __('Branch updated.', 'pms');
    } else {
        PMS_DB::create_branch($data);
        $notice = __('Branch added.', 'pms');
    }

    $action = 'list';
}

if ($action === 'delete' && $branch_id && check_admin_referer('pms_delete_branch_' . $branch_id)) {
    PMS_DB::delete_branch($branch_id);
    $notice = __('Branch deleted.', 'pms');
    $action = 'list';
}

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Organization', 'pms'); ?></p>
        <h1><?php esc_html_e('Branches', 'pms'); ?></h1>
    </div>
    <?php if ($action === 'list') : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-branches&action=add')); ?>" class="pms-btn-primary">
            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('New branch', 'pms'); ?>
        </a>
    <?php else : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-branches')); ?>" class="pms-btn-secondary"><?php esc_html_e('← Back to list', 'pms'); ?></a>
    <?php endif; ?>
</div>

<?php if ($notice) : ?>
    <div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div>
<?php endif; ?>

<?php if (in_array($action, ['add', 'edit'], true)) :
    $branch = $action === 'edit' ? PMS_DB::get_branch($branch_id) : null;
    if ($action === 'edit' && ! $branch) :
        echo '<div class="pms-notice pms-notice-error">' . esc_html__('Branch not found.', 'pms') . '</div>';
    else :
        ?>
        <div class="pms-panel pms-form-panel">
            <form method="post">
                <?php wp_nonce_field('pms_save_branch_action', 'pms_branch_nonce'); ?>
                <input type="hidden" name="branch_id" value="<?php echo esc_attr($branch->id ?? 0); ?>">

                <div class="pms-form-row">
                    <label for="name"><?php esc_html_e('Name', 'pms'); ?></label>
                    <input type="text" id="name" name="name" required class="pms-input" value="<?php echo esc_attr($branch->name ?? ''); ?>">
                </div>

                <div class="pms-form-row">
                    <label for="address"><?php esc_html_e('Address', 'pms'); ?></label>
                    <input type="text" id="address" name="address" class="pms-input" value="<?php echo esc_attr($branch->address ?? ''); ?>">
                </div>

                <div class="pms-form-grid">
                    <div class="pms-form-row">
                        <label for="latitude"><?php esc_html_e('Latitude', 'pms'); ?></label>
                        <input type="text" inputmode="decimal" id="latitude" name="latitude" class="pms-input" placeholder="<?php esc_attr_e('e.g. 6.5980875', 'pms'); ?>" value="<?php echo esc_attr($branch->latitude ?? ''); ?>">
                    </div>
                    <div class="pms-form-row">
                        <label for="longitude"><?php esc_html_e('Longitude', 'pms'); ?></label>
                        <input type="text" inputmode="decimal" id="longitude" name="longitude" class="pms-input" placeholder="<?php esc_attr_e('e.g. 3.3411651', 'pms'); ?>" value="<?php echo esc_attr($branch->longitude ?? ''); ?>">
                    </div>
                </div>

                <div class="pms-form-row">
                    <label for="geofence_radius_m"><?php esc_html_e('Geofence radius (metres)', 'pms'); ?></label>
                    <input type="number" min="0" id="geofence_radius_m" name="geofence_radius_m" class="pms-input"
                           value="<?php echo esc_attr($branch->geofence_radius_m ?? get_option('pms_default_geofence_radius_m', 100)); ?>">
                </div>

                <button type="submit" name="pms_save_branch" class="pms-btn-primary">
                    <?php echo $branch ? esc_html__('Save changes', 'pms') : esc_html__('Add branch', 'pms'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
<?php else :
    $branches = PMS_DB::get_branches();
    ?>
    <div class="pms-panel">
        <table class="pms-table">
            <thead><tr><th><?php esc_html_e('Name', 'pms'); ?></th><th><?php esc_html_e('Address', 'pms'); ?></th><th><?php esc_html_e('Location', 'pms'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if (empty($branches)) : ?>
                <tr><td colspan="4" class="pms-empty"><?php esc_html_e('No branches yet.', 'pms'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($branches as $branch) : ?>
                <tr>
                    <td><strong><?php echo esc_html($branch->name); ?></strong></td>
                    <td><?php echo esc_html($branch->address ?: '—'); ?></td>
                    <td><?php echo $branch->latitude && $branch->longitude ? esc_html(round((float) $branch->latitude, 4) . ', ' . round((float) $branch->longitude, 4)) : '—'; ?></td>
                    <td class="pms-row-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-branches&action=edit&id=' . $branch->id)); ?>"><?php esc_html_e('Edit', 'pms'); ?></a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pms-branches&action=delete&id=' . $branch->id), 'pms_delete_branch_' . $branch->id)); ?>"
                           class="pms-link-danger"
                           onclick="return confirm('<?php echo esc_js(__('Delete this branch?', 'pms')); ?>');">
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

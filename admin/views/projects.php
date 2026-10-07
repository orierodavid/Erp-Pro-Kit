<?php
if (! defined('ABSPATH')) { exit; }
if (! current_user_can('pms_manage_tasks')) { wp_die(__('You do not have permission to view this page.', 'pms')); }

$notice = '';
$error = '';
$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
$id = absint($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pms_project_action'])) {
    check_admin_referer('pms_project_manage', 'pms_project_nonce');
    $post_action = sanitize_key(wp_unslash($_POST['pms_project_action']));
    if ($post_action === 'save') {
        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'status' => sanitize_key(wp_unslash($_POST['status'] ?? 'active')),
            'start_date' => sanitize_text_field(wp_unslash($_POST['start_date'] ?? '')),
            'end_date' => sanitize_text_field(wp_unslash($_POST['end_date'] ?? '')),
            'owner_id' => absint($_POST['owner_id'] ?? 0),
        ];
        if ($data['name'] === '') { $error = __('Project name is required.', 'pms'); $action = $id ? 'edit' : 'add'; }
        elseif ($data['end_date'] && $data['start_date'] && $data['end_date'] < $data['start_date']) { $error = __('The end date cannot be before the start date.', 'pms'); $action = $id ? 'edit' : 'add'; }
        else {
            if ($id) { PMS_DB::update_project($id, $data); $notice = __('Project updated.', 'pms'); }
            else { $id = PMS_DB::create_project($data); $notice = __('Project created.', 'pms'); }
            $action = 'list';
        }
    }
    if ($post_action === 'delete' && $id) { PMS_DB::delete_project($id); $notice = __('Project deleted. Tasks were left in place without a project.', 'pms'); $action = 'list'; }
    if ($post_action === 'set_task_project') {
        $task_id = absint($_POST['task_id'] ?? 0);
        $project_id = absint($_POST['project_id'] ?? 0);
        PMS_DB::set_task_project($task_id, $project_id ?: null);
        $notice = __('Task project assignment updated.', 'pms');
        $action = 'edit'; $id = $project_id;
    }
}

$people = PMS_Roles::get_pms_people();
$projects_rows = PMS_DB::get_projects();
$project = $id ? PMS_DB::get_project($id) : null;
$project_tasks = $project ? PMS_DB::get_project_tasks($id) : [];
$all_tasks = PMS_DB::get_all_tasks();

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header">
    <div><p class="pms-eyebrow"><?php esc_html_e('Work Management', 'pms'); ?></p><h1><?php esc_html_e('Projects', 'pms'); ?></h1><p class="pms-hint"><?php esc_html_e('Create projects and group the existing task records under the work they belong to.', 'pms'); ?></p></div>
    <?php if ($action === 'list') : ?><a class="pms-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=pms-projects&action=add')); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('New project','pms'); ?></a><?php else : ?><a class="pms-btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=pms-projects')); ?>"><?php esc_html_e('Back to projects','pms'); ?></a><?php endif; ?>
</div>
<?php if($notice): ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>
<?php if($error): ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>

<?php if (in_array($action, ['add','edit'], true)) : ?>
<div class="pms-panel pms-form-panel">
    <div class="pms-section-head"><div><h2><?php echo $project ? esc_html__('Edit project','pms') : esc_html__('Create project','pms'); ?></h2></div></div>
    <form method="post">
        <?php wp_nonce_field('pms_project_manage','pms_project_nonce'); ?><input type="hidden" name="pms_project_action" value="save">
        <div class="pms-form-grid">
            <div class="pms-form-row"><label><?php esc_html_e('Project name','pms'); ?></label><input class="pms-input" name="name" required value="<?php echo esc_attr($project->name ?? ''); ?>"></div>
            <div class="pms-form-row"><label><?php esc_html_e('Project owner','pms'); ?></label><select class="pms-input" name="owner_id"><option value=""><?php esc_html_e('Select owner','pms'); ?></option><?php foreach($people as $person): ?><option value="<?php echo esc_attr($person->ID); ?>" <?php selected($project->owner_id ?? '',$person->ID); ?>><?php echo esc_html($person->display_name); ?></option><?php endforeach; ?></select></div>
            <div class="pms-form-row"><label><?php esc_html_e('Status','pms'); ?></label><select class="pms-input" name="status"><option value="active" <?php selected($project->status ?? 'active','active'); ?>><?php esc_html_e('Active','pms'); ?></option><option value="on_hold" <?php selected($project->status ?? 'active','on_hold'); ?>><?php esc_html_e('On hold','pms'); ?></option><option value="completed" <?php selected($project->status ?? 'active','completed'); ?>><?php esc_html_e('Completed','pms'); ?></option><option value="archived" <?php selected($project->status ?? 'active','archived'); ?>><?php esc_html_e('Archived','pms'); ?></option></select></div>
            <div class="pms-form-row"><label><?php esc_html_e('Start date','pms'); ?></label><input class="pms-input" type="date" name="start_date" value="<?php echo esc_attr($project->start_date ?? ''); ?>"></div>
            <div class="pms-form-row"><label><?php esc_html_e('End date','pms'); ?></label><input class="pms-input" type="date" name="end_date" value="<?php echo esc_attr($project->end_date ?? ''); ?>"></div>
        </div>
        <div class="pms-form-row"><label><?php esc_html_e('Description','pms'); ?></label><textarea class="pms-input" rows="4" name="description"><?php echo esc_textarea($project->description ?? ''); ?></textarea></div>
        <button class="pms-btn-primary" type="submit"><span class="dashicons dashicons-saved"></span><?php esc_html_e('Save project','pms'); ?></button>
    </form>
</div>
<?php if($project): ?>
<div class="pms-panel">
<div class="pms-section-head"><div><h2><?php esc_html_e('Project tasks','pms'); ?></h2><p><?php esc_html_e('Attach existing tasks to this project without changing their task records or status.', 'pms'); ?></p></div></div>
<form method="post" class="pms-project-assign-form"><?php wp_nonce_field('pms_project_manage','pms_project_nonce'); ?><input type="hidden" name="pms_project_action" value="set_task_project"><input type="hidden" name="project_id" value="<?php echo esc_attr($project->id); ?>"><select class="pms-input" name="task_id" required><option value=""><?php esc_html_e('Select task to attach','pms'); ?></option><?php foreach($all_tasks as $task): ?><option value="<?php echo esc_attr($task->id); ?>"><?php echo esc_html($task->title); ?></option><?php endforeach; ?></select><button class="pms-btn-secondary" type="submit"><?php esc_html_e('Attach task','pms'); ?></button></form>
<table class="pms-table"><thead><tr><th><?php esc_html_e('Task','pms'); ?></th><th><?php esc_html_e('Assignee','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th><th><?php esc_html_e('Due','pms'); ?></th></tr></thead><tbody>
<?php if(empty($project_tasks)): ?><tr><td colspan="4" class="pms-empty"><?php esc_html_e('No tasks are attached to this project yet.','pms'); ?></td></tr><?php endif; ?>
<?php foreach($project_tasks as $task): ?><tr><td><?php echo esc_html($task->title); ?></td><td><?php echo esc_html($task->assignee_name ?: '—'); ?></td><td><?php echo esc_html(ucwords(str_replace('_',' ',$task->status))); ?></td><td><?php echo esc_html($task->due_date ?: '—'); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php else: ?>
<div class="pms-panel"><table class="pms-table"><thead><tr><th><?php esc_html_e('Project','pms'); ?></th><th><?php esc_html_e('Owner','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th><th><?php esc_html_e('Dates','pms'); ?></th><th><?php esc_html_e('Tasks','pms'); ?></th><th></th></tr></thead><tbody>
<?php if(empty($projects_rows)): ?><tr><td colspan="6" class="pms-empty"><?php esc_html_e('No projects have been created yet.','pms'); ?></td></tr><?php endif; ?>
<?php foreach($projects_rows as $row): ?><tr><td><strong><?php echo esc_html($row->name); ?></strong><?php if($row->description): ?><br><small><?php echo esc_html(wp_trim_words($row->description,18)); ?></small><?php endif; ?></td><td><?php echo esc_html($row->owner_name ?: '—'); ?></td><td><span class="pms-chip"><?php echo esc_html(ucwords(str_replace('_',' ',$row->status))); ?></span></td><td><?php echo esc_html(($row->start_date ?: '—').' → '.($row->end_date ?: '—')); ?></td><td><?php echo esc_html($row->task_count); ?></td><td><a class="pms-btn-secondary pms-btn-small" href="<?php echo esc_url(admin_url('admin.php?page=pms-projects&action=edit&id='.$row->id)); ?>"><?php esc_html_e('Open','pms'); ?></a> <form method="post" class="pms-inline-form"><?php wp_nonce_field('pms_project_manage','pms_project_nonce'); ?><input type="hidden" name="pms_project_action" value="delete"><input type="hidden" name="id" value="<?php echo esc_attr($row->id); ?>"><button class="pms-btn-danger pms-btn-small" type="submit"><?php esc_html_e('Delete','pms'); ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>
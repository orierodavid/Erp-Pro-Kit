<?php
if (! defined('ABSPATH')) { exit; }
if (! current_user_can('pms_manage_tasks')) { wp_die(__('You do not have permission to view this page.', 'pms')); }
$notice='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pms_assignment_action'])) {
    check_admin_referer('pms_assignment_manage','pms_assignment_nonce');
    if (sanitize_key(wp_unslash($_POST['pms_assignment_action']))==='assign') {
        $task_id=absint($_POST['task_id']??0); $user_id=absint($_POST['user_id']??0); $project_id=absint($_POST['project_id']??0);
        $task=PMS_DB::get_task($task_id);
        if($task){
            PMS_DB::update_task($task_id,[
                'title'=>$task->title,'description'=>$task->description,'status'=>$task->status,'priority'=>$task->priority,
                'assigned_to'=>$user_id ?: null,'department_id'=>$task->department_id,'due_date'=>$task->due_date,
                'work_mode'=>$task->work_mode,'address'=>$task->address,'latitude'=>$task->latitude,'longitude'=>$task->longitude,
                'geofence_radius_m'=>$task->geofence_radius_m,'scheduled_date'=>$task->scheduled_date,'scheduled_start_time'=>$task->scheduled_start_time
            ]);
            PMS_DB::set_task_project($task_id,$project_id?:null);
            $notice=__('Assignment updated.','pms');
        }
    }
}
$rows=PMS_DB::assignment_rows(); $people=PMS_Roles::get_pms_people(); $projects=PMS_DB::get_projects();
include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header"><div><p class="pms-eyebrow"><?php esc_html_e('Work Management','pms'); ?></p><h1><?php esc_html_e('Assignments','pms'); ?></h1><p class="pms-hint"><?php esc_html_e('Assign existing tasks to people and projects without changing the task workflow.','pms'); ?></p></div></div>
<?php if($notice): ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>
<div class="pms-panel pms-form-panel">
<div class="pms-section-head"><div><h2><?php esc_html_e('Update assignment','pms'); ?></h2><p><?php esc_html_e('Choose a task, assignee and optional project.','pms'); ?></p></div></div>
<form method="post"><?php wp_nonce_field('pms_assignment_manage','pms_assignment_nonce'); ?><input type="hidden" name="pms_assignment_action" value="assign"><div class="pms-form-grid">
<div class="pms-form-row"><label><?php esc_html_e('Task','pms'); ?></label><select class="pms-input" name="task_id" required><option value=""><?php esc_html_e('Select task','pms'); ?></option><?php foreach($rows as $row): ?><option value="<?php echo esc_attr($row->id); ?>"><?php echo esc_html($row->title); ?></option><?php endforeach; ?></select></div>
<div class="pms-form-row"><label><?php esc_html_e('Assignee','pms'); ?></label><select class="pms-input" name="user_id"><option value=""><?php esc_html_e('Unassigned','pms'); ?></option><?php foreach($people as $person): ?><option value="<?php echo esc_attr($person->ID); ?>"><?php echo esc_html($person->display_name); ?></option><?php endforeach; ?></select></div>
<div class="pms-form-row"><label><?php esc_html_e('Project','pms'); ?></label><select class="pms-input" name="project_id"><option value=""><?php esc_html_e('No project','pms'); ?></option><?php foreach($projects as $project): ?><option value="<?php echo esc_attr($project->id); ?>"><?php echo esc_html($project->name); ?></option><?php endforeach; ?></select></div>
</div><button class="pms-btn-primary" type="submit"><span class="dashicons dashicons-groups"></span><?php esc_html_e('Save assignment','pms'); ?></button></form></div>
<div class="pms-panel"><table class="pms-table"><thead><tr><th><?php esc_html_e('Task','pms'); ?></th><th><?php esc_html_e('Project','pms'); ?></th><th><?php esc_html_e('Assignee','pms'); ?></th><th><?php esc_html_e('Priority','pms'); ?></th><th><?php esc_html_e('Due','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th></tr></thead><tbody>
<?php if(empty($rows)): ?><tr><td colspan="6" class="pms-empty"><?php esc_html_e('No tasks exist yet.','pms'); ?></td></tr><?php endif; ?>
<?php foreach($rows as $row): ?><tr><td><strong><?php echo esc_html($row->title); ?></strong></td><td><?php echo esc_html($row->project_name ?: '—'); ?></td><td><?php echo esc_html($row->assignee_name ?: 'Unassigned'); ?></td><td><?php echo esc_html(ucfirst($row->priority)); ?></td><td><?php echo esc_html($row->due_date ?: '—'); ?></td><td><?php echo esc_html(ucwords(str_replace('_',' ',$row->status))); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>
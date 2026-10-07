<?php
if (! defined('ABSPATH')) { exit; }
if (! current_user_can('pms_view_reports')) { wp_die(__('You do not have permission to view this page.', 'pms')); }

$projects=PMS_DB::project_report_rows();
$all_tasks=PMS_DB::get_all_tasks();
$total=count($all_tasks);
$done=count(array_filter($all_tasks,static fn($t)=>$t->status==='done'));
$progress=count(array_filter($all_tasks,static fn($t)=>$t->status==='in_progress'));
$todo=count(array_filter($all_tasks,static fn($t)=>$t->status==='todo'));
$assigned=count(array_filter($all_tasks,static fn($t)=>(int)$t->assigned_to>0));

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div class="pms-page-header"><div><p class="pms-eyebrow"><?php esc_html_e('Work Management','pms'); ?></p><h1><?php esc_html_e('Task Reports','pms'); ?></h1><p class="pms-hint"><?php esc_html_e('Live reporting from the existing project and task records.','pms'); ?></p></div></div>
<div class="pms-kpi-grid">
<div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Total tasks','pms'); ?></span><strong><?php echo esc_html($total); ?></strong></div>
<div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('To do','pms'); ?></span><strong><?php echo esc_html($todo); ?></strong></div>
<div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('In progress','pms'); ?></span><strong><?php echo esc_html($progress); ?></strong></div>
<div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Done','pms'); ?></span><strong><?php echo esc_html($done); ?></strong></div>
<div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Assigned','pms'); ?></span><strong><?php echo esc_html($assigned); ?></strong></div>
</div>
<div class="pms-panel"><div class="pms-section-head"><div><h2><?php esc_html_e('Project performance','pms'); ?></h2><p><?php esc_html_e('Task counts are calculated from the current task records attached to each project.','pms'); ?></p></div></div>
<table class="pms-table"><thead><tr><th><?php esc_html_e('Project','pms'); ?></th><th><?php esc_html_e('Owner','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th><th><?php esc_html_e('Tasks','pms'); ?></th><th><?php esc_html_e('To do','pms'); ?></th><th><?php esc_html_e('In progress','pms'); ?></th><th><?php esc_html_e('Completed','pms'); ?></th><th><?php esc_html_e('Completion','pms'); ?></th></tr></thead><tbody>
<?php if(empty($projects)): ?><tr><td colspan="8" class="pms-empty"><?php esc_html_e('No projects have been created yet.','pms'); ?></td></tr><?php endif; ?>
<?php foreach($projects as $p): $pt=(int)$p->total_tasks; $pc=(int)$p->completed_tasks; $pct=$pt?round(($pc/$pt)*100):0; ?><tr><td><strong><?php echo esc_html($p->name); ?></strong></td><td><?php echo esc_html($p->owner_name ?: '—'); ?></td><td><?php echo esc_html(ucwords(str_replace('_',' ',$p->status))); ?></td><td><?php echo esc_html($pt); ?></td><td><?php echo esc_html((int)$p->todo_tasks); ?></td><td><?php echo esc_html((int)$p->in_progress_tasks); ?></td><td><?php echo esc_html($pc); ?></td><td><strong><?php echo esc_html($pct); ?>%</strong></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="pms-panel"><div class="pms-section-head"><div><h2><?php esc_html_e('Task status summary','pms'); ?></h2></div></div>
<div class="pms-report-bars">
<div><span><?php esc_html_e('To do','pms'); ?></span><b style="width:<?php echo $total?esc_attr(($todo/$total)*100):0; ?>%"></b><strong><?php echo esc_html($todo); ?></strong></div>
<div><span><?php esc_html_e('In progress','pms'); ?></span><b style="width:<?php echo $total?esc_attr(($progress/$total)*100):0; ?>%"></b><strong><?php echo esc_html($progress); ?></strong></div>
<div><span><?php esc_html_e('Done','pms'); ?></span><b style="width:<?php echo $total?esc_attr(($done/$total)*100):0; ?>%"></b><strong><?php echo esc_html($done); ?></strong></div>
</div></div>
<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>
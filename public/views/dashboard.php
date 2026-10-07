<?php
if (! defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$tasks = PMS_DB::get_tasks_for_user($user_id);
$task_statuses = PMS_Constants::task_statuses();
$work_modes = PMS_Constants::work_modes();
?>
<div class="pms-wrap pms-frontend">
    <div class="pms-panel">
        <div class="pms-panel-heading">
            <h2><?php esc_html_e('My Tasks', 'pms'); ?></h2>
        </div>
        <div style="padding:0 20px;">
            <?php if (empty($tasks)) : ?>
                <p class="pms-empty"><?php esc_html_e('No tasks assigned.', 'pms'); ?></p>
            <?php endif; ?>
            <?php foreach ($tasks as $task) : ?>
                <div style="padding:16px 0;border-bottom:1px solid var(--pms-border);">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div>
                            <strong><?php echo esc_html($task->title); ?></strong>
                            <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;">
                                <span class="pms-chip pms-status-<?php echo esc_attr($task->status); ?>"><?php echo esc_html(PMS_Constants::label_for($task_statuses, $task->status)); ?></span>
                                <span class="pms-chip pms-priority-<?php echo esc_attr($task->priority); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_priorities(), $task->priority)); ?></span>
                                <span class="pms-chip pms-status-todo"><?php echo esc_html(PMS_Constants::label_for($work_modes, $task->work_mode ?: 'remote')); ?></span>
                            </div>
                            <?php if ($task->work_mode === 'location_based' && $task->address) : ?>
                                <p style="margin:8px 0 0;font-size:12px;color:var(--pms-muted);"><span class="dashicons dashicons-location" style="font-size:14px;width:14px;height:14px;"></span> <?php echo esc_html($task->address); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if (! $task->started_at) : ?>
                            <button type="button" class="pms-clock-cta pms-start-task-btn"
                                    data-task-id="<?php echo esc_attr($task->id); ?>"
                                    data-location-based="<?php echo $task->work_mode === 'location_based' ? '1' : '0'; ?>"
                                    style="min-width:auto;">
                                <?php esc_html_e('Start', 'pms'); ?>
                            </button>
                        <?php elseif (! $task->completed_at) : ?>
                            <span class="pms-chip <?php echo $task->punctuality === 'late' ? 'pms-priority-high' : 'pms-status-done'; ?>">
                                <?php echo esc_html($task->punctuality === 'late' ? __('Started late', 'pms') : __('Started on time', 'pms')); ?>
                            </span>
                        <?php else : ?>
                            <span class="pms-chip pms-status-done"><?php esc_html_e('Completed', 'pms'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="pms-location-note" data-note-for="<?php echo esc_attr($task->id); ?>" style="margin:8px 0 0;"></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p style="text-align:center;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-my-tasks')); ?>"><?php esc_html_e('Open full task view →', 'pms'); ?></a>
    </p>
</div>


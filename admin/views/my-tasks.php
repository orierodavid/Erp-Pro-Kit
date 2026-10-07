<?php
if (! defined('ABSPATH')) {
    exit;
}

$pms_max_attachment_bytes = 10 * 1024 * 1024; // 10MB, same ceiling as the Admin Tasks screen

$user_id = get_current_user_id();
$view_task_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$notice = '';
$error = '';

// ---- Status update (from either the list or the detail view) ----
if (isset($_POST['pms_update_status']) && check_admin_referer('pms_update_own_task_action', 'pms_own_task_nonce')) {
    $task_id = absint($_POST['task_id'] ?? 0);
    $task = PMS_DB::get_task($task_id);

    // A staff member may only ever act on a task actually assigned to them.
    if ($task && (int) $task->assigned_to === $user_id) {
        PMS_DB::update_task_status($task_id, sanitize_key($_POST['status'] ?? $task->status));
        $notice = __('Task status updated.', 'pms');
        $view_task_id = $task_id;
    }
}

// ---- Add a comment ----
if (isset($_POST['pms_add_comment']) && check_admin_referer('pms_add_own_task_comment_action', 'pms_own_comment_nonce')) {
    $task_id = absint($_POST['task_id'] ?? 0);
    $task = PMS_DB::get_task($task_id);
    $comment_text = sanitize_textarea_field($_POST['comment'] ?? '');

    if ($task && (int) $task->assigned_to === $user_id && $comment_text !== '') {
        PMS_DB::add_comment($task_id, $user_id, $comment_text);
        $notice = __('Comment added.', 'pms');
    }

    $view_task_id = $task_id;
}

// ---- Upload a response file ----
if (isset($_FILES['response_file']) && isset($_POST['pms_upload_response']) && check_admin_referer('pms_upload_response_action', 'pms_upload_response_nonce')) {
    $task_id = absint($_POST['task_id'] ?? 0);
    $task = PMS_DB::get_task($task_id);
    $view_task_id = $task_id;

    if ($task && (int) $task->assigned_to === $user_id && $_FILES['response_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if (in_array($_FILES['response_file']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $error = sprintf(
                /* translators: %s: the server's current upload limit */
                __('That file was rejected by the server before it reached the plugin — the server\'s upload limit is currently %s. Ask an administrator to raise it.', 'pms'),
                size_format(wp_max_upload_size())
            );
        } elseif ($_FILES['response_file']['error'] !== UPLOAD_ERR_OK) {
            $error = __('The file failed to upload. Please try again.', 'pms');
        } elseif ($_FILES['response_file']['size'] > $pms_max_attachment_bytes) {
            $error = __('That file is larger than the 10MB limit and was not attached.', 'pms');
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded = wp_handle_upload($_FILES['response_file'], ['test_form' => false]);

            if (isset($uploaded['error'])) {
                $error = $uploaded['error'];
            } else {
                PMS_DB::create_attachment($task_id, [
                    'url'  => $uploaded['url'],
                    'path' => $uploaded['file'],
                    'name' => sanitize_file_name($_FILES['response_file']['name']),
                    'size' => $_FILES['response_file']['size'],
                ], $user_id);
                $notice = __('File uploaded — your admin can see it on this task.', 'pms');
            }
        }
    }
}

$task_statuses = PMS_Constants::task_statuses();

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<?php if ($view_task_id) :
    $task = PMS_DB::get_task($view_task_id);
    if (! $task || (int) $task->assigned_to !== $user_id) :
        ?>
        <div class="pms-page-header">
            <div><h1><?php esc_html_e('My Tasks', 'pms'); ?></h1></div>
        </div>
        <div class="pms-notice pms-notice-error"><?php esc_html_e('That task was not found, or is not assigned to you.', 'pms'); ?></div>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=pms-my-tasks')); ?>" class="pms-btn-secondary"><?php esc_html_e('← Back to my tasks', 'pms'); ?></a></p>
    <?php else :
        $comments = PMS_DB::get_comments_for_task($task->id);
        $attachments = PMS_DB::get_attachments_for_task($task->id);
        ?>
        <div class="pms-page-header">
            <div>
                <p class="pms-eyebrow"><?php esc_html_e('My Tasks', 'pms'); ?></p>
                <h1><?php echo esc_html($task->title); ?></h1>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pms-my-tasks')); ?>" class="pms-btn-secondary"><?php esc_html_e('← Back to my tasks', 'pms'); ?></a>
        </div>

        <?php if ($notice) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>

        <div class="pms-panel pms-form-panel">
            <p style="margin-top:0;color:var(--pms-text);white-space:pre-wrap;"><?php echo esc_html($task->description ?: __('No description provided.', 'pms')); ?></p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
                <span class="pms-chip pms-priority-<?php echo esc_attr($task->priority); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_priorities(), $task->priority)); ?></span>
                <span class="pms-chip pms-status-todo"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::work_modes(), $task->work_mode ?: 'remote')); ?></span>
                <?php if ($task->work_mode === 'location_based' && $task->address) : ?>
                    <span class="pms-chip pms-status-todo"><span class="dashicons dashicons-location" style="font-size:12px;width:12px;height:12px;"></span> <?php echo esc_html($task->address); ?></span>
                <?php endif; ?>
                <?php if ($task->scheduled_date || $task->scheduled_start_time) : ?>
                    <span class="pms-chip pms-status-todo"><?php echo esc_html(trim(($task->scheduled_date ?: '') . ' ' . ($task->scheduled_start_time ?: ''))); ?></span>
                <?php endif; ?>
            </div>

            <?php if (! $task->started_at) : ?>
                <div id="pms-start-task-area">
                    <?php if ($task->work_mode === 'location_based') : ?>
                        <p style="color:var(--pms-muted);font-size:12px;">
                            <?php esc_html_e('This is a location based task — you\'ll need to allow location access, and be within range of the task location, to start it.', 'pms'); ?>
                        </p>
                    <?php endif; ?>
                    <p id="pms-start-note" style="color:var(--pms-muted);font-size:12px;"></p>
                    <button type="button" id="pms-start-task-btn" class="pms-btn-primary" data-task-id="<?php echo esc_attr($task->id); ?>" data-location-based="<?php echo $task->work_mode === 'location_based' ? '1' : '0'; ?>">
                        <?php esc_html_e('Start task', 'pms'); ?>
                    </button>
                </div>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field('pms_update_own_task_action', 'pms_own_task_nonce'); ?>
                    <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id); ?>">
                    <div class="pms-form-row" style="max-width:260px;">
                        <label for="status"><?php esc_html_e('Status', 'pms'); ?></label>
                        <select id="status" name="status" class="pms-input" onchange="document.getElementById('pms_update_status_btn').click()">
                            <?php foreach (['in_progress', 'done'] as $key) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($task->status, $key); ?>><?php echo esc_html($task_statuses[$key]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="pms_update_status" id="pms_update_status_btn" class="pms-btn-secondary" style="display:none;">Update</button>
                </form>
                <p style="margin:14px 0 0;font-size:12px;color:var(--pms-muted);">
                    <?php echo esc_html(sprintf(__('Started %s', 'pms'), date_i18n('M j, g:i a', strtotime($task->started_at)))); ?> —
                    <span class="pms-chip <?php echo $task->punctuality === 'late' ? 'pms-priority-high' : 'pms-status-done'; ?>" style="margin-left:4px;">
                        <?php echo esc_html($task->punctuality === 'late' ? __('Late', 'pms') : __('Early', 'pms')); ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>

        <div class="pms-panel pms-form-panel">
            <h2 style="margin-top:0;"><?php esc_html_e('Upload a response', 'pms'); ?></h2>
            <p class="description" style="color:var(--pms-muted);font-size:12px;margin-top:-6px;">
                <?php esc_html_e('Attach a file for your admin to review — a photo of completed work, a document, anything relevant. Maximum 10MB.', 'pms'); ?>
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pms_upload_response_action', 'pms_upload_response_nonce'); ?>
                <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id); ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr($pms_max_attachment_bytes); ?>">
                <div class="pms-form-row">
                    <input type="file" name="response_file" class="pms-input" style="padding:8px 13px;">
                </div>
                <button type="submit" name="pms_upload_response" class="pms-btn-primary"><?php esc_html_e('Upload', 'pms'); ?></button>
            </form>

            <?php if (! empty($attachments)) : ?>
                <ul style="margin:18px 0 0;padding:0;list-style:none;">
                    <?php foreach ($attachments as $file) : ?>
                        <li style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:#fafbfc;border-radius:8px;margin-bottom:6px;font-size:12px;">
                            <span class="dashicons dashicons-paperclip"></span>
                            <a href="<?php echo esc_url($file->file_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($file->file_name); ?></a>
                            <span style="color:var(--pms-muted);">(<?php echo esc_html(size_format((int) $file->file_size)); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="pms-panel">
            <div class="pms-panel-heading">
                <h2><?php esc_html_e('Notes', 'pms'); ?></h2>
            </div>
            <div style="padding:16px 20px;">
                <?php if (empty($comments)) : ?>
                    <p style="color:var(--pms-muted);margin:0 0 16px;"><?php esc_html_e('No notes yet.', 'pms'); ?></p>
                <?php else : ?>
                    <?php foreach ($comments as $comment) :
                        $author = get_userdata($comment->user_id);
                        ?>
                        <div style="display:flex;gap:10px;margin-bottom:16px;">
                            <?php echo get_avatar($comment->user_id, 30); ?>
                            <div>
                                <div style="font-size:12px;"><strong><?php echo esc_html($author ? $author->display_name : __('Unknown', 'pms')); ?></strong> <span style="color:var(--pms-muted);"><?php echo esc_html(human_time_diff(strtotime($comment->created_at), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'pms'); ?></span></div>
                                <div style="font-size:13px;color:var(--pms-text);white-space:pre-wrap;"><?php echo esc_html($comment->comment); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="post">
                    <?php wp_nonce_field('pms_add_own_task_comment_action', 'pms_own_comment_nonce'); ?>
                    <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id); ?>">
                    <div class="pms-form-row">
                        <textarea name="comment" rows="3" class="pms-input" placeholder="<?php esc_attr_e('Add a note for your admin…', 'pms'); ?>" required></textarea>
                    </div>
                    <button type="submit" name="pms_add_comment" class="pms-btn-primary"><?php esc_html_e('Post note', 'pms'); ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php else :
    $my_tasks = PMS_DB::get_tasks_for_user($user_id);
    ?>
    <div class="pms-page-header">
        <div>
            <p class="pms-eyebrow"><?php esc_html_e('Workspace', 'pms'); ?></p>
            <h1><?php esc_html_e('My Tasks', 'pms'); ?></h1>
        </div>
    </div>

    <?php if ($notice) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>

    <div class="pms-panel">
        <table class="pms-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'pms'); ?></th>
                    <th><?php esc_html_e('Priority', 'pms'); ?></th>
                    <th><?php esc_html_e('Work mode', 'pms'); ?></th>
                    <th><?php esc_html_e('Due', 'pms'); ?></th>
                    <th><?php esc_html_e('Status', 'pms'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($my_tasks)) : ?>
                <tr><td colspan="6" class="pms-empty"><?php esc_html_e('Nothing assigned to you yet.', 'pms'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($my_tasks as $task) : ?>
                <tr>
                    <td><strong><?php echo esc_html($task->title); ?></strong></td>
                    <td><span class="pms-chip pms-priority-<?php echo esc_attr($task->priority); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_priorities(), $task->priority)); ?></span></td>
                    <td><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::work_modes(), $task->work_mode ?: 'remote')); ?></td>
                    <td><?php echo esc_html($task->due_date ?: '—'); ?></td>
                    <td><span class="pms-chip pms-status-<?php echo esc_attr($task->status); ?>"><?php echo esc_html(PMS_Constants::label_for($task_statuses, $task->status)); ?></span></td>
                    <td class="pms-row-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-my-tasks&id=' . $task->id)); ?>"><?php esc_html_e('Open →', 'pms'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

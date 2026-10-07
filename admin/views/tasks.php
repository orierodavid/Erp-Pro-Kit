<?php
if (! defined('ABSPATH')) {
    exit;
}

$pms_max_attachment_bytes = 10 * 1024 * 1024; // 10MB — enforced here regardless of php.ini upload_max_filesize

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
$task_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$notice = '';
$error = '';

// ---- Handle the add/edit form submission ----

if (isset($_POST['pms_save_task']) && check_admin_referer('pms_save_task_action', 'pms_task_nonce')) {
    $data = [
        'title'                => sanitize_text_field($_POST['title'] ?? ''),
        'description'          => sanitize_textarea_field($_POST['description'] ?? ''),
        'status'               => sanitize_key($_POST['status'] ?? 'todo'),
        'priority'             => sanitize_key($_POST['priority'] ?? 'medium'),
        'assigned_to'          => absint($_POST['assigned_to'] ?? 0) ?: null,
        'department_id'        => absint($_POST['department_id'] ?? 0) ?: null,
        'due_date'             => sanitize_text_field($_POST['due_date'] ?? '') ?: null,
        'work_mode'            => in_array($_POST['work_mode'] ?? '', ['remote', 'location_based'], true) ? $_POST['work_mode'] : 'remote',
        'address'              => sanitize_text_field($_POST['address'] ?? '') ?: null,
        'latitude'             => is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : '',
        'longitude'            => is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : '',
        'geofence_radius_m'    => absint($_POST['geofence_radius_m'] ?? 0),
        'scheduled_date'       => sanitize_text_field($_POST['scheduled_date'] ?? '') ?: null,
        'scheduled_start_time' => sanitize_text_field($_POST['scheduled_start_time'] ?? '') ?: null,
    ];

    // A remote task never carries location data, even if the browser
    // submitted stale hidden-field values from a mode switch.
    if ($data['work_mode'] === 'remote') {
        $data['address'] = null;
        $data['latitude'] = '';
        $data['longitude'] = '';
        $data['geofence_radius_m'] = 0;
    }

    // Validate manually-entered coordinates. A silently wrong coordinate
    // would make every future geofence check on this task meaningless,
    // so it's worth blocking the save rather than storing bad data.
    $coord_error = '';
    if ($data['work_mode'] === 'location_based') {
        $has_lat = $data['latitude'] !== '';
        $has_lng = $data['longitude'] !== '';

        if ($has_lat !== $has_lng) {
            $coord_error = __('Enter both latitude and longitude, or neither.', 'pms');
        } elseif ($has_lat) {
            $lat = (float) $data['latitude'];
            $lng = (float) $data['longitude'];

            if ($lat < -90 || $lat > 90) {
                $coord_error = __('Latitude must be between −90 and 90.', 'pms');
            } elseif ($lng < -180 || $lng > 180) {
                $coord_error = __('Longitude must be between −180 and 180.', 'pms');
            } elseif ($lat === 0.0 && $lng === 0.0) {
                $coord_error = __('Those coordinates point to the middle of the ocean — please check them.', 'pms');
            }
        }
    }

    $editing_id = absint($_POST['task_id'] ?? 0);

    if ($coord_error) {
        // Don't save anything — bounce back to the form with the error
        // so bad coordinates never reach the database.
        $error = $coord_error;
        $action = $editing_id ? 'edit' : 'add';
        $task_id = $editing_id;
    } else {
        $saved_task_id = $editing_id;

        if ($editing_id) {
            PMS_DB::update_task($editing_id, $data);
            $notice = __('Task updated.', 'pms');
        } else {
            $saved_task_id = PMS_DB::create_task($data, get_current_user_id());
            $notice = __('Task created.', 'pms');
        }

        // ---- Attachment upload (optional — only if a file was actually chosen) ----
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            if (in_array($_FILES['attachment']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $error = sprintf(
                    /* translators: %s: the server's current upload limit */
                    __('That file was rejected by the server before it reached the plugin — your server\'s PHP upload limit is currently %s, lower than the 10MB this plugin allows. Ask your host or server admin to raise upload_max_filesize and post_max_size in php.ini.', 'pms'),
                    size_format(wp_max_upload_size())
                );
            } elseif ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $error = __('The file failed to upload. Please try again.', 'pms');
            } elseif ($_FILES['attachment']['size'] > $pms_max_attachment_bytes) {
                $error = __('That file is larger than the 10MB limit and was not attached.', 'pms');
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $uploaded = wp_handle_upload($_FILES['attachment'], ['test_form' => false]);

                if (isset($uploaded['error'])) {
                    $error = $uploaded['error'];
                } else {
                    PMS_DB::create_attachment($saved_task_id, [
                        'url'  => $uploaded['url'],
                        'path' => $uploaded['file'],
                        'name' => sanitize_file_name($_FILES['attachment']['name']),
                        'size' => $_FILES['attachment']['size'],
                    ], get_current_user_id());
                }
            }
        }

        if ($error) {
            // Keep them on the edit form (not the list) so they can see what happened and retry the upload.
            $action = 'edit';
            $task_id = $saved_task_id;
        } else {
            $action = 'list';
        }
    }
}

// ---- Delete a task entirely ----
if ($action === 'delete' && $task_id && check_admin_referer('pms_delete_task_' . $task_id)) {
    PMS_DB::delete_task($task_id);
    $notice = __('Task deleted.', 'pms');
    $action = 'list';
}

// ---- Add a reply/note (visible to whoever the task is assigned to) ----
if (isset($_POST['pms_add_comment']) && check_admin_referer('pms_add_task_comment_action', 'pms_comment_nonce')) {
    $comment_task_id = absint($_POST['task_id'] ?? 0);
    $comment_text = sanitize_textarea_field($_POST['comment'] ?? '');

    if ($comment_task_id && $comment_text !== '') {
        PMS_DB::add_comment($comment_task_id, get_current_user_id(), $comment_text);
        $notice = __('Note added.', 'pms');
    }

    $action = 'edit';
    $task_id = $comment_task_id;
}

// ---- Delete a single attachment (stays on the edit screen) ----
if ($action === 'delete_attachment') {
    $attachment_id = isset($_GET['attachment_id']) ? absint($_GET['attachment_id']) : 0;
    $return_task_id = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;

    if ($attachment_id && check_admin_referer('pms_delete_attachment_' . $attachment_id)) {
        PMS_DB::delete_attachment($attachment_id);
        $notice = __('Attachment removed.', 'pms');
    }

    $action = 'edit';
    $task_id = $return_task_id;
}

$staff_and_admins = PMS_Roles::get_pms_people();
$departments = PMS_DB::get_departments();

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Workspace', 'pms'); ?></p>
        <h1><?php esc_html_e('Tasks', 'pms'); ?></h1>
    </div>
    <?php if ($action === 'list') : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-tasks&action=add')); ?>" class="pms-btn-primary">
            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('New task', 'pms'); ?>
        </a>
    <?php else : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-tasks')); ?>" class="pms-btn-secondary">
            <?php esc_html_e('← Back to list', 'pms'); ?>
        </a>
    <?php endif; ?>
</div>

<?php if ($notice) : ?>
    <div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div>
<?php endif; ?>
<?php if ($error) : ?>
    <div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div>
<?php endif; ?>

<?php if (in_array($action, ['add', 'edit'], true)) :
    $task = $action === 'edit' ? PMS_DB::get_task($task_id) : null;

    // If a coordinate validation error bounced us back here, keep what
    // the user actually typed rather than silently reverting to the
    // stored values — otherwise their correction disappears on error.
    if (! empty($coord_error)) {
        $task = (object) array_merge((array) ($task ?: new stdClass()), [
            'title'                => $data['title'],
            'description'          => $data['description'],
            'status'               => $data['status'],
            'priority'             => $data['priority'],
            'assigned_to'          => $data['assigned_to'],
            'department_id'        => $data['department_id'],
            'due_date'             => $data['due_date'],
            'work_mode'            => $data['work_mode'],
            'address'              => $data['address'],
            'latitude'             => $data['latitude'],
            'longitude'            => $data['longitude'],
            'geofence_radius_m'    => $data['geofence_radius_m'],
            'scheduled_date'       => $data['scheduled_date'],
            'scheduled_start_time' => $data['scheduled_start_time'],
            'id'                   => $task_id,
        ]);
    }
    if ($action === 'edit' && ! $task) :
        echo '<div class="pms-notice pms-notice-error">' . esc_html__('Task not found.', 'pms') . '</div>';
    else :
        $attachments = $task ? PMS_DB::get_attachments_for_task($task->id) : [];
        ?>
        <div class="pms-panel pms-form-panel">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pms_save_task_action', 'pms_task_nonce'); ?>
                <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id ?? 0); ?>">

                <div class="pms-form-row">
                    <label for="title"><?php esc_html_e('Title', 'pms'); ?></label>
                    <input type="text" id="title" name="title" required class="pms-input" value="<?php echo esc_attr($task->title ?? ''); ?>">
                </div>

                <div class="pms-form-row">
                    <label for="description"><?php esc_html_e('Description', 'pms'); ?></label>
                    <textarea id="description" name="description" rows="4" class="pms-input"><?php echo esc_textarea($task->description ?? ''); ?></textarea>
                </div>

                <div class="pms-form-grid">
                    <div class="pms-form-row">
                        <label for="status"><?php esc_html_e('Status', 'pms'); ?></label>
                        <select id="status" name="status" class="pms-input">
                            <?php foreach (PMS_Constants::task_statuses() as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($task->status ?? 'todo', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pms-form-row">
                        <label for="priority"><?php esc_html_e('Priority', 'pms'); ?></label>
                        <select id="priority" name="priority" class="pms-input">
                            <?php foreach (PMS_Constants::task_priorities() as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($task->priority ?? 'medium', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pms-form-row">
                    <label for="work_mode"><?php esc_html_e('Work mode', 'pms'); ?></label>
                    <select id="work_mode" name="work_mode" class="pms-input">
                        <?php foreach (PMS_Constants::work_modes() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($task->work_mode ?? 'remote', $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="margin-top:6px;color:var(--pms-muted);font-size:11px;">
                        <?php esc_html_e('Location based tasks require the assignee to verify their GPS location before the task can start. Remote tasks track start/completion time only.', 'pms'); ?>
                    </p>
                </div>

                <div id="pms-location-fields" style="<?php echo ($task->work_mode ?? 'remote') === 'location_based' ? '' : 'display:none;'; ?>">
                    <div class="pms-form-row">
                        <label for="address"><?php esc_html_e('Task location', 'pms'); ?></label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="address" name="address" class="pms-input" placeholder="<?php esc_attr_e('e.g. 14 Marina Road, Lagos', 'pms'); ?>" value="<?php echo esc_attr($task->address ?? ''); ?>">
                            <button type="button" id="pms-verify-location" class="pms-btn-secondary" style="white-space:nowrap;"><?php esc_html_e('Verify location', 'pms'); ?></button>
                        </div>
                        <p id="pms-location-result" style="margin-top:8px;font-size:12px;color:var(--pms-muted);">
                            <?php if (($task->latitude ?? null) !== null) : ?>
                                <span class="dashicons dashicons-yes" style="color:var(--pms-success);"></span>
                                <?php esc_html_e('Verified location on file.', 'pms'); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="pms-form-row">
                        <label><?php esc_html_e('Coordinates', 'pms'); ?></label>
                        <p class="description" style="margin:0 0 8px;color:var(--pms-muted);font-size:11px;">
                            <?php esc_html_e('Filled automatically when you verify an address. If the address can\'t be found — a new development, an unnamed road — enter the coordinates manually instead. You can copy these from Google Maps: right-click the spot, and the first item in the menu is "latitude, longitude" in that order.', 'pms'); ?>
                        </p>
                        <div class="pms-form-grid">
                            <div>
                                <label for="latitude" style="font-weight:400;font-size:11px;"><?php esc_html_e('Latitude (north–south, −90 to 90)', 'pms'); ?></label>
                                <input type="text" inputmode="decimal" id="latitude" name="latitude" class="pms-input"
                                       placeholder="<?php esc_attr_e('e.g. 6.5980875', 'pms'); ?>"
                                       value="<?php echo esc_attr($task->latitude ?? ''); ?>">
                            </div>
                            <div>
                                <label for="longitude" style="font-weight:400;font-size:11px;"><?php esc_html_e('Longitude (east–west, −180 to 180)', 'pms'); ?></label>
                                <input type="text" inputmode="decimal" id="longitude" name="longitude" class="pms-input"
                                       placeholder="<?php esc_attr_e('e.g. 3.3411651', 'pms'); ?>"
                                       value="<?php echo esc_attr($task->longitude ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="pms-form-row">
                        <label for="geofence_radius_m"><?php esc_html_e('Geofence radius (metres)', 'pms'); ?></label>
                        <input type="number" min="0" id="geofence_radius_m" name="geofence_radius_m" class="pms-input"
                               value="<?php echo esc_attr($task->geofence_radius_m ?? get_option('pms_default_geofence_radius_m', 100)); ?>">
                    </div>
                </div>

                <div class="pms-form-grid">
                    <div class="pms-form-row">
                        <label for="scheduled_date"><?php esc_html_e('Scheduled date', 'pms'); ?></label>
                        <input type="date" id="scheduled_date" name="scheduled_date" class="pms-input" value="<?php echo esc_attr($task->scheduled_date ?? ''); ?>">
                    </div>
                    <div class="pms-form-row">
                        <label for="scheduled_start_time"><?php esc_html_e('Scheduled start time', 'pms'); ?></label>
                        <input type="time" id="scheduled_start_time" name="scheduled_start_time" class="pms-input" value="<?php echo esc_attr($task->scheduled_start_time ?? ''); ?>">
                        <p class="description" style="margin-top:6px;color:var(--pms-muted);font-size:11px;">
                            <?php esc_html_e('Leave blank to use the company default workday start time from Settings.', 'pms'); ?>
                        </p>
                    </div>
                </div>

                <div class="pms-form-grid">
                    <div class="pms-form-row">
                        <label for="assigned_to"><?php esc_html_e('Assign to', 'pms'); ?></label>
                        <select id="assigned_to" name="assigned_to" class="pms-input">
                            <option value=""><?php esc_html_e('— Unassigned —', 'pms'); ?></option>
                            <?php foreach ($staff_and_admins as $person) : ?>
                                <option value="<?php echo esc_attr($person->ID); ?>" <?php selected($task->assigned_to ?? '', $person->ID); ?>><?php echo esc_html($person->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pms-form-row">
                        <label for="department_id"><?php esc_html_e('Department', 'pms'); ?></label>
                        <select id="department_id" name="department_id" class="pms-input">
                            <option value=""><?php esc_html_e('— None —', 'pms'); ?></option>
                            <?php foreach ($departments as $dept) : ?>
                                <option value="<?php echo esc_attr($dept->id); ?>" <?php selected($task->department_id ?? '', $dept->id); ?>><?php echo esc_html($dept->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pms-form-row">
                    <label for="due_date"><?php esc_html_e('Due date', 'pms'); ?></label>
                    <input type="date" id="due_date" name="due_date" class="pms-input" value="<?php echo esc_attr($task->due_date ?? ''); ?>">
                </div>

                <div class="pms-form-row">
                    <label for="attachment"><?php esc_html_e('Attach a file', 'pms'); ?></label>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr($pms_max_attachment_bytes); ?>">
                    <input type="file" id="attachment" name="attachment" class="pms-input" style="padding:8px 13px;">
                    <p class="description" style="margin-top:6px;color:var(--pms-muted);font-size:11px;">
                        <?php esc_html_e('Maximum file size: 10MB.', 'pms'); ?>
                    </p>
                </div>

                <?php if (! empty($attachments)) : ?>
                    <div class="pms-form-row">
                        <label><?php esc_html_e('Existing attachments', 'pms'); ?></label>
                        <ul style="margin:0;padding:0;list-style:none;">
                            <?php foreach ($attachments as $file) : ?>
                                <li style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 12px;background:#fafbfc;border-radius:8px;margin-bottom:6px;font-size:12px;">
                                    <a href="<?php echo esc_url($file->file_url); ?>" target="_blank" rel="noopener">
                                        <span class="dashicons dashicons-paperclip"></span> <?php echo esc_html($file->file_name); ?>
                                        <span style="color:var(--pms-muted);">(<?php echo esc_html(size_format((int) $file->file_size)); ?>)</span>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pms-tasks&action=delete_attachment&attachment_id=' . $file->id . '&task_id=' . $task->id), 'pms_delete_attachment_' . $file->id)); ?>"
                                       class="pms-link-danger"
                                       onclick="return confirm('<?php echo esc_js(__('Remove this attachment?', 'pms')); ?>');">
                                        <?php esc_html_e('Remove', 'pms'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <button type="submit" name="pms_save_task" class="pms-btn-primary">
                    <?php echo $action === 'edit' ? esc_html__('Save changes', 'pms') : esc_html__('Create task', 'pms'); ?>
                </button>
            </form>
        </div>


        <?php if ($task) :
            $comments = PMS_DB::get_comments_for_task($task->id);
            ?>
            <?php if ($task->started_at) : ?>
                <div class="pms-panel pms-form-panel">
                    <h2 style="margin-top:0;"><?php esc_html_e('Activity', 'pms'); ?></h2>
                    <div class="pms-form-grid">
                        <div>
                            <span class="pms-kpi-label"><?php esc_html_e('Started', 'pms'); ?></span>
                            <p style="margin:4px 0 0;"><?php echo esc_html(date_i18n('M j, g:i a', strtotime($task->started_at))); ?></p>
                        </div>
                        <div>
                            <span class="pms-kpi-label"><?php esc_html_e('Punctuality', 'pms'); ?></span>
                            <p style="margin:4px 0 0;">
                                <span class="pms-chip <?php echo $task->punctuality === 'late' ? 'pms-priority-high' : 'pms-status-done'; ?>">
                                    <?php echo esc_html($task->punctuality === 'late' ? __('Late', 'pms') : __('Early', 'pms')); ?>
                                </span>
                            </p>
                        </div>
                        <?php if ($task->completed_at) : ?>
                            <div>
                                <span class="pms-kpi-label"><?php esc_html_e('Completed', 'pms'); ?></span>
                                <p style="margin:4px 0 0;"><?php echo esc_html(date_i18n('M j, g:i a', strtotime($task->completed_at))); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="pms-panel">
                <div class="pms-panel-heading">
                    <h2><?php esc_html_e('Notes', 'pms'); ?></h2>
                    <span><?php echo esc_html(sprintf(_n('%d note', '%d notes', count($comments), 'pms'), count($comments))); ?></span>
                </div>
                <div style="padding:16px 20px;">
                    <?php if (empty($comments)) : ?>
                        <p style="color:var(--pms-muted);margin:0 0 16px;"><?php esc_html_e('No notes yet — nothing from the assignee, and you haven\'t left one either.', 'pms'); ?></p>
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
                        <?php wp_nonce_field('pms_add_task_comment_action', 'pms_comment_nonce'); ?>
                        <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id); ?>">
                        <div class="pms-form-row">
                            <textarea name="comment" rows="3" class="pms-input" placeholder="<?php esc_attr_e('Reply to the assignee…', 'pms'); ?>" required></textarea>
                        </div>
                        <button type="submit" name="pms_add_comment" class="pms-btn-primary"><?php esc_html_e('Post reply', 'pms'); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php else :
    $task_search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $task_status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    $task_priority_filter = isset($_GET['priority']) ? sanitize_key($_GET['priority']) : '';
    $task_work_mode_filter = isset($_GET['work_mode']) ? sanitize_key($_GET['work_mode']) : '';
    $task_assignee_filter = isset($_GET['assigned_to']) ? absint($_GET['assigned_to']) : 0;
    $task_department_filter = isset($_GET['department_id']) ? absint($_GET['department_id']) : 0;
    $all_tasks = PMS_DB::get_all_tasks();
    $tasks = array_values(array_filter($all_tasks, function ($task) use ($task_search, $task_status_filter, $task_priority_filter, $task_work_mode_filter, $task_assignee_filter, $task_department_filter) {
        $haystack = strtolower($task->title . ' ' . $task->description);
        return (!$task_search || strpos($haystack, strtolower($task_search)) !== false)
            && (!$task_status_filter || $task->status === $task_status_filter)
            && (!$task_priority_filter || $task->priority === $task_priority_filter)
            && (!$task_work_mode_filter || $task->work_mode === $task_work_mode_filter)
            && (!$task_assignee_filter || (int) $task->assigned_to === $task_assignee_filter)
            && (!$task_department_filter || (int) $task->department_id === $task_department_filter);
    }));
    ?>
    <div class="pms-panel pms-form-panel">
        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
            <input type="hidden" name="page" value="pms-tasks">
            <div class="pms-search"><span class="dashicons dashicons-search"></span><input type="search" name="search" class="pms-input" value="<?php echo esc_attr($task_search); ?>" placeholder="<?php esc_attr_e('Search tasks…', 'pms'); ?>"></div>
            <select name="status" class="pms-input"><option value=""><?php esc_html_e('All statuses', 'pms'); ?></option><?php foreach (PMS_Constants::task_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($task_status_filter, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            <select name="priority" class="pms-input"><option value=""><?php esc_html_e('All priorities', 'pms'); ?></option><?php foreach (PMS_Constants::task_priorities() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($task_priority_filter, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            <select name="work_mode" class="pms-input"><option value=""><?php esc_html_e('All work modes', 'pms'); ?></option><?php foreach (PMS_Constants::work_modes() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($task_work_mode_filter, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            <select name="assigned_to" class="pms-input"><option value=""><?php esc_html_e('All assignees', 'pms'); ?></option><?php foreach ($staff_and_admins as $person) : ?><option value="<?php echo esc_attr($person->ID); ?>" <?php selected($task_assignee_filter, $person->ID); ?>><?php echo esc_html($person->display_name); ?></option><?php endforeach; ?></select>
            <select name="department_id" class="pms-input"><option value=""><?php esc_html_e('All departments', 'pms'); ?></option><?php foreach ($departments as $dept) : ?><option value="<?php echo esc_attr($dept->id); ?>" <?php selected($task_department_filter, $dept->id); ?>><?php echo esc_html($dept->name); ?></option><?php endforeach; ?></select>
            <button type="submit" class="pms-btn-primary"><?php esc_html_e('Filter', 'pms'); ?></button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pms-tasks')); ?>" class="pms-btn-secondary"><?php esc_html_e('Reset', 'pms'); ?></a>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'pms_export_tasks_report','search'=>$task_search,'status'=>$task_status_filter,'priority'=>$task_priority_filter,'work_mode'=>$task_work_mode_filter,'assigned_to'=>$task_assignee_filter,'department_id'=>$task_department_filter], admin_url('admin-post.php')), 'pms_export_tasks_report_action')); ?>" class="pms-btn-secondary"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'pms'); ?></a>
        </form>
    </div>
    <div class="pms-panel">
        <table class="pms-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'pms'); ?></th>
                    <th><?php esc_html_e('Status', 'pms'); ?></th>
                    <th><?php esc_html_e('Priority', 'pms'); ?></th>
                    <th><?php esc_html_e('Work mode', 'pms'); ?></th>
                    <th><?php esc_html_e('Assigned to', 'pms'); ?></th>
                    <th><?php esc_html_e('Due', 'pms'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tasks)) : ?>
                <tr><td colspan="7" class="pms-empty"><?php esc_html_e('No tasks yet — create your first one.', 'pms'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($tasks as $task) :
                $attachment_count = count(PMS_DB::get_attachments_for_task($task->id));
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($task->title); ?></strong>
                        <?php if ($attachment_count) : ?>
                            <span class="dashicons dashicons-paperclip" style="font-size:14px;width:14px;height:14px;color:var(--pms-muted);vertical-align:middle;" title="<?php echo esc_attr(sprintf(_n('%d attachment', '%d attachments', $attachment_count, 'pms'), $attachment_count)); ?>"></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="pms-chip pms-status-<?php echo esc_attr($task->status); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_statuses(), $task->status)); ?></span></td>
                    <td><span class="pms-chip pms-priority-<?php echo esc_attr($task->priority); ?>"><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::task_priorities(), $task->priority)); ?></span></td>
                    <td><?php echo esc_html(PMS_Constants::label_for(PMS_Constants::work_modes(), $task->work_mode ?: 'remote')); ?></td>
                    <td><?php echo esc_html($task->assigned_to ? get_the_author_meta('display_name', $task->assigned_to) : '—'); ?></td>
                    <td><?php echo esc_html($task->due_date ?: '—'); ?></td>
                    <td class="pms-row-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=pms-tasks&action=edit&id=' . $task->id)); ?>"><?php esc_html_e('Edit', 'pms'); ?></a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pms-tasks&action=delete&id=' . $task->id), 'pms_delete_task_' . $task->id)); ?>"
                           class="pms-link-danger"
                           onclick="return confirm('<?php echo esc_js(__('Delete this task?', 'pms')); ?>');">
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

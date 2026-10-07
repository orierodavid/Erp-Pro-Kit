<?php
/**
 * Custom tables + query helpers.
 *
 * WordPress has no Eloquent, so this is the equivalent of your old
 * Models — plain $wpdb wrappers. Table names mirror the original Laravel
 * schema (branches, departments, tasks, attendance_records) with a pms_
 * prefix so they never collide with other plugins.
 *
 * Users themselves are NOT a custom table — they're wp_users, with
 * department/branch stored as user meta and role via WP's own role system.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_DB
{
    public static function branches_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_branches';
    }

    public static function departments_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_departments';
    }

    public static function projects_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_projects';
    }

    public static function tasks_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_tasks';
    }

    public static function attendance_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_attendance_records';
    }

    public static function leave_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_leave_requests';
    }

    public static function user_branches_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_user_branches';
    }

    public static function attachments_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_task_attachments';
    }

    public static function comments_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_task_comments';
    }

    /**
     * Called from PMS_Activator via dbDelta(). dbDelta needs a very
     * specific formatting (two spaces after PRIMARY KEY, one field per
     * line, etc.) or it silently fails to detect changes — don't
     * reformat this casually.
     */
    public static function schema_sql(): string
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $branches = self::branches_table();
        $departments = self::departments_table();
        $projects = self::projects_table();
        $tasks = self::tasks_table();
        $attendance = self::attendance_table();
        $leave = self::leave_table();
        $user_branches = self::user_branches_table();
        $attachments = self::attachments_table();
        $comments = self::comments_table();

        return "
CREATE TABLE {$projects} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    start_date DATE NULL,
    end_date DATE NULL,
    owner_id BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY status (status),
    KEY owner_id (owner_id)
) {$charset_collate};

CREATE TABLE {$branches} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    geofence_radius_m INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id)
) {$charset_collate};

CREATE TABLE {$departments} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY branch_id (branch_id)
) {$charset_collate};

CREATE TABLE {$tasks} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'todo',
    priority VARCHAR(32) NOT NULL DEFAULT 'medium',
    assigned_to BIGINT UNSIGNED NULL,
    assigned_by BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    due_date DATE NULL,
    work_mode VARCHAR(20) NOT NULL DEFAULT 'remote',
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    geofence_radius_m INT UNSIGNED NULL,
    scheduled_date DATE NULL,
    scheduled_start_time VARCHAR(5) NULL,
    started_at DATETIME NULL,
    start_lat DECIMAL(10,7) NULL,
    start_lng DECIMAL(10,7) NULL,
    completed_at DATETIME NULL,
    punctuality VARCHAR(10) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY assigned_to (assigned_to),
    KEY project_id (project_id),
    KEY status (status)
) {$charset_collate};

CREATE TABLE {$attendance} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    clock_in DATETIME NULL,
    clock_out DATETIME NULL,
    clock_in_lat DECIMAL(10,7) NULL,
    clock_in_lng DECIMAL(10,7) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'not_started',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY user_id (user_id),
    KEY clock_in (clock_in)
) {$charset_collate};

CREATE TABLE {$leave} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    leave_type VARCHAR(64) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(6,2) NOT NULL DEFAULT 0,
    reason TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY user_id (user_id),
    KEY status (status),
    KEY start_date (start_date)
) {$charset_collate};

CREATE TABLE {$user_branches} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY user_branch (user_id, branch_id)
) {$charset_collate};

CREATE TABLE {$attachments} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY task_id (task_id)
) {$charset_collate};

CREATE TABLE {$comments} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY task_id (task_id)
) {$charset_collate};
";
    }

    // ---- Query helpers (equivalent of your old Eloquent scopes) ----

    public static function get_projects(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT p.*, u.display_name AS owner_name, COUNT(t.id) AS task_count FROM ' . self::projects_table() . ' p LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.owner_id LEFT JOIN ' . self::tasks_table() . ' t ON t.project_id = p.id GROUP BY p.id ORDER BY p.created_at DESC, p.name ASC');
    }

    public static function get_project(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::projects_table() . ' WHERE id = %d', $id)) ?: null;
    }

    public static function create_project(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::projects_table(), [
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'owner_id' => $data['owner_id'] ?: null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function update_project(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update(self::projects_table(), [
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'owner_id' => $data['owner_id'] ?: null,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function delete_project(int $id): void
    {
        global $wpdb;
        $wpdb->update(self::tasks_table(), ['project_id' => null, 'updated_at' => current_time('mysql')], ['project_id' => $id]);
        $wpdb->delete(self::projects_table(), ['id' => $id]);
    }

    public static function get_project_tasks(int $project_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT t.*, u.display_name AS assignee_name FROM ' . self::tasks_table() . ' t LEFT JOIN ' . $wpdb->users . ' u ON u.ID = t.assigned_to WHERE t.project_id = %d ORDER BY t.due_date ASC, t.created_at DESC', $project_id));
    }

    public static function set_task_project(int $task_id, ?int $project_id): void
    {
        global $wpdb;
        $wpdb->update(self::tasks_table(), ['project_id' => $project_id ?: null, 'updated_at' => current_time('mysql')], ['id' => $task_id]);
    }

    public static function assignment_rows(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT t.id, t.title, t.status, t.priority, t.due_date, t.project_id, t.assigned_to, p.name AS project_name, u.display_name AS assignee_name FROM ' . self::tasks_table() . ' t LEFT JOIN ' . self::projects_table() . ' p ON p.id = t.project_id LEFT JOIN ' . $wpdb->users . ' u ON u.ID = t.assigned_to ORDER BY t.assigned_to IS NULL DESC, t.due_date ASC, t.created_at DESC');
    }

    public static function project_report_rows(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT p.id, p.name, p.status, p.start_date, p.end_date, u.display_name AS owner_name, COUNT(t.id) AS total_tasks, SUM(CASE WHEN t.status = "done" THEN 1 ELSE 0 END) AS completed_tasks, SUM(CASE WHEN t.status = "in_progress" THEN 1 ELSE 0 END) AS in_progress_tasks, SUM(CASE WHEN t.status = "todo" THEN 1 ELSE 0 END) AS todo_tasks FROM ' . self::projects_table() . ' p LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.owner_id LEFT JOIN ' . self::tasks_table() . ' t ON t.project_id = p.id GROUP BY p.id ORDER BY p.created_at DESC');
    }

    public static function get_branches(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::branches_table() . ' ORDER BY name ASC');
    }

    public static function get_departments(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::departments_table() . ' ORDER BY name ASC');
    }

    public static function get_tasks_for_user(int $user_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::tasks_table() . ' WHERE assigned_to = %d ORDER BY due_date ASC',
            $user_id
        ));
    }

    public static function get_all_tasks(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::tasks_table() . ' ORDER BY created_at DESC');
    }

    // ---- Tasks: full CRUD ----

    public static function get_task(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::tasks_table() . ' WHERE id = %d', $id)) ?: null;
    }

    /** @param array{title:string,description:string,status:string,priority:string,assigned_to:?int,department_id:?int,due_date:?string} $data */
    public static function create_task(array $data, int $assigned_by): int
    {
        global $wpdb;
        $wpdb->insert(self::tasks_table(), [
            'title'                => $data['title'],
            'description'          => $data['description'],
            'status'               => $data['status'],
            'priority'             => $data['priority'],
            'assigned_to'          => $data['assigned_to'] ?: null,
            'assigned_by'          => $assigned_by,
            'department_id'        => $data['department_id'] ?: null,
            'due_date'             => $data['due_date'] ?: null,
            'work_mode'            => $data['work_mode'] ?? 'remote',
            'address'              => $data['address'] ?? null,
            'latitude'             => $data['latitude'] !== '' && $data['latitude'] !== null ? $data['latitude'] : null,
            'longitude'            => $data['longitude'] !== '' && $data['longitude'] !== null ? $data['longitude'] : null,
            'geofence_radius_m'    => $data['geofence_radius_m'] ?: null,
            'scheduled_date'       => $data['scheduled_date'] ?: null,
            'scheduled_start_time' => $data['scheduled_start_time'] ?: null,
            'created_at'           => current_time('mysql'),
            'updated_at'           => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function update_task(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update(self::tasks_table(), [
            'title'                => $data['title'],
            'description'          => $data['description'],
            'status'               => $data['status'],
            'priority'             => $data['priority'],
            'assigned_to'          => $data['assigned_to'] ?: null,
            'department_id'        => $data['department_id'] ?: null,
            'due_date'             => $data['due_date'] ?: null,
            'work_mode'            => $data['work_mode'] ?? 'remote',
            'address'              => $data['address'] ?? null,
            'latitude'             => $data['latitude'] !== '' && $data['latitude'] !== null ? $data['latitude'] : null,
            'longitude'            => $data['longitude'] !== '' && $data['longitude'] !== null ? $data['longitude'] : null,
            'geofence_radius_m'    => $data['geofence_radius_m'] ?: null,
            'scheduled_date'       => $data['scheduled_date'] ?: null,
            'scheduled_start_time' => $data['scheduled_start_time'] ?: null,
            'updated_at'           => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function delete_task(int $id): void
    {
        global $wpdb;
        self::delete_attachments_for_task($id); // remove files from disk too, not just DB rows
        self::delete_comments_for_task($id);
        $wpdb->delete(self::tasks_table(), ['id' => $id]);
    }

    public static function count_tasks_by_status(string $status): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::tasks_table() . ' WHERE status = %s',
            $status
        ));
    }

    /** Lightweight status-only update — what Staff are allowed to change on their own tasks. */
    /**
     * Status-only update (what Staff use once a task is already started).
     * Stamps/clears completed_at to match — 'done' records a completion
     * time, moving away from 'done' clears it rather than leaving a
     * stale timestamp on a task that's no longer actually complete.
     */
    public static function update_task_status(int $id, string $status): void
    {
        global $wpdb;
        $data = [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ];

        $data['completed_at'] = $status === 'done' ? current_time('mysql') : null;

        $wpdb->update(self::tasks_table(), $data, ['id' => $id]);
    }

    /**
     * The geofence-gated (or, for remote tasks, ungated) moment a task
     * actually begins. This is deliberately separate from
     * update_task_status() — starting is a verified event that can be
     * rejected (wrong location), a plain status change never is.
     *
     * @return array{success:bool,message?:string}
     */
    public static function start_task(int $task_id, ?float $lat, ?float $lng): array
    {
        $task = self::get_task($task_id);

        if (! $task) {
            return ['success' => false, 'message' => __('Task not found.', 'pms')];
        }

        if ($task->started_at) {
            return ['success' => false, 'message' => __('This task has already been started.', 'pms')];
        }

        if ($task->work_mode === 'location_based') {
            if ($task->latitude === null || $task->longitude === null) {
                return ['success' => false, 'message' => __('This task has no verified location set — ask an admin to fix it before starting.', 'pms')];
            }

            if ($lat === null || $lng === null) {
                return ['success' => false, 'message' => __('Location access is required to start this task. Please allow location access and try again.', 'pms')];
            }

            $radius = (int) ($task->geofence_radius_m ?: get_option('pms_default_geofence_radius_m', 100));
            $distance = self::distance_metres($lat, $lng, (float) $task->latitude, (float) $task->longitude);

            if ($distance > $radius) {
                return ['success' => false, 'message' => sprintf(
                    /* translators: %d: distance in metres over the limit */
                    __('You are about %dm outside the task location. Move closer and try again.', 'pms'),
                    (int) round($distance - $radius)
                )];
            }
        }

        global $wpdb;
        $started_at = current_time('mysql');

        $wpdb->update(self::tasks_table(), [
            'status'      => 'in_progress',
            'started_at'  => $started_at,
            'start_lat'   => $lat,
            'start_lng'   => $lng,
            'punctuality' => self::compute_punctuality($started_at, $task->scheduled_start_time),
            'updated_at'  => $started_at,
        ], ['id' => $task_id]);

        return ['success' => true];
    }

    /**
     * Compares the time-of-day a task was started against its own
     * scheduled start time, falling back to the company-wide default
     * (Settings → workday start) when the task didn't set its own —
     * either way, checked against the same grace-period setting.
     */
    public static function compute_punctuality(string $started_at, ?string $scheduled_start_time): string
    {
        $expected = $scheduled_start_time ?: get_option('pms_workday_start', '09:00');
        $grace = (int) get_option('pms_late_grace_minutes', 15);

        $started_minutes = self::time_string_to_minutes(date('H:i', strtotime($started_at)));
        $cutoff_minutes = self::time_string_to_minutes($expected) + $grace;

        return $started_minutes > $cutoff_minutes ? 'late' : 'early';
    }

    public static function open_task_count_for_user(int $user_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::tasks_table() . " WHERE assigned_to = %d AND status != 'done'",
            $user_id
        ));
    }

    public static function last_task_activity_for_user(int $user_id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::tasks_table() . " WHERE assigned_to = %d AND started_at IS NOT NULL ORDER BY started_at DESC LIMIT 1",
            $user_id
        )) ?: null;
    }

    public static function has_task_in_progress(int $user_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::tasks_table() . " WHERE assigned_to = %d AND status = 'in_progress'",
            $user_id
        ));
    }

    // ---- Task attachments ----

    public static function get_attachments_for_task(int $task_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::attachments_table() . ' WHERE task_id = %d ORDER BY id DESC',
            $task_id
        ));
    }

    public static function get_attachment(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::attachments_table() . ' WHERE id = %d',
            $id
        )) ?: null;
    }

    public static function create_attachment(int $task_id, array $file, int $uploaded_by): int
    {
        global $wpdb;
        $wpdb->insert(self::attachments_table(), [
            'task_id'     => $task_id,
            'file_url'    => $file['url'],
            'file_path'   => $file['path'],
            'file_name'   => $file['name'],
            'file_size'   => $file['size'],
            'uploaded_by' => $uploaded_by,
            'created_at'  => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function delete_attachment(int $id): void
    {
        global $wpdb;
        $attachment = self::get_attachment($id);

        if ($attachment && file_exists($attachment->file_path)) {
            @unlink($attachment->file_path);
        }

        $wpdb->delete(self::attachments_table(), ['id' => $id]);
    }

    public static function delete_attachments_for_task(int $task_id): void
    {
        foreach (self::get_attachments_for_task($task_id) as $attachment) {
            self::delete_attachment((int) $attachment->id);
        }
    }

    // ---- Task comments ----

    public static function get_comments_for_task(int $task_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::comments_table() . ' WHERE task_id = %d ORDER BY id ASC',
            $task_id
        ));
    }

    public static function add_comment(int $task_id, int $user_id, string $comment): int
    {
        global $wpdb;
        $wpdb->insert(self::comments_table(), [
            'task_id'    => $task_id,
            'user_id'    => $user_id,
            'comment'    => $comment,
            'created_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function delete_comments_for_task(int $task_id): void
    {
        global $wpdb;
        $wpdb->delete(self::comments_table(), ['task_id' => $task_id]);
    }

    // ---- Leave requests ----

    public static function create_leave_request(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::leave_table(), [
            'user_id' => (int) $data['user_id'],
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => (float) $data['days'],
            'reason' => $data['reason'],
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function get_leave_requests(array $filters = []): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if (! empty($filters['user_id'])) {
            $where[] = 'l.user_id = %d';
            $params[] = (int) $filters['user_id'];
        }
        if (! empty($filters['status'])) {
            $where[] = 'l.status = %s';
            $params[] = $filters['status'];
        }
        if (! empty($filters['leave_type'])) {
            $where[] = 'l.leave_type = %s';
            $params[] = $filters['leave_type'];
        }
        if (! empty($filters['from'])) {
            $where[] = 'l.start_date >= %s';
            $params[] = $filters['from'];
        }
        if (! empty($filters['to'])) {
            $where[] = 'l.end_date <= %s';
            $params[] = $filters['to'];
        }

        $sql = 'SELECT l.*, u.display_name AS user_name
                FROM ' . self::leave_table() . ' l
                LEFT JOIN ' . $wpdb->users . ' u ON u.ID = l.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY l.created_at DESC';

        return $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
    }

    public static function get_leave_request(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::leave_table() . ' WHERE id = %d', $id)) ?: null;
    }

    public static function update_leave_status(int $id, string $status, int $reviewed_by): bool
    {
        global $wpdb;
        return false !== $wpdb->update(self::leave_table(), [
            'status' => $status,
            'reviewed_by' => $reviewed_by,
            'reviewed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function leave_types(): array
    {
        return [
            'annual' => __('Annual', 'pms'),
            'sick' => __('Sick', 'pms'),
            'personal' => __('Personal', 'pms'),
            'unpaid' => __('Unpaid', 'pms'),
        ];
    }

    // ---- Branches: full CRUD ----

    public static function get_branch(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::branches_table() . ' WHERE id = %d', $id)) ?: null;
    }

    public static function create_branch(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::branches_table(), [
            'name'               => $data['name'],
            'address'            => $data['address'],
            'latitude'           => $data['latitude'] !== '' ? $data['latitude'] : null,
            'longitude'          => $data['longitude'] !== '' ? $data['longitude'] : null,
            'geofence_radius_m'  => $data['geofence_radius_m'] ?: null,
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function update_branch(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update(self::branches_table(), [
            'name'               => $data['name'],
            'address'            => $data['address'],
            'latitude'           => $data['latitude'] !== '' ? $data['latitude'] : null,
            'longitude'          => $data['longitude'] !== '' ? $data['longitude'] : null,
            'geofence_radius_m'  => $data['geofence_radius_m'] ?: null,
            'updated_at'         => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function delete_branch(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::branches_table(), ['id' => $id]);
    }

    // ---- Departments: full CRUD ----

    public static function get_department(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::departments_table() . ' WHERE id = %d', $id)) ?: null;
    }

    public static function create_department(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::departments_table(), [
            'name'       => $data['name'],
            'branch_id'  => $data['branch_id'] ?: null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function update_department(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update(self::departments_table(), [
            'name'       => $data['name'],
            'branch_id'  => $data['branch_id'] ?: null,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function delete_department(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::departments_table(), ['id' => $id]);
    }

    public static function branch_name(?int $branch_id): string
    {
        if (! $branch_id) {
            return '—';
        }
        $branch = self::get_branch($branch_id);
        return $branch ? $branch->name : '—';
    }

    public static function department_name(?int $department_id): string
    {
        if (! $department_id) {
            return '—';
        }
        $department = self::get_department($department_id);
        return $department ? $department->name : '—';
    }

    // ---- Reports ----

    /**
     * Detailed, filterable task-start rows for the Reports screen and
     * CSV export — one row per task that has actually been started,
     * with department resolved and punctuality already computed at
     * start time. Filtered and exported from the exact same query so
     * the two can never drift apart.
     *
     * @param array{department_id?:int,work_mode?:string,punctuality?:string} $filters
     */
    public static function filtered_tasks_report(string $from, string $to, array $filters = []): array
    {
        global $wpdb;

        $sql = 'SELECT * FROM ' . self::tasks_table() . ' WHERE started_at IS NOT NULL AND DATE(started_at) BETWEEN %s AND %s';
        $params = [$from, $to];

        if (! empty($filters['work_mode'])) {
            $sql .= ' AND work_mode = %s';
            $params[] = $filters['work_mode'];
        }

        if (! empty($filters['punctuality'])) {
            $sql .= ' AND punctuality = %s';
            $params[] = $filters['punctuality'];
        }

        $sql .= ' ORDER BY started_at DESC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $filtered = [];
        foreach ($rows as $row) {
            $department_id = (int) ($row->department_id ?: 0);

            if (! empty($filters['department_id']) && (int) $filters['department_id'] !== $department_id) {
                continue;
            }

            $row->user_name = $row->assigned_to ? (get_the_author_meta('display_name', $row->assigned_to) ?: __('Unknown', 'pms')) : __('Unassigned', 'pms');
            $row->department_name = self::department_name($department_id ?: null);
            $row->duration_minutes = $row->completed_at ? (int) round((strtotime($row->completed_at) - strtotime($row->started_at)) / 60) : null;

            $filtered[] = $row;
        }

        return $filtered;
    }

    private static function time_string_to_minutes(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return (($parts[0] ?? 0) * 60) + ($parts[1] ?? 0);
    }

    public static function task_status_breakdown(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT status, COUNT(*) AS total FROM ' . self::tasks_table() . ' GROUP BY status'
        );

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row->status] = (int) $row->total;
        }

        return $breakdown;
    }

    /**
     * Great-circle distance between two lat/lng points, in metres
     * (Haversine formula). Used to enforce a branch's geofence
     * server-side — never trust a client to self-report "I'm in range."
     */
    public static function distance_metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth_radius_m = 6371000;

        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $d_phi = deg2rad($lat2 - $lat1);
        $d_lambda = deg2rad($lng2 - $lng1);

        $a = sin($d_phi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($d_lambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius_m * $c;
    }
}

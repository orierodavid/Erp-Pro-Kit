<?php
/**
 * Streams a CSV download of the task punctuality report, respecting
 * whatever filters (date range, department, work mode, early/late) are
 * currently applied on the Reports screen — reuses the exact same
 * PMS_DB::filtered_tasks_report() query the on-screen table uses, so
 * the export can never drift out of sync with what's displayed.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_CSV_Export
{
    public function __construct()
    {
        add_action('admin_post_pms_export_task_report', [$this, 'export_task_report']);
    }

    public function export_task_report(): void
    {
        if (! current_user_can('pms_view_reports')) {
            wp_die(__('You do not have permission to do this.', 'pms'));
        }

        check_admin_referer('pms_export_task_report_action');

        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : gmdate('Y-m-d', strtotime('-6 days'));
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : gmdate('Y-m-d');
        $department_id = isset($_GET['department_id']) ? absint($_GET['department_id']) : 0;
        $work_mode = isset($_GET['work_mode']) ? sanitize_key($_GET['work_mode']) : '';
        $punctuality = isset($_GET['punctuality']) ? sanitize_key($_GET['punctuality']) : '';

        if (strtotime($from) === false) {
            $from = gmdate('Y-m-d', strtotime('-6 days'));
        }
        if (strtotime($to) === false) {
            $to = gmdate('Y-m-d');
        }

        $rows = PMS_DB::filtered_tasks_report($from, $to, [
            'department_id' => $department_id ?: null,
            'work_mode'     => $work_mode ?: null,
            'punctuality'   => $punctuality ?: null,
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="task-report-' . $from . '-to-' . $to . '.csv"');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM so Excel opens names/special characters correctly instead of mangling them.
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            __('Task', 'pms'),
            __('Person', 'pms'),
            __('Department', 'pms'),
            __('Work mode', 'pms'),
            __('Started', 'pms'),
            __('Completed', 'pms'),
            __('Duration (hours)', 'pms'),
            __('Punctuality', 'pms'),
        ]);

        $work_mode_labels = PMS_Constants::work_modes();
        $punctuality_labels = ['early' => __('Early', 'pms'), 'late' => __('Late', 'pms')];

        foreach ($rows as $row) {
            fputcsv($out, [
                $row->title,
                $row->user_name,
                $row->department_name,
                $work_mode_labels[$row->work_mode] ?? $row->work_mode,
                $row->started_at,
                $row->completed_at ?: '',
                $row->duration_minutes !== null ? number_format($row->duration_minutes / 60, 2) : '',
                $punctuality_labels[$row->punctuality] ?? $row->punctuality,
            ]);
        }

        fclose($out);
        exit;
    }
}

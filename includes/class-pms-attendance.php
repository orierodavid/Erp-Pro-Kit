<?php
/**
 * Attendance service. Attendance is independent from task status.
 */
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Attendance
{
    public static function today_for_user(int $user_id): ?object
    {
        global $wpdb;
        $start = current_time('Y-m-d') . ' 00:00:00';
        $end = current_time('Y-m-d') . ' 23:59:59';

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PMS_DB::attendance_table() . ' WHERE user_id = %d AND created_at BETWEEN %s AND %s ORDER BY id DESC LIMIT 1',
            $user_id,
            $start,
            $end
        )) ?: null;
    }

    public static function clock_in(int $user_id, ?float $lat = null, ?float $lng = null): array
    {
        $today = self::today_for_user($user_id);

        if ($today && $today->status === 'clocked_in') {
            return ['success' => false, 'message' => __('You are already clocked in.', 'pms')];
        }

        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(PMS_DB::attendance_table(), [
            'user_id' => $user_id,
            'clock_in' => $now,
            'clock_in_lat' => $lat,
            'clock_in_lng' => $lng,
            'status' => 'clocked_in',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['success' => (bool) $wpdb->insert_id];
    }

    public static function clock_out(int $user_id): array
    {
        $today = self::today_for_user($user_id);

        if (! $today || $today->status !== 'clocked_in') {
            return ['success' => false, 'message' => __('You are not currently clocked in.', 'pms')];
        }

        global $wpdb;
        $now = current_time('mysql');
        $updated = $wpdb->update(
            PMS_DB::attendance_table(),
            ['clock_out' => $now, 'status' => 'clocked_out', 'updated_at' => $now],
            ['id' => (int) $today->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return ['success' => $updated !== false];
    }

    public static function recent(int $limit = 100): array
    {
        global $wpdb;
        $limit = max(1, min(500, $limit));

        return $wpdb->get_results(
            'SELECT * FROM ' . PMS_DB::attendance_table() . ' ORDER BY created_at DESC LIMIT ' . $limit
        );
    }

    public static function duration_minutes(object $record): ?int
    {
        if (! $record->clock_in || ! $record->clock_out) {
            return null;
        }

        return max(0, (int) round((strtotime($record->clock_out) - strtotime($record->clock_in)) / 60));
    }
}

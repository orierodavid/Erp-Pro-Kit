<?php
/**
 * Single source of truth for enum-like values used across views, DB
 * writes, and validation. Nothing else in the plugin should hardcode
 * a status/priority string literal — reference these constants instead,
 * so adding/renaming a status is a one-file change.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Constants
{
    public static function task_statuses(): array
    {
        return [
            'todo'        => __('To do', 'pms'),
            'in_progress' => __('In progress', 'pms'),
            'done'        => __('Done', 'pms'),
        ];
    }

    public static function task_priorities(): array
    {
        return [
            'low'    => __('Low', 'pms'),
            'medium' => __('Medium', 'pms'),
            'high'   => __('High', 'pms'),
        ];
    }

    public static function work_modes(): array
    {
        return [
            'remote'         => __('Remote', 'pms'),
            'location_based' => __('Location based', 'pms'),
        ];
    }

    public static function attendance_statuses(): array
    {
        return [
            'not_started' => __('Not started', 'pms'),
            'clocked_in'  => __('Clocked in', 'pms'),
            'clocked_out' => __('Clocked out', 'pms'),
        ];
    }

    public static function label_for(array $map, ?string $key): string
    {
        return $map[$key] ?? (string) $key;
    }
}

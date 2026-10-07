<?php
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Leave
{
    public static function calculate_days(string $start, string $end): float
    {
        $start_date = new DateTime($start);
        $end_date = new DateTime($end);
        if ($end_date < $start_date) {
            return 0;
        }
        return (float) $start_date->diff($end_date)->days + 1;
    }

    public static function valid_date(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

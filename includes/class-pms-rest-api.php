<?php
/**
 * REST endpoints under /wp-json/pms/v1/...
 *
 * Every route's permission_callback is the capability check;
 * WordPress's REST framework rejects the request before your callback
 * even runs if it fails, so there's no risk of a route being
 * reachable-but-unauthorized.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_REST_API
{
    private const NAMESPACE = 'pms/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/attendance/status', [
            'methods' => 'GET',
            'callback' => [$this, 'attendance_status'],
            'permission_callback' => fn () => PMS_Modules::is_active('attendance') && current_user_can('pms_clock_in_out'),
        ]);

        register_rest_route(self::NAMESPACE, '/attendance/clock-in', [
            'methods' => 'POST',
            'callback' => [$this, 'attendance_clock_in'],
            'permission_callback' => fn () => current_user_can('pms_clock_in_out'),
            'args' => [
                'lat' => ['required' => false, 'type' => 'number'],
                'lng' => ['required' => false, 'type' => 'number'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/attendance/clock-out', [
            'methods' => 'POST',
            'callback' => [$this, 'attendance_clock_out'],
            'permission_callback' => fn () => current_user_can('pms_clock_in_out'),
        ]);
        register_rest_route(self::NAMESPACE, '/tasks/(?P<id>\d+)/start', [
            'methods'             => 'POST',
            'callback'            => [$this, 'start_task'],
            'permission_callback' => [$this, 'can_start_task'],
            'args'                => [
                'lat' => ['required' => false, 'type' => 'number'],
                'lng' => ['required' => false, 'type' => 'number'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/geocode', [
            'methods'             => 'GET',
            'callback'            => [$this, 'geocode'],
            'permission_callback' => fn () => current_user_can('pms_manage_tasks'),
            'args'                => [
                'address' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    /**
     * A task can only be started by the person it's assigned to (or an
     * admin, who can start on behalf of someone if needed) — never by
     * an arbitrary logged-in user guessing a task ID.
     */
    public function can_start_task(WP_REST_Request $request): bool
    {
        if (! PMS_Modules::is_active('tasks') || ! current_user_can('pms_clock_in_out')) {
            return false;
        }

        $task = PMS_DB::get_task((int) $request->get_param('id'));

        if (! $task) {
            return true; // let the callback return a clean 404 instead of a bare permission error
        }

        return (int) $task->assigned_to === get_current_user_id() || current_user_can('pms_manage_tasks');
    }

    public function start_task(WP_REST_Request $request): WP_REST_Response
    {
        $task_id = (int) $request->get_param('id');
        $lat = $request->has_param('lat') ? (float) $request->get_param('lat') : null;
        $lng = $request->has_param('lng') ? (float) $request->get_param('lng') : null;

        $result = PMS_DB::start_task($task_id, $lat, $lng);

        if (! $result['success']) {
            return new WP_REST_Response(['message' => $result['message']], 422);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function attendance_status(): WP_REST_Response
    {
        $record = PMS_Attendance::today_for_user(get_current_user_id());

        return new WP_REST_Response([
            'status' => $record ? $record->status : 'not_started',
            'clock_in' => $record ? $record->clock_in : null,
            'clock_out' => $record ? $record->clock_out : null,
        ]);
    }

    public function attendance_clock_in(WP_REST_Request $request): WP_REST_Response
    {
        $lat = $request->has_param('lat') ? (float) $request->get_param('lat') : null;
        $lng = $request->has_param('lng') ? (float) $request->get_param('lng') : null;
        $result = PMS_Attendance::clock_in(get_current_user_id(), $lat, $lng);

        if (! $result['success']) {
            return new WP_REST_Response(['message' => $result['message']], 422);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function attendance_clock_out(): WP_REST_Response
    {
        $result = PMS_Attendance::clock_out(get_current_user_id());

        if (! $result['success']) {
            return new WP_REST_Response(['message' => $result['message']], 422);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function geocode(WP_REST_Request $request): WP_REST_Response
    {
        $result = PMS_Geocoding::geocode((string) $request->get_param('address'));

        if (is_wp_error($result)) {
            return new WP_REST_Response(['message' => $result->get_error_message()], 422);
        }

        return new WP_REST_Response($result);
    }
}

<?php
/**
 * Runs ONLY when the plugin is deleted from wp-admin (not on deactivation).
 * By default this does NOT touch any data — dropping tables/roles on
 * uninstall is destructive and should require explicit opt-in via a
 * setting (e.g. a "Delete all data on uninstall" checkbox you'd add to
 * settings.php), which this checks for before doing anything.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('pms_delete_data_on_uninstall') !== '1') {
    return;
}

global $wpdb;

require_once __DIR__ . '/includes/class-pms-db.php';

foreach ([
    PMS_DB::branches_table(),
    PMS_DB::departments_table(),
    PMS_DB::tasks_table(),
    PMS_DB::attendance_table(),
    PMS_DB::user_branches_table(),
] as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

require_once __DIR__ . '/includes/class-pms-roles.php';
PMS_Roles::unregister();

delete_option('pms_db_version');
delete_option('pms_company_name');
delete_option('pms_default_geofence_radius_m');
delete_option('pms_workday_start');
delete_option('pms_workday_end');
delete_option('pms_delete_data_on_uninstall');

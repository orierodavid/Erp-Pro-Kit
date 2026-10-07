<?php
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Activator
{
    public static function activate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta both creates tables on first activation AND migrates
        // them safely on later versions if schema_sql() changes.
        dbDelta(PMS_DB::schema_sql());
        dbDelta(PMS_Payroll::schema_sql());
        dbDelta(PMS_CRM::schema_sql());
        dbDelta(PMS_Invoicing::schema_sql());

        PMS_Roles::register();

        update_option('pms_db_version', PMS_DB_VERSION);

        flush_rewrite_rules(); // in case a future version registers custom post types / rewrite rules
    }
}

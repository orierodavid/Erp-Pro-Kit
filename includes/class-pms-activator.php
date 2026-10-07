<?php
if (! defined('ABSPATH')) { exit; }

class PMS_Activator
{
    public static function activate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(PMS_DB::schema_sql());
        dbDelta(PMS_Payroll::schema_sql());
        dbDelta(PMS_CRM::schema_sql());
        dbDelta(PMS_Invoicing::schema_sql());
        dbDelta(PMS_Expenses::schema_sql());
        dbDelta(PMS_Inventory::schema_sql());
        dbDelta(PMS_Real_Estate::schema_sql());
        dbDelta(PMS_Real_Estate_Automation::schema_sql());

        PMS_Roles::register();
        update_option('pms_db_version', PMS_DB_VERSION);
        flush_rewrite_rules();
    }
}

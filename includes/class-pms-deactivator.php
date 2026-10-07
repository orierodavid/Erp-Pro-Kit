<?php
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Deactivator
{
    /**
     * Deliberately does NOT drop tables or remove roles/data —
     * deactivation should be reversible. Only an explicit "uninstall"
     * (uninstall.php, triggered by deleting the plugin from wp-admin)
     * should ever destroy data, and only if the site owner opts in.
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}

<?php
/**
 * Plugin Name:       Project Management System
 * Plugin URI:        https://github.com/orierodavid/Project-management-system
 * Description:       Admin/Staff task and attendance management, built as a native WordPress plugin.
 * Version:           0.7.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            David Oriero
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pms
 *
 * This file only wires things together. Real logic lives in includes/.
 * Nothing below should talk to $wpdb or render HTML directly.
 */

if (! defined('ABSPATH')) {
    exit; // No direct access.
}

define('PMS_VERSION', '0.7.0');
define('PMS_PLUGIN_FILE', __FILE__);
define('PMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMS_DB_VERSION', '1.9.0'); // bump this + update Activator when schema/role changes

require_once PMS_PLUGIN_DIR . 'includes/class-pms-activator.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-deactivator.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-roles.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-modules.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-attendance.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-leave.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-constants.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-db.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-payroll.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-crm.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-invoicing.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-invoice-document.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-expenses.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-inventory.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-real-estate.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-real-estate-automation.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-user-profile-fields.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-csv-export.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-settings.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-fullscreen.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-login-experience.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-admin-menu.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-geocoding.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-rest-api.php';
require_once PMS_PLUGIN_DIR . 'includes/class-pms-assets.php';
require_once PMS_PLUGIN_DIR . 'public/class-pms-shortcode.php';

register_activation_hook(__FILE__, ['PMS_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PMS_Deactivator', 'deactivate']);

/**
 * Everything else boots on `plugins_loaded` so translations and other
 * plugins are available first.
 */
function pms_boot(): void
{
    load_plugin_textdomain('pms', false, dirname(plugin_basename(PMS_PLUGIN_FILE)) . '/languages');

    // Auto-upgrade the DB schema if the plugin was updated since last load.
    if (get_option('pms_db_version') !== PMS_DB_VERSION) {
        PMS_Activator::activate();
    }

    PMS_Real_Estate_Automation::init();
    new PMS_Admin_Menu();
    new PMS_REST_API();
    new PMS_Assets();
    new PMS_Shortcode();
    new PMS_Settings();
    new PMS_Fullscreen();
    new PMS_Login_Experience();
    new PMS_User_Profile_Fields();
    new PMS_CSV_Export();
}
add_action('plugins_loaded', 'pms_boot');

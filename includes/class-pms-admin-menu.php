<?php
/**
 * Registers the wp-admin sidebar menu.
 *
 * Every submenu's third argument (capability) is what WordPress uses to
 * decide whether to show/allow the page — this is the equivalent of the
 * Filament ->pages()/->resources() registration plus the canAccess()
 * checks combined into one call, and it can't drift out of sync the way
 * the Blade @can(...) bug did, because WordPress enforces the capability
 * on BOTH the menu link and the page load itself.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Admin_Menu
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register']);
    }

    public function register(): void
    {
        // Top-level menu — visible to anyone with at least one pms_ capability.
        add_menu_page(
            __('Project Management', 'pms'),
            __('Project Mgmt', 'pms'),
            'read', // refined per-submenu below; this just controls the parent link
            'pms-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-clipboard',
            26
        );

        add_submenu_page('pms-dashboard', __('Dashboard', 'pms'), __('Dashboard', 'pms'), 'read', 'pms-dashboard', [$this, 'render_dashboard']);
        add_submenu_page('pms-dashboard', __('My Tasks', 'pms'), __('My Tasks', 'pms'), 'pms_view_assigned_tasks', 'pms-my-tasks', [$this, 'render_my_tasks']);
        if (PMS_Modules::is_active('attendance')) {
            add_submenu_page('pms-dashboard', __('Attendance', 'pms'), __('Attendance', 'pms'), 'pms_view_attendance', 'pms-attendance', [$this, 'render_attendance']);
        }
        add_submenu_page('pms-dashboard', __('Tasks', 'pms'), __('Tasks', 'pms'), 'pms_manage_tasks', 'pms-tasks', [$this, 'render_tasks']);
        add_submenu_page('pms-dashboard', __('People', 'pms'), __('People', 'pms'), 'pms_manage_users', 'pms-users', [$this, 'render_users']);
        add_submenu_page('pms-dashboard', __('Departments', 'pms'), __('Departments', 'pms'), 'pms_manage_departments', 'pms-departments', [$this, 'render_departments']);
        add_submenu_page('pms-dashboard', __('Branches', 'pms'), __('Branches', 'pms'), 'pms_manage_branches', 'pms-branches', [$this, 'render_branches']);
        add_submenu_page('pms-dashboard', __('Reports', 'pms'), __('Reports', 'pms'), 'pms_view_reports', 'pms-reports', [$this, 'render_reports']);
        add_submenu_page('pms-dashboard', __('Settings', 'pms'), __('Settings', 'pms'), 'pms_manage_settings', 'pms-settings', [$this, 'render_settings']);
        add_submenu_page(null, __('My Account', 'pms'), __('My Account', 'pms'), 'read', 'pms-my-account', [$this, 'render_my_account']);
    }

    /** Every render_*() method just includes a view file — no logic here, easy to keep this class thin. */
    public function render_dashboard(): void
    {
        $is_admin = PMS_Roles::current_user_is_pms_admin();
        include PMS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_my_tasks(): void
    {
        if (! current_user_can('pms_view_assigned_tasks')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/my-tasks.php';
    }

    public function render_attendance(): void
    {
        if (! PMS_Modules::is_active('attendance') || ! current_user_can('pms_view_attendance')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/attendance.php';
    }

    public function render_tasks(): void
    {
        if (! current_user_can('pms_manage_tasks')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/tasks.php';
    }

    public function render_users(): void
    {
        if (! current_user_can('pms_manage_users')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/users.php';
    }

    public function render_departments(): void
    {
        if (! current_user_can('pms_manage_departments')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/departments.php';
    }

    public function render_branches(): void
    {
        if (! current_user_can('pms_manage_branches')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/branches.php';
    }

    public function render_reports(): void
    {
        if (! current_user_can('pms_view_reports')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/reports.php';
    }

    public function render_my_account(): void
    {
        include PMS_PLUGIN_DIR . 'admin/views/my-account.php';
    }

    public function render_settings(): void
    {
        if (! current_user_can('pms_manage_settings')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/settings.php';
    }
}

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
        if (PMS_Modules::is_active('tasks')) {
            add_submenu_page('pms-dashboard', __('My Tasks', 'pms'), __('My Tasks', 'pms'), 'pms_view_assigned_tasks', 'pms-my-tasks', [$this, 'render_my_tasks']);
        }
        if (PMS_Modules::is_active('attendance')) {
            add_submenu_page('pms-dashboard', __('Attendance', 'pms'), __('Attendance', 'pms'), 'pms_view_attendance', 'pms-attendance', [$this, 'render_attendance']);
        }
        if (PMS_Modules::is_active('tasks')) {
            add_submenu_page('pms-dashboard', __('Tasks', 'pms'), __('Tasks', 'pms'), 'pms_manage_tasks', 'pms-tasks', [$this, 'render_tasks']);
        }
        add_submenu_page('pms-dashboard', __('People', 'pms'), __('People', 'pms'), 'pms_manage_users', 'pms-users', [$this, 'render_users']);
        add_submenu_page(null, __('Add person', 'pms'), __('Add person', 'pms'), 'pms_manage_users', 'pms-user-new', [$this, 'render_user_new']);
        add_submenu_page('pms-dashboard', __('Roles & Permissions', 'pms'), __('Roles & Permissions', 'pms'), 'pms_manage_users', 'pms-roles', [$this, 'render_roles']);
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
        if (! PMS_Modules::is_active('tasks') || ! current_user_can('pms_view_assigned_tasks')) {
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
        if (! PMS_Modules::is_active('tasks') || ! current_user_can('pms_manage_tasks')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/tasks.php';
    }

    public function render_user_new(): void
    {
        if (! current_user_can('pms_manage_users')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }

        $error = '';
        $success = '';
        $values = [
            'username' => '', 'email' => '', 'first_name' => '', 'last_name' => '',
            'password' => '', 'role' => PMS_Roles::STAFF_ROLE, 'designation' => '', 'department_id' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pms_create_person'])) {
            check_admin_referer('pms_create_person', 'pms_create_person_nonce');
            foreach (array_keys($values) as $key) {
                if (isset($_POST[$key])) {
                    $values[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
                }
            }
            $values['email'] = sanitize_email($values['email']);

            if ($values['username'] === '' || $values['email'] === '') {
                $error = __('Username and email are required.', 'pms');
            } elseif (! is_email($values['email'])) {
                $error = __('Enter a valid email address.', 'pms');
            } elseif (username_exists($values['username'])) {
                $error = __('That username is already in use.', 'pms');
            } elseif (email_exists($values['email'])) {
                $error = __('That email address is already in use.', 'pms');
            } else {
                $role = get_role($values['role']);
                if (! $role) {
                    $error = __('Select a valid role.', 'pms');
                } else {
                    $password = $values['password'];
                    if (strlen($password) < 8) {
                        $error = __('Password must be at least 8 characters.', 'pms');
                    }
                    if ($error !== '') {
                        $user_id = 0;
                    } else {
                    $user_id = wp_create_user($values['username'], $password, $values['email']);
                    if (is_wp_error($user_id)) {
                        $error = $user_id->get_error_message();
                    } else {
                        wp_update_user([
                            'ID' => $user_id,
                            'first_name' => $values['first_name'],
                            'last_name' => $values['last_name'],
                            'display_name' => trim($values['first_name'] . ' ' . $values['last_name']) ?: $values['username'],
                            'role' => $values['role'],
                        ]);
                        update_user_meta($user_id, 'pms_designation', $values['designation']);
                        update_user_meta($user_id, 'pms_department_id', $values['department_id'] !== '' ? (int) $values['department_id'] : 0);
                        $success = sprintf(__('Person created successfully. %s', 'pms'), esc_html($values['username']));
                        $values = ['username' => '', 'email' => '', 'first_name' => '', 'last_name' => '', 'password' => '', 'role' => PMS_Roles::STAFF_ROLE, 'designation' => '', 'department_id' => ''];
                    }
                    }
                }
            }
        }

        $roles = array_filter(PMS_Roles::role_definitions_for_ui(), function ($role, $slug) {
            return $slug !== 'administrator' || current_user_can('manage_options');
        }, ARRAY_FILTER_USE_BOTH);
        $departments = PMS_DB::get_departments();
        include PMS_PLUGIN_DIR . 'admin/views/user-new.php';
    }

    public function render_roles(): void
    {
        if (! current_user_can('pms_manage_users')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }

        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pms_role_action'])) {
            check_admin_referer('pms_roles_manage', 'pms_roles_nonce');
            $action = sanitize_key(wp_unslash($_POST['pms_role_action']));
            $caps = isset($_POST['caps']) && is_array($_POST['caps']) ? array_map('sanitize_key', wp_unslash($_POST['caps'])) : [];
            if ($action === 'create') {
                $name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
                $created = $name !== '' ? PMS_Roles::create_custom_role($name, $caps) : '';
                $notice = $created ? __('Custom role created.', 'pms') : __('Could not create the role. Check the role name and try again.', 'pms');
            } elseif ($action === 'save') {
                $slug = isset($_POST['role_slug']) ? sanitize_key(wp_unslash($_POST['role_slug'])) : '';
                $notice = PMS_Roles::save_role_capabilities($slug, $caps) ? __('Role permissions saved.', 'pms') : __('Could not save that role.', 'pms');
            } elseif ($action === 'delete') {
                $slug = isset($_POST['role_slug']) ? sanitize_key(wp_unslash($_POST['role_slug'])) : '';
                $notice = PMS_Roles::delete_custom_role($slug) ? __('Custom role deleted.', 'pms') : __('Only custom ERP roles can be deleted.', 'pms');
            }
        }

        $roles = PMS_Roles::role_definitions_for_ui();
        $catalogue = PMS_Roles::capability_catalogue();
        include PMS_PLUGIN_DIR . 'admin/views/roles.php';
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

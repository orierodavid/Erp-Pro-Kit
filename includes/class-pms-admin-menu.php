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
            add_submenu_page('pms-dashboard', __('Attendance', 'pms'), __('Attendance', 'pms'), 'pms_clock_in_out', 'pms-attendance', [$this, 'render_attendance']);
        }
        if (PMS_Modules::is_active('leave')) {
            add_submenu_page('pms-dashboard', __('Leave', 'pms'), __('Leave', 'pms'), 'pms_request_leave', 'pms-leave', [$this, 'render_leave']);
        }
        if (PMS_Modules::is_active('tasks')) {
            add_submenu_page('pms-dashboard', __('Tasks', 'pms'), __('Tasks', 'pms'), 'pms_manage_tasks', 'pms-tasks', [$this, 'render_tasks']);
        }
        if (PMS_Modules::is_active('tasks') && current_user_can('pms_manage_tasks')) {
            add_submenu_page('pms-dashboard', __('Projects', 'pms'), __('Projects', 'pms'), 'pms_manage_tasks', 'pms-projects', [$this, 'render_projects']);
            add_submenu_page('pms-dashboard', __('Assignments', 'pms'), __('Assignments', 'pms'), 'pms_manage_tasks', 'pms-assignments', [$this, 'render_assignments']);
            add_submenu_page('pms-dashboard', __('Task Reports', 'pms'), __('Task Reports', 'pms'), 'pms_view_reports', 'pms-task-reports', [$this, 'render_task_reports']);
        }
        if (PMS_Modules::is_active('crm') && current_user_can('pms_manage_crm')) {
            add_submenu_page('pms-dashboard', __('CRM / Sales', 'pms'), __('CRM / Sales', 'pms'), 'pms_manage_crm', 'pms-crm', [$this, 'render_crm']);
        }
        if (PMS_Modules::is_active('invoicing') && current_user_can('pms_manage_invoices')) {
            add_submenu_page('pms-dashboard', __('Invoicing', 'pms'), __('Invoicing', 'pms'), 'pms_manage_invoices', 'pms-invoicing', [$this, 'render_invoicing']);
        }
        if (PMS_Modules::is_active('expenses') && (current_user_can('pms_manage_expenses') || current_user_can('pms_submit_expenses'))) {
            add_submenu_page('pms-dashboard', __('Expenses', 'pms'), __('Expenses', 'pms'), 'read', 'pms-expenses', [$this, 'render_expenses']);
        }
        if (PMS_Modules::is_active('payroll') && current_user_can('pms_manage_payroll')) {
            add_submenu_page('pms-dashboard', __('Payroll', 'pms'), __('Payroll', 'pms'), 'pms_manage_payroll', 'pms-payroll', [$this, 'render_payroll']);
            add_submenu_page(null, __('Salary Details', 'pms'), __('Salary Details', 'pms'), 'pms_manage_payroll', 'pms-payroll-salary', [$this, 'render_payroll']);
            add_submenu_page(null, __('Allowances', 'pms'), __('Allowances', 'pms'), 'pms_manage_payroll', 'pms-payroll-allowances', [$this, 'render_payroll']);
            add_submenu_page(null, __('Deductions', 'pms'), __('Deductions', 'pms'), 'pms_manage_payroll', 'pms-payroll-deductions', [$this, 'render_payroll']);
            add_submenu_page(null, __('Payslips', 'pms'), __('Payslips', 'pms'), 'pms_manage_payroll', 'pms-payroll-payslips', [$this, 'render_payroll']);
            add_submenu_page(null, __('Payroll Runs', 'pms'), __('Payroll Runs', 'pms'), 'pms_manage_payroll', 'pms-payroll-runs', [$this, 'render_payroll']);
            add_submenu_page(null, __('Payroll Reports', 'pms'), __('Payroll Reports', 'pms'), 'pms_manage_payroll', 'pms-payroll-reports', [$this, 'render_payroll']);
        }
        add_submenu_page('pms-dashboard', __('People', 'pms'), __('People', 'pms'), 'pms_manage_users', 'pms-users', [$this, 'render_users']);
        add_submenu_page(null, __('Add person', 'pms'), __('Add person', 'pms'), 'pms_manage_users', 'pms-user-new', [$this, 'render_user_new']);
        add_submenu_page(null, __('Employee profile', 'pms'), __('Employee profile', 'pms'), 'pms_manage_users', 'pms-user-edit', [$this, 'render_user_edit']);
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
        if (! PMS_Modules::is_active('attendance') || ! current_user_can('pms_clock_in_out')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/attendance.php';
    }

    public function render_leave(): void
    {
        if (! PMS_Modules::is_active('leave') || (! current_user_can('pms_request_leave') && ! current_user_can('pms_manage_leave'))) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/leave.php';
    }

    public function render_crm(): void
    {
        if (! PMS_Modules::is_active('crm') || ! current_user_can('pms_manage_crm')) { wp_die(__('You do not have permission to view this page.', 'pms')); }
        include PMS_PLUGIN_DIR . 'admin/views/crm.php';
    }

    public function render_invoicing(): void
    {
        if (! PMS_Modules::is_active('invoicing') || ! current_user_can('pms_manage_invoices')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/invoicing.php';
    }

    public function render_expenses(): void
    {
        if (! PMS_Modules::is_active('expenses') || (! current_user_can('pms_manage_expenses') && ! current_user_can('pms_submit_expenses'))) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/expenses.php';
    }

    public function render_payroll(): void
    {
        if (! PMS_Modules::is_active('payroll') || ! current_user_can('pms_manage_payroll')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/payroll.php';
    }

    public function render_projects(): void
    {
        if (! PMS_Modules::is_active('tasks') || ! current_user_can('pms_manage_tasks')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/projects.php';
    }

    public function render_assignments(): void
    {
        if (! PMS_Modules::is_active('tasks') || ! current_user_can('pms_manage_tasks')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/assignments.php';
    }

    public function render_task_reports(): void
    {
        if (! PMS_Modules::is_active('tasks') || ! current_user_can('pms_view_reports')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }
        include PMS_PLUGIN_DIR . 'admin/views/task-reports.php';
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

    public function render_user_edit(): void
    {
        if (! current_user_can('pms_manage_users')) {
            wp_die(__('You do not have permission to view this page.', 'pms'));
        }

        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        $user = $user_id ? get_user_by('id', $user_id) : false;

        if (! $user) {
            wp_die(__('Employee not found.', 'pms'));
        }

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pms_update_person'])) {
            check_admin_referer('pms_update_person_' . $user_id, 'pms_update_person_nonce');

            $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
            $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $role_slug = isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : '';
            $designation = isset($_POST['designation']) ? sanitize_text_field(wp_unslash($_POST['designation'])) : '';
            $department_id = isset($_POST['department_id']) ? absint($_POST['department_id']) : 0;
            $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

            if (! is_email($email)) {
                $error = __('Enter a valid email address.', 'pms');
            } elseif (email_exists($email) && (int) email_exists($email) !== $user_id) {
                $error = __('That email address is already in use.', 'pms');
            } elseif (! get_role($role_slug)) {
                $error = __('Select a valid role.', 'pms');
            } elseif ($role_slug === 'administrator' && ! current_user_can('manage_options')) {
                $error = __('Only a WordPress administrator can assign the Administrator role.', 'pms');
            } elseif ($password !== '' && strlen($password) < 8) {
                $error = __('Password must be at least 8 characters.', 'pms');
            } else {
                $updated = wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'user_email' => $email,
                    'display_name' => trim($first_name . ' ' . $last_name) ?: $user->user_login,
                ]);

                if (is_wp_error($updated)) {
                    $error = $updated->get_error_message();
                } else {
                    $user->set_role($role_slug);
                    if ($password !== '') {
                        wp_set_password($password, $user_id);
                    }
                    update_user_meta($user_id, 'pms_designation', $designation);
                    if ($department_id) {
                        update_user_meta($user_id, 'pms_department_id', $department_id);
                    } else {
                        delete_user_meta($user_id, 'pms_department_id');
                    }
                    $success = __('Employee profile updated successfully.', 'pms');
                    $user = get_user_by('id', $user_id);
                }
            }
        }

        $roles = PMS_Roles::role_definitions_for_ui();
        if (! current_user_can('manage_options')) {
            unset($roles['administrator']);
        }
        $departments = PMS_DB::get_departments();
        include PMS_PLUGIN_DIR . 'admin/views/user-edit.php';
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

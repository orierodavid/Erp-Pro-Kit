<?php
/**
 * Registers the two PMS-specific roles and their capabilities.
 *
 * This replaces Spatie's roles/permissions entirely — WordPress only has
 * ONE auth system (wp_users + capabilities), so there's no guard-mismatch
 * class of bug possible here the way there was in the Laravel version.
 *
 * Capability naming: prefix everything with pms_ so it never collides with
 * WordPress core or another plugin's capabilities.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Roles
{
    /** ERP roles and their base capabilities. Module capabilities are additive. */
    public static function erp_roles(): array
    {
        return [
            'pms_hr_manager' => [
                'label' => __('HR Manager', 'pms'),
                'caps' => ['pms_manage_users', 'pms_manage_departments', 'pms_manage_branches', 'pms_manage_hr', 'pms_view_attendance', 'pms_manage_leave'],
            ],
            'pms_accountant' => [
                'label' => __('Accountant', 'pms'),
                'caps' => ['pms_manage_payroll', 'pms_manage_invoices', 'pms_manage_expenses', 'pms_submit_expenses', 'pms_view_reports'],
            ],
            'pms_property_manager' => [
                'label' => __('Property Manager', 'pms'),
                'caps' => ['pms_manage_real_estate', 'pms_manage_crm', 'pms_manage_tasks'],
            ],
            'pms_sales_manager' => [
                'label' => __('Sales / CRM Manager', 'pms'),
                'caps' => ['pms_manage_crm', 'pms_manage_invoices', 'pms_manage_tasks'],
            ],
            'pms_real_estate_agent' => [
                'label' => __('Real Estate Agent', 'pms'),
                'caps' => ['pms_view_real_estate', 'pms_manage_crm', 'pms_view_assigned_tasks', 'pms_update_own_tasks'],
            ],
            'pms_project_manager' => [
                'label' => __('Project Manager', 'pms'),
                'caps' => ['pms_manage_tasks', 'pms_view_reports'],
            ],
        ];
    }

    public const ADMIN_ROLE = 'pms_admin';
    public const STAFF_ROLE = 'pms_staff';

    /** Capabilities an Admin/Super Admin has. */
    public static function admin_capabilities(): array
    {
        return [
            'pms_manage_users'       => true,
            'pms_manage_branches'    => true,
            'pms_manage_departments' => true,
            'pms_manage_tasks'       => true,
            'pms_view_reports'       => true,
            'pms_manage_settings'    => true,
            'pms_clock_in_out'       => true,
            'pms_view_all_attendance' => true,
            'pms_view_attendance'     => true,
            'pms_manage_hr'           => true,
            'pms_manage_leave'        => true,
            'pms_manage_payroll'      => true,
            'pms_manage_crm'          => true,
            'pms_manage_real_estate'  => true,
            'pms_view_real_estate'    => true,
            'pms_manage_invoices'     => true,
            'pms_manage_expenses'     => true,
            'read'                   => true, // needed so the account can log into wp-admin at all
        ];
    }

    /** Capabilities a Staff member has. */
    public static function staff_capabilities(): array
    {
        return [
            'pms_clock_in_out'         => true,
            'pms_view_assigned_tasks'  => true,
            'pms_update_own_tasks'     => true,
            'pms_comment_on_tasks'     => true,
            'pms_upload_attachments'   => true,
            'pms_request_leave'       => true,
            'pms_submit_expenses'    => true,
            'read'                     => true,
        ];
    }

    /**
     * Called on plugin activation. Safe to call repeatedly — add_role()
     * silently no-ops if the role already exists, so we remove+re-add to
     * pick up any capability changes on plugin upgrade.
     */
    public static function register(): void
    {
        $admin = get_role(self::ADMIN_ROLE);
        if (! $admin) {
            add_role(self::ADMIN_ROLE, __('PMS Admin', 'pms'), self::admin_capabilities());
        } else {
            foreach (array_keys(self::admin_capabilities()) as $cap) {
                $admin->add_cap($cap);
            }
        }

        $staff = get_role(self::STAFF_ROLE);
        if (! $staff) {
            add_role(self::STAFF_ROLE, __('PMS Staff', 'pms'), self::staff_capabilities());
        } else {
            foreach (array_keys(self::staff_capabilities()) as $cap) {
                $staff->add_cap($cap);
            }
        }

        foreach (self::erp_roles() as $slug => $definition) {
            $role = get_role($slug);
            $caps = array_fill_keys($definition['caps'], true);
            $caps['read'] = true;

            if (! $role) {
                add_role($slug, $definition['label'], $caps);
                continue;
            }

            foreach (array_keys($caps) as $cap) {
                $role->add_cap($cap);
            }
        }

        self::grant_to_administrators();
    }

    /**
     * WordPress's built-in Administrator role does NOT automatically get
     * our custom pms_ capabilities just because we registered them on
     * a different role — without this, a normal site owner logging in
     * with their existing Administrator account would only see the
     * Dashboard (which only needs 'read') and nothing else, because
     * every other menu item requires a specific pms_ capability they
     * were never granted. Site owners should always have full access,
     * regardless of whether they've also assigned themselves a PMS role.
     */
    public static function grant_to_administrators(): void
    {
        $role = get_role('administrator');

        if (! $role) {
            return;
        }

        foreach (array_keys(self::admin_capabilities()) as $cap) {
            $role->add_cap($cap);
        }
    }

    public static function unregister(): void
    {
        remove_role(self::ADMIN_ROLE);
        remove_role(self::STAFF_ROLE);
        foreach (array_keys(self::erp_roles()) as $role_slug) {
            remove_role($role_slug);
        }

        $role = get_role('administrator');
        if ($role) {
            foreach (array_keys(self::admin_capabilities()) as $cap) {
                if ($cap !== 'read') { // never strip 'read' — that's core to WP itself
                    $role->remove_cap($cap);
                }
            }
        }
    }

    /**
     * Single capability catalogue used by the Roles & Permissions screen.
     * Keeping this in one place prevents the UI from inventing capability names.
     */
    public static function capability_catalogue(): array
    {
        return [
            'People' => [
                'pms_manage_users' => __('Manage people', 'pms'),
                'pms_manage_departments' => __('Manage departments', 'pms'),
                'pms_manage_branches' => __('Manage branches', 'pms'),
            ],
            'Workforce' => [
                'pms_manage_hr' => __('Manage HR records', 'pms'),
                'pms_clock_in_out' => __('Clock in / out', 'pms'),
                'pms_view_attendance' => __('View attendance', 'pms'),
                'pms_view_all_attendance' => __('View all attendance', 'pms'),
                'pms_manage_leave' => __('Manage leave', 'pms'),
                'pms_request_leave' => __('Request leave', 'pms'),
            ],
            'Work' => [
                'pms_manage_tasks' => __('Manage all tasks', 'pms'),
                'pms_view_assigned_tasks' => __('View assigned tasks', 'pms'),
                'pms_update_own_tasks' => __('Update own tasks', 'pms'),
                'pms_comment_on_tasks' => __('Comment on tasks', 'pms'),
                'pms_upload_attachments' => __('Upload task attachments', 'pms'),
                'pms_view_reports' => __('View reports', 'pms'),
            ],
            'Finance' => [
                'pms_manage_payroll' => __('Manage payroll', 'pms'),
                'pms_manage_invoices' => __('Manage invoices', 'pms'),
                'pms_manage_expenses' => __('Manage expenses', 'pms'),
                'pms_manage_inventory' => __('Manage inventory', 'pms'),
                'pms_submit_expenses' => __('Submit expenses', 'pms'),
            ],
            'Sales & Real Estate' => [
                'pms_manage_crm' => __('Manage CRM', 'pms'),
                'pms_view_real_estate' => __('View real estate', 'pms'),
                'pms_manage_real_estate' => __('Manage real estate', 'pms'),
            ],
            'System' => [
                'pms_manage_settings' => __('Manage settings', 'pms'),
            ],
        ];
    }

    public static function capability_labels(): array
    {
        $labels = [];
        foreach (self::capability_catalogue() as $group) {
            $labels = array_merge($labels, $group);
        }
        return $labels;
    }

    public static function role_label(string $role_slug): string
    {
        if ($role_slug === 'administrator') {
            return __('Administrator', 'pms');
        }
        if ($role_slug === self::ADMIN_ROLE) {
            return __('PMS Admin', 'pms');
        }
        if ($role_slug === self::STAFF_ROLE) {
            return __('Staff / Employee', 'pms');
        }
        $roles = self::erp_roles();
        if (isset($roles[$role_slug])) {
            return $roles[$role_slug]['label'];
        }
        $wp_roles = wp_roles();
        return isset($wp_roles->roles[$role_slug]) ? translate_user_role($wp_roles->roles[$role_slug]['name']) : $role_slug;
    }

    public static function managed_role_slugs(): array
    {
        return array_merge([self::ADMIN_ROLE, self::STAFF_ROLE], array_keys(self::erp_roles()));
    }

    public static function role_definitions_for_ui(): array
    {
        $definitions = [];
        $administrator = get_role('administrator');
        if ($administrator) {
            $definitions['administrator'] = [
                'label' => __('Administrator', 'pms'),
                'caps' => $administrator->capabilities,
                'built_in' => true,
                'protected' => true,
            ];
        }
        foreach (self::managed_role_slugs() as $slug) {
            $role = get_role($slug);
            if ($role) {
                $definitions[$slug] = [
                    'label' => self::role_label($slug),
                    'caps' => $role->capabilities,
                    'built_in' => true,
                    'protected' => false,
                ];
            }
        }
        foreach (wp_roles()->roles as $slug => $definition) {
            if (strpos($slug, 'pms_custom_') === 0) {
                $role = get_role($slug);
                if ($role) {
                    $definitions[$slug] = [
                        'label' => translate_user_role($definition['name']),
                        'caps' => $role->capabilities,
                        'built_in' => false,
                        'protected' => false,
                    ];
                }
            }
        }
        return $definitions;
    }

    public static function save_role_capabilities(string $slug, array $caps): bool
    {
        if ($slug === 'administrator') {
            return false;
        }
        $role = get_role($slug);
        if (! $role) {
            return false;
        }

        $allowed = array_keys(self::capability_labels());
        $selected = array_values(array_intersect($allowed, $caps));

        foreach ($allowed as $cap) {
            if (in_array($cap, $selected, true)) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }
        $role->add_cap('read');
        return true;
    }

    public static function create_custom_role(string $name, array $caps): string
    {
        $slug_base = sanitize_title($name);
        $slug = 'pms_custom_' . ($slug_base ?: 'role');
        $i = 2;
        while (get_role($slug)) {
            $slug = 'pms_custom_' . ($slug_base ?: 'role') . '_' . $i;
            $i++;
        }
        $role = add_role($slug, $name, ['read' => true]);
        if (! $role) {
            return '';
        }
        self::save_role_capabilities($slug, $caps);
        return $slug;
    }

    public static function delete_custom_role(string $slug): bool
    {
        if (strpos($slug, 'pms_custom_') !== 0 || ! get_role($slug)) {
            return false;
        }
        remove_role($slug);
        return true;
    }

    /** Convenience check used throughout the plugin instead of role-name string matching. */
    public static function current_user_is_pms_admin(): bool
    {
        return current_user_can('pms_manage_tasks') && current_user_can('pms_manage_users');
    }

    public static function current_user_is_pms_staff(): bool
    {
        $user = wp_get_current_user();

        return in_array(self::STAFF_ROLE, (array) $user->roles, true);
    }

    /**
     * Same check as current_user_is_pms_admin(), but for an arbitrary
     * WP_User object — needed anywhere we're listing/labeling OTHER
     * people (People page, task assignment dropdown), not just the
     * person currently logged in. Capability-based rather than
     * role-name-based, so it correctly recognizes a WordPress
     * Administrator as an admin even though they were never literally
     * assigned the "PMS Admin" role — they just have the capabilities.
     */
    public static function user_is_pms_admin(WP_User $user): bool
    {
        return user_can($user, 'pms_manage_tasks') && user_can($user, 'pms_manage_users');
    }

    /**
     * Everyone who should be selectable as a task assignee or shown on
     * the People page — anyone with either set of PMS capabilities,
     * found by capability rather than literal role name so it also
     * picks up Administrators (see user_is_pms_admin() above).
     */
    public static function get_pms_people(): array
    {
        return get_users([
            'role__in' => array_merge(
                ['administrator', self::ADMIN_ROLE, self::STAFF_ROLE],
                array_keys(self::erp_roles())
            ),
            'orderby' => 'display_name',
        ]);
    }
}

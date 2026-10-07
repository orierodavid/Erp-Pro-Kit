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
        remove_role(self::ADMIN_ROLE);
        remove_role(self::STAFF_ROLE);

        add_role(self::ADMIN_ROLE, __('PMS Admin', 'pms'), self::admin_capabilities());
        add_role(self::STAFF_ROLE, __('PMS Staff', 'pms'), self::staff_capabilities());

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

        $role = get_role('administrator');
        if ($role) {
            foreach (array_keys(self::admin_capabilities()) as $cap) {
                if ($cap !== 'read') { // never strip 'read' — that's core to WP itself
                    $role->remove_cap($cap);
                }
            }
        }
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
            'capability__in' => ['pms_manage_tasks', 'pms_view_assigned_tasks'],
            'orderby'        => 'display_name',
        ]);
    }
}

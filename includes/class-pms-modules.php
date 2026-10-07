<?php
/**
 * ERP module registry and activation state.
 *
 * Modules are company-wide switches. Employee access is controlled separately
 * by WordPress roles/capabilities in PMS_Roles.
 */
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Modules
{
    private const OPTION = 'pms_active_modules';

    public static function definitions(): array
    {
        return [
            'hr'         => ['label' => __('HR / Staff', 'pms'), 'description' => __('Staff records, employment details and HR administration.', 'pms')],
            'attendance' => ['label' => __('Attendance', 'pms'), 'description' => __('Clock in/out, attendance history and attendance reporting.', 'pms')],
            'leave'      => ['label' => __('Leave', 'pms'), 'description' => __('Leave requests, approvals and leave balances.', 'pms')],
            'payroll'    => ['label' => __('Payroll', 'pms'), 'description' => __('Salary, allowances, deductions and payslips.', 'pms')],
            'tasks'      => ['label' => __('Projects / Tasks', 'pms'), 'description' => __('The existing project and task management system.', 'pms')],
            'crm'        => ['label' => __('CRM / Sales', 'pms'), 'description' => __('Leads, prospects, pipeline, deals and follow-ups.', 'pms')],
            'real_estate'=> ['label' => __('Real Estate', 'pms'), 'description' => __('Properties, listings, viewings, tenants, leases and commissions.', 'pms')],
            'invoicing'  => ['label' => __('Invoicing', 'pms'), 'description' => __('Quotes, invoices, payments and outstanding balances.', 'pms')],
            'expenses'   => ['label' => __('Expenses', 'pms'), 'description' => __('Expense recording, approvals and reports.', 'pms')],
            'inventory'  => ['label' => __('Inventory', 'pms'), 'description' => __('Items, stock and suppliers.', 'pms')],
        ];
    }

    public static function active(): array
    {
        $saved = get_option(self::OPTION, ['hr', 'tasks']);
        $saved = is_array($saved) ? array_map('sanitize_key', $saved) : [];

        return array_values(array_intersect(array_keys(self::definitions()), $saved));
    }

    public static function is_active(string $module): bool
    {
        return in_array($module, self::active(), true);
    }

    public static function set_active(array $modules): void
    {
        $modules = array_values(array_intersect(
            array_keys(self::definitions()),
            array_unique(array_map('sanitize_key', $modules))
        ));

        update_option(self::OPTION, $modules);
    }

    public static function option_name(): string
    {
        return self::OPTION;
    }
}

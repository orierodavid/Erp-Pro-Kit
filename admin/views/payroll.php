<?php
if (! defined('ABSPATH')) { exit; }

if (! current_user_can('pms_manage_payroll')) { wp_die(__('You do not have permission to view this page.', 'pms')); }

$tab = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'pms-payroll';
$allowed_tabs = ['pms-payroll','pms-payroll-salary','pms-payroll-allowances','pms-payroll-deductions','pms-payroll-payslips','pms-payroll-runs','pms-payroll-reports'];
if (! in_array($tab, $allowed_tabs, true)) { $tab = 'pms-payroll'; }

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pms_payroll_action'])) {
    check_admin_referer('pms_payroll_manage', 'pms_payroll_nonce');
    $action = sanitize_key(wp_unslash($_POST['pms_payroll_action']));

    if ($action === 'save_salary') {
        $user_id = absint($_POST['user_id'] ?? 0);
        $salary = isset($_POST['basic_salary']) ? (float) wp_unslash($_POST['basic_salary']) : 0;
        $currency = isset($_POST['currency']) ? sanitize_text_field(wp_unslash($_POST['currency'])) : '';
        $frequency = isset($_POST['pay_frequency']) ? sanitize_key(wp_unslash($_POST['pay_frequency'])) : 'monthly';
        $effective = isset($_POST['effective_from']) ? sanitize_text_field(wp_unslash($_POST['effective_from'])) : '';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'active';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        if (! $user_id || $salary < 0 || $currency === '') {
            $error = __('Employee, salary and currency are required.', 'pms');
        } else {
            PMS_Payroll::save_salary($user_id, ['basic_salary'=>$salary,'currency'=>$currency,'pay_frequency'=>$frequency,'effective_from'=>$effective,'status'=>$status,'notes'=>$notes]);
            $notice = __('Salary details saved.', 'pms');
        }
        $tab = 'pms-payroll-salary';
    }

    if ($action === 'save_component') {
        $type = sanitize_key(wp_unslash($_POST['component_type'] ?? 'allowance'));
        $id = absint($_POST['component_id'] ?? 0);
        $user_id = absint($_POST['user_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $amount = (float) wp_unslash($_POST['amount'] ?? 0);
        $frequency = sanitize_key(wp_unslash($_POST['frequency'] ?? 'monthly'));
        $effective = sanitize_text_field(wp_unslash($_POST['effective_from'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'active'));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        if (! in_array($type, ['allowance','deduction'], true) || ! $user_id || $name === '' || $amount < 0) {
            $error = __('Employee, name and a valid amount are required.', 'pms');
        } else {
            PMS_Payroll::save_component($type, ['user_id'=>$user_id,'name'=>$name,'amount'=>$amount,'frequency'=>$frequency,'effective_from'=>$effective,'status'=>$status,'notes'=>$notes,'taxable'=>!empty($_POST['taxable'])], $id ?: null);
            $notice = $id ? __('Payroll component updated.', 'pms') : __('Payroll component added.', 'pms');
        }
        $tab = $type === 'deduction' ? 'pms-payroll-deductions' : 'pms-payroll-allowances';
    }

    if ($action === 'delete_component') {
        $type = sanitize_key(wp_unslash($_POST['component_type'] ?? 'allowance'));
        $id = absint($_POST['component_id'] ?? 0);
        if (in_array($type, ['allowance','deduction'], true) && $id) {
            PMS_Payroll::delete_component($type, $id);
            $notice = __('Payroll component deleted.', 'pms');
        }
        $tab = $type === 'deduction' ? 'pms-payroll-deductions' : 'pms-payroll-allowances';
    }

    if ($action === 'create_run') {
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $start = sanitize_text_field(wp_unslash($_POST['period_start'] ?? ''));
        $end = sanitize_text_field(wp_unslash($_POST['period_end'] ?? ''));
        $pay_date = sanitize_text_field(wp_unslash($_POST['pay_date'] ?? ''));
        if ($name === '' || $start === '' || $end === '') {
            $error = __('Run name and payroll period are required.', 'pms');
        } elseif ($end < $start) {
            $error = __('The payroll period end date cannot be before the start date.', 'pms');
        } else {
            PMS_Payroll::create_run(['name'=>$name,'period_start'=>$start,'period_end'=>$end,'pay_date'=>$pay_date]);
            $notice = __('Payroll run created.', 'pms');
        }
        $tab = 'pms-payroll-runs';
    }

    if ($action === 'generate_payslips') {
        $run_id = absint($_POST['run_id'] ?? 0);
        $count = PMS_Payroll::generate_payslips($run_id);
        $notice = sprintf(_n('%d payslip generated.', '%d payslips generated.', $count, 'pms'), $count);
        $tab = 'pms-payroll-payslips';
    }
}

$employees = PMS_Payroll::employees();
$salaries = PMS_Payroll::salaries();
$allowances = PMS_Payroll::components('allowance');
$deductions = PMS_Payroll::components('deduction');
$runs = PMS_Payroll::runs();
$payslips = PMS_Payroll::payslips();
$totals = PMS_Payroll::report_totals();

$edit_component = null;
$edit_type = '';
if (isset($_GET['edit_component'])) {
    $edit_type = sanitize_key(wp_unslash($_GET['edit_type'] ?? 'allowance'));
    $edit_component = in_array($edit_type, ['allowance','deduction'], true) ? PMS_Payroll::component($edit_type, absint($_GET['edit_component'])) : null;
}

$edit_salary = isset($_GET['edit_salary']) ? PMS_Payroll::salary(absint($_GET['edit_salary'])) : null;

include PMS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<div class="pms-page-header pms-payroll-header">
    <div>
        <p class="pms-eyebrow"><?php esc_html_e('Finance', 'pms'); ?></p>
        <h1><?php esc_html_e('Payroll', 'pms'); ?></h1>
        <p class="pms-hint"><?php esc_html_e('Manage employee salary details, recurring payroll components, payroll runs and payslips.', 'pms'); ?></p>
    </div>
</div>

<?php if ($notice) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>
<?php if ($error) : ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>

<nav class="pms-payroll-tabs" aria-label="<?php esc_attr_e('Payroll sections', 'pms'); ?>">
    <?php
    $tabs = [
        'pms-payroll-salary' => ['Salary Details','dashicons-admin-users'],
        'pms-payroll-allowances' => ['Allowances','dashicons-plus-alt'],
        'pms-payroll-deductions' => ['Deductions','dashicons-minus'],
        'pms-payroll-payslips' => ['Payslips','dashicons-media-document'],
        'pms-payroll-runs' => ['Payroll Runs','dashicons-update'],
        'pms-payroll-reports' => ['Payroll Reports','dashicons-chart-bar'],
    ];
    foreach ($tabs as $slug => $meta) :
        $active = ($tab === $slug || ($tab === 'pms-payroll' && $slug === 'pms-payroll-salary'));
        ?>
        <a class="<?php echo $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $slug)); ?>">
            <span class="dashicons <?php echo esc_attr($meta[1]); ?>"></span><?php echo esc_html($meta[0]); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'pms-payroll' || $tab === 'pms-payroll-salary') : ?>
    <div class="pms-kpi-grid">
        <div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Employees with salary details','pms'); ?></span><strong><?php echo esc_html(count($salaries)); ?></strong></div>
        <div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Active allowances','pms'); ?></span><strong><?php echo esc_html(count(array_filter($allowances, fn($x) => $x->status === 'active'))); ?></strong></div>
        <div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Active deductions','pms'); ?></span><strong><?php echo esc_html(count(array_filter($deductions, fn($x) => $x->status === 'active'))); ?></strong></div>
    </div>

    <div class="pms-panel pms-form-panel">
        <div class="pms-section-head"><div><h2><?php echo $edit_salary ? esc_html__('Edit salary details','pms') : esc_html__('Add salary details','pms'); ?></h2><p><?php esc_html_e('Set the employee’s current basic salary and payroll frequency.','pms'); ?></p></div></div>
        <form method="post">
            <?php wp_nonce_field('pms_payroll_manage','pms_payroll_nonce'); ?>
            <input type="hidden" name="pms_payroll_action" value="save_salary">
            <div class="pms-form-grid">
                <div class="pms-form-row"><label><?php esc_html_e('Employee','pms'); ?></label><select name="user_id" class="pms-input" required <?php disabled((bool)$edit_salary); ?>><option value=""><?php esc_html_e('Select employee','pms'); ?></option><?php foreach($employees as $person): ?><option value="<?php echo esc_attr($person->ID); ?>" <?php selected($edit_salary->user_id ?? '', $person->ID); ?>><?php echo esc_html($person->display_name . ' — ' . $person->user_email); ?></option><?php endforeach; ?></select><?php if($edit_salary): ?><input type="hidden" name="user_id" value="<?php echo esc_attr($edit_salary->user_id); ?>"><?php endif; ?></div>
                <div class="pms-form-row"><label><?php esc_html_e('Basic salary','pms'); ?></label><input type="number" step="0.01" min="0" name="basic_salary" class="pms-input" required value="<?php echo esc_attr($edit_salary->basic_salary ?? ''); ?>"></div>
                <div class="pms-form-row"><label><?php esc_html_e('Currency','pms'); ?></label><input type="text" maxlength="10" name="currency" class="pms-input" required value="<?php echo esc_attr($edit_salary->currency ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. USD','pms'); ?>"></div>
                <div class="pms-form-row"><label><?php esc_html_e('Pay frequency','pms'); ?></label><select name="pay_frequency" class="pms-input"><option value="monthly" <?php selected($edit_salary->pay_frequency ?? 'monthly','monthly'); ?>><?php esc_html_e('Monthly','pms'); ?></option><option value="weekly" <?php selected($edit_salary->pay_frequency ?? 'monthly','weekly'); ?>><?php esc_html_e('Weekly','pms'); ?></option><option value="biweekly" <?php selected($edit_salary->pay_frequency ?? 'monthly','biweekly'); ?>><?php esc_html_e('Bi-weekly','pms'); ?></option></select></div>
                <div class="pms-form-row"><label><?php esc_html_e('Effective from','pms'); ?></label><input type="date" name="effective_from" class="pms-input" value="<?php echo esc_attr($edit_salary->effective_from ?? ''); ?>"></div>
                <div class="pms-form-row"><label><?php esc_html_e('Status','pms'); ?></label><select name="status" class="pms-input"><option value="active" <?php selected($edit_salary->status ?? 'active','active'); ?>><?php esc_html_e('Active','pms'); ?></option><option value="inactive" <?php selected($edit_salary->status ?? 'active','inactive'); ?>><?php esc_html_e('Inactive','pms'); ?></option></select></div>
            </div>
            <div class="pms-form-row"><label><?php esc_html_e('Notes','pms'); ?></label><textarea name="notes" rows="3" class="pms-input"><?php echo esc_textarea($edit_salary->notes ?? ''); ?></textarea></div>
            <button class="pms-btn-primary" type="submit"><span class="dashicons dashicons-saved"></span><?php esc_html_e('Save salary details','pms'); ?></button>
        </form>
    </div>

    <div class="pms-panel">
        <div class="pms-section-head"><div><h2><?php esc_html_e('Salary Details','pms'); ?></h2><p><?php esc_html_e('Current salary record for each employee.','pms'); ?></p></div></div>
        <table class="pms-table"><thead><tr><th><?php esc_html_e('Employee','pms'); ?></th><th><?php esc_html_e('Basic salary','pms'); ?></th><th><?php esc_html_e('Frequency','pms'); ?></th><th><?php esc_html_e('Effective from','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th><th></th></tr></thead><tbody>
        <?php if(empty($salaries)): ?><tr><td colspan="6" class="pms-empty"><?php esc_html_e('No salary details have been added yet.','pms'); ?></td></tr><?php endif; ?>
        <?php foreach($salaries as $row): ?><tr><td><strong><?php echo esc_html($row->display_name); ?></strong><br><small><?php echo esc_html($row->user_email); ?></small></td><td><?php echo esc_html($row->currency . ' ' . number_format((float)$row->basic_salary,2)); ?></td><td><?php echo esc_html(ucfirst($row->pay_frequency)); ?></td><td><?php echo esc_html($row->effective_from ?: '—'); ?></td><td><span class="pms-chip <?php echo $row->status === 'active' ? 'pms-status-done' : 'pms-status-todo'; ?>"><?php echo esc_html(ucfirst($row->status)); ?></span></td><td><a class="pms-btn-secondary pms-btn-small" href="<?php echo esc_url(admin_url('admin.php?page=pms-payroll-salary&edit_salary=' . (int)$row->user_id)); ?>"><?php esc_html_e('Edit','pms'); ?></a></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
<?php endif; ?>

<?php if ($tab === 'pms-payroll-allowances' || $tab === 'pms-payroll-deductions') :
    $is_deduction = $tab === 'pms-payroll-deductions';
    $type = $is_deduction ? 'deduction' : 'allowance';
    $items = $is_deduction ? $deductions : $allowances;
?>
    <div class="pms-panel pms-form-panel">
        <div class="pms-section-head"><div><h2><?php echo $edit_component ? esc_html__('Edit payroll component','pms') : esc_html(sprintf(__('Add %s','pms'), $is_deduction ? __('deduction','pms') : __('allowance','pms'))); ?></h2></div></div>
        <form method="post">
            <?php wp_nonce_field('pms_payroll_manage','pms_payroll_nonce'); ?><input type="hidden" name="pms_payroll_action" value="save_component"><input type="hidden" name="component_type" value="<?php echo esc_attr($type); ?>"><input type="hidden" name="component_id" value="<?php echo esc_attr($edit_component->id ?? 0); ?>">
            <div class="pms-form-grid">
                <div class="pms-form-row"><label><?php esc_html_e('Employee','pms'); ?></label><select name="user_id" class="pms-input" required><option value=""><?php esc_html_e('Select employee','pms'); ?></option><?php foreach($employees as $person): ?><option value="<?php echo esc_attr($person->ID); ?>" <?php selected($edit_component->user_id ?? '',$person->ID); ?>><?php echo esc_html($person->display_name); ?></option><?php endforeach; ?></select></div>
                <div class="pms-form-row"><label><?php esc_html_e('Name','pms'); ?></label><input type="text" name="name" class="pms-input" required value="<?php echo esc_attr($edit_component->name ?? ''); ?>"></div>
                <div class="pms-form-row"><label><?php esc_html_e('Amount','pms'); ?></label><input type="number" step="0.01" min="0" name="amount" class="pms-input" required value="<?php echo esc_attr($edit_component->amount ?? ''); ?>"></div>
                <div class="pms-form-row"><label><?php esc_html_e('Frequency','pms'); ?></label><select name="frequency" class="pms-input"><option value="monthly" <?php selected($edit_component->frequency ?? 'monthly','monthly'); ?>><?php esc_html_e('Monthly','pms'); ?></option><option value="weekly" <?php selected($edit_component->frequency ?? 'monthly','weekly'); ?>><?php esc_html_e('Weekly','pms'); ?></option><option value="one_time" <?php selected($edit_component->frequency ?? 'monthly','one_time'); ?>><?php esc_html_e('One time','pms'); ?></option></select></div>
                <div class="pms-form-row"><label><?php esc_html_e('Effective from','pms'); ?></label><input type="date" name="effective_from" class="pms-input" value="<?php echo esc_attr($edit_component->effective_from ?? ''); ?>"></div>
                <div class="pms-form-row"><label><?php esc_html_e('Status','pms'); ?></label><select name="status" class="pms-input"><option value="active" <?php selected($edit_component->status ?? 'active','active'); ?>><?php esc_html_e('Active','pms'); ?></option><option value="inactive" <?php selected($edit_component->status ?? 'active','inactive'); ?>><?php esc_html_e('Inactive','pms'); ?></option></select></div>
                <?php if(!$is_deduction): ?><div class="pms-form-row pms-checkbox-row"><label><input type="checkbox" name="taxable" value="1" <?php checked(!empty($edit_component->taxable)); ?>> <?php esc_html_e('Taxable allowance','pms'); ?></label></div><?php endif; ?>
            </div>
            <div class="pms-form-row"><label><?php esc_html_e('Notes','pms'); ?></label><textarea name="notes" rows="3" class="pms-input"><?php echo esc_textarea($edit_component->notes ?? ''); ?></textarea></div>
            <button class="pms-btn-primary" type="submit"><?php esc_html_e('Save component','pms'); ?></button>
        </form>
    </div>

    <div class="pms-panel"><div class="pms-section-head"><div><h2><?php echo esc_html($is_deduction ? __('Deductions','pms') : __('Allowances','pms')); ?></h2></div></div>
    <table class="pms-table"><thead><tr><th><?php esc_html_e('Employee','pms'); ?></th><th><?php esc_html_e('Name','pms'); ?></th><th><?php esc_html_e('Amount','pms'); ?></th><th><?php esc_html_e('Frequency','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th><th></th></tr></thead><tbody>
    <?php if(empty($items)): ?><tr><td colspan="6" class="pms-empty"><?php esc_html_e('No records yet.','pms'); ?></td></tr><?php endif; ?>
    <?php foreach($items as $row): ?><tr><td><?php echo esc_html($row->display_name); ?></td><td><strong><?php echo esc_html($row->name); ?></strong><?php if(!$is_deduction && !empty($row->taxable)): ?><span class="pms-chip pms-status-in_progress"><?php esc_html_e('Taxable','pms'); ?></span><?php endif; ?></td><td><?php echo esc_html(number_format((float)$row->amount,2)); ?></td><td><?php echo esc_html(ucwords(str_replace('_',' ',$row->frequency))); ?></td><td><span class="pms-chip <?php echo $row->status==='active'?'pms-status-done':'pms-status-todo'; ?>"><?php echo esc_html(ucfirst($row->status)); ?></span></td><td><a class="pms-btn-secondary pms-btn-small" href="<?php echo esc_url(admin_url('admin.php?page=' . ($is_deduction?'pms-payroll-deductions':'pms-payroll-allowances') . '&edit_component=' . (int)$row->id . '&edit_type=' . $type)); ?>"><?php esc_html_e('Edit','pms'); ?></a> <form method="post" class="pms-inline-form"><?php wp_nonce_field('pms_payroll_manage','pms_payroll_nonce'); ?><input type="hidden" name="pms_payroll_action" value="delete_component"><input type="hidden" name="component_type" value="<?php echo esc_attr($type); ?>"><input type="hidden" name="component_id" value="<?php echo esc_attr($row->id); ?>"><button class="pms-btn-danger pms-btn-small" type="submit"><?php esc_html_e('Delete','pms'); ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<?php if ($tab === 'pms-payroll-runs') : ?>
    <div class="pms-panel pms-form-panel"><div class="pms-section-head"><div><h2><?php esc_html_e('Create payroll run','pms'); ?></h2><p><?php esc_html_e('Define a pay period, then generate payslips from active salary records and monthly components.','pms'); ?></p></div></div>
    <form method="post"><?php wp_nonce_field('pms_payroll_manage','pms_payroll_nonce'); ?><input type="hidden" name="pms_payroll_action" value="create_run"><div class="pms-form-grid">
    <div class="pms-form-row"><label><?php esc_html_e('Run name','pms'); ?></label><input name="name" class="pms-input" required placeholder="<?php esc_attr_e('e.g. October 2026 Payroll','pms'); ?>"></div>
    <div class="pms-form-row"><label><?php esc_html_e('Period start','pms'); ?></label><input type="date" name="period_start" class="pms-input" required></div>
    <div class="pms-form-row"><label><?php esc_html_e('Period end','pms'); ?></label><input type="date" name="period_end" class="pms-input" required></div>
    <div class="pms-form-row"><label><?php esc_html_e('Pay date','pms'); ?></label><input type="date" name="pay_date" class="pms-input"></div>
    </div><button class="pms-btn-primary" type="submit"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Create payroll run','pms'); ?></button></form></div>
    <div class="pms-panel"><table class="pms-table"><thead><tr><th><?php esc_html_e('Run','pms'); ?></th><th><?php esc_html_e('Period','pms'); ?></th><th><?php esc_html_e('Pay date','pms'); ?></th><th><?php esc_html_e('Status','pms'); ?></th><th><?php esc_html_e('Payslips','pms'); ?></th><th></th></tr></thead><tbody>
    <?php if(empty($runs)): ?><tr><td colspan="6" class="pms-empty"><?php esc_html_e('No payroll runs yet.','pms'); ?></td></tr><?php endif; ?>
    <?php foreach($runs as $run): ?><tr><td><strong><?php echo esc_html($run->name); ?></strong></td><td><?php echo esc_html($run->period_start . ' → ' . $run->period_end); ?></td><td><?php echo esc_html($run->pay_date ?: '—'); ?></td><td><span class="pms-chip pms-status-<?php echo $run->status==='processed'?'done':'todo'; ?>"><?php echo esc_html(ucfirst($run->status)); ?></span></td><td><?php echo esc_html($run->payslip_count); ?></td><td><form method="post" class="pms-inline-form"><?php wp_nonce_field('pms_payroll_manage','pms_payroll_nonce'); ?><input type="hidden" name="pms_payroll_action" value="generate_payslips"><input type="hidden" name="run_id" value="<?php echo esc_attr($run->id); ?>"><button class="pms-btn-primary pms-btn-small" type="submit"><?php esc_html_e('Generate payslips','pms'); ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<?php if ($tab === 'pms-payroll-payslips') : ?>
    <div class="pms-panel"><div class="pms-section-head"><div><h2><?php esc_html_e('Payslips','pms'); ?></h2><p><?php esc_html_e('Generated from payroll runs.','pms'); ?></p></div></div>
    <table class="pms-table"><thead><tr><th><?php esc_html_e('Employee','pms'); ?></th><th><?php esc_html_e('Run','pms'); ?></th><th><?php esc_html_e('Basic','pms'); ?></th><th><?php esc_html_e('Allowances','pms'); ?></th><th><?php esc_html_e('Deductions','pms'); ?></th><th><?php esc_html_e('Gross','pms'); ?></th><th><?php esc_html_e('Net','pms'); ?></th></tr></thead><tbody>
    <?php if(empty($payslips)): ?><tr><td colspan="7" class="pms-empty"><?php esc_html_e('No payslips have been generated yet.','pms'); ?></td></tr><?php endif; ?>
    <?php foreach($payslips as $p): ?><tr><td><strong><?php echo esc_html($p->display_name); ?></strong></td><td><?php echo esc_html($p->run_name); ?><br><small><?php echo esc_html($p->period_start . ' → ' . $p->period_end); ?></small></td><td><?php echo esc_html($p->currency . ' ' . number_format((float)$p->basic_salary,2)); ?></td><td><?php echo esc_html(number_format((float)$p->allowances,2)); ?></td><td><?php echo esc_html(number_format((float)$p->deductions,2)); ?></td><td><?php echo esc_html(number_format((float)$p->gross_pay,2)); ?></td><td><strong><?php echo esc_html(number_format((float)$p->net_pay,2)); ?></strong></td></tr><?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<?php if ($tab === 'pms-payroll-reports') : ?>
    <div class="pms-kpi-grid"><div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Payslips generated','pms'); ?></span><strong><?php echo esc_html($totals->payslips); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Gross payroll','pms'); ?></span><strong><?php echo esc_html(number_format((float)$totals->gross,2)); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label"><?php esc_html_e('Total deductions','pms'); ?></span><strong><?php echo esc_html(number_format((float)$totals->deductions,2)); ?></strong></div></div>
    <div class="pms-panel"><div class="pms-section-head"><div><h2><?php esc_html_e('Payroll report','pms'); ?></h2><p><?php esc_html_e('Totals across generated payslips currently stored in the ERP.','pms'); ?></p></div></div><div class="pms-payroll-report-total"><span><?php esc_html_e('Net payroll','pms'); ?></span><strong><?php echo esc_html(number_format((float)$totals->net,2)); ?></strong></div></div>
<?php endif; ?>

<?php include PMS_PLUGIN_DIR . 'admin/views/partials/footer.php'; ?>

<?php
if (! defined('ABSPATH')) { exit; }
if (! PMS_Modules::is_active('invoicing') || ! current_user_can('pms_manage_invoices')) {
    wp_die(__('You do not have permission to view this page.', 'pms'));
}

PMS_Invoicing::mark_overdue();
$tab = isset($_GET['invoice_tab']) ? sanitize_key(wp_unslash($_GET['invoice_tab'])) : 'overview';
$allowed_tabs = ['overview', 'quotes', 'invoices', 'payments', 'outstanding', 'reports'];
if (! in_array($tab, $allowed_tabs, true)) { $tab = 'overview'; }

$notice = '';
$error = '';
$today = current_time('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['pms_invoice_action']) ? sanitize_key(wp_unslash($_POST['pms_invoice_action'])) : '';
    check_admin_referer('pms_invoicing_manage', 'pms_invoicing_nonce');

    if ($action === 'quote') {
        $items = isset($_POST['items']) && is_array($_POST['items']) ? wp_unslash($_POST['items']) : [];
        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $customer_name = sanitize_text_field(wp_unslash($_POST['customer_name'] ?? ''));
        $customer_email = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
        if ($customer_id && class_exists('PMS_CRM')) {
            foreach (PMS_Invoicing::customers() as $customer) {
                if ((int) $customer->id === $customer_id) {
                    $customer_name = $customer->company ?: trim($customer->first_name . ' ' . $customer->last_name);
                    $customer_email = $customer->email;
                    break;
                }
            }
        }
        if ($customer_name === '') {
            $error = __('Customer name is required.', 'pms');
        } else {
            PMS_Invoicing::create_quote([
                'quote_number' => sanitize_text_field(wp_unslash($_POST['quote_number'] ?? '')),
                'customer_id' => $customer_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'issue_date' => sanitize_text_field(wp_unslash($_POST['issue_date'] ?? $today)),
                'valid_until' => sanitize_text_field(wp_unslash($_POST['valid_until'] ?? '')),
                'status' => sanitize_key(wp_unslash($_POST['status'] ?? 'draft')),
                'currency' => sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD')),
                'tax' => (float) ($_POST['tax'] ?? 0),
                'notes' => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
            ], $items);
            $notice = __('Quote created successfully.', 'pms');
            $tab = 'quotes';
        }
    } elseif ($action === 'invoice') {
        $items = isset($_POST['items']) && is_array($_POST['items']) ? wp_unslash($_POST['items']) : [];
        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $customer_name = sanitize_text_field(wp_unslash($_POST['customer_name'] ?? ''));
        $customer_email = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
        if ($customer_name === '') {
            $error = __('Customer name is required.', 'pms');
        } else {
            PMS_Invoicing::create_invoice([
                'invoice_number' => sanitize_text_field(wp_unslash($_POST['invoice_number'] ?? '')),
                'customer_id' => $customer_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'issue_date' => sanitize_text_field(wp_unslash($_POST['issue_date'] ?? $today)),
                'due_date' => sanitize_text_field(wp_unslash($_POST['due_date'] ?? '')),
                'status' => sanitize_key(wp_unslash($_POST['status'] ?? 'sent')),
                'currency' => sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD')),
                'tax' => (float) ($_POST['tax'] ?? 0),
                'notes' => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
            ], $items);
            $notice = __('Invoice created successfully.', 'pms');
            $tab = 'invoices';
        }
    } elseif ($action === 'payment') {
        $ok = PMS_Invoicing::add_payment(absint($_POST['invoice_id'] ?? 0), [
            'payment_date' => sanitize_text_field(wp_unslash($_POST['payment_date'] ?? $today)),
            'amount' => (float) ($_POST['amount'] ?? 0),
            'method' => sanitize_text_field(wp_unslash($_POST['method'] ?? '')),
            'reference' => sanitize_text_field(wp_unslash($_POST['reference'] ?? '')),
            'notes' => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ]);
        $notice = $ok ? __('Payment recorded successfully.', 'pms') : __('Payment could not be recorded. Check the invoice balance and amount.', 'pms');
        $tab = 'payments';
    } elseif ($action === 'convert') {
        $id = PMS_Invoicing::convert_quote_to_invoice(absint($_POST['quote_id'] ?? 0));
        $notice = $id ? __('Quote converted to invoice.', 'pms') : __('The quote could not be converted.', 'pms');
        $tab = 'invoices';
    }
}

$report = PMS_Invoicing::report();
$quotes = PMS_Invoicing::quote_rows();
$invoices = PMS_Invoicing::invoice_rows();
$payments = PMS_Invoicing::payment_rows();
$customers = PMS_Invoicing::customers();

$money = static function ($amount, $currency = 'USD') {
    return esc_html(strtoupper((string) $currency) . ' ' . number_format((float) $amount, 2));
};
$tabs = [
    'overview' => ['Overview', 'dashicons-chart-pie'],
    'quotes' => ['Quotes', 'dashicons-media-document'],
    'invoices' => ['Invoices', 'dashicons-media-text'],
    'payments' => ['Payments', 'dashicons-money-alt'],
    'outstanding' => ['Outstanding Invoices', 'dashicons-warning'],
    'reports' => ['Invoice Reports', 'dashicons-chart-bar'],
];
?>
<div class="pms-invoicing">
    <div class="pms-page-header pms-payroll-header">
        <div>
            <h1><?php esc_html_e('Invoicing', 'pms'); ?></h1>
            <p><?php esc_html_e('Quotes, invoices, payments and outstanding balances.', 'pms'); ?></p>
        </div>
    </div>

    <?php if ($notice) : ?><div class="pms-notice pms-notice-success"><?php echo esc_html($notice); ?></div><?php endif; ?>
    <?php if ($error) : ?><div class="pms-notice pms-notice-error"><?php echo esc_html($error); ?></div><?php endif; ?>

    <div class="pms-payroll-tabs pms-invoice-tabs">
        <?php foreach ($tabs as $key => $item) : ?>
            <a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=pms-invoicing&invoice_tab=' . $key)); ?>">
                <span class="dashicons <?php echo esc_attr($item[1]); ?>"></span><?php echo esc_html($item[0]); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'overview') : ?>
        <div class="pms-kpi-grid pms-invoice-kpis">
            <div class="pms-kpi-card"><span class="dashicons dashicons-media-document"></span><small><?php esc_html_e('QUOTES', 'pms'); ?></small><strong><?php echo esc_html($report['quotes']); ?></strong></div>
            <div class="pms-kpi-card"><span class="dashicons dashicons-media-text"></span><small><?php esc_html_e('INVOICES', 'pms'); ?></small><strong><?php echo esc_html($report['invoices']); ?></strong></div>
            <div class="pms-kpi-card"><span class="dashicons dashicons-yes-alt"></span><small><?php esc_html_e('PAID', 'pms'); ?></small><strong><?php echo $money($report['paid']); ?></strong></div>
            <div class="pms-kpi-card"><span class="dashicons dashicons-warning"></span><small><?php esc_html_e('OUTSTANDING', 'pms'); ?></small><strong><?php echo $money($report['outstanding']); ?></strong></div>
        </div>
        <div class="pms-panel pms-invoice-quick">
            <div class="pms-section-head"><div><h2><?php esc_html_e('Create a transaction', 'pms'); ?></h2><p><?php esc_html_e('Use the dedicated tabs to create quotes, invoices and payments.', 'pms'); ?></p></div></div>
            <div class="pms-invoice-quick-grid">
                <a class="pms-btn pms-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=pms-invoicing&invoice_tab=quotes')); ?>"><span class="dashicons dashicons-media-document"></span><?php esc_html_e('New Quote', 'pms'); ?></a>
                <a class="pms-btn pms-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=pms-invoicing&invoice_tab=invoices')); ?>"><span class="dashicons dashicons-media-text"></span><?php esc_html_e('New Invoice', 'pms'); ?></a>
                <a class="pms-btn" href="<?php echo esc_url(admin_url('admin.php?page=pms-invoicing&invoice_tab=payments')); ?>"><span class="dashicons dashicons-money-alt"></span><?php esc_html_e('Record Payment', 'pms'); ?></a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'quotes') : ?>
        <div class="pms-panel">
            <div class="pms-section-head"><div><h2><?php esc_html_e('Create Quote', 'pms'); ?></h2><p><?php esc_html_e('Prepare a quote and convert an accepted quote into an invoice.', 'pms'); ?></p></div></div>
            <form method="post">
                <?php wp_nonce_field('pms_invoicing_manage', 'pms_invoicing_nonce'); ?><input type="hidden" name="pms_invoice_action" value="quote">
                <div class="pms-form-grid">
                    <label>Quote Number<input class="pms-input" name="quote_number" placeholder="Auto-generated"></label>
                    <label>Customer
                        <select class="pms-input" name="customer_id">
                            <option value="0">Manual customer</option>
                            <?php foreach ($customers as $customer) : $name = $customer->company ?: trim($customer->first_name . ' ' . $customer->last_name); ?>
                                <option value="<?php echo (int) $customer->id; ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Customer Name<input class="pms-input" name="customer_name" required></label>
                    <label>Customer Email<input class="pms-input" type="email" name="customer_email"></label>
                    <label>Issue Date<input class="pms-input" type="date" name="issue_date" value="<?php echo esc_attr($today); ?>" required></label>
                    <label>Valid Until<input class="pms-input" type="date" name="valid_until"></label>
                    <label>Currency<input class="pms-input" name="currency" value="USD"></label>
                    <label>Tax<input class="pms-input" type="number" step="0.01" min="0" name="tax" value="0"></label>
                </div>
                <?php include __DIR__ . '/invoicing-items.php'; ?>
                <label class="pms-form-field">Notes<textarea class="pms-input" name="notes" rows="3"></textarea></label>
                <button class="pms-btn pms-btn-primary" type="submit"><?php esc_html_e('Create Quote', 'pms'); ?></button>
            </form>
        </div>
        <div class="pms-panel">
            <div class="pms-section-head"><h2><?php esc_html_e('Quotes', 'pms'); ?></h2></div>
            <div class="pms-table-wrap"><table class="pms-table"><thead><tr><th>Quote</th><th>Customer</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
            <?php foreach ($quotes as $quote) : ?><tr><td><strong><?php echo esc_html($quote->quote_number); ?></strong></td><td><?php echo esc_html($quote->customer_name); ?><br><small><?php echo esc_html($quote->customer_email); ?></small></td><td><?php echo esc_html($quote->issue_date); ?></td><td><span class="pms-status"><?php echo esc_html(ucfirst($quote->status)); ?></span></td><td><?php echo $money($quote->total, $quote->currency); ?></td><td><?php if (! in_array($quote->status, ['converted'], true)) : ?><form method="post" class="pms-inline-form"><?php wp_nonce_field('pms_invoicing_manage', 'pms_invoicing_nonce'); ?><input type="hidden" name="pms_invoice_action" value="convert"><input type="hidden" name="quote_id" value="<?php echo (int) $quote->id; ?>"><button class="pms-btn pms-btn-small pms-btn-primary">Convert</button></form><?php endif; ?></td></tr><?php endforeach; ?>
            <?php if (! $quotes) : ?><tr><td colspan="6"><?php esc_html_e('No quotes yet.', 'pms'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'invoices') : ?>
        <div class="pms-panel">
            <div class="pms-section-head"><div><h2><?php esc_html_e('Create Invoice', 'pms'); ?></h2><p><?php esc_html_e('Record an invoice with line items, tax and due date.', 'pms'); ?></p></div></div>
            <form method="post">
                <?php wp_nonce_field('pms_invoicing_manage', 'pms_invoicing_nonce'); ?><input type="hidden" name="pms_invoice_action" value="invoice">
                <div class="pms-form-grid">
                    <label>Invoice Number<input class="pms-input" name="invoice_number" placeholder="Auto-generated"></label>
                    <label>Customer Name<input class="pms-input" name="customer_name" required></label>
                    <label>Customer Email<input class="pms-input" type="email" name="customer_email"></label>
                    <label>Issue Date<input class="pms-input" type="date" name="issue_date" value="<?php echo esc_attr($today); ?>" required></label>
                    <label>Due Date<input class="pms-input" type="date" name="due_date"></label>
                    <label>Currency<input class="pms-input" name="currency" value="USD"></label>
                    <label>Tax<input class="pms-input" type="number" step="0.01" min="0" name="tax" value="0"></label>
                    <label>Status<select class="pms-input" name="status"><option value="sent">Sent</option><option value="draft">Draft</option></select></label>
                </div>
                <?php include __DIR__ . '/invoicing-items.php'; ?>
                <label class="pms-form-field">Notes<textarea class="pms-input" name="notes" rows="3"></textarea></label>
                <button class="pms-btn pms-btn-primary" type="submit"><?php esc_html_e('Create Invoice', 'pms'); ?></button>
            </form>
        </div>
        <div class="pms-panel">
            <div class="pms-section-head"><h2><?php esc_html_e('Invoices', 'pms'); ?></h2></div>
            <div class="pms-table-wrap"><table class="pms-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Issue / Due</th><th>Status</th><th>Total</th><th>Balance</th></tr></thead><tbody>
            <?php foreach ($invoices as $invoice) : ?><tr><td><strong><?php echo esc_html($invoice->invoice_number); ?></strong></td><td><?php echo esc_html($invoice->customer_name); ?><br><small><?php echo esc_html($invoice->customer_email); ?></small></td><td><?php echo esc_html($invoice->issue_date); ?><br><small><?php echo esc_html($invoice->due_date ?: '—'); ?></small></td><td><span class="pms-status"><?php echo esc_html(ucfirst($invoice->status)); ?></span></td><td><?php echo $money($invoice->total, $invoice->currency); ?></td><td><?php echo $money($invoice->balance_due, $invoice->currency); ?></td></tr><?php endforeach; ?>
            <?php if (! $invoices) : ?><tr><td colspan="6"><?php esc_html_e('No invoices yet.', 'pms'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'payments') : ?>
        <div class="pms-panel">
            <div class="pms-section-head"><div><h2><?php esc_html_e('Record Payment', 'pms'); ?></h2><p><?php esc_html_e('Payments are capped at the invoice balance and update its status automatically.', 'pms'); ?></p></div></div>
            <form method="post"><input type="hidden" name="pms_invoice_action" value="payment"><?php wp_nonce_field('pms_invoicing_manage', 'pms_invoicing_nonce'); ?>
                <div class="pms-form-grid">
                    <label>Invoice<select class="pms-input" name="invoice_id" required><option value="">Select invoice</option><?php foreach ($invoices as $invoice) if ((float) $invoice->balance_due > 0) : ?><option value="<?php echo (int) $invoice->id; ?>"><?php echo esc_html($invoice->invoice_number . ' — ' . $invoice->customer_name . ' — ' . $money($invoice->balance_due, $invoice->currency)); ?></option><?php endforeach; ?></select></label>
                    <label>Payment Date<input class="pms-input" type="date" name="payment_date" value="<?php echo esc_attr($today); ?>" required></label>
                    <label>Amount<input class="pms-input" type="number" step="0.01" min="0.01" name="amount" required></label>
                    <label>Method<input class="pms-input" name="method" placeholder="Bank transfer, cash, card"></label>
                    <label>Reference<input class="pms-input" name="reference"></label>
                </div>
                <button class="pms-btn pms-btn-primary" type="submit">Record Payment</button>
            </form>
        </div>
        <div class="pms-panel"><div class="pms-section-head"><h2>Payment History</h2></div><div class="pms-table-wrap"><table class="pms-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead><tbody>
        <?php foreach ($payments as $payment) : ?><tr><td><?php echo esc_html($payment->invoice_number); ?></td><td><?php echo esc_html($payment->customer_name); ?></td><td><?php echo esc_html($payment->payment_date); ?></td><td><?php echo $money($payment->amount); ?></td><td><?php echo esc_html($payment->method ?: '—'); ?></td><td><?php echo esc_html($payment->reference ?: '—'); ?></td></tr><?php endforeach; ?>
        <?php if (! $payments) : ?><tr><td colspan="6">No payments yet.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php endif; ?>

    <?php if ($tab === 'outstanding') : ?>
        <div class="pms-panel"><div class="pms-section-head"><div><h2>Outstanding Invoices</h2><p>Live balances after recorded payments.</p></div><strong class="pms-invoice-total"><?php echo $money($report['outstanding']); ?></strong></div>
            <div class="pms-table-wrap"><table class="pms-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Due Date</th><th>Status</th><th>Total</th><th>Balance</th></tr></thead><tbody>
            <?php $has_outstanding=false; foreach ($invoices as $invoice) : if ((float)$invoice->balance_due <= 0 || $invoice->status === 'cancelled') continue; $has_outstanding=true; ?><tr><td><?php echo esc_html($invoice->invoice_number); ?></td><td><?php echo esc_html($invoice->customer_name); ?></td><td><?php echo esc_html($invoice->due_date ?: '—'); ?></td><td><span class="pms-status"><?php echo esc_html(ucfirst($invoice->status)); ?></span></td><td><?php echo $money($invoice->total, $invoice->currency); ?></td><td><strong><?php echo $money($invoice->balance_due, $invoice->currency); ?></strong></td></tr><?php endforeach; ?>
            <?php if (! $has_outstanding) : ?><tr><td colspan="6">No outstanding invoices.</td></tr><?php endif; ?></tbody></table></div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'reports') : ?>
        <div class="pms-kpi-grid pms-invoice-kpis">
            <div class="pms-kpi-card"><small>INVOICED</small><strong><?php echo $money($report['invoice_total']); ?></strong></div>
            <div class="pms-kpi-card"><small>PAID</small><strong><?php echo $money($report['paid']); ?></strong></div>
            <div class="pms-kpi-card"><small>OUTSTANDING</small><strong><?php echo $money($report['outstanding']); ?></strong></div>
            <div class="pms-kpi-card"><small>OVERDUE</small><strong><?php echo $money($report['overdue']); ?></strong></div>
        </div>
        <div class="pms-panel"><div class="pms-section-head"><div><h2>Invoice Reports</h2><p>Current totals from live invoice and payment records.</p></div></div>
            <div class="pms-report-bars">
                <div><span>Invoiced</span><b style="width:<?php echo esc_attr($report['invoice_total'] > 0 ? '100%' : '0'); ?>"></b><strong><?php echo $money($report['invoice_total']); ?></strong></div>
                <div><span>Paid</span><b style="width:<?php echo esc_attr($report['invoice_total'] > 0 ? min(100, ($report['paid'] / $report['invoice_total']) * 100) . '%' : '0'); ?>"></b><strong><?php echo $money($report['paid']); ?></strong></div>
                <div><span>Outstanding</span><b style="width:<?php echo esc_attr($report['invoice_total'] > 0 ? min(100, ($report['outstanding'] / $report['invoice_total']) * 100) . '%' : '0'); ?>"></b><strong><?php echo $money($report['outstanding']); ?></strong></div>
            </div>
        </div>
    <?php endif; ?>
</div>

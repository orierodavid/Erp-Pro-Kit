<?php
/**
 * Invoicing: quotes, invoices, payments and reporting.
 *
 * Uses dedicated tables and the existing WordPress capability/module system.
 * CRM customer IDs are optional links; invoice records retain their own
 * customer snapshot so invoicing remains usable when CRM is not enabled.
 */
if (! defined('ABSPATH')) {
    exit;
}

class PMS_Invoicing
{
    public static function quotes_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_quotes';
    }

    public static function quote_items_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_quote_items';
    }

    public static function invoices_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_invoices';
    }

    public static function invoice_items_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_invoice_items';
    }

    public static function payments_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pms_invoice_payments';
    }

    public static function schema_sql(): string
    {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        $quotes = self::quotes_table();
        $quote_items = self::quote_items_table();
        $invoices = self::invoices_table();
        $invoice_items = self::invoice_items_table();
        $payments = self::payments_table();

        return "
CREATE TABLE {$quotes} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_number VARCHAR(64) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(191) NOT NULL,
    customer_email VARCHAR(191) NULL,
    issue_date DATE NOT NULL,
    valid_until DATE NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    tax DECIMAL(18,2) NOT NULL DEFAULT 0,
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY quote_number (quote_number),
    KEY customer_id (customer_id),
    KEY status (status),
    KEY issue_date (issue_date)
) {$c};

CREATE TABLE {$quote_items} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    PRIMARY KEY  (id),
    KEY quote_id (quote_id)
) {$c};

CREATE TABLE {$invoices} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(64) NOT NULL,
    quote_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(191) NOT NULL,
    customer_email VARCHAR(191) NULL,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    tax DECIMAL(18,2) NOT NULL DEFAULT 0,
    vat_rate DECIMAL(7,2) NOT NULL DEFAULT 0,
    vat_inclusive TINYINT(1) NOT NULL DEFAULT 0,
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY invoice_number (invoice_number),
    KEY quote_id (quote_id),
    KEY customer_id (customer_id),
    KEY status (status),
    KEY due_date (due_date)
) {$c};

CREATE TABLE {$invoice_items} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    PRIMARY KEY  (id),
    KEY invoice_id (invoice_id)
) {$c};

CREATE TABLE {$payments} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    method VARCHAR(64) NULL,
    reference VARCHAR(128) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    PRIMARY KEY  (id),
    KEY invoice_id (invoice_id),
    KEY payment_date (payment_date)
) {$c};";
    }

    public static function quote_rows(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::quotes_table() . ' ORDER BY issue_date DESC, id DESC');
    }

    public static function invoice_rows(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT i.*, GREATEST(i.total - i.amount_paid, 0) AS balance_due FROM ' . self::invoices_table() . ' i ORDER BY i.issue_date DESC, i.id DESC');
    }

    public static function payment_rows(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT p.*, i.invoice_number, i.customer_name FROM ' . self::payments_table() . ' p INNER JOIN ' . self::invoices_table() . ' i ON i.id = p.invoice_id ORDER BY p.payment_date DESC, p.id DESC');
    }

    public static function customers(): array
    {
        if (class_exists('PMS_CRM')) {
            $rows = PMS_CRM::customers();
            if ($rows) {
                return $rows;
            }
        }
        return [];
    }

    public static function next_number(string $type): string
    {
        global $wpdb;
        $table = $type === 'quote' ? self::quotes_table() : self::invoices_table();
        $prefix = $type === 'quote' ? 'QUO-' : 'INV-';
        $max = (int) $wpdb->get_var("SELECT MAX(id) FROM {$table}");
        return $prefix . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    private static function parse_items(array $raw): array
    {
        $items = [];
        foreach ($raw as $item) {
            $description = sanitize_text_field($item['description'] ?? '');
            $quantity = max(0, (float) ($item['quantity'] ?? 0));
            $unit_price = max(0, (float) ($item['unit_price'] ?? 0));
            if ($description === '' || $quantity <= 0) {
                continue;
            }
            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'amount' => round($quantity * $unit_price, 2),
            ];
        }
        return $items;
    }

    private static function totals(array $items, float $tax): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float) $item['amount'];
        }
        $tax = max(0, round($tax, 2));
        return ['subtotal' => round($subtotal, 2), 'tax' => $tax, 'total' => round($subtotal + $tax, 2)];
    }

    public static function create_quote(array $data, array $items): int
    {
        global $wpdb;
        $items = self::parse_items($items);
        $totals = self::totals($items, (float) ($data['tax'] ?? 0));
        $now = current_time('mysql');
        $wpdb->insert(self::quotes_table(), [
            'quote_number' => sanitize_text_field($data['quote_number']) ?: self::next_number('quote'),
            'customer_id' => ! empty($data['customer_id']) ? absint($data['customer_id']) : null,
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'issue_date' => sanitize_text_field($data['issue_date']),
            'valid_until' => sanitize_text_field($data['valid_until'] ?? '') ?: null,
            'status' => sanitize_key($data['status'] ?? 'draft'),
            'currency' => strtoupper(sanitize_text_field($data['currency'] ?? 'USD')),
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $wpdb->insert_id;
        foreach ($items as $item) {
            $item['quote_id'] = $id;
            $wpdb->insert(self::quote_items_table(), $item);
        }
        return $id;
    }

    public static function create_invoice(array $data, array $items, ?int $quote_id = null): int
    {
        global $wpdb;
        $items = self::parse_items($items);
        $totals = self::totals($items, (float) ($data['tax'] ?? 0));
        $now = current_time('mysql');
        $wpdb->insert(self::invoices_table(), [
            'invoice_number' => sanitize_text_field($data['invoice_number']) ?: self::next_number('invoice'),
            'quote_id' => $quote_id ?: null,
            'customer_id' => ! empty($data['customer_id']) ? absint($data['customer_id']) : null,
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'issue_date' => sanitize_text_field($data['issue_date']),
            'due_date' => sanitize_text_field($data['due_date'] ?? '') ?: null,
            'status' => sanitize_key($data['status'] ?? 'sent'),
            'currency' => strtoupper(sanitize_text_field($data['currency'] ?? 'USD')),
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
            'amount_paid' => 0,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $wpdb->insert_id;
        foreach ($items as $item) {
            $item['invoice_id'] = $id;
            $wpdb->insert(self::invoice_items_table(), $item);
        }
        return $id;
    }

    public static function quote_items(int $id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::quote_items_table() . ' WHERE quote_id = %d ORDER BY id ASC', $id), ARRAY_A);
    }

    public static function invoice_items(int $id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::invoice_items_table() . ' WHERE invoice_id = %d ORDER BY id ASC', $id), ARRAY_A);
    }

    public static function convert_quote_to_invoice(int $quote_id): int
    {
        global $wpdb;
        $quote = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::quotes_table() . ' WHERE id = %d', $quote_id));
        if (! $quote) {
            return 0;
        }
        $existing = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::invoices_table() . ' WHERE quote_id = %d LIMIT 1', $quote_id));
        if ($existing) {
            return $existing;
        }
        $items = self::quote_items($quote_id);
        $invoice_id = self::create_invoice([
            'invoice_number' => '',
            'customer_id' => $quote->customer_id,
            'customer_name' => $quote->customer_name,
            'customer_email' => $quote->customer_email,
            'issue_date' => current_time('Y-m-d'),
            'due_date' => gmdate('Y-m-d', strtotime('+30 days')),
            'status' => 'sent',
            'currency' => $quote->currency,
            'tax' => (float) $quote->tax,
            'notes' => $quote->notes,
        ], $items, $quote_id);
        $wpdb->update(self::quotes_table(), ['status' => 'converted', 'updated_at' => current_time('mysql')], ['id' => $quote_id]);
        return $invoice_id;
    }

    public static function add_payment(int $invoice_id, array $data): bool
    {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::invoices_table() . ' WHERE id = %d', $invoice_id));
        if (! $invoice) {
            return false;
        }
        $amount = max(0, (float) ($data['amount'] ?? 0));
        $balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
        $amount = min($amount, $balance);
        if ($amount <= 0) {
            return false;
        }
        $wpdb->insert(self::payments_table(), [
            'invoice_id' => $invoice_id,
            'payment_date' => sanitize_text_field($data['payment_date']),
            'amount' => round($amount, 2),
            'method' => sanitize_text_field($data['method'] ?? ''),
            'reference' => sanitize_text_field($data['reference'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
        $paid = (float) $invoice->amount_paid + $amount;
        $status = $paid >= (float) $invoice->total ? 'paid' : 'partial';
        $wpdb->update(self::invoices_table(), ['amount_paid' => round($paid, 2), 'status' => $status, 'updated_at' => current_time('mysql')], ['id' => $invoice_id]);
        return true;
    }

    public static function report(): array
    {
        global $wpdb;
        $t = self::invoices_table();
        return [
            'quotes' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::quotes_table()),
            'invoices' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}"),
            'invoice_total' => (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t} WHERE status <> 'cancelled'"),
            'paid' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount_paid),0) FROM {$t} WHERE status <> 'cancelled'"),
            'outstanding' => (float) $wpdb->get_var("SELECT COALESCE(SUM(GREATEST(total - amount_paid,0)),0) FROM {$t} WHERE status NOT IN ('cancelled','paid')"),
            'overdue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(GREATEST(total - amount_paid,0)),0) FROM {$t} WHERE due_date < CURDATE() AND status NOT IN ('cancelled','paid')"),
        ];
    }

    public static function mark_overdue(): void
    {
        global $wpdb;
        $wpdb->query("UPDATE " . self::invoices_table() . " SET status = 'overdue', updated_at = '" . esc_sql(current_time('mysql')) . "' WHERE due_date < CURDATE() AND status IN ('sent','partial') AND amount_paid < total");
    }
}

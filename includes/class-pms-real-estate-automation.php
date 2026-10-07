<?php
if (! defined('ABSPATH')) { exit; }

class PMS_Real_Estate_Automation
{
    private const CRON_HOOK = 'pms_real_estate_expiry_check';

    public static function init(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'run']);
    }

    public static function schema_sql(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pms_re_automation_log';
        $charset = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_type VARCHAR(20) NOT NULL,
            agreement_id BIGINT UNSIGNED NOT NULL,
            reminder_key VARCHAR(30) NOT NULL,
            recipient VARCHAR(191) NOT NULL,
            subject VARCHAR(191) NOT NULL,
            sent_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reminder_recipient (agreement_type, agreement_id, reminder_key, recipient),
            KEY agreement_lookup (agreement_type, agreement_id),
            KEY sent_at (sent_at)
        ) {$charset};";
    }

    public static function activate(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function run(): void
    {
        self::process_leases();
        self::process_standalone_rentals();
    }

    private static function process_leases(): void
    {
        global $wpdb;

        $leases = $wpdb->get_results(
            "SELECT l.id, l.lease_number, l.property_id, l.tenant_id, l.rental_id,
                    l.end_date, l.rent_amount, l.currency,
                    p.property_code, p.title AS property_title,
                    t.name AS tenant_name, t.email AS tenant_email,
                    COALESCE(r.agent_id, 0) AS agent_id
             FROM " . PMS_Real_Estate::table_name('leases') . " l
             LEFT JOIN " . PMS_Real_Estate::table_name('properties') . " p ON p.id = l.property_id
             LEFT JOIN " . PMS_Real_Estate::table_name('tenants') . " t ON t.id = l.tenant_id
             LEFT JOIN " . PMS_Real_Estate::table_name('rentals') . " r ON r.id = l.rental_id
             WHERE l.status = 'active'
               AND l.end_date IS NOT NULL
               AND l.end_date <> '0000-00-00'
             ORDER BY l.id ASC"
        );

        foreach ((array) $leases as $lease) {
            self::process_agreement(
                'lease',
                (int) $lease->id,
                (string) $lease->end_date,
                (string) $lease->property_code,
                (string) $lease->property_title,
                (string) $lease->tenant_name,
                (string) $lease->tenant_email,
                (int) $lease->agent_id,
                (string) $lease->rent_amount,
                (string) $lease->currency,
                (string) $lease->lease_number
            );
        }
    }

    private static function process_standalone_rentals(): void
    {
        global $wpdb;

        $rentals = $wpdb->get_results(
            "SELECT r.id, r.property_id, r.tenant_id, r.agent_id,
                    r.end_date, r.rent_amount, r.currency,
                    p.property_code, p.title AS property_title,
                    t.name AS tenant_name, t.email AS tenant_email
             FROM " . PMS_Real_Estate::table_name('rentals') . " r
             LEFT JOIN " . PMS_Real_Estate::table_name('properties') . " p ON p.id = r.property_id
             LEFT JOIN " . PMS_Real_Estate::table_name('tenants') . " t ON t.id = r.tenant_id
             WHERE r.status = 'active'
               AND r.end_date IS NOT NULL
               AND r.end_date <> '0000-00-00'
               AND NOT EXISTS (
                   SELECT 1
                   FROM " . PMS_Real_Estate::table_name('leases') . " l
                   WHERE l.rental_id = r.id
                     AND l.status = 'active'
               )
             ORDER BY r.id ASC"
        );

        foreach ((array) $rentals as $rental) {
            self::process_agreement(
                'rental',
                (int) $rental->id,
                (string) $rental->end_date,
                (string) $rental->property_code,
                (string) $rental->property_title,
                (string) $rental->tenant_name,
                (string) $rental->tenant_email,
                (int) $rental->agent_id,
                (string) $rental->rent_amount,
                (string) $rental->currency,
                'Rental #' . (int) $rental->id
            );
        }
    }

    private static function process_agreement(
        string $agreement_type,
        int $agreement_id,
        string $end_date,
        string $property_code,
        string $property_title,
        string $tenant_name,
        string $tenant_email,
        int $agent_id,
        string $rent_amount,
        string $currency,
        string $agreement_label
    ): void {
        $today = new DateTimeImmutable(current_time('Y-m-d'));
        $expiry = DateTimeImmutable::createFromFormat('Y-m-d', $end_date);

        if (! $expiry) {
            return;
        }

        $days_remaining = (int) $today->diff($expiry)->format('%r%a');
        $reminders = [
            60 => '60_days',
            30 => '30_days',
            7  => '7_days',
            1  => '1_day',
            0  => 'expiry_day',
        ];

        if (! array_key_exists($days_remaining, $reminders)) {
            return;
        }

        $reminder_key = $reminders[$days_remaining];
        $days_text = $days_remaining === 0
            ? 'expires today'
            : 'expires in ' . $days_remaining . ' days';

        $subject = sprintf(
            'Lease/Rental Expiry Reminder - %s %s',
            $property_code ?: $property_title,
            $days_text
        );

        $amount = number_format((float) $rent_amount, 2);
        $message = "ERP lease/rental expiry reminder\n\n"
            . "Property: " . ($property_title ?: $property_code ?: 'Property') . "\n"
            . "Property Code: " . ($property_code ?: 'N/A') . "\n"
            . "Agreement: " . $agreement_label . "\n"
            . "Tenant: " . ($tenant_name ?: 'N/A') . "\n"
            . "Expiry Date: " . $expiry->format('Y-m-d') . "\n"
            . "Rent Amount: " . ($currency ?: 'NGN') . ' ' . $amount . "\n\n"
            . "This is an automated ERP reminder that the lease/rental " . $days_text . ".\n"
            . "Please review the agreement and take the required renewal, notice, or handover action.";

        $recipients = [];

        if ($tenant_email && is_email($tenant_email)) {
            $recipients[] = sanitize_email($tenant_email);
        }

        if ($agent_id > 0) {
            $agent = get_userdata($agent_id);
            if ($agent && ! empty($agent->user_email) && is_email($agent->user_email)) {
                $recipients[] = sanitize_email($agent->user_email);
            }
        }

        if (count($recipients) < 2) {
            $admin_email = sanitize_email(get_option('admin_email'));
            if ($admin_email && is_email($admin_email)) {
                $recipients[] = $admin_email;
            }
        }

        $recipients = array_values(array_unique($recipients));

        foreach ($recipients as $recipient) {
            if (self::already_sent($agreement_type, $agreement_id, $reminder_key, $recipient)) {
                continue;
            }

            if (PMS_Real_Estate::send_email($recipient, $subject, $message)) {
                self::record_sent(
                    $agreement_type,
                    $agreement_id,
                    $reminder_key,
                    $recipient,
                    $subject
                );
            }
        }
    }

    private static function already_sent(
        string $agreement_type,
        int $agreement_id,
        string $reminder_key,
        string $recipient
    ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pms_re_automation_log';

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE agreement_type = %s
                   AND agreement_id = %d
                   AND reminder_key = %s
                   AND recipient = %s
                 LIMIT 1",
                $agreement_type,
                $agreement_id,
                $reminder_key,
                $recipient
            )
        );
    }

    private static function record_sent(
        string $agreement_type,
        int $agreement_id,
        string $reminder_key,
        string $recipient,
        string $subject
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pms_re_automation_log';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table}
                 (agreement_type, agreement_id, reminder_key, recipient, subject, sent_at)
                 VALUES (%s, %d, %s, %s, %s, %s)",
                $agreement_type,
                $agreement_id,
                $reminder_key,
                $recipient,
                $subject,
                current_time('mysql')
            )
        );
    }
}

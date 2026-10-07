<?php
/**
 * Registers plugin settings through WordPress's actual Settings API
 * (register_setting / add_settings_section / add_settings_field),
 * instead of hand-rolled $_POST + update_option calls. This gets you,
 * for free: proper sanitization callbacks, the standard WP "Settings
 * saved" admin notice, capability enforcement on the settings group
 * itself, and compatibility with anything else that inspects
 * registered settings (e.g. the REST API's /wp/v2/settings endpoint,
 * or another plugin extending this one later).
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Settings
{
    private const OPTION_GROUP = 'pms_settings_group';

    public function __construct()
    {
        add_action('admin_init', [$this, 'register']);
    }

    public function register(): void
    {
        register_setting(self::OPTION_GROUP, 'pms_company_name', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => get_bloginfo('name'),
        ]);


        register_setting(self::OPTION_GROUP, 'pms_company_logo_id', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_address', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_city', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_state', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_country', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_phone', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_email', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email'),
        ]);
        register_setting(self::OPTION_GROUP, 'pms_company_website', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/'),
        ]);
        register_setting(self::OPTION_GROUP, 'pms_bank_name', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_bank_account_name', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_bank_account_number', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_bank_sort_code', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::OPTION_GROUP, 'pms_payment_instructions', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ]);

        register_setting(self::OPTION_GROUP, 'pms_primary_color', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_color'],
            'default'           => '#e91e63',
        ]);

        register_setting(self::OPTION_GROUP, 'pms_default_geofence_radius_m', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 100,
        ]);

        register_setting(self::OPTION_GROUP, 'pms_workday_start', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '09:00',
        ]);

        register_setting(self::OPTION_GROUP, 'pms_workday_end', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '17:00',
        ]);

        register_setting(self::OPTION_GROUP, 'pms_late_grace_minutes', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 15,
        ]);

        register_setting(self::OPTION_GROUP, PMS_Modules::option_name(), ['type'=>'array','sanitize_callback'=>static function ($value) { return is_array($value) ? array_map('sanitize_key', $value) : []; },'default'=>['tasks']]);

        register_setting(self::OPTION_GROUP, 'pms_delete_data_on_uninstall', [
            'type'              => 'boolean',
            'sanitize_callback' => fn ($value) => $value === '1' ? '1' : '0',
            'default'           => '0',
        ]);

        add_settings_section('pms_branding', __('Branding', 'pms'), function () {
            echo '<p class="description">' . esc_html__('Shown throughout the app for this company.', 'pms') . '</p>';
        }, 'pms-settings');

        add_settings_field('pms_company_name', __('Company name', 'pms'), [$this, 'field_company_name'], 'pms-settings', 'pms_branding');
        add_settings_field('pms_primary_color', __('Primary color', 'pms'), [$this, 'field_primary_color'], 'pms-settings', 'pms_branding');


        add_settings_section('pms_company_billing', __('Company & Billing', 'pms'), function () {
            echo '<p class="description">' . esc_html__('These details are reused automatically on invoices, PDF previews and invoice emails.', 'pms') . '</p>';
        }, 'pms-settings');

        add_settings_field('pms_company_logo_id', __('Company logo', 'pms'), [$this, 'field_company_logo'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_address', __('Company address', 'pms'), [$this, 'field_company_address'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_city', __('City', 'pms'), [$this, 'field_company_city'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_state', __('State / Region', 'pms'), [$this, 'field_company_state'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_country', __('Country', 'pms'), [$this, 'field_company_country'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_phone', __('Phone', 'pms'), [$this, 'field_company_phone'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_email', __('Email', 'pms'), [$this, 'field_company_email'], 'pms-settings', 'pms_company_billing');
        add_settings_field('pms_company_website', __('Website', 'pms'), [$this, 'field_company_website'], 'pms-settings', 'pms_company_billing');

        add_settings_section('pms_banking', __('Banking & Payment Details', 'pms'), function () {
            echo '<p class="description">' . esc_html__('Saved once here and automatically displayed on invoices. Do not enter these details on individual invoices.', 'pms') . '</p>';
        }, 'pms-settings');

        add_settings_field('pms_bank_name', __('Bank name', 'pms'), [$this, 'field_bank_name'], 'pms-settings', 'pms_banking');
        add_settings_field('pms_bank_account_name', __('Account name', 'pms'), [$this, 'field_bank_account_name'], 'pms-settings', 'pms_banking');
        add_settings_field('pms_bank_account_number', __('Account number', 'pms'), [$this, 'field_bank_account_number'], 'pms-settings', 'pms_banking');
        add_settings_field('pms_bank_sort_code', __('Sort / Routing code', 'pms'), [$this, 'field_bank_sort_code'], 'pms-settings', 'pms_banking');
        add_settings_field('pms_payment_instructions', __('Payment instructions', 'pms'), [$this, 'field_payment_instructions'], 'pms-settings', 'pms_banking');

        add_settings_section('pms_general', __('General', 'pms'), '__return_false', 'pms-settings');

        add_settings_field('pms_default_geofence_radius_m', __('Default task geofence radius (metres)', 'pms'), [$this, 'field_geofence_radius'], 'pms-settings', 'pms_general');
        add_settings_field('pms_workday_hours', __('Workday hours', 'pms'), [$this, 'field_workday_hours'], 'pms-settings', 'pms_general');
        add_settings_field('pms_late_grace_minutes', __('Late after', 'pms'), [$this, 'field_late_grace'], 'pms-settings', 'pms_general');

        add_settings_section('pms_modules', __('ERP Modules', 'pms'), function () { echo '<p class="description">' . esc_html__('Activate company-wide modules here. Employee access is controlled separately by roles and capabilities.', 'pms') . '</p>'; }, 'pms-settings');
        add_settings_field('pms_modules', __('Modules', 'pms'), [$this, 'field_modules'], 'pms-settings', 'pms_modules');

        add_settings_section('pms_danger', __('Danger zone', 'pms'), function () {
            echo '<p class="description">' . esc_html__('Controls what happens if this plugin is ever deleted.', 'pms') . '</p>';
        }, 'pms-settings');

        add_settings_field('pms_delete_data_on_uninstall', __('On uninstall', 'pms'), [$this, 'field_delete_on_uninstall'], 'pms-settings', 'pms_danger');
    }

    /** Falls back to the default if someone submits something that isn't a valid hex color. */
    public function sanitize_color($value): string
    {
        $sanitized = sanitize_hex_color((string) $value);

        return $sanitized ?: '#e91e63';
    }


    public function field_company_logo(): void
    {
        $id = absint(get_option('pms_company_logo_id', 0));
        $url = $id ? wp_get_attachment_image_url($id, 'medium') : '';
        wp_enqueue_media();
        echo '<div class="pms-company-logo-setting">';
        echo '<input type="hidden" id="pms_company_logo_id" name="pms_company_logo_id" value="' . esc_attr($id) . '">';
        echo '<div id="pms-company-logo-preview">';
        if ($url) {
            echo '<img src="' . esc_url($url) . '" alt="' . esc_attr__('Company logo', 'pms') . '" style="max-width:220px;max-height:90px;object-fit:contain;">';
        } else {
            echo '<span class="description">' . esc_html__('No company logo selected.', 'pms') . '</span>';
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="pms-select-company-logo">' . esc_html__('Choose logo', 'pms') . '</button> ';
        echo '<button type="button" class="button" id="pms-remove-company-logo">' . esc_html__('Remove logo', 'pms') . '</button></p>';
        echo '</div>';
        echo '<script>
        jQuery(function($){
            var frame;
            $("#pms-select-company-logo").on("click", function(e){
                e.preventDefault();
                if(frame){ frame.open(); return; }
                frame = wp.media({title:"Choose company logo", button:{text:"Use this logo"}, multiple:false});
                frame.on("select", function(){
                    var a=frame.state().get("selection").first().toJSON();
                    $("#pms_company_logo_id").val(a.id);
                    $("#pms-company-logo-preview").html("<img src=\"" + (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url) + "\" alt=\"Company logo\" style=\"max-width:220px;max-height:90px;object-fit:contain;\">");
                });
                frame.open();
            });
            $("#pms-remove-company-logo").on("click", function(e){
                e.preventDefault();
                $("#pms_company_logo_id").val("0");
                $("#pms-company-logo-preview").html("<span class=\"description\">No company logo selected.</span>");
            });
        });
        </script>';
    }

    public function field_company_address(): void { printf('<textarea class="large-text" rows="3" name="pms_company_address">%s</textarea>', esc_textarea(get_option('pms_company_address', ''))); }
    public function field_company_city(): void { printf('<input type="text" class="regular-text" name="pms_company_city" value="%s">', esc_attr(get_option('pms_company_city', ''))); }
    public function field_company_state(): void { printf('<input type="text" class="regular-text" name="pms_company_state" value="%s">', esc_attr(get_option('pms_company_state', ''))); }
    public function field_company_country(): void { printf('<input type="text" class="regular-text" name="pms_company_country" value="%s">', esc_attr(get_option('pms_company_country', ''))); }
    public function field_company_phone(): void { printf('<input type="text" class="regular-text" name="pms_company_phone" value="%s">', esc_attr(get_option('pms_company_phone', ''))); }
    public function field_company_email(): void { printf('<input type="email" class="regular-text" name="pms_company_email" value="%s">', esc_attr(get_option('pms_company_email', get_option('admin_email')))); }
    public function field_company_website(): void { printf('<input type="url" class="regular-text" name="pms_company_website" value="%s">', esc_attr(get_option('pms_company_website', home_url('/')))); }
    public function field_bank_name(): void { printf('<input type="text" class="regular-text" name="pms_bank_name" value="%s">', esc_attr(get_option('pms_bank_name', ''))); }
    public function field_bank_account_name(): void { printf('<input type="text" class="regular-text" name="pms_bank_account_name" value="%s">', esc_attr(get_option('pms_bank_account_name', ''))); }
    public function field_bank_account_number(): void { printf('<input type="text" class="regular-text" name="pms_bank_account_number" value="%s">', esc_attr(get_option('pms_bank_account_number', ''))); }
    public function field_bank_sort_code(): void { printf('<input type="text" class="regular-text" name="pms_bank_sort_code" value="%s">', esc_attr(get_option('pms_bank_sort_code', ''))); }
    public function field_payment_instructions(): void { printf('<textarea class="large-text" rows="3" name="pms_payment_instructions">%s</textarea>', esc_textarea(get_option('pms_payment_instructions', ''))); }

    public static function company_profile(): array
    {
        $logo_id = absint(get_option('pms_company_logo_id', 0));
        return [
            'name' => get_option('pms_company_name', get_bloginfo('name')),
            'logo_id' => $logo_id,
            'logo_url' => $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '',
            'address' => get_option('pms_company_address', ''),
            'city' => get_option('pms_company_city', ''),
            'state' => get_option('pms_company_state', ''),
            'country' => get_option('pms_company_country', ''),
            'phone' => get_option('pms_company_phone', ''),
            'email' => get_option('pms_company_email', get_option('admin_email')),
            'website' => get_option('pms_company_website', home_url('/')),
        ];
    }

    public static function banking_details(): array
    {
        return [
            'bank_name' => get_option('pms_bank_name', ''),
            'account_name' => get_option('pms_bank_account_name', ''),
            'account_number' => get_option('pms_bank_account_number', ''),
            'sort_code' => get_option('pms_bank_sort_code', ''),
            'instructions' => get_option('pms_payment_instructions', ''),
        ];
    }

    public function field_company_name(): void
    {
        printf(
            '<input type="text" class="regular-text" name="pms_company_name" value="%s">',
            esc_attr(get_option('pms_company_name', get_bloginfo('name')))
        );
    }

    public function field_primary_color(): void
    {
        printf(
            '<input type="text" class="pms-color-picker" name="pms_primary_color" value="%s" data-default-color="#e91e63">',
            esc_attr(get_option('pms_primary_color', '#e91e63'))
        );
    }

    public function field_geofence_radius(): void
    {
        printf(
            '<input type="number" min="0" step="1" name="pms_default_geofence_radius_m" value="%d"> %s',
            (int) get_option('pms_default_geofence_radius_m', 100),
            esc_html__('metres — used for location based tasks that don\'t set their own radius', 'pms')
        );
    }

    public function field_workday_hours(): void
    {
        echo '<div class="pms-settings-time-range">';
        echo '<label><span>' . esc_html__('Start', 'pms') . '</span>';
        printf(
            '<input type="time" name="pms_workday_start" value="%s">',
            esc_attr(get_option('pms_workday_start', '09:00'))
        );
        echo '</label>';
        echo '<label><span>' . esc_html__('End', 'pms') . '</span>';
        printf(
            '<input type="time" name="pms_workday_end" value="%s">',
            esc_attr(get_option('pms_workday_end', '17:00'))
        );
        echo '</label>';
        echo '</div>';
        echo '<p class="description">' . esc_html__('Used as the default working window for task scheduling when a task does not set its own schedule.', 'pms') . '</p>';
    }

    public function field_late_grace(): void
    {
        printf(
            '<input type="number" min="0" step="1" style="width:80px;" name="pms_late_grace_minutes" value="%d"> %s',
            (int) get_option('pms_late_grace_minutes', 15),
            esc_html__('minutes past a task\'s expected start time before it counts as late', 'pms')
        );
    }

    public function field_delete_on_uninstall(): void
    {
        printf(
            '<label><input type="checkbox" name="pms_delete_data_on_uninstall" value="1" %s> %s</label>',
            checked('1', get_option('pms_delete_data_on_uninstall', '0'), false),
            esc_html__('Permanently delete all PMS tables, roles, and settings when this plugin is deleted', 'pms')
        );
    }

    public function field_modules(): void
    {
        $active = PMS_Modules::active();

        foreach (PMS_Modules::definitions() as $slug => $module) {
            printf(
                '<label style="display:block;margin:0 0 14px;"><input type="checkbox" name="%s[]" value="%s" %s> <strong>%s</strong><br><span class="description" style="margin-left:22px;">%s</span></label>',
                esc_attr(PMS_Modules::option_name()),
                esc_attr($slug),
                checked(in_array($slug, $active, true), true, false),
                esc_html($module['label']),
                esc_html($module['description'])
            );
        }
        echo '<input type="hidden" name="' . esc_attr(PMS_Modules::option_name()) . '[]" value="">';
    }

    public static function option_group(): string
    {
        return self::OPTION_GROUP;
    }

    /**
     * Darkens a hex color by a percentage — used to derive the gradient's
     * dark end and the glow shadow from a single admin-chosen color,
     * instead of asking the site owner to pick two colors.
     */
    public static function darken(string $hex, float $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#' . $hex;
        }

        [$r, $g, $b] = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        $r = (int) max(0, min(255, $r - ($r * $percent)));
        $g = (int) max(0, min(255, $g - ($g * $percent)));
        $b = (int) max(0, min(255, $b - ($b * $percent)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** @return array{r:int,g:int,b:int} for building rgba() shadows/glows from the chosen color. */
    public static function to_rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = 'e91e63';
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}

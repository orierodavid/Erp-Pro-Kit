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

        add_settings_section('pms_general', __('General', 'pms'), '__return_false', 'pms-settings');

        add_settings_field('pms_default_geofence_radius_m', __('Default task geofence radius (metres)', 'pms'), [$this, 'field_geofence_radius'], 'pms-settings', 'pms_general');
        add_settings_field('pms_workday_hours', __('Default start time', 'pms'), [$this, 'field_workday_hours'], 'pms-settings', 'pms_general');
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
        printf(
            '<input type="time" name="pms_workday_start" value="%s">',
            esc_attr(get_option('pms_workday_start', '09:00'))
        );
        echo '<p class="description">' . esc_html__('Used as the expected start time for any task that doesn\'t set its own scheduled start time.', 'pms') . '</p>';
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

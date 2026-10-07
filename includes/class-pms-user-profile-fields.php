<?php
/**
 * Adds two fields — Designation (job title) and Department — directly
 * onto WordPress's OWN native user screens: the "Add New User" page and
 * the Edit Profile page. Deliberately not a separate custom form: the
 * request was for these to show up on the normal WordPress flow admins
 * already use to add staff, not a parallel screen to maintain.
 *
 * Stored as plain user meta (pms_designation, pms_department_id) —
 * no new database table needed for this.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_User_Profile_Fields
{
    public function __construct()
    {
        // Rendering the fields.
        add_action('user_new_form', [$this, 'render_fields']);
        add_action('show_user_profile', [$this, 'render_fields']);
        add_action('edit_user_profile', [$this, 'render_fields']);

        // Saving them.
        add_action('user_register', [$this, 'save_fields']);
        add_action('personal_options_update', [$this, 'save_fields']);
        add_action('edit_user_profile_update', [$this, 'save_fields']);
    }

    public static function designation(int $user_id): string
    {
        return (string) get_user_meta($user_id, 'pms_designation', true);
    }

    public static function department_id(int $user_id): ?int
    {
        $id = get_user_meta($user_id, 'pms_department_id', true);

        return $id ? (int) $id : null;
    }

    public static function department_name(int $user_id): string
    {
        $department_id = self::department_id($user_id);

        return $department_id ? PMS_DB::department_name($department_id) : '—';
    }

    /**
     * @param string|WP_User $context 'add-new-user' on the Add User screen,
     *                                 or a WP_User object on profile screens.
     */
    public function render_fields($context): void
    {
        $user_id = ($context instanceof WP_User) ? $context->ID : 0;
        $designation = $user_id ? self::designation($user_id) : '';
        $department_id = $user_id ? self::department_id($user_id) : null;
        $departments = class_exists('PMS_DB') ? PMS_DB::get_departments() : [];
        $is_new_user_screen = ! ($context instanceof WP_User);
        ?>
        <h2><?php esc_html_e('Project Management System', 'pms'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="pms_designation"><?php esc_html_e('Designation', 'pms'); ?></label></th>
                <td>
                    <input type="text" name="pms_designation" id="pms_designation" class="regular-text"
                           value="<?php echo esc_attr($designation); ?>"
                           placeholder="<?php esc_attr_e('e.g. Site Supervisor, Accountant, Project Manager', 'pms'); ?>">
                    <p class="description"><?php esc_html_e('This person\'s job title / position at the company.', 'pms'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pms_department_id"><?php esc_html_e('Department', 'pms'); ?></label></th>
                <td>
                    <select name="pms_department_id" id="pms_department_id">
                        <option value=""><?php esc_html_e('— None —', 'pms'); ?></option>
                        <?php foreach ($departments as $department) : ?>
                            <option value="<?php echo esc_attr($department->id); ?>" <?php selected($department_id, $department->id); ?>>
                                <?php echo esc_html($department->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($departments)) : ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to the Departments admin page */
                                esc_html__('No departments yet — %s', 'pms'),
                                '<a href="' . esc_url(admin_url('admin.php?page=pms-departments')) . '">' . esc_html__('add one first', 'pms') . '</a>'
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_fields(int $user_id): void
    {
        if (! current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['pms_designation'])) {
            update_user_meta($user_id, 'pms_designation', sanitize_text_field(wp_unslash($_POST['pms_designation'])));
        }

        if (isset($_POST['pms_department_id'])) {
            $department_id = absint($_POST['pms_department_id']);
            if ($department_id) {
                update_user_meta($user_id, 'pms_department_id', $department_id);
            } else {
                delete_user_meta($user_id, 'pms_department_id');
            }
        }
    }
}

<?php
/**
 * Shared shell, included at the top of every admin/views/*.php file.
 * Renders the custom sidebar + topbar that replace WordPress's own
 * chrome (hidden by PMS_Fullscreen on these screens). Every view is
 * expected to open its own <main> content after including this, and
 * include footer.php at the end to close the wrapper tags.
 *
 * Expects nothing from the including view — it reads everything it
 * needs (current user, current page) itself, so this can be included
 * with a single line and no setup.
 */

if (! defined('ABSPATH')) {
    exit;
}

$pms_current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'pms-dashboard';
$pms_user = wp_get_current_user();
$pms_is_admin = PMS_Roles::current_user_is_pms_admin();

$pms_nav_items = [
    ['slug' => 'pms-dashboard', 'label' => __('Dashboard', 'pms'), 'icon' => 'dashicons-grid-view', 'cap' => 'read'],
    ['slug' => 'pms-my-tasks', 'label' => __('My Tasks', 'pms'), 'icon' => 'dashicons-yes-alt', 'cap' => 'pms_view_assigned_tasks', 'module' => 'tasks'],
    ['slug' => 'pms-tasks', 'label' => __('Tasks', 'pms'), 'icon' => 'dashicons-list-view', 'cap' => 'pms_manage_tasks', 'module' => 'tasks'],
    ['slug' => 'pms-attendance', 'label' => __('Attendance', 'pms'), 'icon' => 'dashicons-clock', 'cap' => 'pms_clock_in_out', 'module' => 'attendance'],
    ['slug' => 'pms-my-account', 'label' => __('My Profile', 'pms'), 'icon' => 'dashicons-admin-users', 'cap' => 'read'],
];

$pms_org_items = [
    ['slug' => 'pms-users', 'label' => __('People', 'pms'), 'icon' => 'dashicons-groups', 'cap' => 'pms_manage_users'],
    ['slug' => 'pms-departments', 'label' => __('Departments', 'pms'), 'icon' => 'dashicons-networking', 'cap' => 'pms_manage_departments'],
    ['slug' => 'pms-branches', 'label' => __('Branches', 'pms'), 'icon' => 'dashicons-location', 'cap' => 'pms_manage_branches'],
    ['slug' => 'pms-roles', 'label' => __('Roles & Permissions', 'pms'), 'icon' => 'dashicons-admin-users', 'cap' => 'pms_manage_users'],
];

$pms_system_items = [
    ['slug' => 'pms-reports', 'label' => __('Reports', 'pms'), 'icon' => 'dashicons-chart-bar', 'cap' => 'pms_view_reports'],
    ['slug' => 'pms-settings', 'label' => __('Settings', 'pms'), 'icon' => 'dashicons-admin-generic', 'cap' => 'pms_manage_settings'],
];

$pms_visible_org = array_filter($pms_org_items, fn ($item) => current_user_can($item['cap']));
$pms_visible_system = array_filter($pms_system_items, fn ($item) => current_user_can($item['cap']));
?>
<div class="pms-shell">
    <aside class="pms-shell-sidebar">
        <div class="pms-shell-brand">
            <span class="pms-brand-mark"><?php echo esc_html(strtoupper(substr(get_option('pms_company_name', get_bloginfo('name')), 0, 1))); ?></span>
            <div class="pms-brand-copy">
                <strong><?php echo esc_html(get_option('pms_company_name', get_bloginfo('name'))); ?></strong>
                <small><?php echo esc_html($pms_is_admin ? __('Admin workspace', 'pms') : __('Staff workspace', 'pms')); ?></small>
            </div>
        </div>

        <nav class="pms-shell-nav">
            <p class="pms-nav-label"><?php esc_html_e('Workspace', 'pms'); ?></p>
            <?php foreach ($pms_nav_items as $item) :
                if (! current_user_can($item['cap']) || (! empty($item['module']) && ! PMS_Modules::is_active($item['module']))) {
                    continue;
                }
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"
                   class="pms-nav-item <?php echo $pms_current_page === $item['slug'] ? 'is-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endforeach; ?>

            <?php if (! empty($pms_visible_org)) : ?>
                <p class="pms-nav-label"><?php esc_html_e('Organization', 'pms'); ?></p>
                <?php foreach ($pms_visible_org as $item) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"
                       class="pms-nav-item <?php echo $pms_current_page === $item['slug'] ? 'is-active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                        <span><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (! empty($pms_visible_system)) : ?>
                <p class="pms-nav-label"><?php esc_html_e('System', 'pms'); ?></p>
                <?php foreach ($pms_visible_system as $item) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"
                       class="pms-nav-item <?php echo $pms_current_page === $item['slug'] ? 'is-active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                        <span><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <div class="pms-shell-account">
            <a href="<?php echo esc_url(admin_url()); ?>" class="pms-back-link">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Back to WP Dashboard', 'pms'); ?>
            </a>
            <div class="pms-account-card">
                <a href="<?php echo esc_url(admin_url('admin.php?page=pms-my-account')); ?>" class="pms-account-link" title="<?php esc_attr_e('My Account', 'pms'); ?>">
                    <?php echo get_avatar($pms_user->ID, 34); ?>
                    <div class="pms-account-copy">
                        <strong><?php echo esc_html($pms_user->display_name); ?></strong>
                        <small><?php echo esc_html($pms_is_admin ? __('Admin', 'pms') : __('Staff', 'pms')); ?></small>
                    </div>
                </a>
                <a href="<?php echo esc_url(wp_logout_url(admin_url('admin.php?page=pms-dashboard'))); ?>" class="pms-logout" title="<?php esc_attr_e('Log out', 'pms'); ?>">
                    <span class="dashicons dashicons-migrate"></span>
                </a>
            </div>
        </div>
    </aside>

    <div class="pms-shell-body">
        <header class="pms-shell-topbar">
            <button type="button" class="pms-mobile-menu-toggle" aria-label="<?php esc_attr_e('Open navigation', 'pms'); ?>" aria-expanded="false">
                <span class="dashicons dashicons-menu"></span>
            </button>
            <div class="pms-topbar-title">
                <?php
                $pms_page_titles = array_merge($pms_nav_items, $pms_org_items, $pms_system_items, [
                    ['slug' => 'pms-my-account', 'label' => __('My Profile', 'pms')],
                ]);
                $pms_title = __('Project Management', 'pms');
                foreach ($pms_page_titles as $item) {
                    if ($item['slug'] === $pms_current_page) {
                        $pms_title = $item['label'];
                        break;
                    }
                }
                ?>
                <strong><?php echo esc_html($pms_title); ?></strong>
            </div>
            <div class="pms-topbar-actions">
                <a href="<?php echo esc_url(admin_url()); ?>" class="pms-topbar-wp-link">
                    <?php esc_html_e('WP Dashboard', 'pms'); ?> →
                </a>
            </div>
        </header>

        <main class="pms-shell-main">

<?php
/**
 * Shared ERP application shell.
 *
 * The visual structure is intentionally shared by every ERP screen.
 * Page views provide only their content; navigation, topbar, account
 * controls and responsive behavior live here.
 */
if (! defined('ABSPATH')) {
    exit;
}

$pms_current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'pms-dashboard';
$pms_user = wp_get_current_user();
$pms_is_admin = PMS_Roles::current_user_is_pms_admin();
$pms_workspace_class = $pms_is_admin ? 'pms-workspace-admin' : 'pms-workspace-staff';
$pms_company_name = get_option('pms_company_name', get_bloginfo('name'));
$pms_brand_initial = strtoupper(substr((string) $pms_company_name, 0, 1));

$pms_nav_items = [
    ['slug' => 'pms-dashboard', 'label' => __('Dashboard', 'pms'), 'icon' => 'dashicons-grid-view', 'cap' => 'read'],
    ['slug' => 'pms-my-tasks', 'label' => __('My Tasks', 'pms'), 'icon' => 'dashicons-yes-alt', 'cap' => 'pms_view_assigned_tasks', 'module' => 'tasks'],
    ['slug' => 'pms-tasks', 'label' => __('Tasks', 'pms'), 'icon' => 'dashicons-list-view', 'cap' => 'pms_manage_tasks', 'module' => 'tasks'],
    ['slug' => 'pms-attendance', 'label' => __('Attendance', 'pms'), 'icon' => 'dashicons-calendar-alt', 'cap' => 'pms_clock_in_out', 'module' => 'attendance'],
    ['slug' => 'pms-leave', 'label' => __('Leave', 'pms'), 'icon' => 'dashicons-airplane', 'cap' => 'pms_request_leave', 'module' => 'leave'],
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

$pms_visible_org = array_filter($pms_org_items, static fn ($item) => current_user_can($item['cap']));
$pms_visible_system = array_filter($pms_system_items, static fn ($item) => current_user_can($item['cap']));
$pms_page_titles = array_merge($pms_nav_items, $pms_org_items, $pms_system_items);
$pms_title = __('ERP Workspace', 'pms');

foreach ($pms_page_titles as $item) {
    if ($item['slug'] === $pms_current_page) {
        $pms_title = $item['label'];
        break;
    }
}
?>
<div class="pms-shell <?php echo esc_attr($pms_workspace_class); ?>">
    <aside class="pms-shell-sidebar" aria-label="<?php esc_attr_e('ERP navigation', 'pms'); ?>">
        <div class="pms-shell-brand">
            <span class="pms-brand-mark"><?php echo esc_html($pms_brand_initial); ?></span>
            <div class="pms-brand-copy">
                <strong><?php echo esc_html($pms_company_name); ?></strong>
                <small><?php echo esc_html($pms_is_admin ? __('Admin Workspace', 'pms') : __('Staff Workspace', 'pms')); ?></small>
            </div>
        </div>

        <nav class="pms-shell-nav">
            <p class="pms-nav-label"><?php esc_html_e('Workspace', 'pms'); ?></p>
            <?php foreach ($pms_nav_items as $item) :
                if (! current_user_can($item['cap']) || (! empty($item['module']) && ! PMS_Modules::is_active($item['module']))) {
                    continue;
                }
                if ($pms_is_admin && in_array($item['slug'], ['pms-my-tasks', 'pms-leave'], true)) {
                    continue;
                }
                if (! $pms_is_admin && $item['slug'] === 'pms-tasks') {
                    continue;
                }
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"
                   class="pms-nav-item <?php echo $pms_current_page === $item['slug'] ? 'is-active' : ''; ?>"
                   data-pms-nav-label="<?php echo esc_attr(strtolower($item['label'])); ?>">
                    <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endforeach; ?>

            <?php if ($pms_is_admin && ! empty($pms_visible_org)) : ?>
                <p class="pms-nav-label"><?php esc_html_e('Organization', 'pms'); ?></p>
                <?php foreach ($pms_visible_org as $item) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"
                       class="pms-nav-item <?php echo $pms_current_page === $item['slug'] ? 'is-active' : ''; ?>"
                       data-pms-nav-label="<?php echo esc_attr(strtolower($item['label'])); ?>">
                        <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                        <span><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($pms_is_admin && ! empty($pms_visible_system)) : ?>
                <p class="pms-nav-label"><?php esc_html_e('System', 'pms'); ?></p>
                <?php foreach ($pms_visible_system as $item) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>"
                       class="pms-nav-item <?php echo $pms_current_page === $item['slug'] ? 'is-active' : ''; ?>"
                       data-pms-nav-label="<?php echo esc_attr(strtolower($item['label'])); ?>">
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
                    <?php echo get_avatar($pms_user->ID, 42); ?>
                    <span class="pms-account-copy">
                        <strong><?php echo esc_html($pms_user->display_name); ?></strong>
                        <small><?php echo esc_html($pms_is_admin ? __('Admin', 'pms') : __('Staff', 'pms')); ?></small>
                    </span>
                    <span class="dashicons dashicons-arrow-right-alt2 pms-account-arrow"></span>
                </a>
            </div>
        </div>
    </aside>

    <div class="pms-shell-body">
        <header class="pms-shell-topbar">
            <button type="button" class="pms-mobile-menu-toggle" aria-label="<?php esc_attr_e('Open navigation', 'pms'); ?>" aria-expanded="false">
                <span class="dashicons dashicons-menu"></span>
            </button>

            <div class="pms-topbar-search">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search" autocomplete="off" placeholder="<?php esc_attr_e('Search anything...', 'pms'); ?>" aria-label="<?php esc_attr_e('Search ERP navigation', 'pms'); ?>" data-pms-global-search>
                <kbd>⌘ K</kbd>
            </div>

            <div class="pms-topbar-spacer"></div>

            <div class="pms-topbar-notification" aria-hidden="true">
                <span class="dashicons dashicons-bell"></span>
            </div>

            <div class="pms-topbar-account">
                <?php echo get_avatar($pms_user->ID, 38); ?>
                <span>
                    <strong><?php echo esc_html($pms_user->display_name); ?></strong>
                    <small><?php echo esc_html($pms_is_admin ? __('Admin', 'pms') : __('Staff', 'pms')); ?></small>
                </span>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </div>
        </header>

        <main class="pms-shell-main">

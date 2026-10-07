<?php
/**
 * Makes PMS pages feel like a standalone app instead of a WordPress
 * admin screen: hides the default WP sidebar menu, admin toolbar, and
 * footer, ONLY on our own pages (never globally — that would break
 * every other plugin's admin screens too). Our own shell partial
 * (admin/views/partials/header.php) supplies the "← Back to WP
 * Dashboard" link this removes easy access to, so nothing is a dead
 * end.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Fullscreen
{
    public function __construct()
    {
        add_action('admin_head', [$this, 'maybe_hide_wp_chrome']);
        add_filter('admin_body_class', [$this, 'maybe_add_body_class']);
    }

    private function is_pms_screen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen && strpos($screen->id, 'pms-') !== false;
    }

    public function maybe_add_body_class(string $classes): string
    {
        if ($this->is_pms_screen()) {
            $classes .= ' pms-fullscreen-app';
        }

        return $classes;
    }

    public function maybe_hide_wp_chrome(): void
    {
        if (! $this->is_pms_screen()) {
            return;
        }
        ?>
        <style>
            /* Hide WordPress's own chrome only on PMS screens. */
            body.pms-fullscreen-app #adminmenumain,
            body.pms-fullscreen-app #wpadminbar,
            body.pms-fullscreen-app #wpfooter,
            body.pms-fullscreen-app .notice:not(.pms-notice),
            body.pms-fullscreen-app #screen-meta-links,
            body.pms-fullscreen-app #screen-meta {
                display: none !important;
            }
            body.pms-fullscreen-app html.wp-toolbar {
                padding-top: 0 !important;
            }
            body.pms-fullscreen-app #wpcontent,
            body.pms-fullscreen-app #wpbody-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            body.pms-fullscreen-app #wpbody {
                padding-top: 0 !important;
            }
            body.pms-fullscreen-app .update-nag,
            body.pms-fullscreen-app .notice-warning:not(.pms-notice) {
                display: none !important;
            }
        </style>
        <?php
    }
}

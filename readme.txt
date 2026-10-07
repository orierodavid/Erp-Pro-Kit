=== Project Management System ===
Contributors: orierodavid
Tags: task management, attendance, project management
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Task management with per-task location verification and punctuality tracking — built for teams with no fixed office, native to WordPress.

== Description ==

This is a starter skeleton, not a finished product:

* Every task carries its own Work mode — Remote (time tracked, no location check) or Location based (GPS-verified against a geocoded address before it can start)
* Free address verification via OpenStreetMap, cached to stay within their usage policy
* Punctuality (Early/Late) computed per task against its own scheduled time, or a company-wide default from Settings
* Two roles — PMS Admin and PMS Staff — with granular capabilities; your existing Administrator account automatically gets full access too
* A full-screen custom app shell in wp-admin (WordPress's own menu/toolbar are hidden on PMS pages, replaced with a branded sidebar + topbar, with a "Back to WP Dashboard" link)
* Full CRUD for Tasks, Branches, and Departments, with file attachments and a two-way notes thread on every task
* A real Settings page (WP Settings API) — company name, brand color (color picker), default geofence radius, default start time, late-grace period
* Reports and CSV export filterable by department, work mode, and early/late
* Two frontend shortcodes so Staff never need wp-admin access at all:
  - [pms_login] — a branded login form
  - [pms_dashboard] — task list with per-task Start actions (shows the login form automatically if not logged in yet)

== Installation ==

1. Upload the `project-management-system` folder to `/wp-content/plugins/`
2. Activate through the "Plugins" menu — this creates the database tables, the two roles, and grants your Administrator account full access
3. Go to Users → assign a user the "PMS Admin" or "PMS Staff" role
4. Go to the new "Project Mgmt" menu → Settings, to set your company name and brand color
5. Optionally create a front-end page with [pms_dashboard] on it, so Staff can use the app without ever touching wp-admin

== Changelog ==

= 0.6.0 =
* Major change: attendance is no longer branch-based — it's task-based, to fit a workforce with no permanent office location
* Every task now has a Work mode: Remote (time tracking only) or Location based (requires GPS verification to start)
* Location based tasks: type an address, verify it via free OpenStreetMap geocoding, set a geofence radius — a task literally cannot be started unless the assignee is physically within range
* Punctuality (Early/Late) is now computed per task, against that task's own scheduled start time (or the company default from Settings if not set)
* The old standalone branch-based Attendance page and clock-in/out flow have been fully removed — starting a task is now how a workday begins
* Reports and CSV export rebuilt around task punctuality: filter by department, work mode, and early/late
* People page now shows last task activity and live "working now" status instead of branch clock-in data

= 0.5.0 =
* Added: "Late after" setting (workday start + grace period in minutes) used to compute punctuality
* Added: Designation and Department fields directly on WordPress's own Add User / Edit Profile screens — no separate form to maintain
* Reports page rebuilt: filter attendance by branch, department, and early/late, with a detailed per-record table
* Added: CSV export of the attendance report, matching whatever filters are currently applied
* People page now shows Designation and Department columns

= 0.4.0 =
* Fixed: plugin author metadata was incorrectly showing a placeholder company instead of the actual author
* Added: branded login screen (wp-login.php re-skinned with your company name/color) instead of default WordPress branding
* Added: PMS users are redirected straight to the plugin's own dashboard after login — never see WordPress's default Dashboard/sidebar
* Added: full task detail view for Staff — open a task, update status, post notes, and upload a response file (10MB limit) for the assigned admin to see
* Added: two-way notes/comments thread on every task, visible and postable from both the Admin Tasks screen and Staff's My Tasks screen

= 0.3.0 =
* Fixed: Administrator account now correctly recognized in task assignment and People list (was previously filtered out by literal role-name matching)
* Added: task file attachments, 10MB limit enforced server-side (not just client-side)
* Added: "My Tasks" page for Staff (view assigned tasks, update status — no full edit access)
* Added: "My Account" page (update name and password — email intentionally excluded, still requires WordPress's own confirmed-email-change flow)
* Added: real server-side geofence enforcement on clock-in (previously collected but never checked)
* People page now shows open task count, last clock-in, and live status per person

= 0.2.0 =
* Full CRUD for Tasks, Branches (with location/geofence), Departments
* Real Settings page via the WP Settings API, including a white-label brand color picker
* Full-screen custom app shell replacing WordPress's default chrome on PMS pages
* Administrator role automatically granted full plugin access
* [pms_login] shortcode, plus geolocation + branch selection wired into the clock-in flow
* Real Reports page (attendance summary + task breakdown)

= 0.1.0 =
* Initial skeleton: tables, roles, admin menu, REST clock in/out, frontend shortcode.

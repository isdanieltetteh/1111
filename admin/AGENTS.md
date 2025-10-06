You have access to schema.sql, which defines the full database structure — always refer to it and only use existing tables/columns unless explicitly instructed to extend.
You may create sql migration file for non-disruptive new tables for analytics and more if needed (e.g., visitors, traffic_logs), if not already present.

⚙️ Core Rules

Keep all existing PHP logic, forms, and actions intact and functional.

Do not rename existing files, actions, or variable names.

Only enhance and extend the admin system with analytics and insights.

Use Bootstrap 5 + AdminLTE v4 as the design foundation.

Full mobile responsiveness and dark/light theme support required.

🎯 Your Mission

Build a completely new, professional Admin Dashboard (“God Mode”) with:

Stunning modern Bootstrap 5 + AdminLTE-style UI

New Analytics & Insights section for system monitoring

Consistent global design system (header.php, footer.php, theme.css)

Integrated traffic visualization and user activity tracking

Intelligent layout, charts, badges, and status indicators

🏗️ UI & Layout Requirements
1. Global Layout

Header: logo + search + user dropdown (profile/logout).

Sidebar: collapsible, icon-based navigation (Dashboard, Sites, Users, Reviews, Ads, Analytics, Tickets, Settings, etc..).

Content area: dynamic section wrapped in Bootstrap containers.

Footer: copyright, version, and status indicators.

2. Design System

Use AdminLTE v4 visual language:

Gradient navbar

Card-based metrics

Shadows, rounded corners, glowing hover states

Responsive tables and modals

Typography: Inter or Nunito Sans / 14–16 px base.

Color Palette (example):

Primary #4f46e5

Secondary #6b7280

Success #22c55e

Danger #ef4444

Warning #f59e0b

Background #0f172a (dark) / #f8fafc (light)

📊 Analytics & Monitoring Extension

Add a new “Analytics” module in the admin sidebar with the following features:

1. Visitor Tracking (visitors table)
Field	Type	Description
id	INT PK AI	
ip_address	VARCHAR 45	IPv4/IPv6
country	VARCHAR 100	Geolocated country
city	VARCHAR 100	Optional
device_type	VARCHAR 50	Desktop / Mobile / Tablet
referrer	VARCHAR 255	HTTP referrer
page_visited	VARCHAR 255	Page path
visited_at	DATETIME	Visit timestamp

Automatic logging:

Triggered from frontend pages via a small include (track_visit.php) capturing IP + browser + referrer.

Use free GeoIP API (e.g., ipapi.co/json) to resolve country.

2. Admin Dashboard Overview

Cards showing:

Total Users

Total Sites

Total Reviews

Total Visits (last 7 days)

Active vs Inactive Sites

Line chart: Daily visits (30 days)

Pie chart: Visitors by country

Table: Recent Visitors (IP | Country | Page | Time)

Chart library: Chart.js

etc... add all necessary overviews

3. System Health Panel

Show server info: PHP version, MySQL status, uptime, and memory usage.

Highlight anomalies (e.g., database errors, failed logins).

📱 Responsiveness + UX

Works flawlessly on desktop, tablet, and mobile.

Collapsible sidebar for mobile.

Tables scroll horizontally when the content overflows.

Charts resize dynamically.

Smooth CSS transitions and hover glow effects.

⚡ Expected Output

New admin layout files (header.php, footer.php, sidebar.php, theme.css).

Upgraded pages (index.php, sites.php, users.php, reviews.php, ads.php, tickets.php, settings.php, etc, all existing pages).

New analytics module for better overview.

Chart.js integration for visual insights.

Admin able to control all from backend

refer to user frontend pages to know what to implement in admin like ad approvals, sites, deposit tracking, etc....

Responsive, consistent, and professional AdminLTE-quality theme.

🧩 Final Reminder

You are building a new, enterprise-grade admin dashboard — with analytics, insights, and professional styling — without breaking any existing backend logic.

# Final Defense Rubric Mapping (Evidence Pack)

This document maps the system’s current implementation to the final defense rubric and lists concrete demo steps + evidence files.

## 1) Core Functionalities (ISO/IEC 25010) – 20%

**Core modules implemented**
- PUV Database (Operators, Vehicles, Routes/LPTRP, Ownership Transfer): [admin/pages/module1](file:///c:/xampp/htdocs/tmm/admin/pages/module1)
- Franchise Management: [admin/pages/module2](file:///c:/xampp/htdocs/tmm/admin/pages/module2)
- Traffic Violation & Ticketing (Issue/Validate/Settle): [admin/pages/module3](file:///c:/xampp/htdocs/tmm/admin/pages/module3)
- Vehicle Registration & Inspection: [admin/pages/module4](file:///c:/xampp/htdocs/tmm/admin/pages/module4)
- Terminal & Parking: [admin/pages/module5](file:///c:/xampp/htdocs/tmm/admin/pages/module5)

**ISO/IEC 25010 evidence (practical)**
- Functional suitability: modules above
- Usability: consistent UI patterns, toasts, validation, and role-based navigation via [sidebar_items.php](file:///c:/xampp/htdocs/tmm/admin/includes/sidebar_items.php)
- Reliability: transactional writes in ticketing, route fare bulk update, and DB safeguards in [db.php](file:///c:/xampp/htdocs/tmm/admin/includes/db.php)
- Security: RBAC + audit logs (see section 5)

## 2) AI Integration – 15%

**AI / predictive analytics (trend + rules + context weights)**
- Dashboard: [dashboard.php](file:///c:/xampp/htdocs/tmm/admin/pages/dashboard.php)
- Forecast + insights APIs: [admin/api/analytics](file:///c:/xampp/htdocs/tmm/admin/api/analytics)
- AI settings: [settings/general.php](file:///c:/xampp/htdocs/tmm/admin/pages/settings/general.php)
- Description: [AI.md](file:///c:/xampp/htdocs/tmm/docs/AI.md)

## 3) Microservices / API Integration – 10%

**API layer**
- Admin APIs: [admin/api](file:///c:/xampp/htdocs/tmm/admin/api)

**External integrations implemented**
- Treasury integration + callback: [admin/api/integration/treasury](file:///c:/xampp/htdocs/tmm/admin/api/integration/treasury)
- Permits integration: [admin/api/integration/permits](file:///c:/xampp/htdocs/tmm/admin/api/integration/permits)
- Integration specs (payloads/contracts): [INTEGRATION_SPECIFICATIONS.md](file:///c:/xampp/htdocs/tmm/docs/INTEGRATION_SPECIFICATIONS.md)

## 4) Physical Server Setup & Configuration – 15%

**Environment + DB bootstrap**
- Environment loader: [env.php](file:///c:/xampp/htdocs/tmm/includes/env.php)
- DB schema ensure/auto-migrations: [db.php](file:///c:/xampp/htdocs/tmm/admin/includes/db.php)

## 5) Advanced Security (Data Privacy Act + ISO 27001-aligned features) – 15%

**RBAC**
- RBAC overview: [RBAC.md](file:///c:/xampp/htdocs/tmm/docs/RBAC.md)
- Config: [rbac_config.php](file:///c:/xampp/htdocs/tmm/config/rbac_config.php)
- Enforcement: [rbac.php](file:///c:/xampp/htdocs/tmm/includes/rbac.php)

**Audit logging**
- Login audit: [users/activity.php](file:///c:/xampp/htdocs/tmm/admin/pages/users/activity.php)
- Business audit trail: `audit_events` (auto-created by [db.php](file:///c:/xampp/htdocs/tmm/admin/includes/db.php)) and displayed in [users/activity.php](file:///c:/xampp/htdocs/tmm/admin/pages/users/activity.php)

**Privacy / policy**
- Terms + Privacy Policy section: [index.php](file:///c:/xampp/htdocs/tmm/index.php)

## 6) Analytics – 10%
- Dashboard and analytics endpoints: [dashboard.php](file:///c:/xampp/htdocs/tmm/admin/pages/dashboard.php), [admin/api/analytics](file:///c:/xampp/htdocs/tmm/admin/api/analytics)

## 7) Import / Export – 5%
- Export helpers: [export.php](file:///c:/xampp/htdocs/tmm/admin/includes/export.php)
- Ticket exports: [export_csv.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/export_csv.php), [export_pdf.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/export_pdf.php)
- LPTRP import: [import_lptrp.php](file:///c:/xampp/htdocs/tmm/admin/api/routes/import_lptrp.php)

## 8) UI Look & Feel – 10%
- Shared UI styles: [unified.css](file:///c:/xampp/htdocs/tmm/admin/includes/unified.css)
- Consistent admin layout: [admin/index.php](file:///c:/xampp/htdocs/tmm/admin/index.php)

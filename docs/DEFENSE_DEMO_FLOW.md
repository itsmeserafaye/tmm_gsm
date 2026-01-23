# Defense Demo Flow (Suggested)

## Opening (30–60s)
- Show role-based dashboard: [dashboard.php](file:///c:/xampp/htdocs/tmm/admin/pages/dashboard.php)
- Mention modules: PUV DB, Franchise, Ticketing/Treasury, Inspection, Terminal/Parking

## Core Functionality (5–8 min)
- Module 1: Create/maintain Operator + Vehicle + Routes/LPTRP
- Module 2: Submit Franchise Application → endorse/approve
- Module 3: Issue Ticket → Validate → Treasury Payment → OR autopopulates
- Module 4: Schedule inspection → Submit checklist → Issue certificate
- Module 5: Terminal/Parking configuration → payments/slots

## AI / Analytics (2–3 min)
- Dashboard: demand forecast chart + insights + alerts
- Explain inputs: demand observations + context (weather/events/traffic)

## IoT (1–2 min)
- Trigger telemetry demo by calling `/admin/api/iot/ingest.php` (token-based) then refresh dashboard “IoT Live Feed”.

## Security & Privacy (2–3 min)
- RBAC: different users see different modules/features
- Audit logs: login audit + business audit trail: [users/activity.php](file:///c:/xampp/htdocs/tmm/admin/pages/users/activity.php)
- Mention privacy policy and DPA compliance: [index.php](file:///c:/xampp/htdocs/tmm/index.php)

## Import/Export (1–2 min)
- Export tickets CSV/PDF
- Import LPTRP routes from sample CSV

## TAM Evaluation (1 min)
- Fill TAM survey: [tam_survey.php](file:///c:/xampp/htdocs/tmm/admin/pages/research/tam_survey.php)
- Show results aggregation: [tam_results.php](file:///c:/xampp/htdocs/tmm/admin/pages/research/tam_results.php)


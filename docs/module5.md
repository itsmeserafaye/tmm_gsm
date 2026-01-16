Module 5 — Parking & Terminal Management

Overview

The Parking & Terminal Management module is responsible for city-level administration of public transport terminals, municipal parking facilities, loading/unloading bays, and designated PUV parking areas. It provides the LGU with tools to:

Authorize and permit terminals and parking zones (issue/renew permits)
Maintain operator and vehicle rosters per terminal
Monitor terminal operations (entries/exits, occupancy)
Collect and reconcile terminal/parking fees with the City Treasury
Enforce terminal rules and collaborate with Traffic Enforcement for non-compliance
Provide data for transport planning and LPTRP enforcement

This module focuses only on city jurisdiction (city-owned terminals, city-designated loading bays, municipal parking). It does not manage private mall parks or national road parking beyond the LGU remit.


Process Flow (Real-World LGU-aligned Steps)

1. Terminal/Permit Application
A terminal operator (cooperative, association, private operator under LGU contract) files an application for a new terminal permit or renewal. Required documents include: site plan/layout, proof of land use/right (LGU land lease, private permit), fire/safety certificates, sanitation plan, operator list, and any LGU fee payment proof.

LGU Action: Intake clerk creates an application record, issues tracking number, and assigns to Terminal Inspector.

API Interaction:
Government Permits System (if exists) → POST /permits/applications (create permit application record) — otherwise the permit record is created locally.

2. Site Inspection & Compliance Check
Terminal Inspector schedules a site inspection. Inspector uses a terminal checklist (safety compliance: lighting, CCTV, PWD access, signage, passageways; capacity; traffic flow). Inspector records findings and required corrective actions.

LGU Action: Inspector approves, conditionally approves (requires fixes), or rejects the permit application.

API Interaction:
Inspection Module → POST /inspection/terminal/results (inspection outcomes and photo evidence).

3. Permit Issuance & Permit Conditions
If approved, LGU issues a Terminal Permit with conditions (operating hours, capacity limits, operator obligations). Permit includes expiry and renewal schedule.

LGU Action: Permit document stored in system and a permit number issued; fees recorded and forwarded to Treasury.

API Interaction:
Revenue/Treasury → POST /revenue/charges to create a permit fee charge and GET /revenue/receipt/{id} to confirm payment.

4. Operator & Vehicle Enrollment to Terminal
Approved operators/coops submit operator rosters and vehicle lists to be authorized for terminal operations. LGU validates that each vehicle is in the PUV Database and linked to a valid franchise.

LGU Action: Terminal Officer or assigned clerk confirms operator list and sets approved vehicle roster for the terminal.

API Interaction:
PUV Database → GET /vehicles?operatorId={id} to fetch vehicle roster.

Franchise Management → GET /franchise/{operatorId}/status to verify franchise validity.


5. Day-to-Day Terminal Operations
Terminal officers log vehicle arrivals/departures (time-in/time-out), record load/unload activity, handle daily gate activities, and report incidents (breakdown, unruly passenger, illegal parking). The system can optionally accept attachments (photos, statements).

LGU Action: Terminal logs are reconciled daily with operator manifests and used for enforcement or billing (for usage fees).

API Interaction:
Terminal Log API → POST /terminals/{id}/logs to record entries/exits and incidents.

Analytics Module → POST /analytics/terminal/activity for aggregated monitoring.


6. Enforcement & Violation Handling
If a vehicle operates in the terminal without permit, or violates terminal rules, Terminal Officer flags the vehicle. Depending on severity the LGU can issue local citations, impound orders (per city ordinance), or escalate to Traffic Enforcement / Franchise Management for possible sanction.

LGU Action: Generate incident ticket and coordinate with Traffic Violation & Ticketing module for citations.


API Interaction:
Traffic Violation Module → POST /tickets to create a violation case referencing terminal incident.


7. Fee Collection, Reconciliation & Reporting
Terminals and parking spaces often generate local fees (terminal usage, parking fees, monthly operator stall rent). The module records fees and payments, synchronizes receipts with City Treasury, and generates reconciliation reports.

LGU Action: Treasury confirms payments and system updates terminal’s financial ledger; reports prepared for budget/revenue officers.

API Interaction:
Revenue/Treasury → POST /revenue/charges (create charges), GET /revenue/receipt/{id} (confirm payment), GET /revenue/reconciliation/terminals?date=YYYY-MM-DD for periodic reconciliation.


8. Terminal Permit Renewal & Revocation
Before permit expiry, system sends automated reminders to terminal operator. For renewals, operators must re-submit required documents, pay renewal fees, and pass another compliance check. Revocation occurs after repeated non-compliance, serious incidents, or failure to comply with corrective orders.

LGU Action: Create renewal workflow and revoke permit when necessary (with documented reason).

API Interaction:
Permit Workflow → POST /permits/{id}/renewal to initiate renewal; PATCH /permits/{id} to change status to revoked.


Submodules (Internal Components)

Submodules
Terminal Permit & Inspection Management
 Handles terminal and parking permit applications, inspections, approvals, renewals, and revocations, including storage of compliance documents and permit conditions.


Terminal Operations & Vehicle Enrollment
 Manages operator and vehicle rosters per terminal, day-to-day entry and exit logging, and incident reporting within LGU-managed terminals and parking facilities.


Fees, Enforcement & Analytics
 Records terminal and parking fees, reconciles payments with the City Treasury, links incidents to enforcement actions, and produces operational and revenue analytics.





Data Entities & Key Fields

Terminal
terminal_id, name, address, location_coords, lptrp_route_ids[], capacity_per_route{route_id:capacity}, officer_id, permit_id, permit_status, permit_expiry_date

TerminalPermitApplication
application_id, applicant_name, document_refs[], submitted_at, status, assigned_inspector_id

TerminalPermit
permit_id, terminal_id, issue_date, expiry_date, conditions[], payment_receipt_id

TerminalOperatorList
entry_id, terminal_id, operator_id, vehicle_ids[], approved_date, status

TerminalLog
log_id, terminal_id, vehicle_id, operator_id, time_in, time_out, activity_type, remarks, recorded_by

TerminalIncident
incident_id, terminal_id, vehicle_id, operator_id, incident_type, description, evidence_refs[], reported_at, status

TerminalCharge
charge_id, terminal_id, amount, charge_type, due_date, receipt_id, paid_status


System Design

Frontend
Permit & Applicant Portal: For uploading site documents, tracking application status, and paying fees.
Terminal Officer App: Simple touchscreen UI for logging in/out PUVs, registering incidents, and quick checks of operator lists.
Admin Dashboard: For permit approvals, reconciliation, route capacity views, analytics, and enforcement queue.


Backend
Microservice handling terminal CRUD, assignment, logs, and incident management.
Batch jobs to reconcile daily terminal logs with operator manifests and Treasury receipts.
Role-based access: Terminal Officer, Inspector, Permit Admin, Revenue Officer, Enforcement Officer.

Database & Storage
Relational DB (Postgres/MySQL) for structures above.
File store (S3 or LGU local) for permit/inspection documents and incident evidence.

Security
mTLS or OAuth2 for inter-module API calls.
Audit logs for every create/update/delete on permit and incident records.
Data encryption at rest for sensitive fields (e.g., officer IDs).


Descriptive API Interactions (How this module communicates with other subsystems)

1. Parking & Terminal Management → PUV Database
Purpose: Validate vehicle registration and pull vehicle/operator data during enrollment and daily checks.
Interaction: GET /puv/vehicle/{plate} or GET /puv/vehicles?operatorId={id}.
Use Case: Ensure only registered vehicles are allowed into terminals.

2. Parking & Terminal Management → Franchise Management
Purpose: Confirm operator franchise validity before approving terminal rosters.
Interaction: GET /franchise/{operatorId}/status.
Use Case: Block vehicles of suspended franchises.

3. Parking & Terminal Management → Vehicle Inspection Module
Purpose: Check latest inspection result of vehicles on daily entry.
Interaction: GET /inspection/vehicle/{id}/status.
Use Case: Deny terminal access to failed/expired inspection vehicles.

4. Parking & Terminal Management → Revenue/Treasury
Purpose: Create charges for permits and terminal fees and confirm payments.
Interaction: POST /revenue/charges, GET /revenue/receipt/{id}.
Use Case: Update permit status only upon payment confirmation.

5. Parking & Terminal Management → Traffic Violation Module
Purpose: Create violation records from terminal incidents.
Interaction: POST /tickets (ticket with incident ref).
Use Case: Issue local fines, impound orders, or escalate to franchise review.


6. Parking & Terminal Management → Analytics Module
Purpose: Submit aggregated terminal usage for dashboards and planning.
Interaction: POST /analytics/terminal/summary.
Use Case: Provide LPTRP compliance reports and identify congestion hotspots.

7. Parking & Terminal Management → Government Service Management
Purpose: Validate terminal officer identity and role.
Interaction: GET /employee/{id}.
Use Case: Ensure only authorized personnel can modify terminal rosters and logs.


Business Rules & Validation Logic
Permit Required: No terminal operations without a valid and unexpired Terminal Permit.
Operator Roster Validity: All operators/vehicles assigned to terminal must have active franchises and passed inspections.
Route Quota Enforcement: Active terminal vehicle count per route must not exceed LPTRP capacity unless explicit temporary waiver granted by LGU with documented reason.
Incident Escalation: Serious incidents (e.g., violence, public endangerment, structural safety concern) require immediate escalation and temporary suspension of terminal operation pending investigation.
Fee Payment Lock: Permit will not be active until Treasury confirms payment.
Auditability: All changes to terminal rosters, permit statuses, or incident records must have user and timestamp.
Manual Overrides: Senior officers can override system blocks (e.g., temporary capacity waiver) but must record a mandate/reference and rationale.


Scope & Limitations

Scope
City-owned or LGU-designated terminals and parking areas.
Operator/vehicle rostering for terminals.
Local terminal operations logging, incident handling, and fee administration.
Integration with PUV Database, Franchise, Inspection, Traffic Violation, Revenue, and Analytics modules.

Limitations
Does not manage private commercial parking operations (malls, private lots).
Does not replace LTO or LTFRB authority for vehicle registration/franchise issuance.
Relies on accurate LPTRP and PUV Database inputs provided by other modules or LGU staff.
Advanced detection (e.g., automated CCTV plate recognition) is optional/extra and not required for core functionality.



Implementation Notes (Capstone-Ready)
MVP Scope: Terminal catalog, permit application flow, operator/vehicle enrollment, daily log interface, basic enforcement link (create ticket), and reconciliation page for payments.
Mock APIs: Use local mock endpoints to simulate PUV Database, Franchise, Inspection, and Revenue during development and demo.
Data Samples: Prepare a handful of sample terminals, sample cooperatives, and sample fleet lists to simulate day-to-day operations.
UX Notes: Terminal officer UI should be mobile/tablet friendly with large buttons and offline cache for intermittent connectivity.
Testing: Test route capacity scenarios (e.g., adding excess vehicles), incident reporting flows, and permit renewal cycles.
Audit & Reporting: Include downloadable CSV reports for daily logs and monthly reconciliation for the Treasurer’s office.

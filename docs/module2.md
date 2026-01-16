Module 2 — Franchise Management

Overview

The Franchise Management module handles LGU-level processes related to public transport franchises: receiving and processing endorsement requests, validating cooperative consolidation and franchise documents, issuing city permits/endorsements, monitoring renewals, and coordinating escalation/reports to national authorities (LTFRB) when needed. It does not issue national franchises (LTFRB responsibility); instead it manages local endorsement workflows and LGU permit issuance.

This module ensures the city enforces LPTRP constraints and cooperative consolidation policies while keeping accurate records of active franchises and their linked PUV fleets.


Process Flow (Real-world LGU-aligned Steps)

1. Application Intake
A cooperative or operator submits a franchise endorsement request to the LGU. Required items: scanned LTFRB franchise document (or LTFRB reference number if available), cooperative registration documents, list of member vehicles, proposed route(s), and proof of payment for any LGU fees.
The system creates an application record and issues a tracking number.

2. Preliminary Automated Checks
The module performs automated checks:
Validate that the route(s) in the application are present in the LPTRP masterlist (imported or uploaded by LGU staff).
Check whether the cooperative registration number exists in the local Coop Registry.
Compare requested vehicle count against LPTRP route capacity thresholds.

3. Document Verification & Manual Review
LGU officers review scanned LTFRB documents and coop registrations for authenticity. If national APIs are not available, staff manually mark documents as verified/rejected.
If the cooperative is not consolidated or documentation incomplete, the system generates required-document reminders and places the application in a “Pending Documents” state.

4. Endorsement Decision
If all checks pass, the LGU prepares an endorsement letter or local permit:
If endorsement for LTFRB: generate official endorsement document and forward it to LTFRB (via email, web portal, or API if available).
If local permit: issue a city transport permit number and capture fee payment receipt.

5. Post-Issuance Monitoring
The module tracks expiry/renewal dates and compliance. It receives violation summaries (from STS Sync or local Ticketing) and inspection outcomes (from the Inspection module). Repeated non-compliance triggers a compliance case and may escalate to LTFRB.

6. Renewal & Revocation
For renewals, the system sends reminders, accepts renewal applications, and repeats validation steps. For revocation cases, it records the rationale, supporting evidence (violations, inspection failures), and issues local administrative notices.


Submodules
Franchise Application & Cooperative Management
 Manages the intake and tracking of franchise endorsement applications, cooperative profiles, consolidation status, and submitted documentation. This submodule maintains the full application lifecycle from submission to decision.


Validation, Endorsement & Compliance Engine
 Performs document verification, LPTRP capacity enforcement, and policy checks. It generates endorsement letters or local permits, applies compliance rules, and initiates escalation workflows when violations or inspection failures occur.


Renewals, Monitoring & Reporting
 Tracks franchise validity, renewal schedules, and compliance history. It produces audit trails and management reports and supports long-term monitoring of franchise performance and regulatory adherence.



Data Entities & Key Fields

FranchiseApplication
application_id, applicant_coop_id, franchise_ref_number (LTFRB ref or file), submitted_at, status (Pending, Under Review, Conditional, Endorsed, Rejected), assigned_officer_id, route_ids[], vehicle_count_requested, fee_receipt_id, attachments[]

Cooperative
coop_id, name, registration_no, contact, lg_approval_status, consolidation_status (Not consolidated / In progress / Consolidated), members[]

LPTRPRoute
route_id, route_code, start_point, end_point, max_vehicle_capacity, approval_status

EndorsementRecord
endorsement_id, application_id, issued_by, issued_date, document_ref, method_sent_to_ltfrb (API/Email/Portal), local_permit_no (if issued)

ComplianceCase
case_id, subject_franchise_id, triggering_events[], status, actions_taken[], esc_latation_date

Document
document_id, type, file_path, uploaded_by, uploaded_at, verified_by, verified_at, verification_status


System Design

Frontend
Officer Portal: application list, document viewer, verification checklist, route capacity visualization, coop profile pages, compliance cases panel, and endorsement generator.
Applicant/Public Portal: application submission form, upload interface, application tracking by reference number, payment receipt upload.


Backend
Microservice or modular monolith exposing REST endpoints for internal modules.
Role-based authentication (LGU staff roles: Intake Clerk, Verifier, Approver, Compliance Officer).
Document storage (S3 or LGU-hosted file system) with hashed filenames and retention policies.

Database
Relational DB (Postgres/MySQL). Use normalized tables for FranchiseApplication, Cooperative, LPTRPRoute, EndorsementRecord, Document, ComplianceCase.
Indexing on franchise_ref_number, application_id, route_id for quick lookup.

Queue/Workflow
Message queue for long-running tasks (e.g., sending endorsements to LTFRB, batch checks against LPTRP) and for creating follow-up tasks (reminders, escalations).


Descriptive API Interactions (How this module communicates with other subsystems)

> Note: All interactions are API-based (modular architecture). APIs are described at a high level — request/response behavior and purpose — without full payloads.

1. PUV Database → Franchise Management (Inbound)

Purpose: When a PUV record is created/updated, the PUV Database calls Franchise Management to confirm franchise linkage.

Interaction: PUV system sends GET /franchise-applications?franchise_ref=XXX (or GET /franchise/{franchise_id} if ID known). Franchise Management returns application status, endorsement status, and coop affiliation.

Use Case: If a vehicle is uploaded with a franchise reference, PUV DB verifies whether the franchise is endorsed/valid before marking vehicle Active.


2. Franchise Management → PUV Database (Outbound)

Purpose: After endorsement or permit issuance, Franchise Management informs the PUV Database of valid franchise_id and permitted route_ids.

Interaction: Franchise module calls POST /puv/franchise/attach with franchise_id, vehicle_list, route_permissions. PUV DB updates vehicle permissions and route assignments.

Use Case: Ensures only endorsed vehicles are assigned to LPTRP-approved routes and terminals.


3. Franchise Management ↔ LPTRP Repository

Purpose: Validate route assignments and vehicle capacity.

Interaction: Franchise Management calls GET /lptrp/routes or GET /lptrp/route/{route_id} to fetch capacity and status. If LPTRP is in a separate system, this is an authenticated API; otherwise, a CSV upload interface is used.

Use Case: Deny or condition endorsement if vehicle_count_requested > max_vehicle_capacity.


4. Franchise Management ↔ Revenue Collection & Treasury

Purpose: Record and validate payment of LGU endorsement fees or permit fees.

Interaction: The Franchise module calls POST /revenue/charges to create a charge and GET /revenue/receipt/{receipt_id} to verify payment.

Use Case: Application cannot move to Endorsed until fee_receipt_id shows paid=true.


5. Franchise Management ↔ Inspection Module

Purpose: Receive inspection outcomes to inform compliance decisions.

Interaction: Inspection service sends POST /franchise/inspection-result with application_id or franchise_id, inspection_result, and attachments (photos, report).

Use Case: If inspections for fleet under a franchise have mass failures, Franchise Management may flag the franchise for review or conditional endorsement.


6. Franchise Management ↔ Traffic Violation / STS Module

Purpose: Get violation summaries for a given franchise or coop to evaluate compliance and escalate cases.

Interaction: Franchise Management calls GET /tickets/summary?franchise_id=XXX&period=30d. STS (or local ticketing) returns counts by violation type and severity.

Use Case: Trigger compliance workflows when violation thresholds are exceeded (e.g., create ComplianceCase).




7. Franchise Management → LTFRB (External National Authority)

Purpose: Submit endorsements or escalate revocation recommendations.

Interaction: If APIs exist: POST /ltfrb/endorsements with application details and attachments. If not, the system prepares a signed PDF endorsement and logs method_sent = email/portal/manual.

Use Case: Documented evidence of LGU endorsement sent to LTFRB; track LTFRB responses if integration exists.


8. Franchise Management ↔ Citizen Information & Engagement

Purpose: Verify operator identity and allow citizens to view endorsement status.

Interaction: Calls GET /citizen/{operator_id} to fetch identity confirmations or GET /citizen/search?name=XXX for name matching when operators supply IDs.

Use Case: Prevent fraudulent operator claims by cross-checking against the citizen registry.


Business Rules & Validation Logic

Franchise-COOP Binding: A franchise endorsement request must reference a registered cooperative; if coop is consolidation_status != Consolidated and consolidation is required by policy, the application is Pending until consolidation documented.
LPTRP Enforcement: Endorsement only allowed if vehicle_count_requested ≤ route.max_vehicle_capacity. If greater, application is Conditional or Rejected depending on policy.
Payment Lock: Application cannot be moved to ‘Endorsed’ unless fee_receipt.paid = true.
Inspection Precondition: For large fleets (> N vehicles) LGU may require a baseline inspection report before endorsement. Applications without inspections are flagged.
Violation Response: If tickets.count > threshold within timeframe, auto-create ComplianceCase and apply temporary hold on new permit issuance.
Document Retention & Audit: All endorsements, uploaded scanned documents, and verification actions are auditable. Records cannot be deleted—only marked as superseded with reason.
Manual Override: Senior LGU officers can override LPTRP block with documented local resolution; the system records override rationale and approver.






Scope & Limitations

Scope
LGU endorsement processing, permit issuance, coop validation, LPTRP checks, and internal escalation workflows.
Supports both automated checks (where internal data is present) and manual verification when national APIs are not available.

Limitations
The module cannot issue or revoke national franchises — only recommend and send endorsements to LTFRB.
Integration with LTFRB, LTO, or national STS is dependent on availability of APIs; absent APIs, the module must rely on scanned documents and manual data entry.
Some checks (e.g., authenticating LTFRB documents in real-time) are only possible if external APIs exist; otherwise they are manual and subject to human error.


Implementation Notes (Capstone-Ready)

Start Simple: Implement the application intake, coop registry, LPTRP rule engine (CSV import for routes), and endorsement generator. Simulate LTFRB interactions by producing a signed PDF and storing method_sent = manual.

Integrate Later: Build API client stubs for LTFRB, STS, and Treasury. When real APIs are available, replace manual flows with API calls (no schema changes required if designed with abstraction).

Testing: Create test datasets for LPTRP routes, fictional cooperatives in varied consolidation states, and sample violation histories to validate escalation rules.

User Roles: Ensure the system supports different LGU roles and records who performed verification actions for auditability.

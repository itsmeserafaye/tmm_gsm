Module 3 — Traffic Violation & Ticketing System

Overview

The Traffic Violation & Ticketing System (TVTS) is a digital enforcement and citation management system used by the city government to record, track, and process traffic violations committed within the city’s jurisdiction.

For Metro Manila cities, this module operates in compliance with the MMDA Single Ticketing System (STS) framework. However, for flexibility, it can run independently (local LGU ticketing) if STS API access is unavailable.

It focuses on LGU-level responsibilities, such as:

Issuing local citation tickets
Tracking payment and compliance
Coordinating with Franchise Management and Vehicle Inspection for repeat offenders
Reporting violations to LTFRB or MMDA as required

This module streamlines enforcement, reduces redundancy, and provides data analytics for traffic management and compliance monitoring.


Process Flow (Real-World LGU Steps)

1. Violation Detection
Enforcers identify a violation on-site (manual observation, CCTV, or automated sensor input).
The enforcer logs the violation using a mobile app or desktop console — selecting the violation type, vehicle plate number, driver/operator name, and location.
For digital enforcement setups (CCTV, AI cameras), the system can auto-log the violation based on plate recognition results (optional feature).

2. Ticket Creation
The enforcer enters or selects:
Violation type (based on LGU or STS violation codes)
Vehicle plate number
Operator/driver name
Location and time
Optional photo or video attachment
The system generates a unique ticket number and stores the case as “Pending Validation.”

3. Validation & Record Linking
Backend automatically cross-checks:
Vehicle details from PUV Database (ownership, franchise ID, route, coop)
Franchise information from Franchise Management
Violator’s identity from Citizen Information & Engagement
If data matches existing records, the ticket is marked “Validated.” If not, it’s flagged for manual verification.

4. Notification & Payment Processing
If integrated with STS or Treasury:
Ticket details are transmitted to the STS or LGU Treasury for payment collection.
The violator receives a printed or electronic notice with the ticket reference and payment instructions.
Payment can be made online (via Treasury ePayment portal) or over-the-counter.
Once payment is confirmed, the ticket status is updated to “Settled.”



5. Compliance Tracking
If payment is not made within X days, the system marks the case as “Unsettled” and notifies the violator and operator/cooperative.
For repeat offenders (same vehicle/operator), the module generates a compliance summary and pushes a flag to Franchise Management or Inspection Module for escalation.

6. Reporting & Analytics
Supervisors can view dashboards showing violation hotspots, types, frequency, and collection summaries.
Predictive analytics can identify high-risk routes or times of day for violations (optional advanced feature).


Submodules (Internal Components)

Submodules
Violation Logging & Ticket Processing
 Handles on-site and automated violation recording, ticket generation, evidence attachment, and initial case creation by authorized traffic enforcers.


Validation, Payment & Compliance Tracking
 Cross-validates ticket data with PUV and Franchise records, monitors payment status through Treasury integration, and aggregates repeat violations by vehicle, operator, or franchise.


Analytics, Reporting & Enforcement Integration
 Provides dashboards, statistical reports, and trend analysis. It supports escalation to Franchise Management and Inspection modules and synchronizes records with MMDA STS or equivalent systems when applicable.


Data Entities & Key Fields

Ticket
ticket_id, ticket_number, date_issued, violation_code, vehicle_plate, franchise_id, coop_id, driver_name, issued_by, status (Pending, Validated, Settled, Escalated), fine_amount, due_date, payment_ref


ViolationType
violation_code, description, fine_amount, category, sts_equivalent_code

Evidence
evidence_id, ticket_id, file_path, file_type, uploaded_by, timestamp


PaymentRecord
payment_id, ticket_id, amount_paid, date_paid, receipt_ref, verified_by_treasury


ComplianceSummary
vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status

Officer
officer_id, name, role, badge_no, station_id, active_status


System Design

Frontend
Officer Mobile Interface: For ticket creation, capture of photo/video evidence, GPS tagging, and quick violation code lookup.
Back-office Portal: For ticket validation, report generation, and monitoring unpaid tickets.
Treasury View: For viewing tickets marked as “awaiting payment” and updating payment statuses.

Backend
RESTful API service written in Node.js, Django, or Laravel.
Role-based access control using JWT tokens.
Integrates with the central audit service (for user actions).
Event-driven notifications for escalations and renewals (email/SMS).


Database
Relational DB for Tickets, Violations, Payments, and Officers.
Use views for compliance summaries (aggregating tickets per franchise/coop).


Descriptive API Interactions

1. Traffic Violation ↔ PUV Database

Purpose: Validate vehicle ownership and operator information.



Interaction:
On ticket creation, the system calls GET /puv/vehicle?plate=XXX123
Returns vehicle owner, franchise_id, and coop details.
If not found, the ticket remains “Pending Validation.”

Use Case: Ensures only recognized vehicles (registered or franchised) are penalized under LGU rules.


2. Traffic Violation ↔ Franchise Management

Purpose: Report violation counts per franchise for compliance monitoring.

Interaction:
POST /franchise/violations with franchise_id, violation summary, and ticket references.
Franchise system logs this as part of compliance cases.

Use Case: Trigger suspension or inspection requirements for repeat offenders.


3. Traffic Violation ↔ Vehicle Inspection Module

Purpose: Send violation records related to vehicle condition or roadworthiness.

Interaction:
POST /inspection/alerts with plate number, violation type, and timestamp.
Inspection team schedules reinspection if required.

Use Case: Automated enforcement-feedback loop between enforcement and inspection.


4. Traffic Violation ↔ Parking & Terminal Management

Purpose: Detect violations committed inside terminals or parking facilities (e.g., unauthorized entry, illegal parking).

Interaction:
POST /parking/violation with ticket_id and vehicle details.
Parking module logs it and blocks reentry until the ticket is settled.

Use Case: Enforce compliance within LGU-managed terminals.


5. Traffic Violation ↔ Revenue Collection & Treasury

Purpose: Manage fine payments.

Interaction:
On ticket creation, system calls POST /revenue/charges to create a payment record.
Treasury later updates PUT /revenue/receipt/{ticket_id} to confirm payment.

Use Case: Prevent ticket closure without verified payment.


6. Traffic Violation ↔ Citizen Information & Engagement

Purpose: Notify violators or operators about citations.

Interaction:
POST /citizen/notify with citizen_id or contact info, message type (SMS, email).
Citizen module handles delivery and confirmation.

Use Case: Ensure drivers/operators receive real-time updates about their violations.

7. Traffic Violation ↔ MMDA STS (External)

Purpose: Synchronize violation records for cities using the Single Ticketing System.

Interaction:
If STS API is available: POST /sts/violations with all ticket data, or GET /sts/violation-status/{ticket_no} to update status.
If unavailable: export CSV or upload via MMDA web portal.

Use Case: Maintain compliance with regional enforcement integration.


Business Rules & Validation Logic

Duplicate Prevention: The system blocks duplicate ticket numbers for the same plate and timestamp.
Jurisdiction Validation: Tickets can only be issued for incidents within city boundaries.
Payment Lock: Ticket cannot be marked “Settled” without verified payment from Treasury.
Escalation Rule: If a franchise accumulates more than 5 violations in 30 days → automatic compliance case creation in Franchise Management.
Officer Accountability: Each issued ticket is linked to an officer ID; only supervisors can edit or void tickets.
Evidence Requirement: At least one evidence file (photo/video) must be attached for digital issuance.
Data Retention: Tickets and violation data are archived after 5 years but remain searchable for audits.



Scope & Limitations

Scope
Covers digital citation issuance, payment tracking, compliance escalation, and reporting.
Works either as part of MMDA’s STS or as a standalone LGU ticketing module.

Limitations
If no integration with STS exists, violation data sharing must be manual (CSV uploads).
No real-time driver’s license verification unless LTO API is available.
Payment confirmation depends on Treasury module — delays can occur if manual encoding is used.


Implementation Notes (Capstone-Ready)
Start Simple: Implement ticket creation, validation with local PUV DB, and manual payment confirmation.
Extend Gradually: Add integrations (Franchise, Treasury, Citizen Notification) as simulated API calls or local modules.
Demo Tip: Show how a violation triggers an automatic compliance warning in Franchise Management — this impresses panels because it demonstrates inter-module logic and real-world enforcement workflow.

Module 4 — Vehicle Inspection & Registration

Overview

The Vehicle Inspection & Registration module manages the city-level vehicle inspection process to ensure that Public Utility Vehicles (PUVs) operating within the city comply with safety, environmental, and operational standards set by the LGU.

This module does not perform LTO registration but verifies the authenticity of uploaded LTO Certificate of Registration (CR) and Official Receipt (OR) documents.
It records inspection findings, issues city inspection certificates, and ensures that vehicles are linked to valid franchises and assigned routes in line with the Local Public Transport Route Plan (LPTRP).

It acts as a verification and compliance tool to help the LGU monitor operational readiness and safety of the transport fleet.

Process Flow (Real-world LGU-aligned Steps)

1. Vehicle Identification & LTO Document Verification
An operator or cooperative submits a request for vehicle inspection through the city transport office portal.
The officer retrieves the vehicle details from the PUV Database by entering its plate number or body number.
The system checks if the vehicle exists in the local database and is tied to a valid franchise.
The operator uploads scanned LTO documents (CR & OR), which are manually verified by the LGU officer (no LTO API integration).
The verified documents are stored in the system and attached to the inspection record.

API Interaction:
PUV Database API → GET /vehicle/{plateNumber}
Used to retrieve vehicle data (operator, make, model, assigned route, status).
Franchise Management API → GET /franchise/{operatorID}
Used to verify that the franchise linked to the operator is valid and active.

2. Inspection Scheduling
Once documents are verified, the officer creates a vehicle inspection schedule in the system.
Schedules can be based on application date, franchise renewal cycle, or batch scheduling per terminal.
The system assigns an authorized city inspector and sends the operator a notification (via email or SMS) about the inspection date, time, and venue.

API Interaction:
Government Service Management API → POST /auth/validateInspector
Used to confirm that the assigned inspector is a registered LGU employee authorized to conduct inspections.

3. Inspection Checklist & Evaluation
During inspection, the inspector uses a digital checklist on the web or mobile interface to evaluate vehicle condition.

Checklist categories typically include:
Lights, brakes, horn, and mirrors
Emission standards and smoke test results
Tires, wipers, and physical condition
Interior cleanliness and safety features
Validity of documents and plate

Each checklist item can be marked as Pass, Fail, or N/A, and photos or remarks can be attached for documentation.
After completion, results are submitted through the system.

API Interaction:
Inspection Management API → POST /inspection/results
Saves the inspector’s findings, checklist ratings, remarks, and image uploads.
PUV Database API → PATCH /vehicle/{id}/inspectionStatus
Updates the vehicle’s inspection status (Passed / Failed / Pending / For Reinspection).

4. Result Approval & Certification
If the vehicle passes all inspection requirements, the system automatically generates a City Vehicle Inspection Certificate (with QR or digital signature for authenticity).
If the vehicle fails, a reinspection schedule is automatically created, and the operator is notified of deficiencies that must be corrected.
All inspection outcomes are logged for transparency and compliance monitoring.

API Interaction:
PUV Database API → PATCH /vehicle/{id}/approvalTag
Used to update vehicle records and attach inspection certificate reference number.
Franchise Management API → POST /franchise/{id}/vehicleLink
Ensures that approved vehicles remain linked to their franchise and route records.

5. Terminal & Route Validation
Once approved, the system validates that the vehicle operates under the correct terminal and route in accordance with the LPTRP.
It checks route capacity and ensures no route has more vehicles than its approved quota.
If a route is overpopulated, the system flags the record for review by the transport office.

API Interaction:
Parking & Terminal Management API → GET /terminal/{id}/assignedVehicles
Retrieves vehicles assigned to a specific terminal for route balancing.
Analytics Subprocess → Internal logic compares total active vehicles vs. LPTRP-approved capacity and triggers warnings when exceeded.

6. Compliance Reporting & Monitoring
Inspection data is aggregated for compliance reports, route-based safety summaries, and cooperative performance dashboards.
These reports are used by LGU decision-makers to identify which operators or terminals need intervention or stricter enforcement.

API Interaction:
Analytics Module API → POST /analytics/inspection/summary
Sends summarized inspection data for report generation and dashboard analytics.


Submodules
Vehicle Verification & Inspection Scheduling
 Manages CR/OR document uploads, manual verification, vehicle eligibility checks, and inspection scheduling, including inspector assignment and operator notifications.


Inspection Execution & Certification
 Supports checklist-based inspections, photo and remark uploads, result encoding, and issuance of city vehicle inspection certificates for compliant vehicles.


Route Validation & Compliance Reporting
 Validates route and terminal assignments against LPTRP limits after inspection approval and generates inspection compliance reports for monitoring and planning purposes.



Data Entities & Key Fields

VehicleInspection
inspection_id, vehicle_id, inspector_id, inspection_date, status (Passed/Failed/Pending), remarks, certificate_id, photos[], checklist_result[]

InspectionSchedule
schedule_id, vehicle_id, operator_id, assigned_inspector_id, scheduled_date, location_id, status

InspectionChecklist
checklist_id, category, item_description, pass_criteria, created_by, last_updated

InspectionCertificate
certificate_id, vehicle_id, issued_date, inspector_id, signature_ref, approval_tag

UploadedDocument
document_id, vehicle_id, type (CR/OR), file_path, uploaded_by, verified_by, verification_status

TerminalAssignment
terminal_id, vehicle_id, route_id, status, approval_date


System Design

Frontend:
Inspector Dashboard: Displays assigned inspections, checklist input forms, upload tools, and certification module.
Admin Dashboard: For scheduling, verification, and generating reports.
Operator Portal: For uploading CR/OR, tracking inspection results, and requesting reinspections.

Backend:
Centralized inspection service handling scheduling, data entry, document verification, and reporting.
Uses role-based authentication (Inspector, Reviewer, Admin).
Stores data in relational tables linked by vehicle_id and franchise_id.


Database:
Tables: vehicle_inspections, inspection_checklists, inspection_certificates, uploaded_docs, terminals.
Indexed on vehicle_id and inspection_date for fast queries.

Integration Points:
PUV Database: Provides vehicle and operator data; receives updated inspection status.
Franchise Management: Validates franchise linkage.
Parking & Terminal Management: Checks terminal assignments and route capacity.
Analytics Module: Receives inspection summaries for visualization.
Government Service Management: Validates inspectors’ employment and authorization.


Descriptive API Interactions (How this module communicates with other subsystems)

1. PUV Database ↔ Vehicle Inspection Module
Purpose: Exchange vehicle details and update inspection results.
Interaction: Vehicle Inspection module calls GET /vehicle/{plateNumber} to fetch records, then PATCH /vehicle/{id}/inspectionStatus to update status post-inspection.
Use Case: Keeps vehicle records synchronized across modules.


2. Vehicle Inspection → Franchise Management
Purpose: Ensure that only vehicles under valid franchises undergo inspections.
Interaction: GET /franchise/{operatorID} validates the operator’s franchise status; after certification, POST /franchise/{id}/vehicleLink re-links vehicle to its franchise.
Use Case: Prevents unauthorized or expired franchises from renewing inspection clearance.


3. Vehicle Inspection → Parking & Terminal Management
Purpose: Verify vehicle’s route and terminal assignment.
Interaction: GET /terminal/{id}/assignedVehicles retrieves the assigned vehicles; inspection logic compares with LPTRP capacity to detect excess.
Use Case: Controls vehicle congestion per terminal and enforces LPTRP quotas.


4. Vehicle Inspection → Analytics Module
Purpose: Send inspection summaries and compliance rates.
Interaction: POST /analytics/inspection/summary submits aggregated inspection data for dashboards and reports.
Use Case: Enables LGU to monitor compliance and maintenance trends citywide.


5. Vehicle Inspection ↔ Government Service Management
Purpose: Authenticate inspectors.
Interaction: POST /auth/validateInspector validates credentials before inspection.
Use Case: Prevents unauthorized individuals from issuing inspection results.

Business Rules & Validation Logic

LTO Document Requirement: CR and OR must be uploaded and manually verified before inspection can be scheduled.
Inspector Authentication: Only verified city inspectors can encode inspection results.
Fail & Reinspection: Failed inspections automatically trigger a reinspection schedule; vehicle remains non-operational until passed.
Franchise Validation: Vehicle must belong to an active franchise before inspection approval.
Route Quota Enforcement: Vehicle cannot be approved if its route exceeds LPTRP capacity.
Certificate Authenticity: Certificates are system-generated and signed digitally; once issued, they cannot be altered.
Audit Trail: Every inspection action, including manual document verification, is logged for traceability.

Scope & Limitations

Scope
City-level inspection and safety verification for PUVs.
Handles document uploads, scheduling, inspection, and certification.
Integrates with Franchise, PUV Database, and Terminal modules for consistency.

Limitations
Does not perform or issue LTO vehicle registration.
Manual CR/OR verification required due to lack of LTO API.
Route capacity checks rely on LPTRP data accuracy uploaded by LGU.
External validation (emission testing, insurance) only recorded if submitted by operator.


Implementation Notes (Capstone-Ready)

Initial MVP: Start with manual document upload, checklist form, and inspection scheduling.
Add-ons: Integrate automatic email/SMS notifications for schedules and results.
Simulate API Links: For modules like PUV Database and Franchise Management, use internal mock APIs during testing.
Testing Dataset: Create sample routes, inspection results, and franchise records for demo.
Audit Trail: Ensure all actions (upload, verification, inspection) are timestamped and traceable by officer name.
User Roles: Include roles for Inspector, Reviewer, Admin, and Operator for access control.


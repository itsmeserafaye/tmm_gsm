# Master Workflow: City Transport Management System

This end-to-end workflow reflects real LGU processes and matches the submodules implemented in the system. Each step lists the Actor, the exact Submodule, and the System action.

---

## Phase 1: Vehicle Registration & Franchise Endorsement
Objective: Legitimize the vehicle and operator within the city.

### Step 1: Data Intake & PUV Registration
Vehicles must be recorded before any other action.

| Step | Actor | Submodule (Module 1) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 1.1 | Operator | — | Submits LTO OR/CR, Deed of Sale, and franchise docs to the City Transport Office. |
| 1.2 | LGU Encoder | Vehicle & Ownership Registry ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module1/submodule1.php)) | Encodes vehicle metadata; uploads documents to the PUV database; attaches CR/OR files. |
| 1.3 | System | Operator, Cooperative & Franchise Validation ([submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module1/submodule2.php)) | Validates duplicate plates; links operators/cooperatives; associates LPTRP route; sets vehicle status to Pending Validation. |
| 1.4 | System | RBAC Enforcement | Restricts create/list/link APIs to Admin/Encoder roles; Inspector role cannot alter registry. |
| 1.5 | System | Route Capacity Precheck | Pre-computes LPTRP route allocation metrics for later assignment gating. |

### Step 2: Franchise Application & Endorsement
Vehicles operate under valid LTFRB franchise and/or City Permit.

| Step | Actor | Submodule (Module 2) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 2.1 | Cooperative Rep | Franchise Application & Cooperative Management ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module2/submodule1.php)) | Submits franchise endorsement or city permit application; maintains cooperative profile and consolidation details. |
| 2.2 | System | Validation, Endorsement & Compliance Engine ([submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module2/submodule2.php)) | Validates LPTRP route existence; enforces route capacity; verifies uploaded franchise docs; checks coop consolidation rules. |
| 2.3 | Franchise Officer | Validation, Endorsement & Compliance Engine | Reviews and endorses applications; issues endorsement or city permit; records endorsement reference. |
| 2.4 | System | Vehicle & Ownership Registry (Module 1) | Syncs vehicle franchise status to Endorsed/Valid; stores endorsement reference in vehicle record for downstream gates. |
| 2.5 | System | Gate Preparation | Marks vehicle eligible for inspection scheduling (Module 4) once Registered + Endorsed. |

---

## Phase 2: Safety & Roadworthiness
Objective: Ensure vehicles are physically safe for transport.

### Step 3: Inspection Scheduling & Execution
Only registered and franchised vehicles can be inspected.

| Step | Actor | Submodule (Module 4) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 3.1 | Operator | Vehicle Verification & Inspection Scheduling ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module4/submodule1.php)) | Requests inspection appointment; uploads/links supporting documents. |
| 3.2 | System | Vehicle Verification & Inspection Scheduling | Eligibility gate checks Module 1 and Module 2: registered and franchise valid. |
| 3.3 | City Inspector | Inspection Execution & Certification ([submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module4/submodule2.php)) | Conducts digital checklist (brakes, lights, emission, tires, interior). |
| 3.4 | City Inspector | Inspection Execution & Certification | Submits results. Pass: system generates QR-coded City Inspection Certificate. Fail: schedule re-inspection. |
| 3.5 | System | Sync Service | Updates Module 1 status to Inspection: Passed; prepares compliance reporting ([submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module4/submodule3.php)). |

### Step 3A: Terminal Permit Issuance & Activation (LGU-aligned)
Terminals operate only with active permits and confirmed payment.

| Step | Actor | Submodule (Module 5) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 3A.1 | Terminal Operator | Terminal Management ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule1.php)) | Files terminal permit application; provides location, capacity, and required docs. |
| 3A.2 | City Inspector | Inspection Execution & Certification (Module 4) | Conducts site/facility checks if required for permit. |
| 3A.3 | Franchise/Permitting Officer | Terminal Management | Issues permit with conditions; system creates Permit Fee charge and marks permit Pending Payment. |
| 3A.4 | Treasury / System | Parking Fees, Enforcement & Analytics ([submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule3.php)) | Records payment; activates permit; opens terminal for operations; operations remain locked if permit is inactive or expired. |

System Gate: Route assignment and terminal operations require Inspection: Passed and Franchise: Endorsed; terminals require Active permit with confirmed payment.

---

## Phase 3: Operational Assignment
Objective: Assign safe, compliant vehicles to terminals and routes.

### Step 4: Terminal Enrollment
Vehicles are assigned to their home-base terminal.

| Step | Actor | Submodule (Module 5) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 4.1 | Terminal Operator | Terminal Management ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule1.php)) | Submits roster of vehicles for authorization at the terminal; manages areas/routes. |
| 4.2 | System | Terminal Management | Roster Validator cross-checks Modules 1, 2, and 4: Registered + Franchised + Inspected + Not Suspended. |
| 4.3 | Terminal Officer | Terminal Management | Approves valid roster entries; links operators/drivers to areas. |
| 4.4 | System | Terminal Management + Module 2 | Route Balancer enforces LPTRP capacity; within limits, vehicles are added to Authorized List. |

---

## Phase 4: Daily Operations & Monitoring
Objective: Monitor usage and collect revenue.

### Step 5: Daily Terminal & Parking Operations
Routine arrival/departure logging and collections.

| Step | Actor | Submodule (Module 5) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 5.1 | Terminal Staff | Terminal Management ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule1.php)) | Logs Arrival and Dispatch (optional Departure where tracked); maintains operator/driver assignments; trips/headway derived from dispatches. |
| 5.2 | Parking Staff / System | Parking Area Management ([submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule2.php)) | Manages city-level parking areas; status and slot capacity updates. |
| 5.3 | System | Parking Fees, Enforcement & Analytics ([submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule3.php)) | Calculates usage/permit/stall rents; records transactions for Treasury; provides analytics. |
| 5.4 | Terminal/ Parking Staff | Parking Fees, Enforcement & Analytics | Reports incidents/violations (e.g., Illegal Parking, No Permit) and issues tickets. |

---

## Phase 5: Enforcement & Compliance
Objective: Catch violators and enforce penalties.

### Step 6: Traffic Enforcement
On-road apprehension and processing.

| Step | Actor | Submodule (Module 3) | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 6.1 | Traffic Enforcer | Violation Logging & Ticket Processing ([submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module3/submodule1.php)) | Logs violation; attaches evidence; generates citation ticket. |
| 6.2 | System | Validation, Payment & Compliance ([submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module3/submodule2.php)) | Real-time validation with Module 1 (PUV) and Module 2 (Franchise); verifies identity. |
| 6.3 | Traffic Enforcer | Violation Logging & Ticket Processing | Finalizes ticket; queues for payment. |
| 6.4 | Violator / Treasury | Validation, Payment & Compliance | Records payment; updates ticket status to Settled; aggregates repeat violations. |
| 6.5 | System | Analytics, Reporting & Integration ([submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module3/submodule3.php)) | Produces dashboards; exports to STS; notifies Parking/Inspection as needed. |

### Step 7: Cross-Module Compliance Action
System-wide response to repeat or unresolved offenders.

| Step | Actor | Submodule | Action & System Logic |
| :--- | :--- | :--- | :--- |
| 7.1 | System | Module 3 (Compliance Tracking) | Detects >3 unpaid tickets or frequent violations; flags the record. |
| 7.2 | System | Module 2 (Franchise Management) | Suspends franchise endorsement for the vehicle when thresholds are met. |
| 7.3 | System | Module 1 (PUV Database) | Updates vehicle status to Suspended. |
| 7.4 | System | Module 5 (Terminal & Parking Ops) | Blocks terminal/parking entry; staff alerted on scan/log attempt. |

---

## Summary of Roles & Responsibilities

- LGU Encoder: Data entry and document digitization (Module 1).
- Franchise Officer: Review and issuance of endorsements/permits (Module 2).
- City Inspector: Physical vehicle testing and certification (Module 4); terminal site checks as needed.
- Terminal Officer: Managing terminal areas, rosters, and daily logs (Module 5).
- Parking Staff: Managing parking areas, fees, and local enforcement (Module 5).
- Traffic Enforcer: On-road apprehension and ticketing (Module 3).
- System (Automated): Validation gates, LPTRP capacity checks, permit payment lock, fee calculation, and cross-module synchronization.

---

## Key API Endpoints

- Module 1
  - Vehicles: [list_vehicles.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/list_vehicles.php)
  - Assign Route: [assign_route.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/assign_route.php)
  - Route Stats: [route_stats.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/route_stats.php)
  - Assignments: [list_assignments.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/list_assignments.php)
  - Export Assignments CSV: [export_assignments_csv.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/export_assignments_csv.php)
- Module 2
  - Apply Franchise/Permit: [apply.php](file:///c:/xampp/htdocs/tmm/admin/api/franchise/apply.php)
  - Endorse Application: [endorse_app.php](file:///c:/xampp/htdocs/tmm/admin/api/module2/endorse_app.php)
- Module 3
  - Create Ticket: [create.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/create.php)
  - Validate Ticket: [validate.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/validate.php)
  - Settle Payment: [settle.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/settle.php)
  - List Tickets: [list.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/list.php)
- Module 5
  - Submit Terminal + Auto Permit App: [save_terminal.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/save_terminal.php)
  - List Terminal Permits: [list_permits.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/list_permits.php)

Notes
- Gates apply: assign_route and terminal operations should be used only after Inspection: Passed and Franchise: Endorsed; terminal operations require Active permit with payment verified.

---

## Role Quick-Start Checklists

- LGU Encoder
  - Register vehicle: Module 1 [submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module1/submodule1.php)
  - Verify duplicates and status: Module 1 API [list_vehicles.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/list_vehicles.php)
  - Maintain operator/cooperative links: Module 1 [submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module1/submodule2.php)

- Franchise Officer
  - Review applications: Module 2 [submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module2/submodule1.php)
  - Validate LPTRP capacity and documents: Module 2 [submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module2/submodule2.php)
  - Issue endorsement/permit: Module 2 API [endorse_app.php](file:///c:/xampp/htdocs/tmm/admin/api/module2/endorse_app.php)

- City Inspector
  - Schedule inspections: Module 4 [submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module4/submodule1.php)
  - Execute checklist and issue certificate: Module 4 [submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module4/submodule2.php)
  - Support route compliance reporting: Module 4 [submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module4/submodule3.php)

- Terminal Officer
  - Apply terminal permit: Module 5 API [save_terminal.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/save_terminal.php)
  - Check permit status: Module 5 API [list_permits.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/list_permits.php)
  - Manage terminal areas and roster: Module 5 [submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule1.php)
  - Assign vehicles to routes: Module 1 [submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module1/submodule3.php) and API [assign_route.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/assign_route.php)

- Parking Staff
  - Manage parking areas: Module 5 [submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule2.php)
  - Record fees and violations: Module 5 [submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule3.php)

- Traffic Enforcer
  - Create citation ticket: Module 3 [submodule1.php](file:///c:/xampp/htdocs/tmm/admin/pages/module3/submodule1.php) and API [create.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/create.php)
  - Validate ticket/vehicle: Module 3 [submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module3/submodule2.php) and API [validate.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/validate.php)
  - Record payment: Module 3 [submodule2.php](file:///c:/xampp/htdocs/tmm/admin/pages/module3/submodule2.php) and API [settle.php](file:///c:/xampp/htdocs/tmm/admin/api/tickets/settle.php)

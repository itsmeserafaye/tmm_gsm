✅ FINAL DETAILED WORKFLOW (Correct & Complete)
🔹 MODULE 1: PUV DATABASE (Single Source of Truth)
Purpose

Maintain all data about operators, vehicles, routes, and their status — even before they are franchised or inspected.

1. Operator Registration

Action: Register operator in PUV DB
Operator Types: Individual / Cooperative / Corporation

Flow

Encoder selects “Add Operator”

Fill operator info + documents

System creates operator_id

Status set to:

Pending Approval (if needed)

Approved

Outcome

Operator exists in system and can apply for franchise.

2. Vehicle Registration in PUV DB

Action: Register vehicle (even if not franchised yet)

Flow

Encoder selects “Add Vehicle”

Input:

Plate No

Engine / Chassis No

Make/Model

Vehicle Type

Upload OR/CR (optional, but recommended)

System creates vehicle_id

Status set to:

Unlinked

Pending Verification

Outcome

Vehicle exists in PUV database, can be linked to operator later.

3. Linking Vehicle to Operator

Action: Link vehicle to operator (can be individual or coop)

Flow

Select vehicle

Select operator

Link and save

Vehicle status updates:

Linked to Operator

Important Note

A vehicle can be registered in the PUV database before franchise or inspection.

This is correct and realistic.

🔹 MODULE 2: FRANCHISE MANAGEMENT
Purpose

To process franchise application, endorsement, and approval.

Step 1 — Franchise Application (LGU Side)
Inputs

Operator ID

Proposed Route

Vehicle Count

Representative Name (if coop/corp)

Supporting docs

Flow

Operator submits application

System creates franchise_application_id

Status = Submitted

Step 2 — LGU Endorsement
Flow

Franchise Officer reviews application

Validates:

Route capacity (LPTRP limit)

Vehicle count

Operator validity

Endorses the application

Status becomes:

Endorsed

Step 3 — LTFRB Approval (External)
Flow

LTFRB approves and issues:

Franchise number

Decision order

System receives approval

Status becomes:

Approved

Franchise record created

Important Note

LTFRB Franchise Reference is only available after approval.

🔹 MODULE 4: VEHICLE REGISTRATION & INSPECTION
Purpose

Verify vehicle compliance and allow operation.

Step 1 — Vehicle Registration (Official in LGU System)
Flow

Vehicle must already exist in PUV DB

Vehicle must be linked to operator

Encoder inputs:

OR/CR (required)

Plate number

Vehicle details

System creates vehicle_registration_id

Status:

Registered

Step 2 — Inspection Scheduling
Flow

Select vehicle

Schedule inspection

Status:

Inspection Scheduled

Step 3 — Inspection Execution
Flow

Inspector performs checks

Result:

Passed → Vehicle becomes eligible for operation

Failed → Must re-inspect after repairs

Step 4 — Activation
Conditions

Vehicle becomes active only if:

Franchise approved

Inspection passed

OR/CR valid

🔹 MODULE 3: TRAFFIC VIOLATION & TICKETING
Purpose

Enforce compliance and collect penalties.

Step 1 — Ticket Issuance

Officer enters plate number

System fetches vehicle + operator data from PUV DB

Issue ticket

Status = Unpaid

Step 2 — Payment / Settlement

Treasurer inputs OR number

Status becomes:

Settled

Step 3 — Repeat Offender Check

System flags:

Operators with > 3 violations

Vehicles with multiple violations

🔹 MODULE 5: PARKING & TERMINAL MANAGEMENT
Purpose

Manage terminals, parking slots, and fees.

Step 1 — Terminal Assignment
Flow

Select vehicle

Check:

Franchise status = Approved

Inspection status = Passed

Assign to terminal

Status:

Terminal Assigned

Step 2 — Parking Fee Payment

Enter plate number

Check parking status

Generate fee

Record payment

Push to treasury system

✅ COMPLETE SYSTEM WORKFLOW DIAGRAM (TEXT)
PUV Database
    ├── Operator Registration
    ├── Vehicle Registration
    └── Vehicle-Operator Link

Franchise Management
    ├── Franchise Application
    ├── LGU Endorsement
    └── LTFRB Approval (External)

Vehicle Registration & Inspection
    ├── Vehicle Registration (official)
    ├── Inspection Scheduling
    ├── Inspection Execution
    └── Activation (Only if franchised + inspected)

Traffic Violation & Ticketing
    ├── Issue Ticket
    ├── Payment & Settlement
    └── Analytics

Parking & Terminal Management
    ├── Terminal Assignment (requires franchise + inspection)
    ├── Parking Slot Tracking
    └── Fee Collection

✅ Key Points to Fix in Your Current Workflow
❌ Current workflow is wrong because:

You allow franchise application by selecting vehicle.

Franchise should be linked to operator, not vehicle.

Vehicle should be attached after franchise approval.

Vehicle must pass inspection before operation.

✅ Correct fix:

Franchise is operator-based

Vehicle is attached after franchise approval

Inspection happens after vehicle registration

Terminal assignment requires franchise + inspection
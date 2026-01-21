✅ REVISED FINAL DETAILED WORKFLOW
(Correct, Complete, LGU‑Realistic, Panel‑Defensible)

🔹 MODULE 1: PUV DATABASE
(Single Source of Truth)

Purpose
Maintain all data about operators, vehicles, and routes — even before franchise, inspection, or approval.

1️⃣ Operator Registration (DATA ENCODING ONLY)
Action
Register operator in PUV Database

Operator Types
Individual

Cooperative

Corporation

Flow
Encoder selects “Add Operator”

Fill in basic operator information only

Name

Address

Operator Type

System creates operator_id

Status
DRAFT / ENCODED
Important Rule (VERY IMPORTANT)
❗ No validation happens here
❗ Documents are NOT required yet

Outcome
✔ Operator exists in system
✔ Cannot apply for franchise yet

2️⃣ Operator Document Submission (NEW — ADDED)
Action
Operator uploads required documents based on operator type

Operator Type	Required Documents
Individual	Valid ID
Cooperative	CDA Registration, Board Resolution
Corporation	SEC Registration, Articles
Flow
Operator / Encoder uploads documents

System records documents

Status Update
PENDING VALIDATION
3️⃣ Operator Document Validation (NEW — CRITICAL ADDITION)
Purpose
LGU confirms documents before allowing regulatory actions

Who validates
Franchise Officer

Transport Officer

Authorized LGU staff

Flow
Officer opens Operator Validation Screen

Reviews each document:

Completeness

Consistency with encoded data

Marks documents:

✅ Verified

❌ Rejected (with remarks)

Resulting Operator Status
Validation Result	Operator Status
All verified	ACTIVE
Incomplete	RETURNED
Invalid	REJECTED
Outcome
✔ Only ACTIVE operators can apply for franchise

4️⃣ Vehicle Registration in PUV Database (Pre‑Franchise Allowed)
Action
Register vehicle (even if not franchised yet)

Flow
Encoder selects “Add Vehicle”

Input:

Plate No

Engine / Chassis No

Make / Model

Vehicle Type

Upload OR/CR

Optional at encoding stage

Status
UNVERIFIED / UNLINKED
Outcome
✔ Vehicle exists in PUV Database
✔ Can be linked later

5️⃣ Linking Vehicle to Operator
Action
Associate vehicle with an operator

Flow
Select vehicle

Select operator

Save link

Status Update
LINKED TO OPERATOR
📌 This does NOT mean the vehicle is allowed to operate

🔹 MODULE 2: FRANCHISE MANAGEMENT
Purpose
Process franchise application, endorsement, and approval

Step 1 — Franchise Application (LGU)
Pre‑Condition
✔ Operator status = ACTIVE

Inputs
Operator ID

Proposed Route

Vehicle Count

Representative Name (coop/corp)

Supporting documents

Flow
Operator submits application

System creates franchise_application_id

Status
SUBMITTED
Step 2 — LGU Endorsement
Flow
Franchise Officer reviews:

Operator validity

Route availability (LPTRP)

Vehicle count

LGU endorses application

Status
ENDORSED
Step 3 — LTFRB Approval (External)
Flow
LTFRB issues:

Franchise number

Decision Order

LGU records approval details

Status
APPROVED
📌 Franchise is operator‑based, not vehicle‑based

🔹 MODULE 4: VEHICLE REGISTRATION & INSPECTION
Purpose
Verify vehicle compliance before allowing operation

Step 1 — Official Vehicle Registration
Pre‑Conditions
✔ Vehicle exists in PUV DB
✔ Vehicle linked to operator
✔ Franchise approved

Flow
Encoder inputs:

OR/CR (required)

Final vehicle details

System creates vehicle_registration_id

Status
REGISTERED
Step 2 — Inspection Scheduling
Flow
Select vehicle

Schedule inspection

Status
INSPECTION SCHEDULED
Step 3 — Inspection Execution
Flow
Inspector performs inspection

Result:

Passed

Failed

Status
Passed → INSPECTED – PASSED

Failed → INSPECTED – FAILED

Step 4 — Vehicle Activation
Conditions (ALL REQUIRED)
✔ Franchise approved
✔ Inspection passed
✔ OR/CR valid

Status
ACTIVE / ALLOWED TO OPERATE
🔹 MODULE 3: TRAFFIC VIOLATION & TICKETING
(No changes — already correct)

🔹 MODULE 5: PARKING & TERMINAL MANAGEMENT
Terminal Assignment Conditions
✔ Franchise = Approved
✔ Inspection = Passed
✔ Vehicle = Active

✅ UPDATED COMPLETE SYSTEM FLOW (TEXT)
PUV DATABASE
 ├─ Operator Encoding
 ├─ Document Submission
 ├─ Document Validation (LGU)
 ├─ Vehicle Encoding
 └─ Vehicle–Operator Linking

FRANCHISE MANAGEMENT
 ├─ Franchise Application
 ├─ LGU Endorsement
 └─ LTFRB Approval (External)

VEHICLE REGISTRATION & INSPECTION
 ├─ Official Registration
 ├─ Inspection Scheduling
 ├─ Inspection Execution
 └─ Vehicle Activation

TRAFFIC VIOLATION & TICKETING
 └─ Enforcement & Settlement

PARKING & TERMINAL MANAGEMENT
 └─ Terminal Assignment & Fees
🎯 WHY THIS VERSION IS STRONG
✔ Clearly shows LGU document validation responsibility
✔ Separates data encoding vs regulatory approval
✔ Matches actual LGU practice
✔ Impossible for panelists to say:
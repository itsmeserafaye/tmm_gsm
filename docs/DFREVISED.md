✅ REVISED & PANEL‑SAFE DATA FIELDS
(With rationale per change)

🔹 1. PUV DATABASE MODULE (REVISED)
1. operators ✅ (Minor but important fixes)
operators
- operator_id INT PK AI
- operator_type ENUM('Individual','Cooperative','Corporation')
- registered_name VARCHAR
- address VARCHAR
- contact_no VARCHAR
- email VARCHAR
- verification_status ENUM('Draft','Verified','Inactive')
- created_at DATETIME
- updated_at DATETIME
🔧 What changed & why
❌ status = Approved → REMOVED

✅ verification_status instead of approval
👉 avoids regulatory authority confusion

Meaning:

Draft → encoded, docs incomplete

Verified → identity & docs checked

Inactive → archived / disqualified

📌 LGU validates identity, not operating rights

2. operator_documents ✅ (Add validation state)
operator_documents
- doc_id INT PK AI
- operator_id INT FK
- doc_type ENUM('GovID','CDA','SEC','BarangayCert','Others')
- file_path VARCHAR
- is_verified BOOLEAN
- verified_by INT FK (user_id)
- verified_at DATETIME
- uploaded_at DATETIME
📌 This clearly shows document verification ≠ approval

3. vehicles ✅ (Status cleanup)
vehicles
- vehicle_id INT PK AI
- plate_no VARCHAR UNIQUE
- engine_no VARCHAR
- chassis_no VARCHAR
- make VARCHAR
- model VARCHAR
- year INT
- fuel_type VARCHAR
- vehicle_type ENUM('Tricycle','Jeepney','UV Express','Bus')
- operator_id INT FK NULL
- record_status ENUM('Encoded','Linked','Archived')
- created_at DATETIME
- updated_at DATETIME
🔧 Why this change
❌ Removed Active/Inactive

Vehicles are not active by themselves

Activation depends on franchise + inspection

4. vehicle_documents ✅ (Same logic as operator docs)
vehicle_documents
- doc_id INT PK AI
- vehicle_id INT FK
- doc_type ENUM('ORCR','Insurance','Emission','Others')
- file_path VARCHAR
- is_verified BOOLEAN
- verified_at DATETIME
- uploaded_at DATETIME
5. routes ✅ (No change — already correct)
✔ This is purely planning data (LPTRP)
✔ LGU authority is correct here

🔹 2. FRANCHISE MANAGEMENT MODULE (REVISED)
1. franchise_applications ✅ (Clarify authority)
franchise_applications
- application_id INT PK AI
- operator_id INT FK
- route_id INT FK
- requested_vehicle_count INT
- representative_name VARCHAR NULL
- status ENUM('Submitted','LGU-Endorsed','LTFRB-Approved','Rejected')
- submitted_at DATETIME
- endorsed_at DATETIME
- remarks TEXT
📌 LGU never “approves” franchises
They only endorse.

2. franchises ✅ (External authority table)
franchises
- franchise_id INT PK AI
- application_id INT FK
- ltfrb_ref_no VARCHAR
- decision_order_no VARCHAR
- approved_units INT
- expiry_date DATE
- franchise_status ENUM('Active','Expired','Revoked')
✔ This table exists only after LTFRB action

🔹 3. VEHICLE REGISTRATION & INSPECTION (REVISED)
1. vehicle_registrations ✅ (Explicit LGU scope)
vehicle_registrations
- registration_id INT PK AI
- vehicle_id INT FK
- orcr_no VARCHAR
- orcr_date DATE
- registration_status ENUM('Recorded','Expired')
- recorded_at DATETIME
📌 LGU records OR/CR, does not issue it

2. inspection_schedules ✅
✔ No authority issue here — LGU inspections are valid

3. inspections ✅ (Add eligibility flag)
inspections
- inspection_id INT PK AI
- vehicle_id INT FK
- schedule_id INT FK
- result ENUM('Passed','Failed')
- remarks TEXT
- inspected_at DATETIME
Eligibility logic is handled in the system layer, not DB status.

🔹 4. TRAFFIC VIOLATION & TICKETING (Minor Fix)
tickets ✅
tickets
- ticket_id INT PK AI
- plate_no VARCHAR
- operator_id INT FK
- driver_name VARCHAR
- violation_code VARCHAR
- location VARCHAR
- evidence_path VARCHAR
- amount DECIMAL
- status ENUM('Unpaid','Settled','Voided')
- issued_at DATETIME
📌 Add Voided for real‑world accuracy

🔹 5. PARKING & TERMINAL MANAGEMENT (GOOD, JUST 1 RULE)
Add system rule (not DB):
Vehicle can be assigned ONLY IF:

LTFRB franchise exists

Latest inspection = Passed

OR/CR recorded

DB design is already correct.
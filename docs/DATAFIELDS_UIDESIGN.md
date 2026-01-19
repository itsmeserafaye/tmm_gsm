✅ 1. PUV DATABASE MODULE
A. Data Fields (Tables)
1. operators
Column	Type	Description
operator_id	INT PK AI	Unique
operator_type	ENUM('Individual','Cooperative','Corporation')	
name	VARCHAR	Operator / Coop / Corp name
address	VARCHAR	
contact_no	VARCHAR	
email	VARCHAR	
status	ENUM('Pending','Approved','Inactive')	
created_at	DATETIME	
updated_at	DATETIME	
2. operator_documents
Column	Type	Description
doc_id	INT PK AI	
operator_id	INT FK	
doc_type	ENUM('ID','CDA','SEC','Others')	
file_path	VARCHAR	
uploaded_at	DATETIME	
3. vehicles
Column	Type	Description
vehicle_id	INT PK AI	
plate_no	VARCHAR UNIQUE	
engine_no	VARCHAR	
chassis_no	VARCHAR	
make	VARCHAR	
model	VARCHAR	
year	INT	
fuel_type	VARCHAR	
vehicle_type	ENUM('Tricycle','Jeepney','UV Express','Bus')	
operator_id	INT FK	
status	ENUM('Unlinked','Linked','Active','Inactive')	
created_at	DATETIME	
updated_at	DATETIME	
4. vehicle_documents
Column	Type	Description
doc_id	INT PK AI	
vehicle_id	INT FK	
doc_type	ENUM('ORCR','Insurance','Others')	
file_path	VARCHAR	
uploaded_at	DATETIME	
5. routes
Column	Type	Description
route_id	INT PK AI	
route_code	VARCHAR	
origin	VARCHAR	
destination	VARCHAR	
structure	ENUM('Loop','Point-to-Point')	
distance_km	DECIMAL	
authorized_units	INT	
B. UI Screens
Screen 1 — Operators List

Search bar

Filter by type/status

Buttons: Add Operator, View Documents

Screen 2 — Add Operator

Form fields:

Operator Type

Name

Address

Contact No / Email

Upload docs

Screen 3 — Vehicles List

Search by plate

Filter by status/operator

Buttons: Add Vehicle, Link Operator

Screen 4 — Add Vehicle

Plate no, engine, chassis, make, model, year, etc.

Upload OR/CR

Screen 5 — Link Vehicle to Operator

Dropdown vehicle list

Dropdown operator list

✅ 2. FRANCHISE MANAGEMENT MODULE
A. Data Fields (Tables)
1. franchise_applications
Column	Type	Description
application_id	INT PK AI	
operator_id	INT FK	
route_id	INT FK	
vehicle_count	INT	
representative_name	VARCHAR	
status	ENUM('Submitted','Endorsed','Approved','Rejected')	
submitted_at	DATETIME	
endorsed_at	DATETIME	
approved_at	DATETIME	
remarks	TEXT	
2. franchises
Column	Type	Description
franchise_id	INT PK AI	
application_id	INT FK	
ltfrb_ref_no	VARCHAR	
decision_order_no	VARCHAR	
expiry_date	DATE	
status	ENUM('Active','Expired','Revoked')	
B. UI Screens
Screen 1 — Franchise Applications List

Filter by status

Search by operator name or route

Screen 2 — Submit Franchise Application

Select operator

Select route

Vehicle count

Representative name

Upload docs

Screen 3 — Endorse Application

View details

Approve / Reject buttons

Notes field

Screen 4 — LTFRB Approval Entry

Input LTFRB Ref No

Decision Order

Approved vehicle count

Expiry date

Set status = Approved

✅ 3. VEHICLE REGISTRATION & INSPECTION MODULE
A. Data Fields (Tables)
1. vehicle_registrations
Column	Type	Description
registration_id	INT PK AI	
vehicle_id	INT FK	
orcr_no	VARCHAR	
orcr_date	DATE	
registration_status	ENUM('Pending','Registered','Expired')	
created_at	DATETIME	
2. inspection_schedules
Column	Type	Description
schedule_id	INT PK AI	
vehicle_id	INT FK	
inspector_id	INT FK	
schedule_date	DATETIME	
status	ENUM('Scheduled','Completed','Cancelled')	
3. inspections
Column	Type	Description
inspection_id	INT PK AI	
vehicle_id	INT FK	
schedule_id	INT FK	
result	ENUM('Passed','Failed')	
remarks	TEXT	
inspected_at	DATETIME	
B. UI Screens
Screen 1 — Vehicle Registration List

Search by plate

Status filter

Screen 2 — Register Vehicle

Select vehicle

Input OR/CR

Save

Screen 3 — Schedule Inspection

Select vehicle

Choose inspector

Select date/time

Screen 4 — Conduct Inspection

Checklist (Pass/Fail)

Upload photos

Submit result

✅ 4. TRAFFIC VIOLATION & TICKETING MODULE
A. Data Fields (Tables)
1. tickets
Column	Type	Description
ticket_id	INT PK AI	
plate_no	VARCHAR	
operator_id	INT FK	
driver_name	VARCHAR	
violation_type	VARCHAR	
location	VARCHAR	
evidence_path	VARCHAR	
amount	DECIMAL	
status	ENUM('Unpaid','Settled')	
issued_at	DATETIME	
2. ticket_payments
Column	Type	Description
payment_id	INT PK AI	
ticket_id	INT FK	
or_no	VARCHAR	
amount_paid	DECIMAL	
paid_at	DATETIME	
B. UI Screens
Screen 1 — Issue Ticket

Input plate number (auto fetch vehicle details)

Driver name

Violation type

Location

Upload evidence

Save

Screen 2 — Payment

Search by ticket or plate

Input OR no

Mark as paid

✅ 5. PARKING & TERMINAL MANAGEMENT MODULE
A. Data Fields (Tables)
1. terminals
Column	Type	Description
terminal_id	INT PK AI	
name	VARCHAR	
location	VARCHAR	
capacity	INT	
2. terminal_assignments
Column	Type	Description
assignment_id	INT PK AI	
terminal_id	INT FK	
vehicle_id	INT FK	
assigned_at	DATETIME	
3. parking_slots
Column	Type	Description
slot_id	INT PK AI	
terminal_id	INT FK	
slot_no	VARCHAR	
status	ENUM('Free','Occupied')	
4. parking_payments
Column	Type	Description
payment_id	INT PK AI	
vehicle_id	INT FK	
slot_id	INT FK	
amount	DECIMAL	
or_no	VARCHAR	
paid_at	DATETIME	
B. UI Screens
Screen 1 — Terminal List

Create terminal

View assignments

Screen 2 — Assign Vehicle to Terminal

Select vehicle

System checks:

Franchise status

Inspection status

OR/CR valid

Screen 3 — Parking Slot Management

View slots

Toggle status

Screen 4 — Payment

Input plate

Generate fee

Save payment

✅ Final Notes (Important)
✔ Your system needs only one source of truth

That’s the PUV database.

✔ Franchise should be linked to operator

Not vehicle.

✔ Vehicle can be registered before franchise

But it cannot operate without franchise + inspection.
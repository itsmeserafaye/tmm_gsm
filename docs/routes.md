🗂️ ROUTES & LPTRP SUBMODULE — WHAT SHOULD BE IN IT
🔹 Purpose
Define authorized routes

Set capacity limits

Serve as the basis for franchise approval

📋 DATA FIELDS (REVISED & CORRECT)
Table: routes
Column	Type	Description
route_id	INT (PK)	Unique route ID
route_code	VARCHAR	Official route code (e.g., TR‑01)
route_name	VARCHAR	Public name (e.g., “Poblacion Loop”)
origin	VARCHAR	Starting point
destination	VARCHAR	End point
via	TEXT	Major streets / barangays
structure	ENUM('Loop','Point‑to‑Point')	Route type
vehicle_type	ENUM('Tricycle','Jeepney','UV','Bus')	Allowed PUV type
authorized_units	INT	Max allowed franchises
status	ENUM('Active','Inactive')	Route usability
approved_by	VARCHAR	City council / ordinance ref
approved_date	DATE	Approval date
created_at	DATETIME	
updated_at	DATETIME	
✔ This mirrors LPTRP reality
✔ Capacity planning is enforceable
✔ Vehicle‑type restriction is realistic

🖥️ UI SCREENS FOR ROUTES SUBMODULE
🟦 Screen 1 — Route List
Search by route code/name

Filter:

Vehicle type

Status

Columns:

Route Code

Route Name

Vehicle Type

Authorized Units

Used Units / Remaining Units

Status

Buttons:

View

Edit

Deactivate

🟦 Screen 2 — Add / Edit Route
Form Fields

Route Code

Route Name

Vehicle Type (important!)

Origin

Destination

Via (multi‑line)

Route Structure

Authorized Units

Approval reference (ordinance no.)

🛑 Only Admin / Planning role can edit

🟦 Screen 3 — Route Capacity View (Read‑Only)
Route info

Authorized units

Current franchises count

Remaining slots

Status indicator:

🟢 Available

🔴 Full

Used directly by Franchise Endorsement

🔗 HOW OTHER MODULES USE ROUTES
🔹 Franchise Management
Dropdown pulls from routes

System checks:

SELECT COUNT(*) FROM franchises WHERE route_id = ?
Blocks endorsement if capacity exceeded

🔹 Terminal Management
Vehicle assigned to terminals only if

Vehicle type matches route

Franchise route is active
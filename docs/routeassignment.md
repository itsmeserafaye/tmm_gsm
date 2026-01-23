✅ HOW TO SHOW “ROUTE ASSIGNMENT OF VEHICLES” (CORRECT WAY)
🔑 KEY PRINCIPLE (START WITH THIS)
Routes are assigned to operators via franchises

Vehicles are assigned to routes through an active franchise

Therefore, what the panel wants is a Route Utilization View

You are NOT changing your logic — you are visualizing it.

🧠 DATA RELATIONSHIP (VERY IMPORTANT)
Route
 └── Franchise
      ├── Operator
      └── Assigned Vehicles
So when they ask:

“Which vehicles are in this route and who is their operator?”

You answer with:

“Vehicles assigned under active franchises for this route.”

✅ REQUIRED SMALL ADDITION (ONE TABLE)
To make this explicit and clean, add vehicle assignment per franchise.

🔹 New Table: franchise_vehicles
franchise_vehicles
- fv_id (PK)
- franchise_id (FK)
- vehicle_id (FK)
- assigned_at
- status (Active / Inactive)
✔ This does NOT break your design
✔ This makes route assignment explicit
✔ This is very defendable

🖥️ UI: WHAT THE PANEL WANTS TO SEE
🔹 ROUTE MANAGEMENT → Route Details Screen
Screen: Route List

Route Code

Origin → Destination

Authorized Units

Active Units

Button: View Assigned Vehicles

🔹 Route Details → Assigned Vehicles View (THIS IS THE MONEY SCREEN)
Route: R-001 (City Proper – Terminal)

Plate No	Vehicle Type	Operator Name	Operator Type	Franchise ID	Status
ABC-123	Jeepney	Juan Dela Cruz	Individual	FR-2024-001	Active
XYZ-456	Jeepney	ABC Transport Coop	Cooperative	FR-2024-002	Active
✔ Shows vehicle
✔ Shows operator
✔ Shows route
✔ Shows franchise

This is exactly what your panelist wants.

🔁 HOW ASSIGNMENT HAPPENS (WORKFLOW)
Step 1 — Franchise Approved
Operator gets approved franchise for Route A

Step 2 — Vehicle Assignment
Conditions enforced by system:

Vehicle belongs to operator

Vehicle type matches route

Vehicle passed inspection

Step 3 — Save Assignment
Record saved in franchise_vehicles

Vehicle now appears under the route

🚫 IMPORTANT RULES (SAY THIS IF ASKED)
❌ One vehicle cannot be active in two routes at the same time

✔ Vehicle reassignment requires deactivation of previous assignment

✔ Operator may appear multiple times if they serve multiple routes
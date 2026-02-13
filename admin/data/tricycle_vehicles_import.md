🚨 What’s wrong with current design?
Right now your structure is:

Route (Jeepney)
Route (Bus)
Route (UV)
Route (Tricycle)
But in reality, the ROUTE is independent of vehicle type.

Because a route is simply:

A transport corridor from Origin → Destination

Example:

Bagumbong → Deparo
That route can be served by:

Jeepneys

Modern Jeepneys

UV Express

Mini Bus

The vehicle type is a capacity allocation, not a different route.

So right now your database is mixing:

Route definition ❌

Fleet allocation ❌

They should be separate.

🧠 Real-world model (How LGU / LTFRB think)
Step 1 — Define ROUTE NETWORK
Routes are defined once:

route_id	origin	destination
R001	Bagumbong	Deparo
R002	Bagumbong	SM Fairview
R003	Sangandaan	Blumentritt
These are transport corridors.

Step 2 — Define SERVICE ALLOCATION PER VEHICLE TYPE
Then the LGU decides:

How many units of each vehicle type are allowed on that route.

Example:

Route: Bagumbong → Deparo

vehicle_type	authorized_units
Jeepney	140
UV Express	40
Mini Bus	20
Tricycle	260
THIS is what your current table is mixing into one record.

🎯 What you should revise (IMPORTANT)
You do NOT need to delete data.
You need to split your Route table into 2 tables.

✨ New Correct Structure
TABLE 1 — ROUTES (transport corridors)
This table should contain ONLY route identity:

routes
- route_id
- route_code
- route_name
- origin
- destination
- via
- structure
- terminal_id
- status
👉 Remove from this table:

vehicle_type ❌

authorized_units ❌

used_units ❌

remaining_units ❌

fare_min/max ❌

Because fares and quotas differ per vehicle type.

TABLE 2 — ROUTE_SERVICE_ALLOCATION (NEW TABLE)
This becomes the table that currently matches your UI.

route_vehicle_types
- id
- route_id  (FK to routes)
- vehicle_type
- authorized_units
- used_units
- remaining_units
- fare_min
- fare_max
- status
Now your UI becomes correct:

Route: Bagumbong → Deparo
   Jeepney → 140 units
   UV Express → 40 units
   Tricycle → 260 units
ONE route → MANY vehicle types.

This is exactly how real transport planning works.

🖥️ How your UI will look after revision
Instead of this:

JEPP-BAGUMBONG-DEPARO
JEPP-BAGUMBONG-DEPARO (Tricycle)
JEPP-BAGUMBONG-DEPARO (Bus)
You will have:

ROUTE: Bagumbong → Deparo
---------------------------------
Vehicle Type | Authorized | Fare
Jeepney      | 140        | ₱13–18
UV Express   | 40         | ₱25–35
Tricycle     | 260        | ₱15–30
Much cleaner. Much more realistic.

🏛️ Why this is important for Franchise module
Remember your franchise logic?

1 franchise = 1 route

Now this becomes correct:

Franchise Application:

Route: Bagumbong → Deparo

Vehicle Type: Jeepney

Requested Units: 5

That is exactly how LTFRB/LGU franchising works.

🏆 Final verdict
Your system is NOT wrong — it’s just one normalization step away from being perfect.

You should:
Keep your routes ✅

Remove vehicle_type from routes table ❌

Create new table for route ↔ vehicle type allocation ✅

Route seeds you can encode (Caloocan-focused)
1) Bagumbong ↔ Novaliches (Town Proper)
Source route exists (PUJ): 

Allowed vehicle types (suggested): Jeepney / Modern Jeepney (optional: UV Express if you want mixed service)

2) Novaliches ↔ Deparo (Brgy. Deparo)
Source route exists (PUJ): 

Allowed vehicle types (suggested): Jeepney / Modern Jeepney

3) Novaliches ↔ Deparo (via Susano)
Source route exists (PUJ): 

Allowed vehicle types (suggested): Jeepney / Modern Jeepney

4) Novaliches ↔ Blumentritt / Rizal Ave corridor (via A. Bonifacio)
Source route exists (PUJ): 

Allowed vehicle types (suggested): Jeepney / Modern Jeepney (optional: UV Express if you support it)

5) EDSA Carousel: Monumento ↔ PITX
Monumento is a known Carousel stop; route guide exists: 

Allowed vehicle types (suggested): City Bus only (because Carousel is a bus service)

6) “Caloocan–X (provincial)” bus corridors (for your BUS records)
Your spreadsheet shows Caloocan→Baliwag/Cabanatuan/Gapan, etc. That’s plausible as provincial bus corridors (not barangay-terminal style).

Allowed vehicle types (suggested): Bus only (Point-to-point/provincial)

Important fix for your design (so it won’t be “wrong”)
Put these as ROUTES (origin→destination) (no vehicle type in this table).

Then in route_vehicle_types, encode allowed types like:

Route “Bagumbong–Novaliches”: Jeepney 140 units, fare ₱13–18

Route “Bagumbong–Novaliches”: Tricycle should usually NOT be here (see below)

What about tricycles?
Tricycles are usually managed as TODA/service areas (barangay/zone coverage), not long corridors like jeepneys/buses. So instead of “Route: Bagumbong→Deparo (Tricycle)”, a more realistic model is:

Tricycle Service Area: “Brgy 176 Bagumbong Zone”, “Camarin Zone”, “Deparo Zone”

Allowed vehicle type: Tricycle only

Optional: set “coverage points” (terminal/landmarks) rather than origin–destination.


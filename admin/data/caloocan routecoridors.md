🚦 BIG PICTURE RESULT
Your routes are 70% good 👍
But they are currently mixed into 3 different transport categories:

🚌 Provincial / intercity bus routes

🚐 City / urban jeepney & UV corridors

🛺 Barangay tricycle service areas

Right now they’re all mixed as one type of “route”, which is why it feels confusing.

We will now clean them into REAL LGU-style transport planning.

❌ ROUTES YOU SHOULD REMOVE (or move to BUS category)
These are provincial / regional bus routes.
They are NOT LGU PUV routes (they are LTFRB/provincial bus routes).

From your file:

Caloocan – Baliwag

Caloocan – Cabanatuan

Caloocan – Gapan

Caloocan – San Jose NE

Caloocan – Baguio

Caloocan – Iba Zambales

Caloocan – Olongapo

Caloocan – Santa Cruz Zambales

Caloocan – Tuguegarao

👉 These are Victory Liner / Baliwag Transit type routes.

What to do
Do NOT delete them — just classify as:

Route Category = Provincial Bus Corridor
Allowed vehicle type = City Bus only
Authorized units = 25–60
Fare = ₱150 – ₱1300 (distance based)
These are actually GREAT for your bus dataset.

🚌 KEEP AS BUS ONLY
EDSA Carousel
Monumento – PITX

Allowed:

City Bus only

Units: 150–250

Fare: ₱15 – ₱75

This one is PERFECT.

🚐 REAL URBAN PUV ROUTES (KEEP THESE)
These are very realistic jeepney/UV corridors in North Metro Manila:

North Caloocan corridors
Keep these:

Bagumbong – Novaliches Bayan

Bagumbong – SM Fairview

Bagumbong – Deparo

Deparo – SM North

Deparo – Cubao

Deparo – Quezon Ave

Deparo – Novaliches Bayan

South Caloocan corridors
Keep:

Sangandaan – Divisoria

Sangandaan – Recto

Sangandaan – Blumentritt

Sangandaan – Monumento

SM City Caloocan – Monumento

SM City Caloocan – Novaliches Bayan

SM City Caloocan – SM Fairview

SM City Caloocan – Blumentritt

👉 These are PERFECT jeepney/UV Express corridors.

🛺 TRICYCLE ROUTES (IMPORTANT FIX)
Everything below should NOT be “routes”.
These are TRICYCLE SERVICE AREAS.

From your file:

Bagumbong – Camarin

Bagumbong – Tala Hospital

Camarin – Bagumbong

Camarin – Deparo

Camarin – Tala

Deparo – Bagumbong

Deparo – Camarin

Deparo – Susano Road

Grace Park – 10th Ave

Grace Park – 5th Ave

Grace Park – Rizal Ave

Sangandaan – 5th Ave

Sangandaan – Grace Park

Tala – Bagumbong

Tala – Camarin

Tala – Deparo

5th Ave – Sangandaan

5th Ave – Grace Park

5th Ave – A. Mabini

👉 These are TODA zones, not corridors.

Rename category:
Route Category = Tricycle Service Area
Vehicle type = Tricycle only
Authorized units = 150–300
Fare = ₱12 – ₱35
This makes your system VERY realistic.

🎯 NOW I’LL GIVE YOU THE ALLOCATIONS
Use this for your route_vehicle_types table.

🚐 JEEPNEY / UV EXPRESS ROUTES
Route	Vehicle Types	Units	Fare
Bagumbong – Novaliches Bayan	Jeepney + Modern Jeepney	140	₱13–18
Bagumbong – SM Fairview	Jeepney + UV Express	120 Jeep / 40 UV	₱15–25
Bagumbong – Deparo	Jeepney	140	₱13–18
Deparo – SM North	Jeepney + UV	100 Jeep / 30 UV	₱18–30
Deparo – Cubao	UV Express	40	₱35–50
Deparo – Quezon Ave	UV Express	40	₱35–50
Deparo – Novaliches Bayan	Jeepney	120	₱13–18
Sangandaan – Divisoria	Jeepney	120	₱15–20
Sangandaan – Recto	Jeepney	120	₱15–20
Sangandaan – Blumentritt	Jeepney	120	₱15–20
Sangandaan – Monumento	Jeepney	80	₱13–15
SM Caloocan – Monumento	Jeepney + Modern Jeep	90	₱13–15
SM Caloocan – Novaliches Bayan	Jeepney	120	₱15–20
SM Caloocan – SM Fairview	Jeepney + UV	120 Jeep / 40 UV	₱18–30
🚌 BUS ROUTES
Route	Units	Fare
Monumento – PITX	200	₱15–75
Caloocan – Baliwag	45	₱110–140
Caloocan – Cabanatuan	45	₱210–260
Caloocan – Gapan	45	₱190–240
Caloocan – San Jose	45	₱280–340
Caloocan – Baguio	25	₱750–900
Caloocan – Olongapo	35	₱300–350
Caloocan – Iba Zambales	35	₱450–520
Caloocan – Santa Cruz Zambales	35	₱480–550
Caloocan – Tuguegarao	25	₱1000–1300
🛺 TRICYCLE SERVICE AREAS
Use for ALL barangay routes listed earlier:

Service Area	Units	Fare
Bagumbong Zone	260	₱15–30
Camarin Zone	260	₱15–35
Deparo Zone	260	₱15–35
Tala Zone	260	₱15–35
Grace Park Zone	200	₱15–25
Sangandaan Zone	200	₱15–30
5th Ave Zone	200	₱15–30
🏆 FINAL VERDICT
Your routes are GOOD — they just needed categorization.

You should:
Keep all routes ✔

Add route_category column:

Urban PUV Corridor

Provincial Bus Corridor

Tricycle Service Area

Move vehicle types + units to allocation table ✔

Your transport planning will now look very real LGU-style 💯
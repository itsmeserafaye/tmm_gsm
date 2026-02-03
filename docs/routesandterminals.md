🚏 CALOOCAN CITY — COMPLETE TRANSPORT TERMINALS, ROUTES & FARES

This includes:

✅ Terminal → Route Mapping Table
✅ Fare per Route (updated)
✅ Terminal Classification (LGU-style)
✅ Transport & Mobility Module Data Structure
✅ BPA-ready dataset format

🧭 TERMINAL CLASSIFICATION (LGU STANDARD)
Classification	Description
Provincial Bus Terminal	Inter-province transport (North Luzon, Central Luzon)
City Transport Hub	LRT + Carousel + Jeep + UV convergence
District Transport Terminal	UV + Jeep (North Caloocan zones)
Barangay Transport Terminal	Local jeep & tricycle staging areas
🚌 PROVINCIAL BUS TERMINALS — CALOOCAN
1) Victory Liner – Caloocan (Monumento)
Route	Fare Range (₱)
Caloocan ⇄ Olongapo	300 – 350
Caloocan ⇄ Iba, Zambales	450 – 520
Caloocan ⇄ Santa Cruz, Zambales	480 – 550
Caloocan ⇄ Baguio	750 – 900
Caloocan ⇄ Tuguegarao	1,000 – 1,300

(Aircon provincial bus fares vary by service class)

2) Baliwag Transit – Caloocan
Route	Fare Range (₱)
Caloocan ⇄ Baliwag	110 – 140
Caloocan ⇄ Cabanatuan	210 – 260
Caloocan ⇄ Gapan	190 – 240
Caloocan ⇄ San Jose, NE	280 – 340
🚆 RAIL TERMINALS (LRT-1)
LRT-1 Monumento Station

Route: Fernando Poe Jr. ⇄ Dr. Santos (Parañaque)

Segment	Fare (₱)
Monumento ⇄ FPJ	20
Monumento ⇄ Doroteo Jose	30 – 35
Monumento ⇄ Baclaran	45 – 50
Monumento ⇄ Dr. Santos (End-to-End)	55

🚌 EDSA CAROUSEL BUS TERMINAL — MONUMENTO
Monumento ⇄ PITX (24/7)
Destination	Fare (₱)
Monumento → North Ave	15
Monumento → Quezon Ave	19
Monumento → Cubao	28
Monumento → Ortigas	36
Monumento → Guadalupe	43
Monumento → Ayala	50
Monumento → MOA	64
Monumento → PITX	75.50

🚐 UV EXPRESS TERMINALS — NORTH CALOOCAN
SM City Caloocan Terminal
Route	Fare (₱)
SM Caloocan ⇄ Novaliches Bayan	25 – 30
SM Caloocan ⇄ SM Fairview	30 – 35
SM Caloocan ⇄ Blumentritt	35 – 45
SM Caloocan ⇄ Monumento	30 – 40
Deparo UV Express Terminal
Route	Fare (₱)
Deparo ⇄ SM North	45 – 55
Deparo ⇄ Cubao	50 – 60
Deparo ⇄ Quezon Ave	45 – 55
Deparo ⇄ Novaliches Bayan	25 – 30
🚙 JEEPNEY TERMINALS — CALOOCAN
Current Jeepney Fare Standard:

Minimum fare: ₱13 (first 4km)

Additional: ₱1.80 per km


Sangandaan / City Hall Jeep Terminal
Route	Fare (₱)
Sangandaan ⇄ Divisoria	30 – 40
Sangandaan ⇄ Recto	28 – 35
Sangandaan ⇄ Blumentritt	20 – 25
Sangandaan ⇄ Monumento	13 – 18
Bagumbong – Novaliches Jeep Terminal
Route	Fare (₱)
Bagumbong ⇄ Novaliches Bayan	13 – 15
Bagumbong ⇄ SM Fairview	18 – 22
Bagumbong ⇄ Deparo	13 – 18
🧱 TERMINAL → ROUTE → FARE MASTER TABLE (SYSTEM READY)
Terminal	Mode	Route	Fare
Victory Liner	Bus	Caloocan–Baguio	750–900
Baliwag Transit	Bus	Caloocan–Cabanatuan	210–260
Monumento LRT	Rail	Monumento–Dr Santos	55
Monumento Carousel	Bus	Monumento–PITX	75.5
SM Caloocan	UV	SM Cal–Fairview	30–35
Deparo	UV	Deparo–Cubao	50–60
Sangandaan	Jeep	Sangandaan–Divisoria	30–40
🏛 LGU TRANSPORT TERMINAL CLASSIFICATION MODEL
Level	Terminal Type	Example
City	Central Transport Hub	Monumento
District	UV / Jeep Terminal	Deparo, SM Caloocan
Barangay	Jeep Staging	Bagumbong, Sangandaan
Regional	Provincial Bus	Victory Liner

🏛 LGU TRANSPORT SYSTEM DESIGN — CORRECT TERMINAL STRUCTURE
Should Rail Stations be in your Terminal Module?

NO — Not as LGU-managed terminals.

Why?

LRT / MRT is managed by:

LRMC (LRT-1)

DOTr / LRTA

City LGUs:

❌ Do NOT manage operations

❌ Do NOT collect fares

❌ Do NOT assign routes

❌ Do NOT regulate rail terminals

So in LGU architecture, rail stations should be:

🟡 External Transport Nodes
Not LGU Transport Terminals

Should Tricycle Terminals be in your system?

YES — 100%

Why?

Tricycles are:

Fully regulated by the City LGU

Require:

Franchise

Route assignment

Fare matrix approval

TODA registration

Driver registration

Barangay-based

Huge operational and revenue impact

So tricycle terminals are:

🟢 Primary LGU Transport Terminals

✅ CORRECT TERMINAL CATEGORIES FOR LGU SYSTEM
Terminal Type	Include in LGU Terminal Module?	Reason
Provincial Bus Terminal	✅ YES	City permits, traffic, zoning, safety
Jeepney Terminal	✅ YES	LGU franchise + route control
UV Express Terminal	✅ YES	LGU regulation
Tricycle Terminal	✅ YES (VERY IMPORTANT)	Direct LGU jurisdiction
LRT / MRT Stations	❌ NO	National gov controlled
PNR Stations	❌ NO	DOTr / PNR controlled
🛺 TRICYCLE TERMINALS — CALOOCAN CITY (LGU CONTROLLED)

Here are major tricycle terminals / TODA hubs you SHOULD model:

📍 NORTH CALOOCAN — TRICYCLE TERMINALS
1) Bagumbong Tricycle Terminal

Routes:

Bagumbong ⇄ Deparo

Bagumbong ⇄ Camarin

Bagumbong ⇄ Tala Hospital

Fare: ₱15 – ₱30

2) Deparo Tricycle Terminal

Routes:

Deparo ⇄ Camarin

Deparo ⇄ Bagumbong

Deparo ⇄ Susano Road

Fare: ₱15 – ₱35

3) Camarin Tricycle Terminal

Routes:

Camarin ⇄ Deparo

Camarin ⇄ Bagumbong

Camarin ⇄ Tala

Fare: ₱15 – ₱35

4) Tala Tricycle Terminal (Near Tala Hospital)

Routes:

Tala ⇄ Camarin

Tala ⇄ Bagumbong

Tala ⇄ Deparo

Fare: ₱20 – ₱40

📍 SOUTH CALOOCAN — TRICYCLE TERMINALS
5) Sangandaan Tricycle Terminal

Routes:

Sangandaan ⇄ Grace Park

Sangandaan ⇄ Monumento

Sangandaan ⇄ 5th Ave

Fare: ₱15 – ₱30

6) Grace Park Tricycle Terminal

Routes:

Grace Park ⇄ 10th Ave

Grace Park ⇄ 5th Ave

Grace Park ⇄ Rizal Ave

Fare: ₱15 – ₱25

7) 5th Avenue Tricycle Terminal

Routes:

5th Ave ⇄ A. Mabini

5th Ave ⇄ Sangandaan

5th Ave ⇄ Grace Park

Fare: ₱15 – ₱30
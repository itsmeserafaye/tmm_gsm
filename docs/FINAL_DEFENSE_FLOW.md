# Final Defense Demo Flow (Recommended Order)

This guide gives you a clear, step-by-step **demo + narrative flow** for presenting the TMM system in your final defense.

The goal is to show a **complete LGU operational workflow** while clearly stating the boundary:

- **This system does NOT replace LTO or LTFRB.**
- It uses **LGU-usable operational data** (registry references, routing/assignment, compliance tracking, enforcement, and analytics).
- Any LTO/LTFRB details are treated as **references** (e.g., OR/CR files, franchise reference numbers), not “issuing authority”.

---

## 1) Two Different “Orders” You Need

### A. Encoding / Setup Order (what you encode first to make the system work)
Use this when building demo data.

1. **Module 1 – PUV Database**
   - Encode your “foundation” data:
     - Routes & terminals (assignment context)
     - Operators / cooperatives (ownership/operations context)
     - Vehicles (plate-based registry + basic type/operator linkage)
   - Output: every later module can reliably reference `plate_number`, `route`, and `terminal`.

2. **Module 4 – Vehicle Inspection & Registration (LGU compliance)**
   - Schedule inspection, verify documents, record results, issue inspection certificate reference.
   - Output: vehicles get LGU compliance state (`inspection_status`, certificate ref).

3. **Module 2 – Franchise Management (LGU workflow)**
   - Encode franchise applications and LGU endorsement/compliance decisions.
   - Output: the city has a trackable internal workflow (not LTFRB franchising).

4. **Module 3 – Traffic Violation & Ticketing**
   - Issue violations, validate, settle payments (role-based).
   - Output: enforcement + compliance signals that can support analytics.

5. **Module 5 – Parking & Terminal Management (optional if in scope)**
   - Parking areas, parking payments, enforcement analytics.
   - Output: additional operational signals (can be used as demand proxies if no ridership feeds exist).

### B. Presentation Order (what you show first to last in your defense)
Use this as your “storyline”. This is what panels understand best.

1. **Dashboard – Problem → AI Solution → Action**
2. **Module 1 – Data Foundation (why the system can forecast at all)**
3. **Module 4 – Compliance & Inspection (LGU-level “registration”)**
4. **Module 2 – Franchise workflow (LGU endorsement tracking)**
5. **Module 3 – Enforcement & Payment workflow**
6. **Module 5 – Parking & terminal ops (optional / extension)**
7. **Security / RBAC – Show roles (Encoder, Inspector, Treasurer, ParkingStaff, Viewer)**

---

## 2) Suggested Final Defense Script (Talk Track + Click Path)

### Step 1 — Dashboard (Open First)
**Purpose:** Prove the system answers the stated problem: predicting when/where demand will spike.

**Talk Track (what to say):**
- “The problem is over- or under-deployment during peak hours due to no forecasting system.”
- “This dashboard provides predictive analytics using historical operational logs plus weather and public events.”
- “The output is decision support: hotspots + recommended actions.”

**What to show (click/scroll):**
- **Forecast chart** (next 24 hours)
- **Hotspots (Next 6 Hours)** (where demand might spike)
- **Recommended Actions** (what to do)
- **Forecast Readiness / Accuracy target** (objective: ≥ 80%)

**Boundary statement (important):**
- “We are not issuing registrations or franchises. We only track LGU operational data and compliance, referencing external documents when needed.”

---

### Step 2 — Module 1 (PUV Database) = Your “Single Source of Reference”
**Purpose:** Explain that forecasting is only possible if the city has a consistent operational registry.

**What to say:**
- “We store what LGU needs for operations: plate, vehicle type, operator/cooperative references, route assignment, and status.”
- “We don’t replicate LTO/LTFRB authority; we store reference numbers and uploaded documents as needed.”

**What to demo (order):**
1. **Routes & Terminal Assignment**
   - Show route list and terminal assignment concept (“authorized units per route/terminal context”).
2. **Operator & Franchise Validation**
   - Show operator/cooperative references.
3. **Vehicle & Ownership Registry**
   - Show a sample vehicle record by plate, its route/operator link, and statuses.

**Key defense point:**
- “Module 1 is the foundation: every other module references the plate and operational assignment.”

---

### Step 3 — Module 4 (Vehicle Inspection & Registration)
**Purpose:** Clarify what “registration” means in your scope.

**What to say (very clear):**
- “Registration here means LGU compliance registration after inspection.”
- “We do NOT register the vehicle for LTO. We only track whether it passed LGU inspection and issue an inspection certificate/reference.”

**What to demo (order):**
1. **Schedule Inspection**
   - Choose a plate from Module 1 and schedule date/time.
2. **CR/OR Verification**
   - Mark required docs as verified/not verified.
3. **Inspection Execution**
   - Record results and status.
4. **Certificate Issuance**
   - Show certificate queue/issued count, and the certificate reference attached to a vehicle.

**Key defense point:**
- “This supports safety/compliance monitoring without duplicating national agency responsibilities.”

---

### Step 4 — Module 2 (Franchise Management)
**Purpose:** Show LGU-side workflow tracking, not LTFRB franchising.

**What to say:**
- “This module tracks the LGU endorsement and compliance workflow for franchise-related applications.”
- “It supports monitoring, documentation, and decision traceability.”

**What to demo (order):**
1. **Application intake** (create/track an application)
2. **Validation & Endorsement** (review and status transitions)
3. **Renewals & Monitoring** (show reporting/renewal tracking)

**Key defense point:**
- “The system supports governance: audit trail + consistency + route capacity awareness (LGU planning).”

---

### Step 5 — Module 3 (Traffic Violation & Ticketing)
**Purpose:** Prove the system closes the loop: enforcement affects compliance and planning.

**What to say:**
- “Violations and payments are enforcement signals that can inform policy and deployment decisions.”
- “Role-based access ensures separation of duties.”

**What to demo (order):**
1. **Issue ticket** (Inspector)
2. **Validate / Settle** (Treasurer / authorized roles)
3. **Analytics/Reporting**
   - Show trends, repeat offenders, summary metrics

---

### Step 6 — Module 5 (Parking & Terminal Management) (Optional / Extension)
**Purpose:** This is an operational extension—use it if it’s part of your scope and panel time allows.

**What to say:**
- “This module manages parking operations and produces additional activity logs that can support demand signals.”

**What to demo (order):**
1. Terminal management (if used for parking/terminal ops)
2. Parking areas
3. Transactions & enforcement analytics

---

### Step 7 — Security / RBAC (Very Important for Defense)
**Purpose:** Show professionalism: separation of duties and least privilege.

**What to say:**
- “We enforce access by role and permission at the menu and page/API level.”

**Quick demo suggestion:**
- Log in as each role (or show screenshots) and highlight:
  - **Encoder:** Module 1 encoding only
  - **Inspector:** inspection/ticket issuance only
  - **Treasurer:** settling/payments only
  - **ParkingStaff:** parking-only
  - **Viewer:** view-only across modules

---

## 3) Practical Demo Dataset Checklist (So You Don’t Get Stuck During Demo)

Before you start your defense, ensure these exist:

- At least **2 routes** and **2 terminals**
- At least **3 vehicles** (different types, different operators)
- At least **1 inspection schedule** and **1 completed inspection** (for certificate tracking)
- At least **1 franchise application** with a visible status change
- At least **1 ticket** and **1 settled payment**
- Optional: at least **5 demand observations** so the dashboard doesn’t look empty

---

## 4) What to Emphasize to the Panel (Short Version)

- **Problem:** no predictive system for demand spikes → over/under deployment.
- **Solution:** forecasting + drivers (weather/events/trend) + actionable recommendations.
- **Objectives:** measurable accuracy target and operational improvements.
- **Scope boundary:** LGU operational decision-support; not LTO/LTFRB authority replacement.
- **Governance:** role-based access and auditability.


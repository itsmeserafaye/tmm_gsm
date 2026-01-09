Module 1: PUV Database

Overview

The PUV (Public Utility Vehicle) Database serves as the LGU’s registry of all active, pending, and deactivated PUVs operating within the city.
While registration itself is under LTO/LTFRB jurisdiction, the city maintains this database to support local monitoring, franchise validation, route management, and inspection scheduling.

This ensures compliance with local ordinances (e.g., traffic route limitation, COOP validation, and terminal assignment).


Process Flow (Realistic LGU-Aligned Steps)

1. Data Intake
Operator or cooperative submits copy of OR/CR and Franchise permit from LTFRB (uploaded via system or at the office).
System records metadata (vehicle type, plate number, operator name, franchise number, route ID).
Staff verifies the documents for authenticity and consistency with LTFRB franchise list.

2. Record Creation
Once verified, an entry is created in the LGU’s PUV Database.
Vehicle is linked to a cooperative (COOP) and assigned a validated route as defined by the Local Public Transport Route Plan (LPTRP).

3. Cross-System Validation
The system performs API calls to the Franchise Management Module to confirm if the franchise ID exists and is valid.
If found, the franchise information (operator name, validity period, COOP name) is synchronized.

4. Route and Terminal Assignment
The database links each PUV record to the Parking & Terminal Management Module via API to check which terminals or sections the vehicle is allowed to operate in.
If the route is congested, predictive analytics from the PUV Demand Forecasting Engine may flag overcapacity.

5. Ongoing Updates
When a vehicle is sold, the new operator’s deed of sale is uploaded, and ownership transfer is reflected in the record.
Expired franchises are flagged and restricted from inspection scheduling or parking assignment.


Submodules

Submodules
Vehicle & Ownership Registry
 Manages all PUV master records, including vehicle identification details, OR/CR document storage, ownership information, and vehicle status (active, suspended, deactivated). This submodule also handles ownership transfers and historical record tracking.


Operator, Cooperative & Franchise Validation
 Maintains operator and cooperative profiles and validates franchise references through internal records or API-based cross-checks with the Franchise Management module. It ensures that only LGU-verified cooperatives and valid franchises are linked to vehicles.


Route & Terminal Assignment Management
 Handles assignment of vehicles to LPTRP-approved routes and authorized terminals. It enforces route capacity limits, validates terminal eligibility, and flags overcapacity or unauthorized assignments for review.



Data Entities & Key Fields

Vehicle Table

vehicle_id, plate_number, vehicle_type, or_number, cr_number, coop_id, franchise_id, route_id, status

Operator Table

operator_id, full_name, contact_info, coop_id

COOP Table

coop_id, coop_name, address, chairperson_name, lgu_approval_number

Route Table (from LPTRP integration)

route_id, route_name, route_code, max_vehicle_limit, status


System Design

Frontend:
Web-based admin panel for LGU Transport Office (view, filter, update PUV records).
Upload module for OR/CR and deed of sale (PDF/JPEG).
Route visualization panel showing capacity per route (via LPTRP data).

Backend:
RESTful API service layer for data retrieval and synchronization.
Role-based access control (RBAC):
- **Admin**: Full access (Create/Edit Vehicle, Upload Docs, Transfer Ownership, Manage Routes).
- **Encoder**: Create/Edit Vehicle, Upload Docs.
- **Inspector**: Read-only access to registry; can view details but cannot modify records.
Validation scripts that cross-check franchise status and COOP approval.

Database:
MySQL/PostgreSQL for structured data.
File storage (AWS S3, local drive, or LGU-hosted equivalent) for uploaded documents.


User Interface Features
- **Real-time Search & Filtering**: Instant search by plate, operator, or route with debounce optimization.
- **Dynamic Data Tables**: Sortable columns, server-side pagination, and responsive layout.
- **Ownership Transfer**: Dedicated workflow for transferring vehicle ownership with deed reference (Admin only).
- **Document Management**: Integrated viewer for uploaded OR/CR and franchise documents.



Descriptive API Interactions

1. Franchise Management Module (same subsystem)

Purpose: Validate franchise details and operator legitimacy.

Process:
PUV Database sends a GET /franchise/{franchise_id} request.
Franchise Management API returns operator name, coop, validity date, and status.
If expired or invalid, system sets vehicle_status = "Suspended".

2. Parking & Terminal Management Module

Purpose: Identify terminals and sections where each PUV is assigned.

Process:
GET /terminals/route/{route_id} → returns list of terminals for the route.
PUV Database matches terminal assignment to ensure no overlap.

3. Revenue Collection & Treasury Services

Purpose: Synchronize local fees (e.g., inspection, parking fees).

Process:
POST /fees/record when a vehicle is enrolled or renewed.
Returns payment confirmation for treasury reconciliation.

4. Citizen Information & Engagement (for complaints or feedback)

Purpose: Allow passengers to report identified PUVs by plate number.

Process:
GET /puv/search?plate=XXX allows citizens to verify if a PUV is registered and its cooperative.


Business Rules & Validation Logic
Only LGU-verified franchises are stored.
Franchise must match LTFRB-issued reference (through validation data).
One route per vehicle (unless ordinance allows multi-route operation).
Vehicle with expired franchise cannot be assigned a terminal or inspection schedule.
COOP without LGU approval cannot register vehicles.






Scope & Limitations

Scope:
LGU-level monitoring and record-keeping of PUVs operating under the city’s jurisdiction.
Document validation and synchronization with internal systems (Franchise, Parking, etc.).

Limitations:
Does not perform LTO registration or encode OR/CR data manually — only uploads scanned copies.
Does not modify LTFRB or LTO records — read-only cross-checking only.
COOP processes depend on ordinance data manually inputted by LGU staff.

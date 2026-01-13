# Integration Specifications for Transport & Mobility Management (TMM)

This document outlines the data exchange requirements between the TMM System and external government subsystems. Use this guide to coordinate API contracts with the respective system leaders.

---

## 1. Permits & Licensing Management System
**Purpose:** To process the final approval of franchises and transport permits after TMM endorsement.

### A. Franchise Endorsement (Sending Data)
*   **Trigger Module:** `Franchise Management` > `Application Review`
*   **Trigger Event:** When the Franchise Officer clicks "Endorse for Approval".
*   **Data to SEND (TMM → Permits):**
    ```json
    {
      "transaction_type": "FRANCHISE_ENDORSEMENT",
      "reference_no": "TMM-APP-2024-001",
      "applicant": {
        "full_name": "Juan Dela Cruz",
        "address": "123 Rizal St, Brgy 1",
        "contact_no": "09123456789",
        "type": "Individual" // or "Cooperative"
      },
      "units": [
        {
          "chassis_no": "CH-12345",
          "engine_no": "EN-67890",
          "plate_no": "ABC-123",
          "make": "Toyota",
          "year_model": "2023"
        }
      ],
      "route": {
        "route_code": "R-01",
        "route_name": "City Hall Loop"
      }
    }
    ```

### B. Permit Status Update (Receiving Data)
*   **Receiving Module:** `Franchise Management` > `Franchise List`
*   **Data to RECEIVE (Permits → TMM):**
    ```json
    {
      "reference_no": "TMM-APP-2024-001",
      "permit_details": {
        "permit_number": "MTOP-2024-0500",
        "status": "APPROVED", // or "REJECTED"
        "issued_date": "2024-01-15",
        "expiry_date": "2025-01-15",
        "remarks": "Approved pending payment."
      }
    }
    ```

---

## 2. Revenue Collection & Treasury Services
**Purpose:** To centralize collection of traffic fines, parking fees, and franchise fees.

### A. Order of Payment (Sending Data)
*   **Trigger Module:** 
    1. `Traffic Violation & Ticketing` > `Issue Ticket`
    2. `Parking Management` > `Terminal Fees`
*   **Trigger Event:** When a ticket is issued or a fee is assessed.
*   **Data to SEND (TMM → Treasury):**
    ```json
    {
      "transaction_id": "TICKET-7890",
      "account_code": "402-01-050", // Specific code for Traffic Fines
      "amount": 500.00,
      "description": "Violation: Obstruction of Traffic",
      "payer": {
        "name": "Maria Clara",
        "license_no": "L02-12-123456"
      },
      "due_date": "2024-01-20"
    }
    ```

### B. Payment Confirmation (Receiving Data)
*   **Receiving Module:** `Traffic Violation` > `Settlement`
*   **Data to RECEIVE (Treasury → TMM):**
    ```json
    {
      "transaction_id": "TICKET-7890",
      "payment_status": "PAID",
      "official_receipt_no": "OR-99887766",
      "amount_paid": 500.00,
      "date_paid": "2024-01-16 14:30:00",
      "payment_channel": "Over-the-Counter" // or "GCash", "Online"
    }
    ```

---

## 3. Urban Planning, Zoning & Housing
**Purpose:** To ensure transport routes and terminals comply with the city's Comprehensive Land Use Plan (CLUP).

### A. Route Validation (Sending Data)
*   **Trigger Module:** `PUV Database` > `Route Management`
*   **Trigger Event:** When creating or modifying a transport route (LPTRP).
*   **Data to SEND (TMM → Planning):**
    ```json
    {
      "request_type": "ROUTE_VALIDATION",
      "route_code": "PROPOSED-R-05",
      "path_geometry": {
        "type": "LineString",
        "coordinates": [
          [121.001, 14.501],
          [121.002, 14.502],
          [121.005, 14.505]
        ]
      },
      "terminal_locations": [
        { "lat": 14.501, "lng": 121.001, "type": "Origin" },
        { "lat": 14.505, "lng": 121.005, "type": "Destination" }
      ]
    }
    ```

### B. Zoning Clearance (Receiving Data)
*   **Receiving Module:** `Route Management`
*   **Data to RECEIVE (Planning → TMM):**
    ```json
    {
      "route_code": "PROPOSED-R-05",
      "zoning_status": "COMPLIANT", // or "NON_COMPLIANT"
      "restrictions": [
        "No entry on Rizal St. during 7AM-9AM",
        "Terminal B is within a residential zone (requires special permit)"
      ],
      "approved_capacity": 50 // Max units allowed based on road capacity
    }
    ```

# Integration Specifications for Transport & Mobility Management (TMM)

This document defines the cross-system integrations for our capstone cluster group that involve the TMM system:

- **TMM (Transport & Mobility Management)** ↔ **Permits & Licensing Management (Franchise & Transport Permits)**
- **TMM (Transport & Mobility Management)** ↔ **Revenue Collection & Treasury Services** (Traffic fines and parking/terminal fees)

This follows real LGU practice: the City Transport Office (or equivalent LGU transport unit) evaluates applications and issues endorsements; the Permits & Licensing Office performs final approval/rejection and issues the official permit number (e.g., MTOP / local transport permit). TMM then uses that decision for enforcement, monitoring, and operational controls.

---

## 1) Permits & Licensing Management (Franchise & Transport Permits)

### Purpose of Integration (LGU-aligned)
- **Final issuance lives in Permits:** Permits & Licensing is the issuing authority for LGU permits and must generate the official permit number and final status.
- **Enforcement lives in TMM:** TMM needs the final decision to enable/disable operations (route assignment, terminal/parking access, monitoring, compliance).
- **Audit & transparency:** Both systems need aligned records for renewals, suspensions, and public accountability.

### Integration Scope
- TMM sends **endorsement submissions** (with reviewed/validated details).
- Permits sends **permit decisions and post-issuance status changes**.
- Treasury integration is documented in Section 2.

---

## A) Endorsement Submission (TMM → Permits)

### Trigger (in TMM)
- **Module:** Franchise Management → Application Review / Validation
- **Event:** Officer clicks “Endorse for Approval”

### Purpose
- Create (or update) the permit case in the Permits system so they can continue LGU permitting steps without re-encoding.

### Data to Send (Recommended Minimum)
**Identifiers**
- `transaction_type`
- `reference_no` (TMM tracking / application reference; must be unique)
- `submitted_at`

**Applicant**
- `applicant.type` (Individual / Cooperative)
- `applicant.name`
- `applicant.address`
- `applicant.contact_no` (recommended)

**Route (LPTRP)**
- `route.route_code` (LPTRP code, shared masterlist)
- `route.route_name` (human readable label)

**Units / Vehicles**
- `units[]` list, each with:
  - `plate_no` (if available)
  - `engine_no` and `chassis_no` (if available; many permitting flows require one or both)
  - `make`, `year_model` (optional)

**Endorsement**
- `endorsement.recommended_status` (RECOMMENDED_APPROVAL / RECOMMENDED_REJECTION)
- `endorsement.notes` (findings, conditions)
- `endorsement.endorsed_by` (office + officer identity)
- `endorsement.supporting_documents[]` (document references or attachment ids)

### Example Payload
```json
{
  "transaction_type": "FRANCHISE_ENDORSEMENT",
  "reference_no": "TMM-APP-2026-0001",
  "submitted_at": "2026-01-16T10:30:00+08:00",
  "applicant": {
    "type": "Cooperative",
    "name": "Sample Transport Cooperative",
    "address": "Caloocan City, Metro Manila",
    "contact_no": "09123456789"
  },
  "route": {
    "route_code": "CAL-LPTRP-01",
    "route_name": "Caloocan Main Loop"
  },
  "units": [
    {
      "plate_no": "ABC-1234",
      "engine_no": "EN-67890",
      "chassis_no": "CH-12345",
      "make": "Toyota",
      "year_model": "2023"
    }
  ],
  "endorsement": {
    "recommended_status": "RECOMMENDED_APPROVAL",
    "endorsed_by": {
      "office": "City Transport Office",
      "officer_name": "Franchise Officer",
      "officer_id": "TMM-USER-001"
    },
    "notes": "LPTRP checks passed; documents verified; recommended for issuance under standard LGU permitting steps.",
    "supporting_documents": [
      { "type": "LTFRB_DECISION_OR_ORDER", "ref": "TMM-DOC-001" },
      { "type": "COOP_REGISTRATION", "ref": "TMM-DOC-002" }
    ]
  }
}
```

### Expected Immediate Response (Permits → TMM)
```json
{
  "ok": true,
  "reference_no": "TMM-APP-2026-0001",
  "permits_case_id": "PERMITS-CASE-2026-01001",
  "status": "RECEIVED"
}
```

---

## B) Permit Decision / Issuance Update (Permits → TMM)

### Trigger (in Permits)
- **Module:** Franchise & Transport Permits → Final Approval / Issuance
- **Event:** Case status becomes APPROVED/REJECTED, or moved into a meaningful stage (FOR_PAYMENT, ON_HOLD)

### Purpose
- Update TMM so it can:
  - activate/allow operations for approved permits
  - block operations for rejected/held/cancelled permits
  - show accurate status in LGU dashboards and compliance monitoring

### Data to Receive (Recommended Minimum)
- `reference_no` (must match what TMM originally sent)
- `permit.status` (APPROVED / REJECTED / FOR_PAYMENT / ON_HOLD / CANCELLED)
- `permit.permit_number` (required if approved)
- `permit.issued_date`, `permit.expiry_date` (required if approved)
- `permit.remarks` (reason/conditions/next steps)

### Example Payload
```json
{
  "reference_no": "TMM-APP-2026-0001",
  "permit": {
    "permit_number": "MTOP-2026-0500",
    "status": "APPROVED",
    "issued_date": "2026-01-20",
    "expiry_date": "2027-01-20",
    "remarks": "Approved. Release subject to standard LGU requirements and fees."
  }
}
```

---

## C) Post-Issuance Status Changes (Permits → TMM)

### Trigger (in Permits)
- Any change after issuance:
  - SUSPENDED / REVOKED / CANCELLED
  - RENEWED / EXPIRED
  - ON_HOLD (administrative hold due to non-compliance)

### Purpose
- Keep enforcement accurate in TMM (route assignment, terminal/parking access, compliance tracking).

### Data to Receive (Recommended Minimum)
- Identify the record using either:
  - `permit.permit_number`, or
  - `reference_no` (if permit not issued yet)
- `change.type`
- `change.effective_date`
- `change.reason`

### Example Payload
```json
{
  "permit": { "permit_number": "MTOP-2026-0500" },
  "change": {
    "type": "SUSPENDED",
    "effective_date": "2026-06-01",
    "reason": "Suspended due to repeated violations and unresolved compliance case."
  }
}
```

---

## What to Ask the Permits & Licensing System Leader (Integration Checklist)

### 1) Identifiers & Mapping
- What are their primary identifiers: `permits_case_id`, `permit_number`, or both?
- Will they always include and preserve our `reference_no` in every update?
- What is the agreed LPTRP key: `route_code` format and uniqueness rules?
- Vehicle identification requirements: plate only vs engine/chassis required.

### 2) Status Model (Must be agreed first)
- Provide the definitive list of statuses they will send (e.g., RECEIVED, UNDER_REVIEW, FOR_PAYMENT, APPROVED, REJECTED, ON_HOLD, CANCELLED, SUSPENDED, REVOKED, EXPIRED, RENEWED).
- For each status: which ones should TMM treat as “allowed to operate”?

### 3) Data Transfer Method
- Do they support webhook/callbacks to TMM for updates?
  - If yes: what is the callback URL contract and authentication?
  - If no: provide polling endpoints (e.g., `GET /permits/cases/{reference_no}`) and update frequency expectations.

### 4) Auth, Security, and Audit
- Auth method: API key / bearer tokens / HMAC signatures / IP allowlisting.
- Logging requirements: do they need officer id, office code, timestamps for audit trails?
- Data privacy constraints: which PII fields can be transmitted and stored?

### 5) Documents & Attachments
- How should documents be transferred: upload endpoint vs document reference IDs?
- Allowed file types and size limits.
- Whether they require storing copies of documents or only references.

### 6) Validation Rules (So we don’t block each other)
- Mandatory fields to create a case.
- Whether endorsements can be accepted if some unit identifiers are pending.
- Whether proof of inspection is required before approval/issuance.

### 7) Error Handling & Idempotency
- Can we safely retry endorsement submission (idempotent by `reference_no`)?
- Error codes/messages to handle (invalid_route_code, missing_vehicle_id, etc.).

### 8) Testing & Environment
- Staging/test base URL and sample API credentials.
- Sample test cases: 1 approved, 1 rejected, 1 on-hold/for-payment.

---

## Notes for Real City LGU Practice
- Transport endorsement and permit issuance are commonly separated across LGU offices; this integration supports that real workflow.
- Payment steps are typically handled by Treasury/Revenue; Section 2 documents the ticket fines + parking fee integration patterns.

---

## 2) Revenue Collection & Treasury Services

### Purpose of Integration (LGU-aligned)
- **Centralize collections:** Treasury is the official collector/recorder of LGU payments and issues official receipts (OR).
- **Close the loop for enforcement:** TMM must know when a fine/fee is paid to mark cases as settled and prevent duplicate billing.
- **Reconciliation & reporting:** Treasury needs consistent transaction identifiers and standardized codes for accounting and daily remittance reports.

### Integration Scope
- **Traffic fines** (Ticketing): TMM provides the assessed fine (order of payment); Treasury returns payment confirmation with OR.
- **Parking/terminal fees** (Parking Management): Treasury may either (a) consume TMM’s paid transactions for reconciliation, or (b) accept a full cashiering workflow if implemented later. In this capstone, we support **reconciliation export** of paid parking transactions.

### Authentication
- All Treasury integration endpoints support an integration header:
  - `X-Integration-Key: <shared_secret>`
- The shared secret is configured in TMM as: `TMM_TREASURY_INTEGRATION_KEY`

---

## A) Traffic Fine “Order of Payment” (TMM → Treasury)

### Trigger (in TMM)
- **Module:** Traffic Violation & Ticketing → Issue Ticket / Validate Ticket
- **Event:** Ticket is created/validated and becomes ready for assessment/payment.

### Purpose
- Lets Treasury create a cashiering record without re-encoding the fine details.

### Data to Send / Provide (Recommended Minimum)
- `transaction_type` (TRAFFIC_FINE)
- `transaction_id` (ticket number)
- `account_code` (LGU accounting/treasury mapping code; from violation type mapping if available)
- `amount` (fine)
- `description` (violation description)
- `payer` (at minimum: vehicle plate; optionally driver name if captured)
- `due_date`

### Example Response Payload (TMM provides to Treasury)
```json
{
  "transaction_type": "TRAFFIC_FINE",
  "transaction_id": "TCK-2026-0001",
  "account_code": "STS-IP",
  "amount": 1000.0,
  "description": "Violation: Illegal Parking",
  "payer": {
    "name": "Driver (Unknown)",
    "vehicle_plate": "ABC-1234"
  },
  "due_date": "2026-01-23"
}
```

### Endpoint (Implemented)
- `GET /tmm/admin/api/integration/treasury/order_of_payment.php?kind=ticket&ticket_number=TCK-2026-0001`

---

## B) Payment Confirmation (Treasury → TMM)

### Trigger (in Treasury)
- **Event:** Payment is completed and OR is issued (Over-the-Counter, Online, etc.).

### Purpose
- Marks the ticket as **Settled** in TMM and stores the official receipt number for audit and enforcement.

### Data to Receive (Recommended Minimum)
- `transaction_id` (ticket number)
- `payment_status` (PAID)
- `official_receipt_no` (OR number)
- `amount_paid`
- `date_paid`
- `payment_channel` (optional but recommended)
- `external_payment_id` (optional; treasury reference)

### Example Payload (Treasury sends to TMM)
```json
{
  "kind": "ticket",
  "transaction_id": "TCK-2026-0001",
  "payment_status": "PAID",
  "official_receipt_no": "OR-2026-000123",
  "amount_paid": 1000.0,
  "date_paid": "2026-01-16 14:30:00",
  "payment_channel": "Over-the-Counter",
  "external_payment_id": "TRSY-INV-889900"
}
```

### Endpoint (Implemented)
- `POST /tmm/admin/api/integration/treasury/payment_confirmation.php`

---

## C) Parking/Terminal Fee Reconciliation Export (TMM → Treasury)

### Trigger (in TMM)
- **Module:** Parking & Terminal Management
- **Event:** Payments are recorded as “Paid” in TMM.

### Purpose
- Provides Treasury a list of paid parking/terminal fee transactions for reconciliation and reporting.
- Avoids double encoding while preserving audit fields (receipt reference, channel).

### Data to Provide (Recommended Minimum)
- `transaction_type` (PARKING_FEE)
- `transaction_id` (parking transaction id)
- `amount`
- `description` (charge type / label)
- `payer.vehicle_plate`
- `receipt_ref` (OR reference if available)
- `payment_channel` (optional)
- `date_paid`

### Endpoint (Implemented)
- `GET /tmm/admin/api/integration/treasury/parking_payments.php?unexported=1&limit=200&from=2026-01-01`

### Mark as Exported (Optional but Recommended)
- After Treasury ingests records, it can mark them exported to avoid duplicates:
  - `POST /tmm/admin/api/integration/treasury/parking_mark_exported.php`
```json
{ "ids": [1, 2, 3] }
```

---

## What to Ask the Treasury System Leader (Integration Checklist)

### 1) Accounting Codes & Mapping
- What is the required **account_code** format for traffic fines and parking fees?
- Do they want TMM to send a mapping code (like `STS-IP`) or do they maintain the mapping on their side?

### 2) Payment Status & Partial Payments
- Exact `payment_status` values they will send (`PAID`, `FAILED`, `CANCELLED`, `PARTIAL`).
- Whether partial payment is allowed (and how to represent remaining balance).

### 3) Official Receipt Requirements
- OR format and uniqueness rules (citywide).
- Whether OR is issued per transaction or can cover multiple transactions.

### 4) Data Transfer Method
- Will Treasury push confirmations via webhook (recommended) or should TMM poll?
- Update frequency and SLA for confirmations.

### 5) Security & Audit
- Authentication method (shared integration key, token, IP allowlist).
- Audit fields required: cashier id, office code, timestamp, terminal id, etc.

### 6) Reconciliation Rules
- If Treasury ingests “Paid” parking/terminal records, do they require an “exported” acknowledgement step?
- How do they want corrections handled (voids/reversals)?

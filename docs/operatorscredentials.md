✅ OPERATOR PORTAL
REGISTRATION → VERIFICATION → APPROVAL FLOW
I’ll show this in 3 layers:

Operator Portal (what the operator sees)

Admin / LGU Portal (what staff does)

System rules (what connects them)

🔹 1️⃣ OPERATOR REGISTRATION (OPERATOR PORTAL)
🧍 Operator Side
Registration Form (Credentials only):

Email (username)

Password

Confirm password

Operator type (Individual / Coop / Corp)

Operator name

Contact number

CAPTCHA

Agree to Terms

🖥️ System Action
Create operator_account

Create operator_profile

Status = PENDING

Access = LIMITED

✅ Result
✔ Operator can log in
❌ Cannot apply for franchise
❌ Cannot register vehicles

🔁 FLOW (TEXT SKETCH)
Operator
   ↓
Register Account
   ↓
System creates account
   ↓
Status = PENDING
   ↓
Limited Access Dashboard
🔹 2️⃣ DOCUMENT VERIFICATION (OPERATOR + ADMIN)
🧍 Operator Side
After first login:

Sees banner:

“Please complete your profile and upload required documents”

Uploads documents based on type:

Individual → Valid ID

Coop → CDA + Board Resolution

Corp → SEC + Authority

Clicks Submit for Verification

🧑‍💼 Admin / LGU Side
Admin Portal → Operator Verification Module

Admin can:

View operator list (Status = Pending)

Open operator profile

View uploaded documents

Mark documents as:

✔ Valid

❌ Invalid (with remarks)

🔁 FLOW (TEXT SKETCH)
Operator uploads documents
   ↓
Submit for Verification
   ↓
Admin reviews documents
   ↓
Approve OR Reject
🔹 3️⃣ APPROVAL & ACTIVATION (ADMIN SIDE)
🧑‍💼 Admin Action
If documents are valid:

Click Approve Operator

System updates:

Operator Status = APPROVED

Account Access = FULL

If invalid:

Status stays PENDING

Admin adds remarks

Operator is notified

🖥️ System Rules
✔ Only APPROVED operators can:

Apply for franchise

Register vehicles

Link vehicles

Request inspections

🔁 FINAL FLOW (COMPLETE SKETCH)
Operator Registration
        ↓
Status: PENDING
        ↓
Upload Documents
        ↓
Admin Verification
     ↓        ↓
 Approved   Rejected
     ↓
Status: APPROVED
     ↓
Full System Access
🔗 HOW THIS CONNECTS TO YOUR ADMIN MODULES
🔐 Admin Portal Modules Involved
Operator Management

View operators

Verify documents

Approve / deactivate

Franchise Management

Only visible if operator = APPROVED

Vehicle & Inspection

Locked until approval

🧠 IMPORTANT SYSTEM CHECK (VERY DEFENSIBLE)
Before any sensitive action:

IF operator.status != APPROVED
THEN block action
This shows:

Security

Governance

Proper LGU workflow
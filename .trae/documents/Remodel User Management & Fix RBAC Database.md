I will completely remodel the User Management module to be robust, secure, and UI-enhanced.

1.  **Database Repair (First Step)**:
    *   Create a utility script `admin/tools/fix_rbac_schema.php` to automatically clean up duplicate roles, fix missing keys, and ensure the database strictly follows the RBAC model. You will run this once.

2.  **Backend API Overhaul**:
    *   **List Users**: Rewrite `admin/api/settings/rbac_users.php` to efficiently fetch users with their roles (preventing duplicates).
    *   **Create/Update**: Rewrite `rbac_user_create.php` and `rbac_user_update.php` to use **Database Transactions**. This ensures that creating a user and assigning roles happens perfectly or not at all (no partial data).

3.  **Frontend UI Remodel (`accounts.php`)**:
    *   **Modern Design**: Replace the current layout with a professional "Staff Directory" table including status badges and role tags.
    *   **Better Forms**: Create a unified "Add/Edit User" modal with proper validation and a clear Role Selector (checkboxes).
    *   **Feedback**: Add clear success/error toasts.

I will implement these changes now.
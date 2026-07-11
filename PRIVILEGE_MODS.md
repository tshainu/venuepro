# Hall Manager Privilege Modifications

## Database Changes
- Added `hall_id` column to the `users` table.
- Added a foreign key constraint linking `users.hall_id` to `halls.id`.

## Core Logic Changes
- **Auth.php**: Updated to fetch and store `hall_id` and `hall_name` in the session upon login and session re-hydration.
- **Access Restrictions**:
    - **Branches**: Blocked access for Hall Managers in `modules/branches/index.php`.
    - **Settings**: Blocked access for Hall Managers in `modules/settings/index.php`.
    - **Staff Management**: Restricted `modules/users/index.php`, `create.php`, and `edit.php` to exclude Hall Managers.

## Data Filtering
- **Bookings**: Added filtering in `modules/bookings/index.php` to show only bookings associated with the Hall Manager's assigned `hall_id`.
- **Reports**: Updated `modules/reports/index.php` to filter revenue, bookings, and outstanding reports by the Hall Manager's `hall_id`.
- **Payments**: Updated `modules/payments/index.php` to filter transactions and pending balances by the Hall Manager's `hall_id`.

## UI Changes
- **Sidebar**: Hidden "Branches", "Settings", "Manage Staff", and "Administration" sections for Hall Managers.
- **User Management**: Added a "Assigned Hall" dropdown in `create.php` and `edit.php` that appears only when the "Hall Manager" role is selected.

# Masseuse User Management System - Setup Complete

## Database Migration Completed ✓

The `masseuses` table has been successfully updated with the following columns:

- `user_id` (INT, NULL) - Links to the users table
- `mobile` (VARCHAR(20), NULL) - Mobile number for the masseuse
- Foreign key constraint: `fk_masseuse_user` (ON DELETE CASCADE)
- Index: `idx_masseuse_user_id` for better query performance

## How the System Works

### For Administrators:

1. **Creating a Masseuse**:

   - Go to Admin Panel → Masseuses → Add Masseuse
   - Fill in all fields including:
     - Name
     - Mobile (required for login)
     - Bio
     - Specialties
     - Image URL
     - Password (required)
     - Confirm Password (must match)
   - System automatically creates:
     - A user account with role `'masseuse'`
     - A masseuse profile linked to that user account

2. **Editing a Masseuse**:

   - Update any field including mobile number
   - Password fields are optional (leave blank to keep existing password)
   - Both user account and masseuse profile are updated

3. **Deleting a Masseuse**:
   - Deletes both the masseuse profile and the associated user account
   - All data is removed cleanly

### For Masseuses:

1. **Logging In**:

   - Use mobile number and password set by admin
   - Access the admin dashboard at `/admin/`

2. **Dashboard Access**:

   - See only their own bookings
   - View available services
   - Limited stats (their bookings only)

3. **Schedule Management**:
   - Direct access to their own availability schedule
   - Can set availability for the next 10 days
   - Cannot view or edit other masseuses' schedules

### Access Control Summary:

| Feature      | Admin          | Masseuse        | Customer |
| ------------ | -------------- | --------------- | -------- |
| Dashboard    | ✓ All data     | ✓ Own data only | ✗        |
| Bookings     | ✓ All          | ✓ Own only      | ✗        |
| Services     | ✓ Manage       | ✓ View only     | ✗        |
| Masseuses    | ✓ Manage       | ✗               | ✗        |
| Users        | ✓ Manage       | ✗               | ✗        |
| Own Schedule | ✓ Any masseuse | ✓ Own only      | ✗        |
| Passwords    | ✓ View all     | ✗               | ✗        |

## Files Modified:

1. `includes/functions.php` - Added helper functions:

   - `isMasseuse()` - Check if user is a masseuse
   - `getMasseuseIdByUserId()` - Get masseuse ID from user ID

2. `admin/masseuses.php` - Enhanced with:

   - Password fields (required for add, optional for edit)
   - Mobile field
   - User account creation/update logic
   - Cascading delete for user accounts

3. `admin/index.php` - Updated access control:

   - Allows masseuse access
   - Filters data based on role

4. `admin/masseuse_schedule.php` - Updated access control:

   - Admins can access any masseuse schedule
   - Masseuses can only access their own schedule

5. `database/migrate_add_user_id.php` - Migration script
6. `database/add_user_id_to_masseuses.sql` - SQL migration file

## Security Notes:

⚠️ **Important**: Passwords are currently stored in plain text as per previous development instructions.
For production use, implement proper password hashing using `password_hash()` and `password_verify()`.

## Testing the System:

1. Create a test masseuse through the admin panel
2. Log out from admin account
3. Log in using the masseuse's mobile number and password
4. Verify access to dashboard and own schedule
5. Verify cannot access other masseuses' data or admin-only pages

# Database Schema Verification

## Issue Found: Missing `userid` Field

**Problem**: Code tries to save `userid` to `local_aicc_hacp_persistent_sessions` table, but schema doesn't have this field.

**Location**: 
- Code: `local/aicc_export/content_launcher.php` line 107
- Schema: `local/aicc_hacp/db/install.xml` - `persistent_sessions` table

## Fix Applied

Added `userid` field to `local_aicc_hacp_persistent_sessions` table in the XML schema.

## Database Update Required

Since the database structure changed, you need to update the database:

### On LMS-1:

```bash
# SSH into the Docker container
docker exec -it apache_8 bash
cd /var/www/html/lms-one

# Run Moodle upgrade
php admin/cli/upgrade.php --non-interactive
```

Or via MySQL directly:
```bash
# Access MySQL
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one

# Add the missing field
ALTER TABLE mdl_local_aicc_hacp_persistent_sessions ADD COLUMN userid INT(10) AFTER student_email;

# Exit MySQL
exit
```

## Complete Database Schema

### aicc_export Plugin

**Table: `local_aicc_export_sessions`**
- id (int, primary key)
- session_id (char 36)
- token_nonce (char 36)
- scormid (int 10)
- scoid (int 10)
- courseid (int 10)
- au (char 255)
- status (char 20)
- created_at (int 10)
- expires_at (int 10)
- last_activity_at (int 10)
- origin (char 255, nullable)
- student_id (char 100, nullable)

### aicc_hacp Plugin

**Table: `local_aicc_hacp_logs`**
- id (int, primary key)
- session_id (char 36)
- command (char 255)
- request_body (text)
- signature_valid (int 1)
- remote_ip (char 45)
- user_agent (char 255)
- parsed_data_json (text)
- result_code (int 3)
- result_message (char 255)
- created_at (int 10)

**Table: `local_aicc_hacp_usermap`**
- id (int, primary key)
- external_id (char 100)
- userid (int 10)
- trusted_origin (char 100)
- created_at (int 10)
- updated_at (int 10)

**Table: `local_aicc_hacp_sessions`**
- id (int, primary key)
- session_id (char 100, unique)
- scormid (int 10)
- scoid (int 10)
- student_id (char 100)
- origin (char 255)
- **persistent_session_id (int 10, nullable)** ✓
- status (char 20)
- created_at (int 10)
- last_activity_at (int 10)
- expires_at (int 10)

**Table: `local_aicc_hacp_student_state`**
- id (int, primary key)
- student_id (char 100)
- scormid (int 10)
- scoid (int 10)
- state_data (text)
- lesson_status (char 50)
- lesson_location (char 255)
- score (char 10)
- session_time (char 20)
- created_at (int 10)
- updated_at (int 10)

**Table: `local_aicc_hacp_persistent_sessions`**
- id (int, primary key)
- student_id (char 100)
- student_name (char 255)
- student_email (char 255)
- **userid (int 10, nullable)** ✓ ADDED
- scormid (int 10)
- scoid (int 10)
- origin (char 255)
- status (char 20)
- created_at (int 10)
- last_access_at (int 10)
- access_count (int 10)

## Verification Commands

### Check Tables Exist
```sql
SHOW TABLES LIKE 'local_aicc%';
```

### Check Structure
```sql
DESCRIBE mdl_local_aicc_hacp_persistent_sessions;
```

### Expected Output
Should show `userid` field in the persistent_sessions table.

## Files Updated

1. `local/aicc_hacp/db/install.xml` - Added `userid` field to persistent_sessions table
2. `local/DB_VERIFICATION.md` - This document

## Next Steps

1. Run the database upgrade (see commands above)
2. Verify the field was added successfully
3. Test the external user creation flow


# Quick Start Guide - AICC Cross-LMS Sharing

## TL;DR

### 1. Install
```bash
docker exec -it apache_8 bash
cd /var/www/html/lms-one
php admin/cli/upgrade.php --non-interactive
```

### 2. Test
```bash
php local/run_all_tests.php
```

### 3. Use
1. Create course with SCORM on LMS-1
2. Export: Visit course → Export AICC package
3. Import on LMS-2
4. Students access content (hosted on LMS-1)

## Complete Installation

### Step 1: Access Container
```bash
docker exec -it apache_8 bash
cd /var/www/html/lms-one
```

### Step 2: Run Upgrade
```bash
php admin/cli/upgrade.php --non-interactive
```

Expected output:
```
Notifying plugins about the upgrade...
Your moodle files have been updated successfully.
```

### Step 3: Configure Settings

#### Enable AICC Export
- Site administration → Plugins → Local plugins → AICC Export
- Check "Enable AICC Export"
- Save changes

#### Enable AICC HACP
- Site administration → Plugins → Local plugins → AICC HACP  
- Check "Enable AICC HACP"
- Set Log Level to "debug" (for testing)
- Uncheck "Require HTTPS" (for local testing)
- Save changes

### Step 4: Run Tests

```bash
# Comprehensive test suite
php local/run_all_tests.php

# Individual tests
php local/aicc_export/tests/test_export.php
php local/aicc_hacp/tests/test_token.php
php local/aicc_hacp/tests/test_external_users.php
php local/aicc_hacp/tests/test_full_workflow.php
```

## Usage

### Export a Course

1. Go to any course with SCORM activity
2. Navigate to: `/local/aicc_export/export.php?courseid=X`
3. Download the ZIP file
4. The package contains AICC descriptor files

### Import on LMS-2

1. Go to course on LMS-2
2. Add activity → SCORM
3. Upload the ZIP file you downloaded
4. Configure and save
5. The content URLs point to LMS-1

### View External Students

1. Go to: Site administration → Plugins → AICC HACP
2. Click "View External Students Progress"
3. Or access: `/local/aicc_hacp/admin/external_progress.php`

## Troubleshooting

### Problem: Plugin not showing in admin
```bash
# Solution: Purge caches
php admin/cli/purge_caches.php
```

### Problem: Database tables missing
```bash
# Solution: Run upgrade
php admin/cli/upgrade.php --non-interactive --force
```

### Problem: Tokens invalid
```bash
# Solution: Generate new token secret in plugin settings
# Go to admin settings and generate new secret
```

### Problem: External users not created
```bash
# Check logs
tail -f /var/log/apache2/error.log

# Verify database
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SHOW TABLES LIKE 'local_aicc%';
```

## Test Commands

```bash
# Full test suite
php local/run_all_tests.php

# Check export
php local/aicc_export/tests/test_export.php

# Check tokens
php local/aicc_hacp/tests/test_token.php

# Check external users
php local/aicc_hacp/tests/test_external_users.php

# Check full workflow
php local/aicc_hacp/tests/test_full_workflow.php

# Debug endpoint
php local/aicc_hacp/tests/debug_endpoint.php
```

## File Locations

```
local/
├── aicc_export/          # Export plugin
├── aicc_hacp/            # HACP communication plugin
├── README_AICC_IMPLEMENTATION.md  # Full docs
├── INSTALL.md            # Installation guide
├── SUMMARY.md            # Summary
├── QUICK_START.md        # This file
└── run_all_tests.php    # Master test runner
```

## Common URLs

### On LMS-1

- Export: `http://localhost:8300/lms-one/local/aicc_export/export.php?courseid=X`
- External Progress: `http://localhost:8300/lms-one/local/aicc_hacp/admin/external_progress.php`
- Student Details: `http://localhost:8300/lms-one/local/aicc_hacp/admin/student_details.php?courseid=X&scormid=Y`
- View Logs: `http://localhost:8300/lms-one/local/aicc_hacp/admin/viewlog.php`

### On LMS-2

- Import AICC package via SCORM activity
- Students access content (loads from LMS-1 automatically)

## Quick Test

1. Create course with SCORM
2. Export: `export.php?courseid=1`
3. Download ZIP
4. Import on LMS-2
5. Access as student
6. Check progress on LMS-1

## Support

For detailed documentation, see:
- `README_AICC_IMPLEMENTATION.md` - Complete docs
- `INSTALL.md` - Detailed installation
- `SUMMARY.md` - Implementation summary

## Success Indicators

✅ All tests pass: `php local/run_all_tests.php`
✅ Plugins enabled in admin
✅ Can export course
✅ Can import on LMS-2  
✅ Students can access content
✅ Progress tracked on LMS-1

## Next Steps

1. ✅ Install plugins
2. ✅ Run tests
3. ✅ Export a test course
4. ✅ Import on LMS-2
5. ✅ Test student access
6. ✅ Monitor logs
7. ✅ Check progress reports

**Everything is ready to use!**

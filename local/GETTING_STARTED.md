# Getting Started - Step by Step

## Issue You Encountered

You got "Error: Service not enabled" because the plugins weren't enabled yet.

## Quick Fix

Run these commands in order:

### Step 1: Enable Plugins

```bash
docker exec -it apache_8 bash
cd /var/www/html/lms-one
php local/setup_enable_plugins.php
```

This will:
- Enable both AICC plugins
- Set default configurations
- Generate security secrets
- Purge caches

### Step 2: Verify Plugins are Enabled

```bash
php local/run_all_tests.php
```

You should see all tests passing.

### Step 3: Export a Course

1. **Go to LMS-1** (http://localhost:8300/lms-one)
2. **Create a course** with a SCORM activity
3. **Navigate to the course**
4. **Go to**: `/local/aicc_export/export.php?courseid=X` (replace X with your course ID)
5. **Download the ZIP file**

The ZIP should contain AICC descriptor files with URLs pointing to `http://localhost:8300/lms-one`

### Step 4: Import on LMS-2

1. **Go to LMS-2** (http://localhost:8300/lms-two)
2. **Create/edit a course**
3. **Add activity** → **SCORM**
4. **Upload** the ZIP file you downloaded
5. **Configure** and save

### Step 5: Test Access

1. **On LMS-2**, access the SCORM activity as a student
2. The content should **load from LMS-1**
3. Progress will be **tracked on LMS-1**

## If Still Getting Errors

### Error: "Service not enabled"

Run:
```bash
php local/setup_enable_plugins.php
```

### Error: "Token invalid"

Re-export the course from LMS-1 (the token changes each time)

### Error: Content not loading

Check:
1. Both plugins enabled on LMS-1
2. Can access `http://localhost:8300/lms-one` from LMS-2
3. URLs in package point to `http://localhost:8300/lms-one` not `http://localhost:8300/lms-two`

## Complete Checklist

Run through this to verify everything:

```bash
# 1. Enable plugins
php local/setup_enable_plugins.php

# 2. Run all tests
php local/run_all_tests.php

# 3. Export test
php local/aicc_export/tests/test_export.php

# 4. Token test
php local/aicc_hacp/tests/test_token.php

# 5. External users test
php local/aicc_hacp/tests/test_external_users.php

# 6. Full workflow test
php local/aicc_hacp/tests/test_full_workflow.php
```

If all pass, you're ready!

## Testing the Workflow

### On LMS-1

1. Create course with SCORM
2. Export: `http://localhost:8300/lms-one/local/aicc_export/export.php?courseid=X`
3. View external students: `http://localhost:8300/lms-one/local/aicc_hacp/admin/external_progress.php`

### On LMS-2

1. Import AICC package as SCORM
2. Access as student
3. Content loads from LMS-1
4. Check progress on LMS-1 admin panel

## Debug If Needed

If something's not working:

```bash
# Check plugin status
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_config_plugins WHERE plugin LIKE 'local_aicc%';

# Check logs
php local/aicc_hacp/tests/debug_endpoint.php

# Enable debug mode
# Edit config.php and add:
$CFG->debug = DEBUG_DEVELOPER;
```

## Key Points

1. **LMS-1 hosts the content** (must have plugins enabled)
2. **LMS-2 imports the package** (no plugins needed)
3. **Students on LMS-2 access content from LMS-1**
4. **Progress tracked on LMS-1**
5. **URLs in package point to LMS-1**

## What Changed

I fixed:
- ✅ Test scripts now define `CLI_SCRIPT`
- ✅ URLs now use absolute paths (`out(true)` instead of `out(false)`)
- ✅ Created setup script to enable plugins automatically

Now run:
```bash
php local/setup_enable_plugins.php
```

Then try exporting and importing again!

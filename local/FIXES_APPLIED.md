# Fixes Applied

## Issues Found and Fixed

### 1. CLI Script Definition Missing
**Problem:** Test scripts didn't define `CLI_SCRIPT` before requiring config.php
**Error:** "Command line scripts must define CLI_SCRIPT before requiring config.php"

**Fixed in:**
- `local/aicc_export/tests/test_export.php`
- `local/aicc_hacp/tests/test_token.php`
- `local/aicc_hacp/tests/test_external_users.php`
- `local/aicc_hacp/tests/test_full_workflow.php`
- `local/aicc_hacp/tests/debug_endpoint.php`

**Solution:** Added `define('CLI_SCRIPT', true);` at the top of each test script

### 2. Service Not Enabled
**Problem:** Plugins weren't enabled after installation
**Error:** "Error: Service not enabled"

**Fixed in:**
- Created `local/setup_enable_plugins.php` to automatically enable both plugins

**Solution:** Run `php local/setup_enable_plugins.php` to enable plugins with proper settings

### 3. URLs Pointing to Wrong LMS
**Problem:** Exported URLs were relative, pointing to LMS-2 instead of LMS-1
**Error:** Content trying to load from wrong server

**Fixed in:**
- `local/aicc_export/classes/exporter.php` line 201

**Solution:** Changed from `$content_url->out(false)` to `$content_url->out(true)` to generate absolute URLs pointing to LMS-1

## New Files Created

1. `local/setup_enable_plugins.php` - Auto-enable both plugins with proper settings
2. `local/GETTING_STARTED.md` - Step-by-step instructions
3. `local/FIXES_APPLIED.md` - This file

## What You Need to Do Now

### Step 1: Enable Plugins
```bash
cd /var/www/html/lms-one
php local/setup_enable_plugins.php
```

### Step 2: Run Tests
```bash
php local/run_all_tests.php
```

### Step 3: Export a Course (again)
The URLs will now correctly point to LMS-1:
- Go to course on LMS-1
- Export: `/local/aicc_export/export.php?courseid=X`
- Download ZIP

### Step 4: Import on LMS-2
- Import the new ZIP file
- URLs should now point to `http://localhost:8300/lms-one`

## Verification

After enabling plugins, check:

```bash
# Check if plugins are enabled
php -r "
require_once('/var/www/html/lms-one/config.php');
echo 'AICC Export: ' . get_config('local_aicc_export', 'enabled') . PHP_EOL;
echo 'AICC HACP: ' . get_config('local_aicc_hacp', 'enabled') . PHP_EOL;
"
```

Should output:
```
AICC Export: 1
AICC HACP: 1
```

## Summary of Changes

### Files Modified
- `local/aicc_export/tests/test_export.php` - Added CLI_SCRIPT
- `local/aicc_hacp/tests/test_token.php` - Added CLI_SCRIPT
- `local/aicc_hacp/tests/test_external_users.php` - Added CLI_SCRIPT  
- `local/aicc_hacp/tests/test_full_workflow.php` - Added CLI_SCRIPT
- `local/aicc_hacp/tests/debug_endpoint.php` - Added CLI_SCRIPT
- `local/aicc_export/classes/exporter.php` - Fixed URL generation (out(true))

### Files Created
- `local/setup_enable_plugins.php` - Auto-enable script
- `local/GETTING_STARTED.md` - Instructions
- `local/FIXES_APPLIED.md` - This file

## Quick Command Reference

```bash
# Enable everything
php local/setup_enable_plugins.php

# Test everything
php local/run_all_tests.php

# Export course
# Visit: http://localhost:8300/lms-one/local/aicc_export/export.php?courseid=X

# View logs
php local/aicc_hacp/tests/debug_endpoint.php
```

## Next Steps

Follow the instructions in `local/GETTING_STARTED.md` to complete the setup and test the full workflow.

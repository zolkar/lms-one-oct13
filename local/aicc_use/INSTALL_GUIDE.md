# AICC User Extension (aicc_use) - Installation Guide

## Quick Start

This plugin makes LMS-2 automatically send real student information to LMS-1 when launching AICC content.

## Step 1: Copy Plugin to LMS-2

```bash
# On your host machine (Mac)
cd /Users/vtechnocrat/Documents/php7.2.15_projects

# Copy the plugin to LMS-2
cp -r lms-one/local/aicc_use lms-two/local/

# Make sure permissions are correct
docker exec apache_8 bash -c "chown -R www-data:www-data /var/www/html/lms-two/local/aicc_use"
```

## Step 2: Install Plugin on LMS-2

1. Visit: `http://localhost:8301/lms-two/admin/notifications.php`
2. You should see: "New plugin detected: local_aicc_use"
3. Click: "Upgrade Moodle database now"
4. Wait for installation to complete

## Step 3: Modify AICC Export on LMS-1

You need to update the .au file to point to the new launcher instead of directly to content_launcher.php

### Option A: Modify the Exporter (Recommended)

Update `local/aicc_export/classes/exporter.php` line 209-220:

```php
// OLD (points directly to content launcher)
$content_url = new \moodle_url('/local/aicc_export/content_launcher.php', [
    'id' => $activity->id,
    'token' => $token
]);

// NEW (points to LMS-2's launcher which adds student info)
$content_url = 'http://localhost:8301/lms-two/local/aicc_use/launcher.php?' . 
    http_build_query([
        'id' => $activity->id,
        'token' => $token,
        'target_lms' => $CFG->wwwroot  // LMS-1 URL
    ]);
```

Then re-export the course and import to LMS-2.

### Option B: Manual Edit (Quick Test)

Extract the AICC package and edit `dsfasd.au`:

**Find:**
```
"AU1",..., "http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=..."
```

**Replace with:**
```
"AU1",..., "http://localhost:8301/lms-two/local/aicc_use/launcher.php?id=42&token=...&target_lms=http://localhost:8300"
```

Re-zip and import to LMS-2.

## Step 4: Test the Integration

1. Log in to LMS-2 as a student
2. Go to the SCORM activity
3. Click to launch the content
4. Check logs on LMS-2:

```bash
docker logs apache_8 2>&1 | grep "AICC USE" | tail -5
```

You should see:
```
AICC USE: Redirecting user john.doe (john@example.com) to http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username=john.doe&email=john@example.com&firstname=John&lastname=Doe
```

5. Check logs on LMS-1:

```bash
docker logs apache_8 2>&1 | grep "AICC Launch Parameters" -A 15
```

You should see real student information:
```
GET params: Array
(
    [id] => 42
    [token] => ...
    [username] => john.doe
    [email] => john@example.com
    [firstname] => John
    [lastname] => Doe
)
```

## Step 5: Verify Student Data is Saved

```bash
docker exec mariadb_moodle bash -c "mysql -u root -pexample lms_one -e \"SELECT student_id, student_name, student_email FROM mdl_local_aicc_hacp_persistent_sessions ORDER BY created_at DESC LIMIT 1;\""
```

You should see the actual student email, not `external_student_...@external-lms.local`.

## What Changed?

**Before (with placeholders):**
- .au file had `{{student.username}}` placeholders
- Moodle couldn't replace them
- Auto-generated emails appeared

**After (with plugin):**
- .au file points to the launcher on LMS-2
- Launcher gets real student info from Moodle
- Real student data is sent to LMS-1
- Actual student names and emails are recorded

## Troubleshooting

### Plugin not detected
```bash
# Check if files exist
docker exec apache_8 ls -la /var/www/html/lms-two/local/aicc_use

# Check database
docker exec mariadb_moodle bash -c "mysql -u root -pexample lms_two -e 'SELECT * FROM mdl_config_plugins WHERE plugin = \"local_aicc_use\";'"
```

### Parameters still showing placeholders
Make sure you re-exported and re-imported after modifying the exporter.

### 404 Not Found on launcher.php
Check that the plugin was installed correctly and try visiting:
`http://localhost:8301/lms-two/local/aicc_use/launcher.php?test=1`

## Next Steps

1. Install the plugin on LMS-2
2. Re-export from LMS-1 with updated URLs
3. Re-import to LMS-2
4. Test with real student login
5. Verify actual emails are being captured

Done! No more manual parameter editing needed.


# Fix LMS-2 Launch Error

When accessing the SCORM activity on LMS-2, you're likely seeing one of these errors:

## Error 1: "Missing access token"

**Cause**: The SCORM player isn't using the AICC URL from the package

**Fix**: The imported package should have URLs pointing to LMS-1, but Moodle's SCORM player might not be using them.

**Solution**: Check if AICC HACP is enabled on the SCORM activity:

1. Go to: `http://localhost:8300/lms-two/mod/scorm/view.php?id=YOUR_SCORM_CM_ID`
2. Click "Edit settings"
3. Look for "AICC HACP" setting
4. Enable it if not already enabled
5. Save

## Error 2: "Service not enabled"

**Cause**: Plugins not enabled on LMS-1

**Fix**: Already done! But verify:
```bash
docker exec apache_8 bash -c "cd /var/www/html/lms-one && php local/setup_enable_plugins.php"
```

## Error 3: "Invalid or expired access token"

**Cause**: Token has expired (they expire after 1 hour)

**Fix**: Re-export the course from LMS-1:
```
http://localhost:8300/lms-one/local/aicc_export/index.php?courseid=9
```

## Error 4: 404 Not Found

**Cause**: URL pointing to wrong location

**Fix**: The .au file should contain URLs like:
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...
```

## Debug Steps

### Step 1: Check the actual error

In browser console (F12), look at the Network tab when accessing the SCORM. What's the error response?

### Step 2: Check the AICC package

Extract the ZIP and check the .au file. The URL should be:
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...
```

### Step 3: Test the launcher directly

Try accessing the launcher URL directly from LMS-2:
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=YOUR_TOKEN
```

Replace YOUR_TOKEN with the token from your .au file.

## Common Issues

1. **Moodle SCORM player strips AICC URLs** - Some SCORM implementations don't use AICC properly
2. **CORS issues** - Browser blocking cross-origin requests
3. **Token expired** - Tokens expire after 1 hour

## Alternative: Use LMS-2's SCORM Player Directly

If AICC isn't working, you might need to:
1. Import the SCORM package directly (not via AICC)
2. Or configure the SCORM activity to allow AICC HACP

## Get More Details

Run this to see what's happening:
```bash
docker exec apache_8 bash -c "cd /var/www/html/lms-one && php local/debug_launcher.php"
```

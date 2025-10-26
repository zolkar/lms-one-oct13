# Troubleshooting Guide - AICC Implementation

## Quick Diagnostics

```bash
# Run comprehensive diagnostics
php local/run_all_tests.php

# Check specific areas
php local/aicc_export/tests/test_export.php
php local/aicc_hacp/tests/test_token.php
php local/aicc_hacp/tests/test_external_users.php
```

## Common Issues

### Issue 1: Plugin Not Appearing

**Symptoms:** Plugin not in admin settings

**Diagnosis:**
```bash
# Check if plugin files exist
ls -la local/aicc_export/
ls -la local/aicc_hacp/

# Check config table
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_config_plugins WHERE plugin = 'local_aicc_export';
SELECT * FROM mdl_config_plugins WHERE plugin = 'local_aicc_hacp';
```

**Solution:**
```bash
php admin/cli/purge_caches.php
php admin/cli/upgrade.php --non-interactive
```

### Issue 2: Database Tables Missing

**Symptoms:** Errors about missing tables

**Diagnosis:**
```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SHOW TABLES LIKE 'local_aicc%';
```

**Solution:**
```bash
# Force reinstall
php admin/cli/upgrade.php --non-interactive --force
```

### Issue 3: Token Invalid/Expired

**Symptoms:** "Invalid or expired access token"

**Diagnosis:**
```bash
# Check token secret configured
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_config_plugins 
WHERE plugin = 'local_aicc_export' AND name = 'launch_token_secret';
```

**Solution:**
1. Re-export the AICC package
2. Check token TTL setting (should be 3600 or higher)
3. Verify system time is correct on both LMSs

### Issue 4: Content Not Loading on LMS-2

**Symptoms:** Blank page, error loading content

**Diagnosis:**
```bash
# Check if LMS-2 can reach LMS-1
curl http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=X&token=Y

# Check HACP endpoint
curl http://localhost:8300/lms-one/local/aicc_hacp/endpoint.php
```

**Solution:**
1. Verify network connectivity between LMSs
2. Check URLs in AICC package point to correct LMS-1
3. Check browser console for CORS errors
4. Verify plugin is enabled on LMS-1

### Issue 5: Progress Not Tracking

**Symptoms:** Progress not saved, resets on reload

**Diagnosis:**
```bash
# Check HACP logs
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_local_aicc_hacp_logs ORDER BY created_at DESC LIMIT 10;

# Check sessions
SELECT * FROM mdl_local_aicc_hacp_sessions ORDER BY created_at DESC LIMIT 10;

# Check student state
SELECT * FROM mdl_local_aicc_hacp_student_state ORDER BY updated_at DESC LIMIT 10;
```

**Solution:**
1. Enable HACP on LMS-1
2. Check HACP endpoint accessible
3. Verify external user created
4. Check session persistence
5. Review signature validation

### Issue 6: External Users Not Created

**Symptoms:** No users in persistent_sessions table

**Diagnosis:**
```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_local_aicc_hacp_persistent_sessions;

# Check user table structure
DESCRIBE mdl_user;
```

**Solution:**
1. Verify email passed in request
2. Check user creation permissions
3. Review error logs
4. Check database structure

### Issue 7: HACP Request Failing

**Symptoms:** 105 error or communication errors

**Diagnosis:**
```bash
# Check logs
php local/aicc_hacp/tests/debug_endpoint.php

# View recent HACP requests
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_local_aicc_hacp_logs 
WHERE command != '' 
ORDER BY created_at DESC LIMIT 20;
```

**Solution:**
1. Check shared secret configured
2. Verify signature validation
3. Check rate limiting not exceeded
4. Review log level setting

### Issue 8: Export Produces Empty ZIP

**Symptoms:** ZIP file is empty or missing files

**Diagnosis:**
```bash
# Check for SCORM activities
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT c.id, c.shortname, s.id as scormid 
FROM mdl_course c 
JOIN mdl_scorm s ON s.course = c.id 
WHERE c.id > 1;
```

**Solution:**
1. Ensure course has SCORM activity
2. Check file permissions on temp directory
3. Review error logs
4. Verify ZIP library installed

### Issue 9: Sessions Expiring Too Quickly

**Symptoms:** Sessions closed, students lose progress

**Diagnosis:**
```bash
# Check session timeout setting
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_config_plugins 
WHERE plugin = 'local_aicc_hacp' AND name = 'session_timeout';
```

**Solution:**
1. Increase session_timeout setting
2. Check system time accurate
3. Enable session persistence

## Debug Mode

### Enable Debug Logging

Edit `config.php`:
```php
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
$CFG->perfdebug = 15;
```

### View Logs

```bash
# PHP error log
tail -f /var/log/apache2/error.log

# HACP logs via web interface
# Go to: /local/aicc_hacp/admin/viewlog.php

# Or via database
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SELECT * FROM mdl_local_aicc_hacp_logs ORDER BY created_at DESC LIMIT 50;
```

## Database Inspection

### Check All Tables

```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
SHOW TABLES LIKE 'local_aicc%';

# Get counts
SELECT 'export_sessions' as table_name, COUNT(*) as count 
FROM mdl_local_aicc_export_sessions
UNION ALL
SELECT 'hacp_sessions', COUNT(*) FROM mdl_local_aicc_hacp_sessions
UNION ALL
SELECT 'persistent_sessions', COUNT(*) FROM mdl_local_aicc_hacp_persistent_sessions
UNION ALL
SELECT 'student_state', COUNT(*) FROM mdl_local_aicc_hacp_student_state
UNION ALL
SELECT 'logs', COUNT(*) FROM mdl_local_aicc_hacp_logs;
```

## Configuration Verification

### Check All Settings

```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one

# Export settings
SELECT * FROM mdl_config_plugins 
WHERE plugin = 'local_aicc_export';

# HACP settings
SELECT * FROM mdl_config_plugins 
WHERE plugin = 'local_aicc_hacp';
```

## Network Testing

### Test Connectivity

```bash
# From LMS-2 container, test LMS-1
# Replace with actual IPs/hostnames
curl http://lms-one:8300/local/aicc_hacp/endpoint.php
```

## Common Fixes

### Fix 1: Complete Reinstall

```bash
# Full reset
php admin/cli/purge_caches.php
php admin/cli/upgrade.php --non-interactive --force

# Restart web server
apachectl restart
```

### Fix 2: Reset Configuration

```bash
# Remove settings
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one
DELETE FROM mdl_config_plugins WHERE plugin LIKE 'local_aicc%';

# Re-install
php admin/cli/upgrade.php --non-interactive
```

### Fix 3: Database Cleanup

```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one

# Clean old sessions
DELETE FROM mdl_local_aicc_hacp_sessions 
WHERE expires_at < UNIX_TIMESTAMP() - 86400;

# Clean old logs (keep last 1000)
DELETE FROM mdl_local_aicc_hacp_logs 
WHERE id NOT IN (
    SELECT id FROM (
        SELECT id FROM mdl_local_aicc_hacp_logs 
        ORDER BY created_at DESC LIMIT 1000
    ) temp
);
```

## Getting Help

If issues persist:

1. Run diagnostics: `php local/run_all_tests.php`
2. Enable debug mode
3. Check logs
4. Review database state
5. Verify configuration
6. Test each component individually

For detailed logs:
- HACP Logs: `/local/aicc_hacp/admin/viewlog.php`
- PHP Error Log: `/var/log/apache2/error.log`
- Database Logs: Check logs table

## Success Checklist

Use this checklist to verify everything is working:

- [ ] Plugins installed without errors
- [ ] Tables created in database
- [ ] Settings configured
- [ ] Tests pass (`php local/run_all_tests.php`)
- [ ] Can export course
- [ ] Can import on LMS-2
- [ ] Students can access content
- [ ] Progress tracking works
- [ ] Can view external students report
- [ ] Logs are being recorded

If all checked, system is working! ✓

# Fix Course 9 Database Error

You're getting "Error reading from database" when accessing course 9.

## Quick Diagnostic

Run this to see what's wrong:

```bash
docker exec -it apache_8 bash
cd /var/www/html/lms-one
php local/diagnose_database.php
```

This will show you:
- If course 9 exists
- What modules are in course 9
- If there are database integrity issues
- What's causing the error

## Quick Fixes to Try

### Fix 1: Purge Caches
```bash
cd /var/www/html/lms-one
php admin/cli/purge_caches.php
```
Then try accessing course 9 again: `http://localhost:8300/lms-one/course/view.php?id=9`

### Fix 2: Check Database Schema
```bash
php admin/cli/check_database_schema.php
```
This will tell you if there are schema issues.

### Fix 3: Rebuild Course Cache
```bash
php admin/cli/rebuild_course_cache.php --help
# To rebuild just course 9:
php admin/cli/rebuild_course_cache.php --courseid=9
```

### Fix 4: Check Database Directly

```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one

# Check course 9
SELECT * FROM mdl_course WHERE id = 9\G

# Check if context exists
SELECT * FROM mdl_context WHERE instanceid = 9 AND contextlevel = 50\G

# Check course modules
SELECT * FROM mdl_course_modules WHERE course = 9 AND deletioninprogress = 0;

# Check for problematic modules
SELECT cm.id, m.name, cm.instance
FROM mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module
WHERE cm.course = 9
AND cm.deletioninprogress = 0;

# Exit
exit
```

### Fix 5: Check if Course 9 is Corrupted

If course 9 is corrupted, you can:
1. **Backup and delete it**, OR
2. **Try to fix specific issues**

To see what modules might be causing issues:
```bash
cd /var/www/html/lms-one
php local/fix_course9_issues.php
```

## Common Causes

1. **Deleted modules still in database** - Remnants of deleted activities
2. **Corrupted course context** - Missing context entry
3. **Orphaned modules** - Modules pointing to non-existent instances
4. **Cache issues** - Stale cache data

## Check Specific Course 9 Issue

Run:
```bash
php local/fix_course9_issues.php
```

This will show you exactly what's wrong with course 9.

## Alternative: Use Different Course

If course 9 is corrupted beyond easy repair:

1. Try course 8 or 10
2. Or create a new course for testing

## Get More Details

```bash
# Enable debug mode temporarily
# Edit config.php and add:
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

# Then try accessing course 9
# Check the error message in detail
```

## Contact Points

After running diagnostics, share the output and I can help fix the specific issue!

#!/bin/bash
#
# Database Table Checker Script
# Checks if required Moodle tables exist in the lms_one database
#

echo "======================================================================"
echo "DATABASE TABLE CHECK - lms_one"
echo "======================================================================"
echo ""

# Connect to database and check tables
docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e '
SHOW TABLES;
'" | head -100

echo ""
echo "======================================================================"
echo "Checking if core Moodle tables exist..."
echo "======================================================================"
echo ""

# Check for core tables
docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e '
SELECT \"core\" as type, COUNT(*) as count, \"OK\" as status 
FROM information_schema.tables 
WHERE table_schema = \"lms_one\" 
AND table_name IN (\"mdl_course\", \"mdl_course_modules\", \"mdl_modules\", \"mdl_context\", \"mdl_course_sections\", \"mdl_scorm\", \"mdl_user\")
UNION ALL
SELECT \"aicc\" as type, COUNT(*) as count, \"OK\" as status
FROM information_schema.tables 
WHERE table_schema = \"lms_one\" 
AND table_name IN (\"mdl_local_aicc_export_sessions\", \"mdl_local_aicc_hacp_sessions\", \"mdl_local_aicc_hacp_persistent_sessions\", \"mdl_local_aicc_hacp_student_state\", \"mdl_local_aicc_hacp_logs\");
'"

echo ""
echo "======================================================================"
echo "Checking specific tables..."
echo "======================================================================"
echo ""

# Check each table individually
for table in "mdl_course" "mdl_course_modules" "mdl_modules" "mdl_context" "mdl_course_sections" "mdl_scorm" "mdl_user" "mdl_local_aicc_export_sessions" "mdl_local_aicc_hacp_sessions" "mdl_local_aicc_hacp_persistent_sessions" "mdl_local_aicc_hacp_student_state" "mdl_local_aicc_hacp_logs"; do
    result=$(docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e 'SHOW TABLES LIKE \"$table\";'" 2>/dev/null)
    if echo "$result" | grep -q "$table"; then
        echo "✓ $table EXISTS"
        count=$(docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e 'SELECT COUNT(*) FROM $table;'" 2>/dev/null | tail -1)
        echo "  Records: $count"
    else
        echo "✗ $table MISSING"
    fi
done

echo ""
echo "======================================================================"
echo "Checking course 9 specifically..."
echo "======================================================================"
echo ""

docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e '
SELECT id, shortname, fullname, timemodified FROM mdl_course WHERE id = 9\G
'"

echo ""
echo "======================================================================"
echo "Checking context for course 9..."
echo "======================================================================"
echo ""

docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e '
SELECT * FROM mdl_context WHERE instanceid = 9 AND contextlevel = 50\G
'"

echo ""
echo "======================================================================"
echo "Checking course modules for course 9..."
echo "======================================================================"
echo ""

docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e '
SELECT cm.id, cm.instance, m.name as module, cm.section, cm.visible, cm.deletioninprogress
FROM mdl_course_modules cm
LEFT JOIN mdl_modules m ON m.id = cm.module
WHERE cm.course = 9
ORDER BY cm.section, cm.id\G
'"

echo ""
echo "======================================================================"
echo "Checking for problematic modules in course 9..."
echo "======================================================================"
echo ""

docker exec -it mariadb_moodle bash -c "mysql -u root -pexample lms_one -e '
SELECT cm.id, cm.instance, m.name as modname, cm.deletioninprogress
FROM mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module
WHERE cm.course = 9 AND cm.deletioninprogress = 1\G
'"

echo ""
echo "======================================================================"
echo "SUMMARY"
echo "======================================================================"
echo ""
echo "Check the output above for any MISSING tables (marked with ✗)"
echo "If course 9 appears but has issues, you may need to:"
echo "1. Purge caches: php admin/cli/purge_caches.php"
echo "2. Rebuild course cache: php admin/cli/rebuild_course_cache.php"
echo "3. Fix deleted modules if any found"
echo ""

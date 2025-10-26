# Installation Instructions for AICC Plugins

## Quick Start

### 1. Access Docker Container

```bash
docker exec -it apache_8 bash
cd /var/www/html/lms-one
```

### 2. Install/Upgrade Plugins

```bash
php admin/cli/upgrade.php --non-interactive
```

This will:
- Install/upgrade the database tables
- Configure default settings
- Generate security secrets
- Register capabilities

### 3. Enable Plugins

Go to: **Site administration → Plugins → Local plugins**

Enable:
- **AICC Export**
- **AICC HACP**

Configure settings (see README_AICC_IMPLEMENTATION.md for details)

### 4. Run Tests

```bash
# Run all tests
php local/run_all_tests.php

# Run individual tests
php local/aicc_export/tests/test_export.php
php local/aicc_hacp/tests/test_token.php
php local/aicc_hacp/tests/test_external_users.php
php local/aicc_hacp/tests/test_full_workflow.php
```

## Database Setup

### If Tables Don't Exist

```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample lms_one

# Verify tables
SHOW TABLES LIKE 'local_aicc%';

# If missing, run:
# php admin/cli/upgrade.php --non-interactive
```

## Plugin Settings

### AICC Export
- **Enabled:** Yes
- **Default AICC Version:** 4.0
- **Launch Token Secret:** (auto-generated)
- **Launch Token TTL:** 3600

### AICC HACP
- **Enabled:** Yes
- **Allowed Origins:** (empty for all, or add specific URLs)
- **Shared Secret:** (auto-generated)
- **Require HTTPS:** No (for testing)
- **Log Level:** debug (for testing)
- **Max Requests/Min:** 60
- **Session Timeout:** 7200
- **Launch Token Secret:** (auto-generated)
- **Launch Token TTL:** 3600

## Troubleshooting

### Database Errors

```bash
# Reset and reinstall
php admin/cli/purge_caches.php
php admin/cli/upgrade.php --non-interactive --force
```

### Missing Secrets

```bash
# Generate new secrets
# Go to Site administration → Plugins → Local plugins
# Click "Edit" for each plugin
# Generate new secrets
```

### Tables Exist But Plugin Not Working

```bash
# Check plugin version
SELECT * FROM mdl_config_plugins WHERE plugin = 'local_aicc_export';

# Check if enabled
SELECT * FROM mdl_config_plugins WHERE plugin = 'local_aicc_export' AND name = 'enabled';
```

## Testing Workflow

1. **Create Course with SCORM**
   - Create a new course
   - Add a SCORM activity
   - Upload a SCORM package

2. **Export Course**
   - Go to course
   - Click "Export as AICC"
   - Download ZIP file

3. **Import on LMS-2**
   - Go to another course on LMS-2
   - Add SCORM activity
   - Upload the AICC package

4. **Test Access**
   - As student on LMS-2, access the activity
   - Content should load from LMS-1
   - Check progress on LMS-1 admin panel

## Next Steps

See `README_AICC_IMPLEMENTATION.md` for complete documentation.

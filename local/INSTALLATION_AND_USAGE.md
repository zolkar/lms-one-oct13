# AICC Course Sharing - Installation and Usage Guide

## System Overview

This implementation enables course sharing between two Moodle 4.3 instances using the AICC protocol with HACP (HTTP AICC Communication Protocol).

**Components:**
- **LMS-1** (Content Provider): Hosts the course content and serves it to external students
- **LMS-2** (Importer): Imports the AICC package and provides access to students
- **Plugins**: Three local plugins handle export, tracking, and student injection

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  LMS-2 (Importer)                                           │
│                                                             │
│  ┌──────────────┐        ┌──────────────┐                   │
│  │   Student    │        │   Plugin    │                   │
│  │  Launches    │───────►│  aicc_use   │                   │
│  │   Content    │        └──────┬──────┘                   │
│  └──────────────┘               │                          │
│                                 │ Adds student info       │
│                                 ▼                          │
│                         ┌──────────────┐                   │
│                         │  LMS-1       │                   │
└─────────────────────────┤  Content     ├───────────────────┘
                          │  Launcher    │
                          └──────┬───────┘
                                 │
                          ┌──────▼───────┐
                          │  HACP        │
                          │  Endpoint    │
                          └──────┬───────┘
                                 │
                          ┌──────▼───────┐
                          │  Progress    │
                          │  Database    │
                          └─────────────┘
```

## File Structure

```
local/
├── aicc_export/              # Plugin A - Export (LMS-1)
│   ├── classes/
│   │   └── exporter.php      # Export logic
│   ├── content_launcher.php  # Serves content to external LMS
│   ├── file_server.php      # Serves assets
│   ├── export.php           # Export handler
│   ├── index.php            # Export interface
│   ├── settings.php         # Configuration
│   └── tests/
│       ├── test_export.php
│       └── diagnose_export_issue.php
│
├── aicc_hacp/                # Plugin B - HACP (LMS-1)
│   ├── classes/
│   │   ├── handler.php      # Processes HACP commands
│   │   ├── parser.php       # Parses AICC data
│   │   ├── session_persistence.php # Saves progress
│   │   ├── secure_auth.php  # Token generation
│   │   └── secure_session.php
│   ├── endpoint.php         # HACP endpoint
│   ├── admin/
│   │   ├── student_details.php  # View progress
│   │   ├── delete_student.php   # Manage students
│   │   ├── reset_student.php     # Reset progress
│   │   ├── manualmap.php        # Manual user mapping
│   │   └── viewlog.php          # View HACP logs
│   └── tests/
│       ├── test_token.php
│       ├── test_external_users.php
│       └── test_full_workflow.php
│
└── aicc_use/                  # Plugin C - Launcher (LMS-2)
    ├── launcher.php          # Injects student info
    ├── version.php
    ├── INSTALL_GUIDE.md
    └── README.txt
```

## Installation

### Step 1: Install Plugins on LMS-1

The plugins are already installed and enabled:
- `local_aicc_export` - AICC Export
- `local_aicc_hacp` - AICC HACP Communication

Verify installation:
```bash
docker exec mariadb_moodle bash -c "mysql -u root -pexample lms_one -e \"SELECT name, version FROM mdl_config_plugins WHERE plugin LIKE 'local_aicc%';\" | cat"
```

### Step 2: Install Plugin on LMS-2

```bash
# Copy plugin
cp -r local/aicc_use ../lms-two/local/

# Set permissions
docker exec apache_8 bash -c "chown -R www-data:www-data /var/www/html/lms-two/local/aicc_use"

# Install via Moodle admin
# Visit: http://localhost:8301/lms-two/admin/notifications.php
# Click: "Upgrade Moodle database now"
```

### Step 3: Configure Settings

#### On LMS-1:
1. Visit: `http://localhost:8300/lms-one/admin/settings.php?section=localplugins`
2. Find "AICC Export" section
3. Set:
   - **LMS-2 Launcher URL**: `http://localhost:8301/lms-two/local/aicc_use/launcher.php`
   - **Launch Token TTL**: `86400` (24 hours)
4. Save

## Usage

### Export Course from LMS-1

1. Go to the course you want to share (e.g., Course 9)
2. Visit: `http://localhost:8300/lms-one/local/aicc_export/index.php?courseid=9`
3. Click "Export Course as AICC Package"
4. Download the `dsfasd_aicc_[timestamp].zip` file

### Import to LMS-2

1. Login to LMS-2 as admin
2. Go to the course where you want to add the shared content
3. Turn editing on
4. Add activity → SCORM package
5. Choose "Upload AICC package"
6. Upload the ZIP file from LMS-1
7. Save and display

### Access Content from LMS-2

1. Login as a student on LMS-2
2. Go to the course
3. Click the SCORM activity
4. Content from LMS-1 loads automatically
5. Student's email, name, and progress are tracked

### View Progress on LMS-1

As admin on LMS-1:
1. Go to: `http://localhost:8300/lms-one/local/aicc_hacp/admin/student_details.php?courseid=9&scormid=34`
2. View all external students and their progress
3. Use "Delete" button to remove student data
4. Use "Reset Progress" button to clear student progress

## Database Tables

### On LMS-1:

```sql
-- Export history
mdl_local_aicc_exports

-- HACP sessions (temporary)
mdl_local_aicc_hacp_sessions

-- Student progress (persistent)
mdl_local_aicc_hacp_student_state
  - student_id
  - scormid
  - scoid
  - lesson_status
  - lesson_location
  - score
  - session_time
  - state_data (JSON)

-- Student information
mdl_local_aicc_hacp_persistent_sessions
  - student_id
  - student_name
  - student_email
  - userid (mapped to Moodle user)

-- User mapping
mdl_local_aicc_hacp_usermap

-- HACP request logs
mdl_local_aicc_hacp_logs
```

## Testing

### Test Export
```bash
cd /var/www/html/lms-one
php local/aicc_export/tests/test_export.php
```

### Test Token Generation
```bash
php local/aicc_hacp/tests/test_token.php
```

### Test External Users
```bash
php local/aicc_hacp/tests/test_external_users.php
```

### Test Full Workflow
```bash
php local/aicc_hacp/tests/test_full_workflow.php
```

## Admin Features

### View Student Progress
URL: `/local/aicc_hacp/admin/student_details.php?courseid=X&scormid=Y`

Shows:
- Student ID, name, email
- Lesson status (completed/incomplete)
- Score
- Session time
- Last activity time

### Delete Student
Click "Delete" button to remove all student data (sessions, state, persistent sessions, user map)

### Reset Progress
Click "Reset Progress" button to clear student progress but keep the student record

### Manual User Mapping
URL: `/local/aicc_hacp/admin/manualmap.php?courseid=X&scormid=Y`

Manually map external student IDs to internal Moodle users

### View HACP Logs
URL: `/local/aicc_hacp/admin/viewlog.php?courseid=X`

View all HACP requests and responses

## Features

### ✅ Implemented:

1. **AICC Package Export**: Export Moodle courses with SCORM activities
2. **HACP Communication**: Full AICC HACP support (GetParam, PutParam, ExitAU)
3. **Progress Tracking**: Track slides, quizzes, completion status
4. **Time Tracking**: Record session time
5. **Score Tracking**: Track quiz/exam scores
6. **Student Management**: Create, view, delete student records
7. **Session Persistence**: Resume progress across visits
8. **Real Student Data**: Capture actual emails and names from LMS-2
9. **Admin Interface**: View progress, reset, delete students
10. **Completion Detection**: Automatically detect when content is completed
11. **Token Security**: JWT tokens for secure content access
12. **External User Accounts**: Auto-create users for external students

### ⚠️ Known Limitations:

1. **Slide Counting**: Cannot track exact slide numbers in single-page SCORM apps
   - **Workaround**: Uses "slide 1" as lesson location, completion status tracks overall progress

2. **Completion Detection**: Relies on DOM indicators
   - **Workaround**: Checks for `.slide.finish`, `#finish`, `.complete` elements and title containing "Complete"

3. **Manual Configuration**: LMS-2 must have `local/aicc_use` plugin installed
   - **Workaround**: Provided complete installation guide

## Security

### Token Security:
- JWT tokens with 24-hour expiration
- HMAC-SHA256 signatures
- URL-safe Base64 encoding

### Access Control:
- Content served without login requirements
- Token validation on every request
- Rate limiting (can be configured)

### Database Security:
- Separate tables for external students
- Isolation from Moodle's user system
- Audit trail via HACP logs

## Troubleshooting

### Issue: "Error reading from database"
**Solution**: Run purge cache: `php admin/cli/purge_caches.php`

### Issue: "Service not enabled"
**Solution**: Enable both plugins:
- `local_aicc_export`
- `local_aicc_hacp`

### Issue: "Error: Missing access token"
**Solution**: Re-export the course with fresh token

### Issue: Student info showing placeholders
**Solution**: Ensure `local/aicc_use` is installed on LMS-2 and configured on LMS-1

### Issue: Progress not saving
**Solution**: 
1. Check HACP endpoint: `http://localhost:8300/lms-one/local/aicc_hacp/endpoint.php`
2. Check logs: `docker logs apache_8 | grep "PutParam"`
3. Verify database connection

## Support Files

- `README.md` - This file
- `README_AICC_IMPLEMENTATION.md` - Detailed technical documentation
- `QUICK_START_AICC_USE.txt` - Quick start for aicc_use plugin
- `aicc_use/INSTALL_GUIDE.md` - Installation guide for LMS-2 plugin

## License

Same as Moodle (GPL v3 or later)


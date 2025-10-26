# AICC Course Sharing Implementation

Complete implementation for sharing Moodle courses between two LMS instances using AICC/HACP protocol.

## Overview

This implementation allows LMS-1 to export courses as AICC packages that can be imported into LMS-2. Students on LMS-2 can access content hosted on LMS-1, with full progress tracking synchronized between both systems.

## Architecture

```
┌─────────────┐                    ┌─────────────┐
│   LMS-2     │                    │   LMS-1     │
│ (Importer)  │◄─────HACP──────────►│ (Content)   │
└─────────────┘                    └─────────────┘
     │                                    │
     │ 1. Imports AICC package           │
     │ 2. Student launches activity      │
     │ 3. Loads content from LMS-1       │
     │ 4. Sends progress via HACP ────────┼──► Saves progress
     │ 5. Shows progress on LMS-2        │   (tracks on LMS-1)
```

## Plugins

### 1. Plugin A: `local/aicc_export` (LMS-1)
**Purpose**: Export courses as AICC packages

**Features**:
- Generates AICC descriptor files (.au, .crs, .des, etc.)
- Creates JWT launch tokens with expiration
- Packages everything into a ZIP file
- Provides web interface for export (`/local/aicc_export/index.php`)

**Key Files**:
- `classes/exporter.php` - Main export logic
- `content_launcher.php` - Serves SCORM content to external LMS
- `file_server.php` - Serves SCORM assets (JS, CSS, images)
- `export.php` - Handles export workflow
- `settings.php` - Plugin configuration

### 2. Plugin B: `local/aicc_hacp` (LMS-1)
**Purpose**: Handle AICC HACP communication and progress tracking

**Features**:
- Receives HACP commands (GetParam, PutParam, ExitAU)
- Saves student progress to database
- Creates external user accounts
- Provides admin interface for viewing student progress
- Session management and token validation

**Key Files**:
- `endpoint.php` - HACP endpoint
- `classes/handler.php` - Processes HACP commands
- `classes/parser.php` - Parses AICC data format
- `classes/session_persistence.php` - Saves/loads progress
- `classes/secure_auth.php` - Token generation/validation
- `admin/student_details.php` - View student progress
- `admin/delete_student.php` - Manage student data

### 3. Plugin C: `local/aicc_use` (LMS-2)
**Purpose**: Inject student information into AICC launch URLs

**Features**:
- Intercepts AICC launch requests
- Adds current student information (username, email, name)
- Redirects to LMS-1 with student parameters
- Eliminates need for manual parameter editing

**Key Files**:
- `launcher.php` - Main launcher script
- `version.php` - Plugin definition
- `INSTALL_GUIDE.md` - Installation instructions

## Installation

### On LMS-1:

Plugins are already installed:
- `local/aicc_export` - Export functionality
- `local/aicc_hacp` - HACP communication

### On LMS-2:

Install the `aicc_use` plugin:

```bash
# Copy the plugin
cp -r /path/to/lms-one/local/aicc_use /path/to/lms-two/local/

# Set permissions
docker exec apache_8 bash -c "chown -R www-data:www-data /var/www/html/lms-two/local/aicc_use"

# Install via Moodle admin
# Visit: http://localhost:8301/lms-two/admin/notifications.php
```

## Configuration

### On LMS-1:

1. Go to: Site administration → Plugins → Local plugins → AICC Export
2. Set "LMS-2 Launcher URL" to: `http://localhost:8301/lms-two/local/aicc_use/launcher.php`
3. Set "Launch Token TTL" to: `86400` (24 hours)

### On LMS-2:

1. Install the `local/aicc_use` plugin (see above)

## Usage

### Export Course from LMS-1:

1. Go to the course you want to export
2. Visit: `http://localhost:8300/lms-one/local/aicc_export/index.php?courseid=X`
3. Click "Export Course as AICC Package"
4. Download the ZIP file

### Import on LMS-2:

1. Create a new course on LMS-2
2. Add activity → SCORM/AICC
3. Choose "Upload AICC package"
4. Upload the ZIP file from LMS-1
5. Save

### View Progress on LMS-1:

1. As an administrator, go to the course
2. Visit: `http://localhost:8300/lms-one/local/aicc_hacp/admin/student_details.php?courseid=X&scormid=Y`
3. View student progress, completion status, scores, etc.

## Testing

### Test Export:
```bash
php local/aicc_export/tests/test_export.php
```

### Test Token:
```bash
php local/aicc_hacp/tests/test_token.php
```

### Test External Users:
```bash
php local/aicc_hacp/tests/test_external_users.php
```

### Test Full Workflow:
```bash
php local/aicc_hacp/tests/test_full_workflow.php
```

## Database Tables

### `mdl_local_aicc_exports`
Stores export history and metadata

### `mdl_local_aicc_hacp_sessions`
Temporary HACP sessions for active communications

### `mdl_local_aicc_hacp_student_state`
Persistent student progress (status, location, score, time)

### `mdl_local_aicc_hacp_persistent_sessions`
Student account information (name, email, user mapping)

### `mdl_local_aicc_hacp_usermap`
Maps external students to internal Moodle users

### `mdl_local_aicc_hacp_logs`
HACP request logs for debugging

## Features

### ✅ What Works:

1. **Course Export**: Export any Moodle course with SCORM activities as AICC package
2. **Content Delivery**: Serve SCORM content to external LMS without login
3. **Progress Tracking**: Track student progress (slides, quizzes, completion)
4. **Session Persistence**: Resume progress across multiple visits
5. **Student Management**: View, reset, and delete student data
6. **Real Student Data**: Capture actual student emails and names from LMS-2
7. **Completion Detection**: Automatically detect when content is completed
8. **Time Tracking**: Record session time spent in content

### ⚠️ Limitations:

1. **Slide Counting**: Can't track exact slide numbers in single-page apps
2. **Manual Student Mapping**: If LMS-2 doesn't pass parameters, use manual mapping interface
3. **Completion Detection**: Relies on DOM indicators, may need adjustment for custom content

## Troubleshooting

### Export Not Working:
- Check that course has SCORM activities
- Verify plugin is enabled
- Check logs: `docker logs apache_8`

### Progress Not Saving:
- Verify HACP endpoint is reachable: `http://localhost:8300/lms-one/local/aicc_hacp/endpoint.php`
- Check database connection
- Review logs for parsing errors

### Student Info Not Passing:
- Ensure `local/aicc_use` is installed on LMS-2
- Configure LMS-2 launcher URL on LMS-1
- Re-export and re-import the package

## Files Structure

```
local/
├── aicc_export/          # Plugin A - Export
│   ├── classes/
│   ├── content_launcher.php
│   ├── export.php
│   ├── file_server.php
│   ├── index.php
│   └── settings.php
├── aicc_hacp/           # Plugin B - HACP
│   ├── classes/
│   ├── admin/
│   ├── endpoint.php
│   ├── lib.php
│   └── settings.php
└── aicc_use/            # Plugin C - Launcher (for LMS-2)
    ├── launcher.php
    ├── version.php
    └── INSTALL_GUIDE.md
```

## Support

For detailed setup instructions, see:
- `local/aicc_use/INSTALL_GUIDE.md` - Installation guide
- `local/aicc_use/README.txt` - Plugin documentation

## License

Same as Moodle (GPL v3 or later)


# AICC Cross-LMS Content Sharing Implementation

This implementation enables secure sharing of Moodle courses between LMS-1 (content provider) and LMS-2 (consumer) using AICC standards and HACP (HTTP AICC Communication Protocol).

## Overview

**LMS-1 (Content Provider):**
- Exports course as AICC package
- Hosts content and serves it to external students
- Tracks progress via HACP

**LMS-2 (Consumer):**
- Imports AICC package as SCORM activity
- Students access content hosted on LMS-1
- Progress syncs automatically via HACP

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│ LMS-1 (Content Provider)                                    │
│                                                              │
│ ┌─────────────────┐     ┌──────────────────┐              │
│ │  Course C-1     │────▶│ AICC Export       │              │
│ │  (SCORM)        │     │ Plugin            │              │
│ └─────────────────┘     └──────────────────┘              │
│                                                              │
│                    ┌──────────────────┐                    │
│                    │ Content Launcher  │                    │
│                    │ + Token Auth     │                    │
│                    └──────────────────┘                    │
│                           │                                 │
│                           ▼                                 │
│                    ┌──────────────────┐                    │
│                    │ AICC HACP        │                    │
│                    │ Endpoint         │                    │
│                    │ + Tracking       │                    │
│                    └──────────────────┘                    │
└────────────────────────────┬───────────────────────────────┘
                             │
                             │ HTTPS + Token
                             │ HACP Messages
                             ▼
┌─────────────────────────────────────────────────────────────┐
│ LMS-2 (Consumer)                                             │
│                                                              │
│ ┌─────────────────┐                                         │
│ │  Course C-2     │                                         │
│ │  (AICC Activity) │                                         │
│ └─────────────────┘                                         │
│         │                                                    │
│         ▼                                                    │
│  Students (S1, S2, S3)                                       │
│  Access content via activity                                 │
│  Content loaded from LMS-1                                  │
│  Progress tracked on LMS-1                                  │
└─────────────────────────────────────────────────────────────┘
```

## Components

### Plugin: `local_aicc_export`

**Purpose:** Export Moodle courses as AICC packages for external LMS

**Key Features:**
- Generates AICC descriptor files (.crs, .cst, .des, .au, .ort, .pre, .cmp)
- Creates secure URLs pointing back to LMS-1
- Embeds authentication tokens in URLs
- Supports SCORM activities only

**Files:**
- `export.php` - Export page
- `classes/exporter.php` - AICC package generator
- `content_launcher.php` - Launches content for external students
- `secure_content_server.php` - Serves SCORM content with AICC support
- `settings.php` - Admin configuration

### Plugin: `local_aicc_hacp`

**Purpose:** Handle AICC HACP communication and track external students

**Key Features:**
- Handles GetParam, PutParam, ExitAU commands
- Creates external user accounts automatically
- Tracks student progress persistently
- Provides admin reports for external students
- Secure session management
- Request logging and monitoring

**Files:**
- `endpoint.php` - HACP endpoint handler
- `classes/handler.php` - Command processor
- `classes/session_persistence.php` - Session and progress management
- `classes/secure_auth.php` - Authentication and token management
- `classes/secure_session.php` - Session lifecycle management
- `classes/parser.php` - AICC data parser
- `admin/external_progress.php` - External students report
- `admin/student_details.php` - Detailed student information

## Installation

### Prerequisites

- Moodle 4.2 or higher
- PHP 7.4 or higher
- MySQL/MariaDB
- Both LMS instances running (for testing: `localhost:8300`)

### Step 1: Install Plugins on LMS-1

1. Copy plugin directories to `/local/`:
   ```bash
   docker exec -it apache_8 bash
   cd /var/www/html/lms-one
   ```

2. Ensure plugins are in place:
   - `local/aicc_export/`
   - `local/aicc_hacp/`

3. Install via Moodle CLI:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

   Or install via web interface:
   - Go to Site administration → Plugins → Install plugins
   - Search for "AICC Export" and "AICC HACP"
   - Install both plugins

### Step 2: Configure Plugin Settings

#### AICC Export Plugin

Go to Site administration → Plugins → Local plugins → AICC Export:

1. **Enable AICC Export** - Check to enable
2. **Default AICC Version** - Set to `4.0`
3. **Launch Token Secret** - Auto-generated (don't change unless necessary)
4. **Launch Token TTL** - Set to `3600` seconds (1 hour)

#### AICC HACP Plugin

Go to Site administration → Plugins → Local plugins → AICC HACP:

1. **Enable AICC HACP** - Check to enable
2. **Allowed Origins** - Leave empty for testing, or add `http://localhost:8300`
3. **Shared Secret** - Auto-generated
4. **Require HTTPS** - Uncheck for local testing
5. **Log Level** - Set to `debug` for testing
6. **Max Requests Per Minute** - Set to `60`
7. **Session Timeout** - Set to `7200` seconds (2 hours)
8. **Launch Token Secret** - Auto-generated
9. **Launch Token TTL** - Set to `3600` seconds (1 hour)

### Step 3: Run Database Setup (if needed)

```bash
docker exec -it mariadb_moodle bash
mysql -u root -pexample

USE lms_one;

-- Verify tables exist
SHOW TABLES LIKE 'local_aicc%';

-- If tables don't exist, run:
-- php admin/cli/upgrade.php --non-interactive
```

## Usage

### Exporting a Course (LMS-1)

1. Go to the course you want to export
2. Click on course settings → More → "Export Course as AICC Package" (if menu item added)
3. Or access directly: `/local/aicc_export/export.php?courseid=1`
4. Download the `.zip` file
5. The file contains AICC descriptor files with URLs pointing to LMS-1

### Importing on LMS-2

1. On LMS-2, go to course where you want to add the content
2. Add activity → SCORM
3. Upload the AICC package you downloaded
4. Configure SCORM settings
5. Save

**Note:** The content remains hosted on LMS-1. The AICC package just provides access.

### Viewing External Students Progress (LMS-1)

1. Go to Site administration → Plugins → Local plugins → AICC HACP
2. Click "View External Students Progress"
3. Or access: `/local/aicc_hacp/admin/external_progress.php`

## Testing

### Test Scripts

Run these scripts to verify the installation:

```bash
# SSH into Apache container
docker exec -it apache_8 bash
cd /var/www/html/lms-one

# Test Export functionality
php local/aicc_export/tests/test_export.php

# Test Token generation/validation
php local/aicc_hacp/tests/test_token.php

# Test External Users functionality
php local/aicc_hacp/tests/test_external_users.php

# Test Full Workflow
php local/aicc_hacp/tests/test_full_workflow.php

# Debug HACP endpoint
php local/aicc_hacp/tests/debug_endpoint.php
```

### Manual Testing

1. **Export Test:**
   - Create a course with SCORM activity on LMS-1
   - Export as AICC package
   - Verify ZIP file downloads
   - Extract and check descriptor files contain URLs with tokens

2. **Import Test:**
   - Import package on LMS-2
   - Check SCORM activity appears
   - Verify URLs point to LMS-1

3. **Access Test:**
   - As student on LMS-2, access SCORM activity
   - Content should load from LMS-1
   - Check external user created on LMS-1

4. **Progress Test:**
   - Complete some content
   - Check progress tracked on LMS-1
   - Close and reopen
   - Verify progress persists

## Database Schema

### aicc_export

**Table: `local_aicc_export_sessions`**
- Session tracking for remote students
- Stores token, SCORM ID, student info

### aicc_hacp

**Tables:**
- `local_aicc_hacp_sessions` - Temporary HACP sessions
- `local_aicc_hacp_persistent_sessions` - Persistent sessions for external students
- `local_aicc_hacp_student_state` - Student progress data
- `local_aicc_hacp_logs` - Request logging
- `local_aicc_hacp_usermap` - Manual user mappings (optional)

## Security

- **Token-based access:** Only exported packages contain valid tokens
- **Time-limited tokens:** Tokens expire after configured TTL
- **Activity-specific tokens:** Each token tied to specific SCORM activity
- **External user isolation:** External students tracked separately
- **Signature validation:** HMAC signatures for HACP requests
- **Rate limiting:** Configurable requests per minute
- **Session expiry:** Sessions expire after timeout

## Troubleshooting

### Export Not Working

1. Check plugin is enabled in settings
2. Check user has export capability
3. Check token secret is configured
4. Review error logs

### Token Invalid/Expired

1. Re-export the AICC package
2. Check token TTL setting
3. Verify time synchronization between LMSs
4. Check launch_token_secret setting

### Content Not Loading on LMS-2

1. Check LMS-2 can reach LMS-1 network
2. Verify URLs in AICC package point to correct LMS-1
3. Check CORS settings if browser blocks
4. Review browser console for errors

### Progress Not Tracking

1. Check HACP endpoint accessible from LMS-2
2. Review HACP logs in admin
3. Check external user creation
4. Verify session persistence

### External User Creation Failing

1. Check database permissions
2. Verify `local_aicc_hacp_persistent_sessions` table exists
3. Check `user` table has proper structure
4. Review error logs

## Debug Mode

Enable debug logging:

```php
// In config.php
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
```

View logs:
```bash
# View PHP error log
tail -f /path/to/error.log

# View HACP logs via admin interface
# Site administration → AICC HACP → View Logs
```

## Command Line Tools

```bash
# Run all tests
cd /var/www/html/lms-one
php local/aicc_export/tests/test_export.php
php local/aicc_hacp/tests/test_token.php
php local/aicc_hacp/tests/test_external_users.php
php local/aicc_hacp/tests/test_full_workflow.php

# View debug info
php local/aicc_hacp/tests/debug_endpoint.php

# Run Moodle upgrade (if needed)
php admin/cli/upgrade.php --non-interactive
```

## Files and Directories

### aicc_export
```
local/aicc_export/
├── classes/
│   ├── exporter.php
│   └── launcher.php
├── tests/
│   └── test_export.php
├── content_launcher.php
├── export.php
├── secure_content_server.php
├── settings.php
├── version.php
└── db/
    ├── access.php
    ├── install.php
    ├── install.xml
    └── uninstall.php
```

### aicc_hacp
```
local/aicc_hacp/
├── classes/
│   ├── handler.php
│   ├── session_persistence.php
│   ├── secure_auth.php
│   ├── secure_session.php
│   ├── parser.php
│   └── mapper.php
├── admin/
│   ├── external_progress.php
│   ├── student_details.php
│   ├── reset_student.php
│   ├── viewlog.php
│   └── manualmap.php
├── tests/
│   ├── test_token.php
│   ├── test_external_users.php
│   ├── test_full_workflow.php
│   └── debug_endpoint.php
├── endpoint.php
├── lib.php
├── settings.php
├── version.php
└── db/
    ├── access.php
    ├── install.php
    ├── install.xml
    └── uninstall.php
```

## Support

For issues or questions:
1. Review this README
2. Run test scripts
3. Check logs
4. Review database tables

## Next Steps

1. Test export → import → access flow
2. Configure allowed origins for production
3. Set up HTTPS for production
4. Monitor HACP logs
5. Review external student progress regularly

## License

GPL v3 or later

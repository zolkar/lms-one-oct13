# AICC Export and HACP Implementation Summary

## Overview
This implementation enables cross-LMS content sharing between two Moodle instances (LMS-1 and LMS-2) using AICC standards and HACP (HTTP AICC Communication Protocol).

## Architecture

### LMS-1 (Content Provider)
- **Plugin**: `local_aicc_export` - Exports courses as AICC packages
- **Plugin**: `local_aicc_hacp` - Handles HACP communication from external students

### Flow
1. LMS-1 exports a course as an AICC package (ZIP with descriptor files)
2. LMS-2 imports the package and students access it via a SCORM activity
3. Content is hosted on LMS-1, students from LMS-2 access it directly
4. Progress tracking is synchronized via HACP

## Components

### Plugin: aicc_export

**Purpose**: Export Moodle courses as AICC packages

**Key Files**:
- `export.php` - Main export page
- `classes/exporter.php` - AICC package generation logic
- `classes/launcher.php` - Token signing/validation
- `content_launcher.php` - Launches SCORM content for external students
- `secure_content_server.php` - Serves SCORM content with AICC HACP support
- `db/install.xml` - Database schema for sessions

**Features**:
- Generates AICC descriptor files (.crs, .cst, .des, .au, .ort, .pre, .cmp)
- Creates packages with URLs pointing back to LMS-1
- Enables AICC HACP for SCORM activities
- Supports external student access without login

### Plugin: aicc_hacp

**Purpose**: Handle AICC HACP communication from external LMS

**Key Files**:
- `endpoint.php` - Main HACP endpoint
- `classes/handler.php` - Processes HACP commands (GetParam, PutParam, ExitAU)
- `classes/session_persistence.php` - Manages sessions and student state
- `classes/secure_auth.php` - Authentication and rate limiting
- `classes/secure_session.php` - Session management
- `classes/parser.php` - Parses AICC data format
- `admin/external_progress.php` - View external students' progress
- `admin/student_details.php` - Detailed student information
- `db/install.xml` - Database schema

**Database Tables**:
- `local_aicc_hacp_sessions` - Temporary HACP sessions
- `local_aicc_hacp_persistent_sessions` - Persistent sessions for external students
- `local_aicc_hacp_student_state` - Student progress data
- `local_aicc_hacp_logs` - Request logging
- `local_aicc_hacp_usermap` - Manual user mappings

**Features**:
- Handles GetParam, PutParam, ExitAU commands
- Stores student progress persistently
- View external students and their progress
- Reset student progress
- Request logging and monitoring
- Rate limiting and security
- Origin validation

## How It Works

### Export Process (LMS-1)
1. Teacher navigates to course
2. Clicks "Export Course as AICC Package"
3. System generates AICC descriptor files (.crs, .cst, .des, etc.)
4. URLs in package point to LMS-1's content_launcher.php
5. Package is downloaded as ZIP file

### Import Process (LMS-2)
1. Teacher imports AICC package into LMS-2 course
2. Package is imported as SCORM activity
3. URLs in the package point to LMS-1's content server

### Student Access Flow (LMS-2 → LMS-1)
1. Student on LMS-2 accesses SCORM activity
2. LMS-2 launches content using URL from AICC package
3. URL points to LMS-1's `content_launcher.php?id=<cmid>&AICC_SID=<student_id>`
4. LMS-1 creates persistent session and HACP session
5. Student's browser loads SCORM content from LMS-1
6. Content communicates with LMS-1 via HACP endpoint

### HACP Communication
1. SCORM content makes HACP requests to `/local/aicc_hacp/endpoint.php`
2. Endpoint validates session and processes command
3. Commands supported:
   - **GetParam**: Retrieve student's progress
   - **PutParam**: Save student's progress
   - **ExitAU**: End session

### Progress Tracking
1. Student progress is stored in `local_aicc_hacp_student_state`
2. Sessions are tracked in `local_aicc_hacp_persistent_sessions`
3. Teachers on LMS-1 can view external students' progress
4. Progress persists across sessions

## Database Schema

### aicc_export
- `local_aicc_export_sessions` - Remote sessions for AICC export

### aicc_hacp
- `local_aicc_hacp_sessions` - Temporary HACP sessions (with persistent_session_id)
- `local_aicc_hacp_persistent_sessions` - Persistent sessions for external students
- `local_aicc_hacp_student_state` - Student progress data
- `local_aicc_hacp_logs` - Request logging
- `local_aicc_hacp_usermap` - Manual user mappings

## Security Features

1. **Signature Validation**: HMAC-SHA256 signatures for all requests
2. **Rate Limiting**: Configurable requests per minute
3. **Origin Validation**: Whitelist trusted origins
4. **Session Expiry**: Sessions expire after timeout
5. **HTTPS Support**: Optional HTTPS requirement
6. **Logging**: Comprehensive request logging

## Configuration

### aicc_export Settings
- Enable AICC Export
- Default AICC Version (default: 4.0)
- Launch Token Secret
- Launch Token TTL

### aicc_hacp Settings
- Enable AICC HACP
- Allowed Origins
- Shared Secret
- Require HTTPS
- Log Level
- Max Requests Per Minute
- Session Timeout

## Testing

### On LMS-1
1. Install both plugins
2. Enable plugins in settings
3. Configure AICC Export and HACP settings
4. Export a course with SCORM activities
5. Import it on LMS-2

### On LMS-2
1. Import the AICC package as SCORM
2. Have students access the activity
3. Verify they can access content from LMS-1
4. Verify progress tracking works

### Verification
1. Check LMS-1 admin reports for external students
2. Verify progress is recorded
3. Verify students can resume sessions
4. Check logs for HACP requests

## Next Steps

1. **Test the implementation**:
   - Install both plugins on LMS-1
   - Import a SCORM package
   - Export as AICC
   - Import on LMS-2 (or simulate)

2. **Configure settings**:
   - Enable AICC Export
   - Enable AICC HACP
   - Set allowed origins
   - Set shared secret

3. **Test the flow**:
   - Export course
   - Import on another LMS
   - Access as student
   - Verify progress tracking

4. **Monitor**:
   - Check admin reports
   - Review logs
   - Verify session persistence


# AICC Security and External User Tracking

## Security Implementation

### Token-Based Access Control
- **Token Generation**: When exporting AICC packages, tokens are automatically generated and embedded in URLs
- **Token Validation**: All launch requests must include a valid, non-expired token
- **Token Scope**: Each token is scoped to a specific SCORM activity
- **Expiry**: Tokens expire after configured TTL (default 3600 seconds = 1 hour)

### Access Flow with Security
1. **Export** (LMS-1): Teacher exports course → system generates token → token embedded in AICC URLs
2. **Import** (LMS-2): Teacher imports package → SCORM activity created with secure URL
3. **Launch** (LMS-2 → LMS-1): 
   - Student accesses activity
   - LMS-2 sends request to: `http://lms-one.local/aicc_export/content_launcher.php?id=X&token=ABC123...`
   - **Token validated** before any processing
4. **Access Granted**: Only if token is valid, non-expired, and matches SCORM activity

### Why Only Token-Holders Can Access
- **URLs with tokens**: Only exported AICC packages contain valid tokens
- **No token, no access**: Direct access attempts without token are rejected (401 error)
- **Expired tokens rejected**: Expired tokens cause 401 error
- **Wrong activity rejected**: Token for activity A cannot access activity B (403 error)

## External User Account Creation

### Automatic User Creation
When a student from LMS-2 accesses content on LMS-1, the system:

1. **Collects student information**:
   - Email (required)
   - Full name
   - Student ID
   - Origin LMS identifier

2. **Creates Moodle user account** (on LMS-1):
   - Unique username: `external_[timestamp]_[uniqid]`
   - Email from external student
   - Full name from external student
   - ID number: `EXTERNAL_[md5(email + origin)]`
   - Password: Secure random password (student cannot login with it)
   - Account confirmed and ready for tracking

3. **Links to tracking**:
   - User ID stored in `local_aicc_hacp_persistent_sessions` table
   - Progress tracked using this user account
   - Teachers can see student progress in LMS-1

### User Account Details
- **Username**: Auto-generated, unique (e.g., `external_1234567890_abc123def`)
- **Email**: Student's email from external LMS
- **Password**: Random secure hash (student cannot login; for internal tracking only)
- **Name**: Student's name from external LMS
- **ID Number**: `EXTERNAL_[hash]` for identification
- **Status**: Confirmed (ready for tracking)
- **Purpose**: Internal tracking only, not for login

### Progress Tracking
- Student progress is stored in Moodle's SCORM tracking tables
- Uses the created external user account
- Teachers on LMS-1 can view progress reports
- Progress persists across sessions
- Completion status visible to LMS-1 teachers

## Example Flow

### 1. Export (LMS-1)
```
Teacher → Export Course → Generate AICC Package
- File: course_abc.zip
- Contains: .crs, .cst, .des, .au files
- URLs point to: http://lms-one.local/aicc_export/content_launcher.php?id=123&token=XYZ789...
```

### 2. Import (LMS-2)
```
Teacher → Import AICC Package → Create SCORM Activity
- URL in package: http://lms-one.local/aicc_export/content_launcher.php?id=123&token=XYZ789...
- This is the ONLY valid URL for this content
```

### 3. Student Access (LMS-2 → LMS-1)
```
Student clicks activity on LMS-2
↓
Request: http://lms-one.local/aicc_export/content_launcher.php?id=123&token=XYZ789&student_email=student@lms2.edu
↓
LMS-1 validates token ✓
↓
LMS-1 creates external user account (email: student@lms2.edu, name: John Doe)
↓
LMS-1 creates HACP session
↓
LMS-1 serves SCORM content
↓
Content displays in LMS-2
↓
Progress tracked on LMS-1 for external user
```

### 4. Progress Tracking (LMS-1)
```
Teacher on LMS-1 → View Reports → See External Students
- Student: John Doe (student@lms2.edu)
- Progress: 75% complete
- Score: 85/100
- Last Activity: 2 hours ago
- Origin: LMS-2
```

## Security Benefits

1. **Access Control**: Only token-holders can access content
2. **No Direct Access**: Direct URL guessing fails (no valid token)
3. **Time-Limited**: Tokens expire after configured time
4. **Activity-Specific**: Tokens tied to specific SCORM activities
5. **External User Isolation**: External students tracked separately from internal students
6. **No Password Sharing**: External students can't login to LMS-1

## Configuration

### On LMS-1 (Content Provider)

**AICC Export Settings:**
- Enable AICC Export: ✓
- Default AICC Version: 4.0
- Launch Token Secret: [auto-generated]
- Launch Token TTL: 3600 seconds

**AICC HACP Settings:**
- Enable AICC HACP: ✓
- Allowed Origins: http://lms-two.local (optional, for origin validation)
- Shared Secret: [auto-generated]
- Max Requests Per Minute: 60
- Session Timeout: 7200 seconds

### On LMS-2 (Consumer)

No special configuration needed. Just:
1. Import the AICC package
2. Students access the activity
3. Content loads from LMS-1 automatically

## Troubleshooting

### Token Issues
- **Error: "Missing access token"**: Package URL missing token parameter
- **Error: "Invalid or expired access token"**: Token invalid or expired
- **Solution**: Re-export the AICC package from LMS-1

### User Creation Issues
- **Error: "Student email is required"**: External LMS not passing email parameter
- **Solution**: Ensure LMS-2 passes student_email parameter in AICC launch

### Access Control
- **401 Error**: Token missing, invalid, or expired
- **403 Error**: Token doesn't match SCORM activity or plugin disabled
- **Solution**: Check token in URL, re-export package if needed

## Architecture Summary

```
┌─────────────────┐
│   LMS-1         │  Exports course with TOKEN in URLs
│  (Content Host) │  
└────────┬────────┘
         │ AICC Package (ZIP)
         ▼
┌─────────────────┐
│   LMS-2         │  Imports package
│  (Consumer)     │  Creates SCORM activity
└────────┬────────┘
         │
         ▼ Student clicks activity
         │
         ▼ Request to LMS-1 with TOKEN + email
         │
         ▼
┌─────────────────┐
│   LMS-1         │  Validates TOKEN ✓
│  (Content Host) │  Creates external user account
                  │  Creates HACP session
                  │  Serves SCORM content
                  │  Tracks progress
└─────────────────┘
```

The token ensures that ONLY exported AICC packages can access content on LMS-1. Anyone without a valid token from an exported package cannot access the content.


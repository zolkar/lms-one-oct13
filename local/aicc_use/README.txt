=============================================================================
AICC User Extension (aicc_use) - Local Plugin for LMS-2
=============================================================================

DESCRIPTION
-------------------------------------------------------------------------
This plugin automatically injects current student information into AICC launch
URLs before redirecting to the content provider (LMS-1).

PURPOSE
-------------------------------------------------------------------------
When AICC content is imported from LMS-1 into LMS-2, the launch URL contains
placeholders like {{student.username}} that need actual values. This plugin
replaces those placeholders with real student data from LMS-2 before
launching the content.

INSTALLATION
-------------------------------------------------------------------------

Step 1: Install on LMS-2
-------------------------
1. Copy the entire 'aicc_use' folder to LMS-2:
   cp -r local/aicc_use /path/to/lms-two/local/

2. Visit http://localhost:8301/lms-two/admin/notifications.php
   - Moodle will detect the new plugin
   - Click "Upgrade Moodle database now"

Step 2: Configure AICC Export on LMS-1
--------------------------------------
1. Re-export the course from LMS-1 with updated URLs
2. Or manually edit the .au file to use the launcher

The .au file should point to this launcher:
Original URL in .au:
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...

Modified URL in .au:
http://localhost:8301/lms-two/local/aicc_use/launcher.php?id=42&token=...

Step 3: Re-import on LMS-2
--------------------------
1. Import the modified AICC package
2. Launch the SCORM activity
3. The plugin will automatically add student info

HOW IT WORKS
-------------------------------------------------------------------------
1. Student clicks SCORM activity on LMS-2
2. LMS-2 loads the .au file which points to launcher.php
3. launcher.php gets current user info (username, email, name)
4. launcher.php builds a new URL with student parameters
5. launcher.php redirects to LMS-1's content_launcher.php
6. LMS-1 receives actual student information

EXPECTED PARAMETERS
-------------------------------------------------------------------------
The launcher adds these parameters to the URL:
- username: Student's Moodle username
- email: Student's email address  
- firstname: Student's first name
- lastname: Student's last name

EXAMPLE
-------------------------------------------------------------------------
Input URL (from .au file):
http://localhost:8301/lms-two/local/aicc_use/launcher.php?id=42&token=abc123

Output URL (redirects to):
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?
id=42&token=abc123&username=john.doe&email=john@example.com
&firstname=John&lastname=Doe

TROUBLESHOOTING
-------------------------------------------------------------------------
If student info is not being passed:

1. Check Apache logs on LMS-2:
   docker logs apache_8 2>&1 | grep "AICC USE"

2. Check parameters received on LMS-1:
   docker logs apache_8 2>&1 | grep "AICC Launch Parameters"

3. Verify plugin is installed:
   Visit: http://localhost:8301/lms-two/admin/plugins.php
   Look for "AICC User Extension" in Local plugins

REQUIREMENTS
-------------------------------------------------------------------------
- Moodle 4.0 or higher
- Access to LMS-2's file system
- SCORM module enabled on LMS-2

AUTHOR
-------------------------------------------------------------------------
Created for AICC course sharing between two Moodle instances

LICENSE
-------------------------------------------------------------------------
Same as Moodle


# LMS-2 Configuration for AICC Student Information

## Problem
LMS-2 is not passing student information (username, email, name) to the AICC content launch URL.

## Solution

You need to modify LMS-2's SCORM/AICC launch URL to include student parameters.

### Option 1: Modify the .au file after import (Quick Fix)

After importing the AICC package on LMS-2:

1. Go to the SCORM activity settings
2. Open the course structure (AICC structure)
3. Find the AU (Assignable Unit) entry
4. Manually edit the `web_launch` field to include student parameters:

Original:
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...
```

Modified:
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username=[[student.username]]&email=[[student.email]]&firstname=[[student.firstname]]&lastname=[[student.lastname]]
```

Note: `[[student.xxx]]` is a placeholder that Moodle/your LMS should replace with actual student data.

### Option 2: Modify the AICC Export to Include Parameters (Recommended)

You need to modify the exporter to include parameter placeholders in the launch URL. LMS-2 should then substitute these with actual student data.

In `local/aicc_export/classes/exporter.php`, modify the `get_hacp_launch_url` method to append parameter placeholders:

```php
$content_url = new \moodle_url('/local/aicc_export/content_launcher.php', [
    'id' => $activity->id,
    'token' => $token,
    'username' => '{{student.username}}',
    'email' => '{{student.email}}',
    'firstname' => '{{student.firstname}}',
    'lastname' => '{{student.lastname}}'
]);
```

Then on LMS-2, you need to configure the AICC handler to replace these placeholders with actual student data.

### Option 3: Use Moodle's SCORM API Wrapper (Advanced)

If LMS-2 is Moodle, you can create a custom AICC handler that wraps the launch URL with student information:

1. Create a local plugin on LMS-2: `local/aicc_launcher`
2. Create a launcher script that:
   - Gets the current logged-in user
   - Appends user information to the launch URL
   - Redirects to LMS-1

Example launcher script:
```php
<?php
require_once(__DIR__ . '/../../../config.php');

require_login();

$launch_url = required_param('url', PARAM_URL);
$username = $USER->username;
$email = $USER->email;
$firstname = $USER->firstname;
$lastname = $USER->lastname;

$url = new moodle_url($launch_url, [
    'username' => $username,
    'email' => $email,
    'firstname' => $firstname,
    'lastname' => $lastname
]);

redirect($url);
```

Then modify the .au file to use this launcher:
```
http://localhost:8301/lms-two/local/aicc_launcher/launch.php?url=http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...
```

## Testing

After implementing one of the above solutions, reload the SCORM activity on LMS-2 and check the logs:

```bash
docker logs apache_8 2>&1 | grep "AICC Launch Parameters" -A 20
```

You should see:
```
GET params: Array
(
    [id] => 42
    [token] => ...
    [username] => actual_username
    [email] => actual@email.com
    [firstname] => John
    [lastname] => Doe
)
```

## Completion Status

The completion status is now automatically detected when the content sends completion signals. The wrapper JavaScript checks for completion indicators every 10 seconds.

If you need to manually mark completion (for testing), you can edit the database:

```sql
UPDATE mdl_local_aicc_hacp_student_state 
SET lesson_status = 'completed', score = '100'
WHERE student_id = 'external_student_...';
```


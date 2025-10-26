# Verification: AICC Export with Student Parameters

## What Was Modified

The AICC Export now includes student parameter placeholders in the launch URL.

### File: `local/aicc_export/classes/exporter.php`

Lines 209-216:
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

### File: `local/aicc_export/content_launcher.php`

Lines 121-128 handle the placeholders:
```php
$username = optional_param('username', '', PARAM_TEXT);
$student_email = optional_param('email', '', PARAM_EMAIL);
$student_name = optional_param('student_name', '', PARAM_TEXT);

// Remove placeholder values (if LMS-2 didn't replace them)
$username = str_replace('{{student.username}}', '', $username);
$student_email = str_replace('{{student.email}}', '', $student_email);
$student_name = str_replace('{{student.name}}', '', $student_name);
```

## How to Test

### Step 1: Re-Export the Course

1. Visit: `http://localhost:8300/lms-one/local/aicc_export/index.php?courseid=9`
2. Click "Export Course as AICC Package"
3. Download the new ZIP file

### Step 2: Extract and Check the .au File

Extract the ZIP and open the `.au` file. You should see:

```csv
"system_id","title","parent","type","command_line","Max_Time_Allowed","time_limit_action","file_name","max_score","mastery_score","system_vendor","core_vendor","web_launch","AU_password"
"AU1","Course Name","/","","","","","http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username={{student.username}}&email={{student.email}}&firstname={{student.firstname}}&lastname={{student.lastname}}","","","Moodle","","http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username={{student.username}}&email={{student.email}}&firstname={{student.firstname}}&lastname={{student.lastname}}",""
```

Notice the parameters: `username={{student.username}}&email={{student.email}}&firstname={{student.firstname}}&lastname={{student.lastname}}`

### Step 3: Import on LMS-2

Import the new AICC package on LMS-2.

### Step 4: Test with Manual Parameters

Before launching the content, manually edit the launch URL to replace placeholders:

**Original URL:**
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username={{student.username}}&email={{student.email}}...
```

**Modified URL (for testing):**
```
http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username=john.doe&email=john.doe@example.com&firstname=John&lastname=Doe
```

### Step 5: Verify in Logs

After launching with parameters, check the logs:

```bash
docker logs apache_8 2>&1 | grep "AICC Launch Parameters" -A 20 | tail -25
```

You should see:
```
GET params: Array
(
    [id] => 42
    [token] => ...
    [username] => john.doe
    [email] => john.doe@example.com
    [firstname] => John
    [lastname] => Doe
)
```

### Step 6: Verify in Database

Check that the student information was saved correctly:

```bash
docker exec mariadb_moodle bash -c "mysql -u root -pexample lms_one -e \"SELECT student_id, student_name, student_email FROM mdl_local_aicc_hacp_persistent_sessions WHERE student_id LIKE '%john%';\""
```

## What Happens Now

### With Placeholders
If LMS-2 sends the actual values, you'll see real student names and emails.

### Without Placeholders
If LMS-2 doesn't replace the placeholders, the system falls back to auto-generated values:
- Email: `{{student.username}}@external-lms.local` becomes empty, then generates a new one
- Name: Uses the username if available

## Next Steps

1. **Re-export the course** to get the updated .au file
2. **Import on LMS-2**
3. **Test with manual parameters** to verify it works
4. **Configure LMS-2** to automatically replace placeholders with actual student data (see `LMS2_CONFIGURATION.md`)


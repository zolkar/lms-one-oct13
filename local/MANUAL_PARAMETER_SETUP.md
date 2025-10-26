# Manual Parameter Setup for LMS-2

Since Moodle (LMS-2) doesn't automatically replace AICC placeholders, you need to manually add student parameters to the .au file.

## Quick Solution: Manual Edit

### Step 1: Extract the AICC Package

Extract your AICC package (`dsfasd_aicc_1761474004.zip`).

### Step 2: Open the .au File

Open `dsfasd.au` in a text editor.

### Step 3: Find the web_launch URL

Look for a line like this:
```
"http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...&username={{student.username}}&email={{student.email}}..."
```

### Step 4: Replace Placeholders with Actual Values

Replace the parameters with actual values. For example:

**Before (with placeholders):**
```
&username={{student.username}}&email={{student.email}}&firstname={{student.firstname}}&lastname={{student.lastname}}
```

**After (with real values):**
```
&username=john.doe&email=john.doe@example.com&firstname=John&lastname=Doe
```

### Step 5: Complete Example

Here's what the final .au file should look like:

```csv
"system_id","title","parent","type","command_line","Max_Time_Allowed","time_limit_action","file_name","max_score","mastery_score","system_vendor","core_vendor","web_launch","AU_password"
"AU1","socrm","/","","","","","http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=eyJ0eXAi...&username=john.doe&email=john.doe@example.com&firstname=John&lastname=Doe","","","Moodle","","http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=eyJ0eXAi...&username=john.doe&email=john.doe@example.com&firstname=John&lastname=Doe",""
```

### Step 6: Re-import to LMS-2

1. Re-zip the package
2. Import it to LMS-2

### Step 7: Test

Launch the SCORM activity and check the logs:

```bash
docker logs apache_8 2>&1 | grep "AICC Launch Parameters" -A 15
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

## Alternative: Create Multiple Versions

If you have multiple students on LMS-2, create multiple .au files with different parameters:

- `dsfasd_student1.au` - with John's info
- `dsfasd_student2.au` - with Jane's info
- etc.

Then import each as a separate SCORM activity.

## Better Solution: LMS-2 Configuration

To avoid manual editing for each student, you would need to:

1. Modify LMS-2's AICC handler to replace placeholders
2. Or create a plugin on LMS-2 that wraps the launch URL with student data

This requires custom development on LMS-2 (outside the scope of this plugin).

## Testing Your Setup

After making the manual edit, test by checking the database:

```bash
docker exec mariadb_moodle bash -c "mysql -u root -pexample lms_one -e \"SELECT student_id, student_name, student_email FROM mdl_local_aicc_hacp_persistent_sessions ORDER BY created_at DESC LIMIT 1;\""
```

You should see the actual student email instead of `external_student_...@external-lms.local`.


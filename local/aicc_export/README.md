# AICC Export Plugin

This plugin allows you to export Moodle courses as AICC packages that can be shared with other Learning Management Systems (LMS) following proper AICC architecture.

## Features

- Export entire courses as AICC packages
- Generate proper AICC descriptor files (.crs, .cst, .des, .au, .ort, .pre, .cmp)
- URLs in packages point back to originating Moodle site
- Content remains hosted on originating LMS
- Uses HACP (HTTP AICC Communication Protocol) for communication
- Compatible with AICC 4.0 specification

## Installation

1. Copy the `aicc_export` folder to your Moodle `local/` directory
2. Visit the Site Administration > Notifications page to install the plugin
3. **Enable AICC HACP**: Run `/local/aicc_export/enable_hacp.php` to enable HACP communication
4. Configure the plugin settings in Site Administration > Plugins > Local plugins > AICC Export

## Usage

1. Navigate to any course
2. Go to the AICC Export page (usually accessible via course navigation)
3. Click "Export Course as AICC Package" to download the AICC package
4. The downloaded ZIP file contains descriptor files with URLs pointing back to this Moodle site
5. Import the package into other LMS systems - they will launch content from this Moodle site

## AICC Package Structure

The exported package contains **descriptor files only**:

- **Course files (.crs)**: Course metadata and information
- **Structure files (.cst)**: Course structure and activity hierarchy
- **Descriptor files (.des)**: Activity information with launch URLs
- **Assignable Unit files (.au)**: AU definitions with URLs to originating LMS
- **Objective files (.ort)**: Learning objectives (empty but properly formatted)
- **Prerequisite files (.pre)**: Prerequisites (empty but properly formatted)
- **Completion files (.cmp)**: Completion requirements (empty but properly formatted)

**Important**: The package does NOT contain actual content files. All URLs point back to the originating Moodle site where content remains hosted.

## Compatibility

- Exported packages can be imported into other LMS systems that support AICC standards
- Content is accessed via HACP (HTTP AICC Communication Protocol)
- Requires the `aicc_hacp` plugin to be enabled for external LMS communication
- Compatible with AICC 4.0 specification

## How It Works

1. **Export**: Creates AICC descriptor files with HACP URLs pointing to SCORM activities on this Moodle site
2. **Import**: External LMS imports the descriptor files as SCORM packages
3. **Launch**: When students access content, external LMS launches HACP URLs pointing to `/mod/scorm/aicc.php`
4. **HACP Communication**: External LMS communicates seamlessly with this Moodle site via HACP protocol
5. **No Login Required**: Students don't need to create accounts or login to the host Moodle site
6. **Content**: All actual content remains hosted on this Moodle site and is accessed via HACP

### AICC Package Structure

The exported package contains **descriptor files only** with a simplified structure:

- **Course files (.crs)**: Course metadata and information (Total_AUs = 1)
- **Structure files (.cst)**: Empty structure file (header only) to avoid parsing errors
- **Descriptor files (.des)**: Single AU1 element with HACP launch URL to SCORM activity
- **Assignable Unit files (.au)**: Identical to DES file (AU1 definition with HACP URL)
- **Objective files (.ort)**: Learning objectives (empty but properly formatted)
- **Prerequisite files (.pre)**: Prerequisites (empty but properly formatted)
- **Completion files (.cmp)**: Completion requirements (empty but properly formatted)

**Important**: The package creates a single AU1 element that launches SCORM activities via HACP. This allows seamless communication between external LMS and host Moodle site without requiring student login.

### HACP Integration

The package generates URLs that point directly to Moodle's AICC handler (`/mod/scorm/aicc.php`) with proper HACP session management:

- **HACP URLs**: `https://yoursite.com/local/aicc_export/content_launcher.php?id=activityid`
- **Content Access**: Serves actual SCORM content without requiring login
- **HACP Communication**: Handles both content display and protocol communication
- **Session Management**: Creates sessions in `scorm_aicc_session` table for external LMS communication
- **No Login Required**: Students access content seamlessly without creating accounts on host LMS
- **Seamless Communication**: External LMS communicates with host LMS via HACP protocol for tracking and data exchange

## Troubleshooting

### Common Issues

1. **"Invalid HACP session" error**:
   - Ensure `allowaicchacp` is enabled in SCORM settings
   - Run `/local/aicc_export/enable_hacp.php` to enable HACP
   - Check `/local/aicc_export/debug_sessions.php` to see existing sessions

2. **Redirecting to login page**:
   - Check that `allowaicchacp` is set to 1 in SCORM configuration
   - Verify the course has at least one SCORM activity

3. **Session timeout errors**:
   - Increase `aicchacptimeout` setting in SCORM configuration
   - Default is 60 minutes, recommended: 120 minutes

4. **External LMS session ID issues**:
   - The universal HACP handler can work with any session ID format
   - Sessions are created dynamically when the handler is called

### HACP Settings

Required SCORM settings for HACP to work:
- `allowaicchacp = 1` (Enable AICC HACP)
- `aicchacptimeout = 120` (HACP session timeout in minutes)

## Configuration

The plugin can be configured with the following settings:

- **Enable AICC Export**: Enable or disable the export functionality
- **Default AICC Version**: Set the default AICC version (default: 4.0)
- **Launch Token Secret**: Secret key for launch token signing
- **Launch Token TTL**: Time-to-live for launch tokens (in seconds)

## Troubleshooting

- Ensure SCORM activities contain actual content files
- Check that the plugin is enabled in Site Administration
- Verify user permissions for the export capability
- Check Moodle logs for any error messages

## Requirements

- Moodle 4.2 or later
- SCORM activities with content files
- Appropriate user permissions

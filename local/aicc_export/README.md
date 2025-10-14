# AICC Export Plugin

This plugin allows you to export SCORM activities from Moodle as AICC packages that can be shared with other Learning Management Systems (LMS).

## Features

- Export SCORM activities as complete AICC packages
- Include all course content files in the export
- Generate proper AICC descriptor files (.crs, .cst, .des, .au, .ort, .pre, .cmp)
- Compatible with Moodle's SCORM import functionality
- Self-contained packages that don't require external dependencies

## Installation

1. Copy the `aicc_export` folder to your Moodle `local/` directory
2. Visit the Site Administration > Notifications page to install the plugin
3. Configure the plugin settings in Site Administration > Plugins > Local plugins > AICC Export

## Usage

1. Navigate to a course containing SCORM activities
2. Go to the AICC Export page (usually accessible via course navigation)
3. Select a SCORM activity to export
4. Click "Export" to download the AICC package
5. The downloaded ZIP file contains all necessary files for import into other LMS systems

## AICC Package Structure

The exported package contains:

- **Course files (.crs)**: Course metadata and information
- **Structure files (.cst)**: Course structure and hierarchy
- **Descriptor files (.des)**: Detailed SCO information
- **Assignable Unit files (.au)**: AU definitions and launch information
- **Objective files (.ort)**: Learning objectives (if any)
- **Prerequisite files (.pre)**: Prerequisites (if any)
- **Completion files (.cmp)**: Completion requirements (if any)
- **Content files**: All HTML, CSS, JavaScript, and media files from the SCORM package

## Compatibility

- Exported packages can be imported into Moodle SCORM activities
- Compatible with other LMS systems that support AICC standards
- Follows AICC 4.0 specification guidelines

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

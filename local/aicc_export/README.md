# AICC Export Plugin

This plugin allows you to export Moodle courses as AICC packages. These packages can then be imported into other Learning Management Systems (LMS) that support the AICC standard.

## Installation

1.  Copy the `aicc_export` directory to the `local` directory of your Moodle installation.
2.  Log in to your Moodle site as an administrator and go to "Site administration" -> "Notifications".
3.  Moodle will automatically detect the new plugin and prompt you to install it. Follow the on-screen instructions to complete the installation.

## Configuration

1.  Go to "Site administration" -> "Plugins" -> "Local plugins" -> "AICC Export".
2.  Set a secret key for signing launch tokens. This key should be a long, random string to ensure security.
3.  Set the Time-To-Live (TTL) for launch tokens. This is the duration, in seconds, for which a launch token will be valid.

## Usage

1.  Go to the course you want to export.
2.  Add a SCORM activity to the course.
3.  Go to the SCORM activity settings and click on the "Export as AICC" link.
4.  An AICC package will be downloaded to your computer.

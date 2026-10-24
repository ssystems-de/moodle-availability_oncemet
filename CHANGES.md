moodle-availability_oncemet
===========================

Changes
-------

### Unreleased

* 2026-10-24 - Cleanup: Remove the redundant drop of the unlock table from the uninstaller as Moodle core drops the tables from db/install.xml on its own.
* 2026-10-24 - Bugfix: Describe the restriction on the "Existing unlocks" report properly. A restriction with a nested activity completion condition was shown as not configured, and the names of activities and grade items which nested restrictions point at were left out or shown as a bare id.

### v5.2-r3

* 2026-10-23 - Bugfix: Do not store permanent unlocks for users who may ignore availability restrictions (teachers and managers by default) as they pass the restriction anyway, resolves #2.
  Please note: Unlocks which have already been stored for such users are not removed automatically by this fix as they could only assess the roles at the moment of the upgrade, but do not know about the roles when the unlocks were set initially. If you want to get rid of them, you will have to reset all unlocks of a particular activity in its "Existing unlocks" report.
* 2026-10-22 - Tests: Open the course page with editing mode on directly in Behat instead of toggling the edit mode switch which is rendered as React component since Moodle 5.3 and made the scenarios flaky.

### v5.2-r2

* 2026-09-23 - Remove german language pack as the plugin is now translated in AMOS

### v5.2-r1

* 2026-07-23 - Initial release

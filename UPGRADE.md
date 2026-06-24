Upgrading this plugin
=====================

This is an internal documentation for plugin developers with some notes what has to be considered when updating this plugin to a new Moodle major version.

General
-------

* This plugin wraps nested availability restrictions and stores persistent unlock records in its own database table.
* It still depends on Moodle's core availability form API, including the YUI form modules used by availability conditions.
* Upgrading effort is usually moderate and should focus on the form UI and unlock evaluation.


Upstream changes
----------------

* This plugin does not inherit or copy anything from upstream sources.
* The nested restriction editing UI reuses Moodle core availability list/item behaviour. Re-test nested add/delete after core availability form changes.


Automated tests
---------------

* The plugin has a good coverage with Behat and PHPUnit tests which test all of the plugin's user stories.


Manual tests
------------

* There aren't any manual tests needed to upgrade this plugin.


Visual checks
-------------

* It might be advisable to have a look at the output of the plugin in the activity condition GUI as Moodle themes can always change small details in this area.

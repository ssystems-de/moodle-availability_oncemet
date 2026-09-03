moodle-availability_oncemet
============================

[![Moodle Plugin CI](https://github.com/ssystems-de/moodle-availability_oncemet/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=MOODLE_501_STABLE)](https://github.com/ssystems-de/moodle-availability_oncemet/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3AMOODLE_501_STABLE)

Moodle availability plugin which permanently remembers nested access restrictions once they have been fulfilled.

Requirements
------------

This plugin requires Moodle 5.1+


Motivation for this plugin
--------------------------

Standard Moodle availability conditions are re-evaluated continuously. That is usually correct, but some courses need a one-time unlock: once a learner has met a set of nested restrictions, access should stay open even if those nested restrictions later change or no longer apply.

Once met wraps other availability conditions and stores a persistent unlock for that Once met instance on the activity or section. Removing the Once met restriction itself ends the permanent unlock.


Installation
------------

Install the plugin like any other plugin to folder
/availability/condition/oncemet

See https://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it is ready to use without the need for any configuration.

Teachers (and other roles who can edit availability restrictions) can add the "Once met" availability condition to activities / resources / sections. Inside Once met they nest one or more normal availability restrictions. When a user matches those nested restrictions once, access remains unlocked for that Once met instance.

If you want to learn more about using availability plugins in Moodle, please see https://docs.moodle.org/en/Restrict_access.


Capabilities
------------

This plugin also introduces these additional capabilities:

### availability/oncemet:addinstance

This capability controls who is able to add Once met conditions to activities and course sections.
It is assigned to the manager and to the editing teacher role by default.

Withdrawing this capability only hides the restriction from the "Add restriction..." dialogue.
Once met restrictions which have already been added continue to apply and can still be removed.

### availability/oncemet:viewunlocks

This capability controls who is able to see which users have already unlocked a Once met condition permanently.
It is assigned to the manager and to the editing teacher role by default.

### availability/oncemet:resetunlock

This capability controls who is able to take a permanent unlock away from users again.
It is assigned to the manager and to the editing teacher role by default.

This capability is of no use without availability/oncemet:viewunlocks, as the unlocks are reset from the very report which that capability opens.


Scheduled Tasks
---------------

This plugin does not add any additional scheduled tasks.


How this plugin works / Pitfalls
--------------------------------

The internal workings of this availability plugin are quite easy to understand: It is simply a wrapper which holds other availability restrictions, similar to the way how the 'restriction set' in Moodle core works.

The contained restrictions are evaluated through Moodle's normal availability tree which means that they are applied as usual and as expected.
But as soon as they match for a particular student and unlock the activity for him / her on a course page, the plugin stores an unlock record keyed by a stable Once met instance id. This unlock record allows that student to access the activity even if the contained restrictions do not apply anymore sometime in the future (for example if a group restriction is contained and the student leaves the group) or if the contained restrictions are modified by the teacher (and the student does not fulfil the new requirements anymore).

Important things to know for correctly using the plugin:

* The unlock record is written the first time that Moodle evaluates the Once met restriction for a particular student while the contained restrictions are fulfilled. That normally happens when the student opens the course page, but it is by no means the only occasion: any code which asks Moodle whether an activity / section is available for a particular student evaluates the contained restrictions as that student and therefore writes the unlock record for him / her. Scheduled tasks do that as well, and they do it for all enrolled users of a course at once - the tasks which send out the activity reminders of Moodle core are the most common example. Put briefly: "once met" means "once evaluated as met" and not "once seen as met by the student". Please do not build a course on the assumption that only the students who really looked at it during the relevant period end up with an unlock.
* As a consequence of that, using Moodle's "Log in as" feature on a student evaluates the contained restrictions as that student and can therefore write an unlock record for him / her. The record is not wrong, as the contained restrictions really were fulfilled at that moment, but administrators should know that looking at a course this way is not free of side effects.
* For the same reason, teachers and administrators gather unlock records as well, even though a Once met condition never restricts them: Moodle evaluates the availability of an activity / section before it checks whether the user is allowed to ignore access restrictions at all. These records are never used, as the moodle/course:ignoreavailabilityrestrictions capability takes precedence over any restriction, and they are removed together with the records of everybody else as soon as the Once met condition or the activity / section is gone. The only place where they ever become visible is a privacy export, which then lists unlock records for activities which were never restricted for that person in the first place.
* As said, changing the contained restrictions of a Once met instance does not revoke an existing unlock for that Once met instance. The unlock is tied to the Once met instance itself and not to the restrictions which it contained at the time the student got through it. That is what allows teachers to correct a mistake in a contained restriction or to replace it with a better one without locking out the students who already gained their access. The flip side is that tightening the contained restrictions never locks a student out again.
* But removing the Once met condition from the activity / section ends the permanent unlock effect for that place. The unlock records which belong to it are deleted as soon as the activity or the section is saved without it. Adding the very same Once met condition back afterwards therefore does not bring the old unlocks back, the students have to fulfil the contained restrictions again. If you only want to change what a Once met condition contains, edit the contained restrictions instead of removing the Once met condition and adding a new one.
* If the availability plugin of a contained restriction is disabled or uninstalled at some point, that contained restriction is ignored when Moodle decides who may access the activity / section, exactly as Moodle core ignores it when such a restriction was added to an activity / section directly. The remaining contained restrictions of the Once met condition continue to apply as usual and the students who already gained an unlock keep their access. A Once met condition which is left without any contained restriction this way is treated as an unconfigured one, which never grants access to anybody who does not hold an unlock for it already.
* The ignored restriction is not deleted, though. It stays in the availability settings of the activity / section and applies again as soon as its plugin is enabled again. Until then, however, teachers cannot save the availability settings of the affected activity / section at all: Moodle marks the restriction as an unknown one in the availability form and refuses to save the form until it is removed. This is Moodle core behaviour which applies to restrictions inside a Once met condition just as it applies to restrictions which were added directly, and removing the restriction to get the form saved does delete it for good.
* Multiple Once met blocks on the same activity are treated as separate instances and hold their individual unlock records. A student who got through one of them is not automatically through another one, and removing one of them leaves the unlock records of the others untouched. The same applies to a Once met condition which is nested inside another Once met condition, as each of the two is an instance of its own.
* Which users hold an unlock is shown on the unlock report of a Once met condition, see the availability/oncemet:viewunlocks capability above. Please note that this report lists every unlock record which belongs to the condition, including the ones which teachers and administrators have gathered themselves and which are never used, as explained above.
* The fact that a Once met restriction is applied to an activity / section is just shown to the teachers. Students see the contained restrictions on the course page just like normal restrictions, without any hint that these restrictions are wrapped into a Once met condition and without any hint that they might have been unlocked permanently already. This is intentional, as the wrapper is a matter of course design which students do not have to reason about.
* Unlock records are not part of a course backup, not even of a backup which is taken with user data. Moodle only lets certain plugin types add data of their own to a backup, for example activity modules, course formats and local plugins, and availability plugins are not among them. The Once met conditions themselves do survive a backup, as they are part of the availability settings of the activities and course sections which Moodle backs up anyway, but the unlocks which students have gained for them do not. Restoring a course therefore hands the students a Once met condition which nobody has got through yet, and they have to fulfil the contained restrictions again. The same is true for duplicating an activity within a course: the copy carries the Once met condition, but not the unlocks of the original.
* Resetting a course only clears the unlock records if the reset unenrols the users of the course. What a student has met once stays met, so a reset which just wipes activity completion or gradebook grades leaves the unlock records untouched. Please note that the course reset page does not offer an option of its own for this, as Moodle only lets activity modules add options there.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is published and regularly updated in the Moodle plugins repository:
http://moodle.org/plugins/view/availability_oncemet

The latest development version can be found on Github:
https://github.com/ssystems-de/moodle-availability_oncemet


Bug and problem reports
-----------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/ssystems-de/moodle-availability_oncemet/issues


Community feature proposals
---------------------------

The functionality of this plugin is primarily implemented for the needs of our clients and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/ssystems-de/moodle-availability_oncemet/issues

Please create pull requests on Github:
https://github.com/ssystems-de/moodle-availability_oncemet/pulls


Paid support
------------

We are always interested to read about your issues and feature proposals or even get a pull request from you on Github. However, please note that our time for working on community Github issues is limited.

As solution provider, we also offer paid support for this plugin. If you are interested, please have a look at our services on [ssystems.de](https://www.ssystems.de/) or get in touch with us directly via vertrieb@ssystems.de.


Moodle release support
----------------------

This plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

If you are running a legacy version of Moodle, but want or need to run the latest version of this plugin, you can get the latest version of the plugin, remove the line starting with $plugin->requires from version.php and use this latest plugin version then on your legacy Moodle. However, please note that you will run this setup completely at your own risk. We can't support this approach in any way and there is an undeniable risk for erratic behavior.


Translating this plugin
-----------------------

This Moodle plugin is shipped with an english language pack only. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.

As the plugin creator, we manage the translation into german for our own local needs on AMOS. Please contribute your translation into all other languages in AMOS where they will be reviewed by the official language pack maintainers for Moodle.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
ssystems GmbH


Copyright
---------

The copyright of this plugin is held by\
ssystems GmbH

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.

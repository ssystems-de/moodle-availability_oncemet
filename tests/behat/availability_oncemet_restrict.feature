@availability @availability_oncemet @availability_oncemet_restrict
Feature: Keep an item unlocked once its Once met restrictions have been fulfilled
  In order to open a learning path without ever closing it again
  As a teacher
  I need Once met restrictions to remember which learners have fulfilled them

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t@example.com  |
      | learner1 | Learner   | One      | l@example.com  |
      | learner2 | Learner   | Two      | l2@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | learner1 | C1     | student        |
      | learner2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name   | idnumber |
      | page     | C1     | 1       | Page 1 | PAGE1    |
      | page     | C1     | 2       | Page 2 | PAGE2    |

  # This is the one scenario which lets a learner earn an unlock through the user interface. It is
  # what backs the shortcut which every other scenario takes, namely the unlock records written by
  # the "availability_oncemet > unlocks" generator step.
  @javascript
  Scenario: A learner who meets the nested restrictions keeps access after they stop applying
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    # Viewing the course while the nested restriction applies is what stores the unlock.
    When I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    # The nested restriction stops applying, but the stored unlock keeps the access.
    And I am on the "l@example.com" "user > editing" page logged in as "admin"
    And I expand all fieldsets
    And I set the field "Email address" to "other@example.com"
    And I click on "Update profile" "button"
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    # A learner who never met the nested restriction never gained an unlock and stays locked out.
    And I log out
    And I am on the "Course 1" course page logged in as "learner2"
    Then "Page 1" "link" should not exist in the "region-main" "region"

  Scenario: The unlock of one learner does not open the item for another
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    When I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    And I log out
    And I am on the "Course 1" course page logged in as "learner2"
    Then "Page 1" "link" should not exist in the "region-main" "region"
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"

  @javascript
  Scenario: The unlock survives the teacher changing the nested restrictions
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    # The teacher points the nested restriction at something neither learner matches. Saving the
    # form has to keep the instance id, as the unlock is tied to it and would be orphaned otherwise.
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I set the field "Value to compare against" to "q@example.com"
    And I click on "Save and return to course" "button"
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    # The new nested restriction is really in effect, it is only the unlock which overrides it.
    And I log out
    And I am on the "Course 1" course page logged in as "learner2"
    Then "Page 1" "link" should not exist in the "region-main" "region"
    And I should see "Your Email address is q@example.com" in the "Page 1" "core_availability > Activity availability"

  @javascript
  Scenario: Two Once met restrictions on one activity keep their own unlocks when the activity is saved
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid  | rootop | profile                    |
      | PAGE1    | UNLOCKKEPT  | or     | email = nobody@example.com |
      | PAGE1    | UNLOCKOTHER | or     | email = never@example.com  |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCKKEPT |
    And I am on the "Course 1" course page logged in as "learner1"
    And "Page 1" "link" should exist in the "region-main" "region"
    # The teacher saves the activity without changing anything at all. Both Once met restrictions
    # have to keep their own instance id, as the unlock of the first one is lost otherwise.
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Save and return to course" "button"
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"

  @javascript
  Scenario: Adding a second Once met restriction keeps the unlock of the saved one
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    And I am on the "Course 1" course page logged in as "learner1"
    And "Page 1" "link" should exist in the "region-main" "region"
    # The teacher adds a second Once met restriction next to the saved one. The saved one has to keep
    # its instance id, as the unlock record which belongs to it is dropped otherwise.
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button" in the ".availability-field > .availability-list > .availability-inner > .availability-button" "css_element"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item .availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    # The saved restriction carries fields of the same name, so the new one has to be addressed within
    # its own Once met block.
    And I set the field "User profile field" in the ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item .availability_oncemet" "css_element" to "Email address"
    And I set the field "Value to compare against" in the ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item .availability_oncemet" "css_element" to "l@example.com"
    And I click on "Save and return to course" "button"
    # Both restrictions have to be fulfilled now. The added one applies to the learner directly, the
    # saved one only through its stored unlock.
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    # The other learner meets neither of them.
    And I log out
    And I am on the "Course 1" course page logged in as "learner2"
    Then "Page 1" "link" should not exist in the "region-main" "region"

  Scenario: A Once met restriction on a course section unlocks its content permanently
    Given the following "availability_oncemet > section restrictions" exist:
      | course | section | instanceid | profile                    |
      | C1     | 1       | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | course | section | instanceid |
      | learner1 | C1     | 1       | UNLOCK1    |
    When I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    And I log out
    And I am on the "Course 1" course page logged in as "learner2"
    Then "Page 1" "link" should not exist in the "region-main" "region"
    # The restriction sits on the first section only, the rest of the course is untouched.
    And "Page 2" "link" should exist in the "region-main" "region"

  @javascript
  Scenario: Removing the Once met restriction ends the permanent unlock
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    And I am on the "Course 1" course page logged in as "learner1"
    And "Page 1" "link" should exist in the "region-main" "region"
    # The delete icon of the Once met block itself sits in the root list of the form. The nested
    # restrictions carry one of their own, hence the path down from the root rather than a plain
    # ".availability-item .availability-delete".
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete img" "css_element"
    # The restriction holds an unlock of the learner, so removing it has to be confirmed first.
    And I click on "Remove restriction" "button" in the "Remove the Once met restriction?" "dialogue"
    And I click on "Save and return to course" "button"
    # Putting the very same restriction back locks the learner out again, which is only possible
    # if saving the activity without it really did drop the unlock record.
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should not exist in the "region-main" "region"

  @javascript
  Scenario: Removing one of two Once met restrictions keeps the unlock of the other
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCKKEPT | email = nobody@example.com |
      | PAGE1    | UNLOCKGONE | email = never@example.com  |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCKKEPT |
      | learner1 | PAGE1    | UNLOCKGONE |
    And I am on the "Course 1" course page logged in as "learner1"
    And "Page 1" "link" should exist in the "region-main" "region"
    # The teacher removes the second Once met block only. Its unlock becomes obsolete, but the
    # first block is still there and its unlock is the only thing which keeps the item open.
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item > .availability-delete img" "css_element"
    # The removed restriction holds an unlock of the learner, so its removal has to be confirmed first.
    And I click on "Remove restriction" "button" in the "Remove the Once met restriction?" "dialogue"
    And I click on "Save and return to course" "button"
    # The two blocks are told apart by their nested restriction, so this pins down that it really
    # was the second one which went and the first one which stayed.
    Then I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"
    And I should not see "never@example.com" in the "Page 1" "core_availability > Activity availability"
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"

  Scenario: A hidden Once met restriction removes the item from the course page entirely
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    | hidden |
      | PAGE1    | UNLOCK1    | email = nobody@example.com | 1      |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    When I am on the "Course 1" course page logged in as "learner2"
    Then I should not see "Page 1" in the "region-main" "region"
    And I should not see "Your Email address is nobody@example.com" in the "region-main" "region"
    And I log out
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"

  Scenario: A nested restriction whose plugin is disabled is ignored and the remaining ones still apply
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | nested                                                                                                                            |
      | PAGE1    | UNLOCK1    | {"op":"&","c":[{"type":"profile","op":"isequalto","sf":"email","v":"nobody@example.com"},{"type":"date","d":">=","t":1577836800}]} |
    # The date restriction passed long ago, so the profile restriction is the only thing which locks
    # the learner out here, and it does.
    When I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should not exist in the "region-main" "region"
    # The profile restriction is gone from now on. The date restriction is not, so it is the one
    # which decides and it opens the activity.
    And I log out
    And I disable "profile" "availability" plugin
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"

  Scenario: An unlock keeps applying when the plugin of the only nested restriction is disabled
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    When I disable "profile" "availability" plugin
    # The Once met restriction is left without any nested restriction at all, which makes it an
    # unconfigured one. The learner who got through it while it still worked keeps the access.
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should exist in the "region-main" "region"
    # Everybody else stays locked out, and the activity is still on the page with a reason rather
    # than having disappeared from it.
    And I log out
    And I am on the "Course 1" course page logged in as "learner2"
    Then "Page 1" "link" should not exist in the "region-main" "region"
    And I should see "This restriction is not configured correctly." in the "Page 1" "core_availability > Activity availability"

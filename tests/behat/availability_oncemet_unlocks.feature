@availability @availability_oncemet @availability_oncemet_unlocks
Feature: Look at and reset the unlocks of a Once met restriction
  In order to know who has gained permanent access and to take it away again
  As a teacher or manager
  I need a report which lists the unlocks of a Once met restriction

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
      | manager1 | Manager   | One      | m1@example.com |
      | learner1 | Anna      | Adams    | l1@example.com |
      | learner2 | Bernd     | Berger   | l2@example.com |
      | learner3 | Clara     | Clark    | l3@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | topics | 2           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
      | learner1 | C1     | student        |
      | learner2 | C1     | student        |
      | learner3 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name   | idnumber |
      | page     | C1     | 1       | Page 1 | PAGE1    |
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
      | learner2 | PAGE1    | UNLOCK1    |

  Scenario: The report lists the users who hold an unlock with the time they gained it
    When I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    Then I should see "Existing unlocks"
    And I should see "This report lists the users who have permanently unlocked the following Once met restriction of \"Page 1\""
    And the following should exist in the "availability-oncemet-unlocks" table:
      | First name | Last name |
      | Anna       | Adams     |
      | Bernd      | Berger    |
    And I should not see "Clara" in the "availability-oncemet-unlocks" "table"
    # The unlocks were stored just now, so the formatted date has to carry today's date.
    And I should see "## today ##%d %B %Y##" in the "availability-oncemet-unlocks" "table"

  Scenario: A manager can look at the report as well
    When I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "manager1"
    Then I should see "Anna" in the "availability-oncemet-unlocks" "table"

  # Users who may not see the report at all, and identifiers which do not belong to the item, are
  # turned away by require_capability() and by an exception. Neither of the two can be checked here,
  # as Behat fails any step which lands on a Moodle error page, no matter whether that page is the
  # expected outcome. What can be checked is that the report is not offered in the first place, which
  # is what the last scenario of this file does.

  Scenario: A restriction which nobody has unlocked yet has a report of its own
    Given the following "activities" exist:
      | activity | course | section | name   | idnumber |
      | page     | C1     | 1       | Page 2 | PAGE2    |
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE2    | UNLOCK3    | email = nobody@example.com |
    When I am on the "PAGE2 > UNLOCK3" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    Then I should see "Existing unlocks"
    And I should see "Nothing to display"
    And "Reset unlock" "button" should not exist

  # An activity can carry several Once met restrictions, and their instance ids are UUIDs which say
  # nothing to a reader, so the report describes the nested restrictions of the one it is about.
  Scenario: The report says which of several Once met restrictions it is about
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile        |
      | PAGE1    | UNLOCK2    | city = Rijeka  |
    When I am on the "PAGE1 > UNLOCK2" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    Then I should see "Rijeka" in the ".availability-oncemet-unlockrestriction" "css_element"
    And I should not see "nobody@example.com" in the ".availability-oncemet-unlockrestriction" "css_element"
    And I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page
    And I should see "nobody@example.com" in the ".availability-oncemet-unlockrestriction" "css_element"
    And I should not see "Rijeka" in the ".availability-oncemet-unlockrestriction" "css_element"

  Scenario: The report of a course section restriction lists its own unlocks
    Given the following "availability_oncemet > section restrictions" exist:
      | course | section | instanceid | profile                    |
      | C1     | 2       | UNLOCK2    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | course | section | instanceid |
      | learner3 | C1     | 2       | UNLOCK2    |
    When I am on the "C1 > 2 > UNLOCK2" "availability_oncemet > section unlocks report" page logged in as "teacher1"
    Then I should see "Clara" in the "availability-oncemet-unlocks" "table"
    And I should not see "Anna" in the "availability-oncemet-unlocks" "table"

  # A report which was opened without a return url, as it is the case for these scenarios, falls back
  # to the course page. The round trip from the availability form is checked further down.
  Scenario: The back button falls back to the course page
    Given I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    When I click on "Back" "link" in the ".availability-oncemet-unlockback" "css_element"
    Then I should see "Course 1"
    And "Page 1" "link" should exist in the "region-main" "region"

  Scenario: The initials filter narrows the report down to the users whose name starts with the letter
    Given I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    When I click on "#lastinitial_page-item_B a" "css_element"
    Then I should see "Bernd" in the "availability-oncemet-unlocks" "table"
    And I should not see "Anna" in the "availability-oncemet-unlocks" "table"
    And I click on "#firstinitial_page-item_A a" "css_element"
    # First name A and last name B do not go together, so nobody is left.
    And I should see "Nothing to display"
    And I click on "#lastinitial_page-item_All a" "css_element"
    And I should see "Anna" in the "availability-oncemet-unlocks" "table"
    And I should not see "Bernd" in the "availability-oncemet-unlocks" "table"

  Scenario: A teacher without the resetunlock capability sees the report without the bulk controls
    Given the following "permission overrides" exist:
      | capability                       | permission | role           | contextlevel | reference |
      | availability/oncemet:resetunlock | Prohibit   | editingteacher | Course       | C1        |
    When I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    Then I should see "Anna" in the "availability-oncemet-unlocks" "table"
    And "Reset unlock" "button" should not exist
    And "Select all" "button" should not exist
    And "Select 'Anna Adams'" "checkbox" should not exist

  @javascript
  Scenario: The reset button stays out of reach until a user is selected
    Given I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    Then the "Reset unlock" "button" should be disabled
    When I click on "Select 'Anna Adams'" "checkbox"
    Then the "Reset unlock" "button" should be enabled
    # Taking the selection back locks the button again, so it never invites a reset of nothing.
    And I click on "Select 'Anna Adams'" "checkbox"
    And the "Reset unlock" "button" should be disabled

  @javascript
  Scenario: A teacher resets the unlock of a single user
    Given I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    When I click on "Select 'Anna Adams'" "checkbox"
    And I click on "Reset unlock" "button"
    Then I should see "The permanent unlock was reset for 1 users"
    And I should not see "Anna" in the "availability-oncemet-unlocks" "table"
    And I should see "Bernd" in the "availability-oncemet-unlocks" "table"

  @javascript
  Scenario: Resetting an unlock takes the permanent access away again
    Given I am on the "Course 1" course page logged in as "learner1"
    And "Page 1" "link" should exist in the "region-main" "region"
    And I log out
    And I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    When I click on "Select 'Anna Adams'" "checkbox"
    And I click on "Reset unlock" "button"
    And I log out
    And I am on the "Course 1" course page logged in as "learner1"
    Then "Page 1" "link" should not exist in the "region-main" "region"
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"

  @javascript
  Scenario: The select all link ticks every user of the report
    Given I am on the "PAGE1 > UNLOCK1" "availability_oncemet > activity unlocks report" page logged in as "teacher1"
    When I click on "Select all" "button"
    Then the field "Select 'Anna Adams'" matches value "1"
    And the field "Select 'Bernd Berger'" matches value "1"
    And I click on "Reset unlock" "button"
    And I should see "The permanent unlock was reset for 2 users"
    And I should see "Nothing to display"

  @javascript
  Scenario: The availability form links to the report and the report leads back to the form
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on "Review existing unlocks" "link" in the ".availability_oncemet" "css_element"
    Then I should see "Existing unlocks"
    And I should see "Anna" in the "availability-oncemet-unlocks" "table"
    # The report was opened from the settings of the activity, so that is where the back button goes.
    And I click on "Back" "link" in the ".availability-oncemet-unlockback" "css_element"
    And the field "Name" matches value "Page 1"

  @javascript
  Scenario: A restriction which is only being added right now has no report to link to
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    # The stored restriction keeps its button, the one which was just added does not get one. The
    # second block of the root list is the one which was just added, hence the sibling selector.
    Then "Review existing unlocks" "link" should exist in the ".availability_oncemet" "css_element"
    And ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item .availability-oncemet-report a" "css_element" should not exist

  @javascript
  Scenario: A teacher without the viewunlocks capability is not offered the report in the form
    Given the following "permission overrides" exist:
      | capability                       | permission | role           | contextlevel | reference |
      | availability/oncemet:viewunlocks | Prohibit   | editingteacher | Course       | C1        |
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then I should see "Email address" in the ".availability_oncemet" "css_element"
    And I should not see "Review existing unlocks"

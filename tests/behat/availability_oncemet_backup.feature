@availability @availability_oncemet @availability_oncemet_backup @javascript
Feature: Copy courses and activities which contain Once met restrictions
  In order to reuse a course without rebuilding its learning path
  As a teacher
  I need Once met restrictions to keep working after duplicating, backing up and restoring

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following config values are set as admin:
      | enableasyncbackup | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email         |
      | teacher1 | Teacher   | One      | t@example.com |
      | learner1 | Learner   | One      | l@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
      | Course 2 | C2        | topics | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | learner1 | C1     | student        |
      | learner1 | C2     | student        |
    And the following "activities" exist:
      | activity | course | section | name   | idnumber | completion |
      | page     | C1     | 1       | Gate   | GATE     | 1          |
      | page     | C1     | 1       | Target | TARGET   | 0          |

  # Both activities carry the same Once met instance id after the copy, so an unlock which leaked
  # from one to the other would go unnoticed anywhere else. Unlock records belong to one activity.
  Scenario: A duplicated activity keeps the restriction but not the unlock
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | TARGET   | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | TARGET   | UNLOCK1    |
    And I am on the "Course 1" course page logged in as "teacher1"
    And I turn editing mode on
    When I duplicate "Target" activity
    And I log out
    And I am on the "Course 1" course page logged in as "learner1"
    # The original stays open through the unlock which the learner holds for it.
    Then "Target" "link" should exist in the "region-main" "region"
    And I should see "Your Email address is nobody@example.com" in the "Target (copy)" "core_availability > Activity availability"
    And "Target (copy)" "link" should not exist in the "region-main" "region"

  Scenario: The restriction survives a restore into another course without carrying the unlock
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | TARGET   | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | TARGET   | UNLOCK1    |
    And I am on the "Course 1" course page logged in as "admin"
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into "Course 2" course using this options:
    And I log out
    And I am on the "Course 2" course page logged in as "learner1"
    Then I should see "Your Email address is nobody@example.com" in the "Target" "core_availability > Activity availability"
    And "Target" "link" should not exist in the "region-main" "region"
    # The unlock of the original course is untouched by all of this.
    And I am on the "Course 1" course page
    And "Target" "link" should exist in the "region-main" "region"

  # Course sections carry their restrictions in a field of their own, so what holds for an activity
  # has to be shown separately for them.
  #
  # The restore has to create the section for its restriction to come across. Merging into a course
  # which already has that section keeps the section as it is, on purpose and regardless of which
  # plugin the restriction belongs to, see the comment at "Section exists, update non-empty
  # information" in restore_section_structure_step::process_section(). The backup therefore goes
  # into a new course here, which is also why this is checked as staff: nobody is enrolled there.
  Scenario: A section restriction survives a restore into a new course
    Given the following "availability_oncemet > section restrictions" exist:
      | course | section | instanceid | profile                    |
      | C1     | 1       | UNLOCK1    | email = nobody@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | course | section | instanceid |
      | learner1 | C1     | 1       | UNLOCK1    |
    And I am on the "Course 1" course page logged in as "admin"
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name       | Course 3 |
      | Schema | Course short name | C3       |
    # The restriction is in place with its nested tree intact, and is named as a Once met one.
    Then I should see "Met at least once:" in the "section-1" "core_availability > Section availability"
    And I should see "Your Email address is nobody@example.com" in the "section-1" "core_availability > Section availability"
    # The unlock belongs to the section of the original course and is untouched by all of this.
    And I am on the "Course 1" course page logged in as "learner1"
    And "Gate" "link" should exist in the "region-main" "region"

  # A nested restriction which names another activity stores its course module id as a number. The
  # restore has to rewrite that number to the id of the restored copy, which it only does for
  # conditions it can reach: the Once met condition is a leaf for core, so everything nested inside
  # it is only reached through availability_oncemet\condition::update_after_restore().
  Scenario: A nested restriction which points at another activity is remapped on restore
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | nested                                                            |
      | TARGET   | UNLOCK1    | {"op":"&","c":[{"type":"completion","cm":"##cmid:GATE##","e":1}]} |
    And I am on the "Course 1" course page logged in as "admin"
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into "Course 2" course using this options:
    And I log out
    # Naming the gate proves that the restore mapped it instead of losing it along the way.
    And I am on the "Course 2" course page logged in as "learner1"
    Then I should see "The activity Gate is marked complete" in the "Target" "core_availability > Activity availability"
    And "Target" "link" should not exist in the "region-main" "region"
    # Completing the gate of this course opens the target. That only works if the nested condition
    # points at the restored gate rather than at the one which stayed behind in Course 1.
    When I click on "Gate" "link" in the "region-main" "region"
    And I press "Mark as done"
    And I am on the "Course 2" course page
    Then "Target" "link" should exist in the "region-main" "region"

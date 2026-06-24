@availability @availability_oncemet @availability_oncemet_permissions @javascript
Feature: Control who can add Once met restrictions
  In order to keep the Once met restriction in the hands of the intended roles
  As a teacher or manager
  I need the restriction to be offered only to users who hold the addinstance capability

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email         |
      | teacher1 | Teacher   | One      | t@example.com |
      | manager1 | Manager   | One      | m@example.com |
      | learner1 | Learner   | One      | l@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
      | learner1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name   | idnumber |
      | page     | C1     | 1       | Page 1 | PAGE1    |

  Scenario: A teacher can add the restriction
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on "Add restriction..." "button"
    Then I should see "Once met" in the "Add restriction..." "dialogue"

  Scenario: A manager can add the restriction
    Given I am on the "Page 1" "page activity editing" page logged in as "manager1"
    And I expand all fieldsets
    When I click on "Add restriction..." "button"
    Then I should see "Once met" in the "Add restriction..." "dialogue"

  Scenario: A teacher without the addinstance capability cannot add the restriction, but an existing one still applies
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "permission overrides" exist:
      | capability                       | permission | role           | contextlevel | reference |
      | availability/oncemet:addinstance | Prohibit   | editingteacher | Course       | C1        |
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    Then I should not see "Once met" in the "Add restriction..." "dialogue"
    And I log out
    And I am on the "Course 1" course page logged in as "learner1"
    And I should not see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"
    And "Page 1" "link" should not exist in the "region-main" "region"

  Scenario: An existing restriction stays visible and removable without the capability
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    And the following "permission overrides" exist:
      | capability                       | permission | role           | contextlevel | reference |
      | availability/oncemet:addinstance | Prohibit   | editingteacher | Course       | C1        |
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then I should see "Email address" in the ".availability_oncemet" "css_element"
    # The delete icon of the block itself is a sibling of the plugin node, not a descendant of it.
    # A plain ".availability_oncemet .availability-delete" would only find the icon of the nested
    # restriction and would pass even if the block itself could no longer be removed.
    And ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete" "css_element" should exist
    # Removing it and saving has to work, which is what the capability may not take away.
    And I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete img" "css_element"
    And I click on "Save and return to course" "button"
    And I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    And ".availability_oncemet" "css_element" should not exist

  # A course section has no context of its own, so the capability of a section form is checked in
  # the context of the course, see availability_oncemet\frontend::get_edit_context().
  Scenario: The restriction is offered in the section form
    Given I am on the "Course 1" course page logged in as "teacher1"
    And I turn editing mode on
    And I edit the section "1"
    And I expand all fieldsets
    When I click on "Add restriction..." "button"
    Then I should see "Once met" in the "Add restriction..." "dialogue"

  Scenario: A teacher without the addinstance capability cannot add the restriction to a section either
    Given the following "permission overrides" exist:
      | capability                       | permission | role           | contextlevel | reference |
      | availability/oncemet:addinstance | Prohibit   | editingteacher | Course       | C1        |
    And I am on the "Course 1" course page logged in as "teacher1"
    And I turn editing mode on
    And I edit the section "1"
    And I expand all fieldsets
    When I click on "Add restriction..." "button"
    Then I should not see "Once met" in the "Add restriction..." "dialogue"
    # Other restrictions are still on offer, so the dialogue itself is fine.
    And I should see "Date" in the "Add restriction..." "dialogue"

@availability @availability_oncemet @availability_oncemet_form @javascript
Feature: Configure the Once met restriction in the availability form
  In order to set up a permanent unlock without surprises
  As a teacher
  I need the Once met block to hold nested restrictions, to validate them and to store them again unchanged

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email         |
      | teacher1 | Teacher   | One      | t@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | section | name   | idnumber |
      | page     | C1     | 1       | Page 1 | PAGE1    |

  Scenario: A Once met restriction with a nested restriction survives the round trip
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    And I set the field "User profile field" to "Email address"
    And I set the field "Value to compare against" to "l@example.com"
    And I click on "Save and return to course" "button"
    When I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    # Core builds the accessible heading of an item from the title string of its plugin, so this is
    # what names the block as a Once met one rather than as some anonymous restriction.
    Then I should see "Once met" in the ".availability-item > h3" "css_element"
    And ".availability_oncemet .availability_profile" "css_element" should exist
    And the field "Value to compare against" matches value "l@example.com"

  Scenario: Saving a Once met restriction without a nested restriction is rejected
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    When I press "Save and return to course"
    Then I should see "Add at least one restriction." in the "#id_error_availabilityconditionsjson" "css_element"
    And "Add restriction..." "button" should exist

  Scenario: The Once met block explains what the permanent unlock does
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    Then I should see "Add restrictions that should be remembered once fulfilled." in the ".availability_oncemet" "css_element"
    And I should see "Users who met the nested restrictions once keep access even if those restrictions later change or no longer apply." in the ".availability_oncemet" "css_element"
    And I should see "Removing this Once met restriction from the activity or course section removes the permanent unlock." in the ".availability_oncemet" "css_element"

  # Core deletes conditions through the root list, which cannot see anything rendered inside a
  # plugin control. The plugin therefore routes delete clicks within the block to the nested list
  # itself, see M.availability_oncemet.form.bindNestedDeletes().
  Scenario: A nested restriction can be removed again inside the Once met block
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    And I should see "User profile field" in the ".availability_oncemet" "css_element"
    When I click on ".availability-oncemet-children .availability-item > .availability-delete img" "css_element"
    Then I should not see "User profile field" in the ".availability_oncemet" "css_element"
    # The Once met block itself stays behind, and is empty again.
    And ".availability_oncemet" "css_element" should exist
    And I press "Save and return to course"
    And I should see "Add at least one restriction." in the "#id_error_availabilityconditionsjson" "css_element"

  # The other half of bindNestedDeletes(): a nested list is deleted through the icon which core
  # puts into its "None" placeholder, and reaches the plugin as a list rather than as an item.
  Scenario: A nested restriction set can be added and removed inside the Once met block
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    When I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "Restriction set" "button" in the "Add restriction..." "dialogue"
    Then ".availability-oncemet-children .availability-children .availability-childlist" "css_element" should exist
    When I click on ".availability-oncemet-children .availability-children .availability-none .availability-delete img" "css_element"
    Then ".availability-oncemet-children .availability-children .availability-childlist" "css_element" should not exist

  Scenario: Removing a Once met restriction which holds unlocks has to be confirmed (Teacher clicks Cancel)
    Given the following "users" exist:
      | username | firstname | lastname | email         |
      | learner1 | Learner   | One      | l@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | learner1 | C1     | student |
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete img" "css_element"
    Then I should see "Other users have already unlocked this Once met restriction permanently." in the "Remove the Once met restriction?" "dialogue"
    And I should see "Removing it deletes their permanent unlocks as soon as you save this form." in the "Remove the Once met restriction?" "dialogue"
    And I should see "Adding the very same restriction again afterwards does not bring the unlocks back" in the "Remove the Once met restriction?" "dialogue"
    # Backing out of the question leaves the restriction exactly where it was.
    And I click on "Cancel" "button" in the "Remove the Once met restriction?" "dialogue"
    And ".availability_oncemet .availability_profile" "css_element" should exist

  Scenario: Removing a Once met restriction which holds unlocks has to be confirmed (Teacher clicks Remove)
    Given the following "users" exist:
      | username | firstname | lastname | email         |
      | learner1 | Learner   | One      | l@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | learner1 | C1     | student |
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete img" "css_element"
    And I click on "Remove restriction" "button" in the "Remove the Once met restriction?" "dialogue"
    Then ".availability_oncemet" "css_element" should not exist
    And I click on "Save and return to course" "button"
    And I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    And ".availability_oncemet" "css_element" should not exist

  Scenario: Removing a Once met restriction which holds unlocks has to be confirmed (No unlocks to be confirmed)
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete img" "css_element"
    Then ".availability_oncemet" "css_element" should not exist

  Scenario: Removing a Once met restriction which holds unlocks has to be confirmed (Just the teacher unlock exists)
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = t@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | teacher1 | PAGE1    | UNLOCK1    |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item > .availability-delete img" "css_element"
    Then ".availability_oncemet" "css_element" should not exist

  Scenario: Removing a Once met restriction which holds unlocks has to be confirmed (Restriction is just added)
    Given the following "users" exist:
      | username | firstname | lastname | email         |
      | learner1 | Learner   | One      | l@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | learner1 | C1     | student |
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button" in the ".availability-field > .availability-list > .availability-inner > .availability-button" "css_element"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    When I click on ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item > .availability-delete img" "css_element"
    Then ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item" "css_element" should not exist
    And ".availability_oncemet .availability_profile" "css_element" should exist

  Scenario: Removing a Once met restriction which holds unlocks has to be confirmed (No need to confirm inner restrictions)
    Given the following "users" exist:
      | username | firstname | lastname | email         |
      | learner1 | Learner   | One      | l@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | learner1 | C1     | student |
    And the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile               |
      | PAGE1    | UNLOCK1    | email = l@example.com |
    And the following "availability_oncemet > unlocks" exist:
      | user     | activity | instanceid |
      | learner1 | PAGE1    | UNLOCK1    |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    When I click on ".availability-oncemet-children .availability-item > .availability-delete img" "css_element"
    Then ".availability_oncemet .availability_profile" "css_element" should not exist
    And ".availability_oncemet" "css_element" should exist

  Scenario: Several nested restrictions survive the round trip
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    And I set the field "User profile field" to "Email address"
    And I set the field "Value to compare against" to "l@example.com"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "Date" "button" in the "Add restriction..." "dialogue"
    And I click on "Save and return to course" "button"
    When I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    Then ".availability_oncemet .availability_profile" "css_element" should exist
    And ".availability_oncemet .availability_date" "css_element" should exist
    And the field "Value to compare against" matches value "l@example.com"

  Scenario: A Once met restriction can be nested inside a restriction set
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Restriction set" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability-childlist" "css_element"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    And I set the field "User profile field" to "Email address"
    And I set the field "Value to compare against" to "l@example.com"
    And I click on "Save and return to course" "button"
    When I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    Then ".availability-childlist .availability_oncemet" "css_element" should exist
    And the field "Value to compare against" matches value "l@example.com"

  # This covers the form side of nesting only: that the inner block is offered, stored and read back
  # in place. Whether the identifiers of nested blocks are all found again when the restriction is
  # removed is a question for the observer, and is answered by condition_test::test_get_instance_ids_nested().
  Scenario: A Once met restriction can be nested inside another Once met restriction
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    And I set the field "User profile field" to "Email address"
    And I set the field "Value to compare against" to "l@example.com"
    And I click on "Save and return to course" "button"
    When I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    Then ".availability_oncemet .availability_oncemet .availability_profile" "css_element" should exist
    And the field "Value to compare against" matches value "l@example.com"

  # Two blocks in one form have to keep their nested restrictions apart rather than mirror each
  # other. That they also keep their instance ids apart cannot be seen in the form, as a shared id
  # would round-trip just as well; it shows up as a lost unlock and is covered by
  # availability_oncemet_restrict.feature.
  Scenario: Two Once met restrictions on one activity keep their own contents
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability_oncemet .availability-list" "css_element"
    And I click on "User profile" "button" in the "Add restriction..." "dialogue"
    And I set the field "User profile field" to "Email address"
    And I set the field "Value to compare against" to "l@example.com"
    And I click on "Add restriction..." "button" in the ".availability-field > .availability-list > .availability-inner > .availability-button" "css_element"
    And I click on "Once met" "button" in the "Add restriction..." "dialogue"
    And I click on "Add restriction..." "button" in the ".availability-field > .availability-list > .availability-inner > .availability-children > .availability-item ~ .availability-item .availability_oncemet .availability-list" "css_element"
    And I click on "Date" "button" in the "Add restriction..." "dialogue"
    And I click on "Save and return to course" "button"
    When I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    Then ".availability-item .availability_oncemet .availability_profile" "css_element" should exist
    And ".availability-item ~ .availability-item .availability_oncemet .availability_date" "css_element" should exist
    And the field "Value to compare against" matches value "l@example.com"

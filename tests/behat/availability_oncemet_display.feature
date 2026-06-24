@availability @availability_oncemet @availability_oncemet_display
Feature: Show a Once met restriction to staff and hide the mechanism from learners
  In order to understand why an item is locked
  As a learner
  I need to see the nested restrictions as if they were the restrictions of the item itself

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email         | department |
      | teacher1 | Teacher   | One      | t@example.com | Somewhere  |
      | learner1 | Learner   | One      | l@example.com | Somewhere  |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | learner1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name   | idnumber |
      | page     | C1     | 1       | Page 1 | PAGE1    |
      | page     | C1     | 2       | Page 2 | PAGE2    |

  Scenario: Only the teacher is told that a Once met restriction is in play
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    When I am on the "Course 1" course page logged in as "teacher1"
    Then I should see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"
    # A single nested restriction is one sentence, so core renders it inline instead of opening a
    # list for it. Nothing may be indented here, neither by core nor by the plugin.
    And "ul[data-region='availability-multiple']" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    And ".availability-oncemet-restrictions" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    And I log out
    # The learner is shown the nested restriction as if it had been added to the activity directly.
    And I am on the "Course 1" course page logged in as "learner1"
    Then I should not see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"
    And "ul[data-region='availability-multiple']" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    And "Page 1" "link" should not exist in the "region-main" "region"

  Scenario: A negated Once met restriction is described as one which has not been met yet
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | rootop  | profile               |
      | PAGE1    | UNLOCK1    | not and | email = l@example.com |
    When I am on the "Course 1" course page logged in as "teacher1"
    Then I should see "Not yet met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is l@example.com" in the "Page 1" "core_availability > Activity availability"
    And I log out
    # The learner matches the nested restriction, so the negated condition locks them out. The
    # Once met label stays hidden and the nested restriction is handed to them inverted instead.
    And I am on the "Course 1" course page logged in as "learner1"
    Then I should not see "Not yet met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is not l@example.com" in the "Page 1" "core_availability > Activity availability"
    And "Page 1" "link" should not exist in the "region-main" "region"

  # Core renders every entry of a restriction list as a bullet of one and the same list, so the
  # label and the nested restrictions would end up next to each other rather than below each other.
  # The plugin hands them over as one entry which carries a list of its own, see
  # availability_oncemet\condition::wrap_multiple_description().
  @javascript
  Scenario: Several nested restrictions are listed below the Once met label
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                                          |
      | PAGE1    | UNLOCK1    | email = nobody@example.com, department = Nowhere |
    When I am on the "Course 1" course page logged in as "teacher1"
    # Two nested restrictions are past the 100 characters at which the course page shortens
    # availability info, so the full reason only becomes visible after opening it.
    And I click on "Show more" "button" in the "Page 1" "core_availability > Activity availability"
    Then I should see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Department is Nowhere" in the "Page 1" "core_availability > Activity availability"
    # The nested list has to sit inside the list item which carries the label, not beside it as a
    # second item of the outer list. This is the assertion which tells the two apart, and with it
    # the indented rendering from the flat one.
    And "ul[data-region='availability-multiple'] > li > ul.availability-oncemet-restrictions" "css_element" should exist in the "Page 1" "core_availability > Activity availability"
    # The nested tree brings its own list header and its own list, one level further in again, so
    # the two restrictions end up two levels below the label rather than next to it. The header is
    # asserted against the whole availability info rather than against the nested list, because the
    # page also holds the collapsed copy of it, in which the plugin stylesheet hides that list.
    And I should see "All of:" in the "Page 1" "core_availability > Activity availability"
    And "ul.availability-oncemet-restrictions > li > ul[data-region='availability-multiple'] > li" "css_element" should exist in the "Page 1" "core_availability > Activity availability"

  # The counterpart of the scenario above: what the learner gets has to be indistinguishable from
  # two restrictions which were added to the activity directly, so neither the Once met wrapper nor
  # the extra list level it brings for staff may show up here.
  @javascript
  Scenario: The learner sees several nested restrictions at the depth of ordinary restrictions
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                                          |
      | PAGE1    | UNLOCK1    | email = nobody@example.com, department = Nowhere |
    When I am on the "Course 1" course page logged in as "learner1"
    And I click on "Show more" "button" in the "Page 1" "core_availability > Activity availability"
    Then I should not see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should not see "All of:" in the "Page 1" "core_availability > Activity availability"
    And ".availability-oncemet-restrictions" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    # Both restrictions are items of the very first list, and nothing is nested below them.
    And I should see "Your Email address is nobody@example.com" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Department is Nowhere" in the "Page 1" "core_availability > Activity availability"
    And "ul[data-region='availability-multiple'] > li > ul" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    And "Page 1" "link" should not exist in the "region-main" "region"

  # A negated restriction with several nested ones takes the other branch of
  # wrap_multiple_description(): the label changes, the list around the nested restrictions does
  # not. The learner meets both nested restrictions here, which is what makes the negated condition
  # lock them out and puts a reason on the page in the first place.
  @javascript
  Scenario: A negated Once met restriction keeps the nesting and only swaps the label
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | rootop  | profile                                       |
      | PAGE1    | UNLOCK1    | not and | email = l@example.com, department = Somewhere |
    When I am on the "Course 1" course page logged in as "teacher1"
    And I click on "Show more" "button" in the "Page 1" "core_availability > Activity availability"
    Then I should see "Not yet met at least once:" in the "Page 1" "core_availability > Activity availability"
    And I should not see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    # The nested restrictions are described as they are configured, the negation is expressed by
    # the label alone, and they sit at the same depth as in the positive case.
    And I should see "All of:" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is l@example.com" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Department is Somewhere" in the "Page 1" "core_availability > Activity availability"
    And "ul[data-region='availability-multiple'] > li > ul.availability-oncemet-restrictions" "css_element" should exist in the "Page 1" "core_availability > Activity availability"
    And "ul.availability-oncemet-restrictions > li > ul[data-region='availability-multiple'] > li" "css_element" should exist in the "Page 1" "core_availability > Activity availability"
    And I log out
    # The learner gets the nested restrictions inverted and at ordinary depth, with no wrapper.
    And I am on the "Course 1" course page logged in as "learner1"
    And I click on "Show more" "button" in the "Page 1" "core_availability > Activity availability"
    Then I should not see "met at least once" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Email address is not l@example.com" in the "Page 1" "core_availability > Activity availability"
    And I should see "Your Department is not Somewhere" in the "Page 1" "core_availability > Activity availability"
    And ".availability-oncemet-restrictions" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    And "ul[data-region='availability-multiple'] > li > ul" "css_element" should not exist in the "Page 1" "core_availability > Activity availability"
    And "Page 1" "link" should not exist in the "region-main" "region"

  # The excerpt which the course page shows while the reason is collapsed is built from the list
  # header and the first entry of the list. The nested restrictions are part of that first entry,
  # so without the plugin stylesheet they would be spelled out in full within a single line of text.
  @javascript
  Scenario: The collapsed availability excerpt leaves the nested restrictions out
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                                          |
      | PAGE1    | UNLOCK1    | email = nobody@example.com, department = Nowhere |
    When I am on the "Course 1" course page logged in as "teacher1"
    Then ".availability-excerpt" "css_element" should exist
    And I should see "Met at least once:" in the "Page 1" "core_availability > Activity availability"
    And ".availability-excerpt .availability-oncemet-restrictions" "css_element" should not be visible

  Scenario: The restricted activity page names the nested restriction
    Given the following "availability_oncemet > activity restrictions" exist:
      | activity | instanceid | profile                    |
      | PAGE1    | UNLOCK1    | email = nobody@example.com |
    When I am on the "Page 1" "page activity" page logged in as "learner1"
    Then I should see "Your Email address is nobody@example.com" in the "region-main" "region"
    And I should not see "Met at least once:" in the "region-main" "region"

  Scenario: A restricted section names the nested restriction and hides its content
    Given the following "availability_oncemet > section restrictions" exist:
      | course | section | instanceid | profile                    |
      | C1     | 1       | UNLOCK1    | email = nobody@example.com |
    When I am on the "Course 1" course page logged in as "learner1"
    Then I should see "Your Email address is nobody@example.com" in the "section-1" "core_availability > Section availability"
    And I should not see "Met at least once:" in the "region-main" "region"
    And I should not see "Page 1" in the "region-main" "region"
    And I should see "Page 2" in the "region-main" "region"
    And I log out
    And I am on the "Course 1" course page logged in as "teacher1"
    Then I should see "Met at least once:" in the "section-1" "core_availability > Section availability"

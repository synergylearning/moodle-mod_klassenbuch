@mod @mod_klassenbuch
Feature: A new klassenbuch module can be created

  Background:
    Given the following klassenbuch global fields exist:
      | title   |
      | Field 1 |
      | Field 2 |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | teacher1 |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | teacher1 | editingteacher |

  Scenario: A teacher can create a Klassenbuch instance
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Klassenbuch" to section "1" and I fill the form with:
      | Name    | Test Klassenbuch  |
      | Summary | Klassenbuch intro |
    And I follow "Test Klassenbuch"
    And I set the following fields to these values:
      | Chapter title | Chapter 1           |
      | Field 1       | Content for field 1 |
    And I press "Save changes"
    Then I should see "Chapter 1"
    And I should see "Content for field 1"
    And I should not see "Save changes"
    And I should not see "Field 2"

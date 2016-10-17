@mod @mod_klassenbuch
Feature: A user can print out a klassenbuch

  Background:
    Given the following "users" exist:
      | username |
      | teacher1 |
      | student1 |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | teacher1 | editingteacher |
      | C1     | student1 | student        |
    And the following klassenbuch global fields exist:
      | title   |
      | Field 1 |
      | Field 2 |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Klassenbuch" to section "1" and I fill the form with:
      | Name | Klassenbuch test |
    And I follow "Klassenbuch test"
    And I set the following fields to these values:
      | Chapter title | Chapter 1           |
      | Field 1       | Content for field 1 |
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: A student can print out a klassenbuch
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Klassenbuch test"
    And I navigate to "Print klassenbuch" node in "Klassenbuch administration"
    And I switch to "popup" window
    Then I should see "Klassenbuch test"
    And I switch to the main window

  @javascript
  Scenario: A student can print out a klassenbuch chapter
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Klassenbuch test"
    And I navigate to "Print this chapter" node in "Klassenbuch administration"
    And I switch to "popup" window
    Then I should see "Chapter 1"
    And I should see "Content for field 1"
    And I switch to the main window

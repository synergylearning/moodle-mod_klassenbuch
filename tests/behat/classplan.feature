@mod @mod_klassenbuch @javascript
Feature: A class plan can be added to the klassenbuch module

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
    And the following "activities" exist:
      | activity    | name          | idnumber     | course | showclassplan |
      | klassenbuch | Klassenbuch 1 | klassenbuch1 | C1     | 1             |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Klassenbuch 1"
    And I set the following fields to these values:
      | Chapter title | Chapter 1       |
      | Field 1       | Field 1 content |
      | Field 2       | Field 2 content |
    And I press "Save changes"
    And I navigate to "Turn editing on" node in "Klassenbuch administration"

  Scenario: If a teacher navigates away from a form that has been autosaved, then their entry is retrieved as expected
    When I click on "View Class Plan" "link" in the "Chapter 1" "list_item"
    And I click on "Add Row" "link"

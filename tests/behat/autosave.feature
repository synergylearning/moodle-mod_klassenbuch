@mod @mod_klassenbuch @javascript
Feature: Whilst editing klassenbuch chapters the autosave feature correctly saves the work in progress

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
      | activity    | name          | idnumber     | course |
      | klassenbuch | Klassenbuch 1 | klassenbuch1 | C1     |
    And the following config values are set as admin:
      | autosaveseconds | 4 | klassenbuch |
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
    When I click on "Add new chapter" "link" in the "Chapter 1" "list_item"
    And I set the following fields to these values:
      | Chapter title | Chapter 2                        |
      | Field 1       | The content of chapter 2 field 1 |
      | Field 2       | The content of chapter 2 field 2 |
    And I wait "5" seconds
    And I should see "Autosaved on:"
    And I follow "Klassenbuch 1"
    And I click on "Edit" "link" in the "Chapter 2" "list_item"
    Then the following fields match these values:
      | Chapter title | Chapter 2                        |
      | Field 1       | The content of chapter 2 field 1 |
      | Field 2       | The content of chapter 2 field 2 |
    And I press "Cancel"

  Scenario: If a teacher cancels the editing of a new chapter that is already autosaved, then it will be deleted
    When I click on "Add new chapter" "link" in the "Chapter 1" "list_item"
    And I set the following fields to these values:
      | Chapter title | Chapter 2                        |
      | Field 1       | The content of chapter 2 field 1 |
      | Field 2       | The content of chapter 2 field 2 |
    And I wait "5" seconds
    And I should see "Autosaved on:"
    And I press "Cancel"
    Then I should not see "Chapter 2"

  Scenario: If a teacher saves a chapter after it has been autosaved, then it should save correctly
    When I click on "Add new chapter" "link" in the "Chapter 1" "list_item"
    And I set the following fields to these values:
      | Chapter title | Chapter 2                        |
      | Field 1       | The content of chapter 2 field 1 |
      | Field 2       | The content of chapter 2 field 2 |
    And I wait "5" seconds
    And I should see "Autosaved on:"
    And I press "Save changes"
    Then I should see "The content of chapter 2 field 1"
    And I should see "The content of chapter 2 field 2"
    And I should not see "Save changes"
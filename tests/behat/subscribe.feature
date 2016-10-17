@mod @mod_klassenbuch
Feature: Users can subscribe to a klassenbuch and receive updates when sent by a teacher

  Background:
    Given the following klassenbuch global fields exist:
      | title   |
      | Field 1 |
      | Field 2 |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher  | 1        |
      | student1 | Student  | 1        |
      | student2 | Student  | 2        |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | teacher1 | editingteacher |
      | C1     | student1 | student        |
      | C1     | student2 | student        |
    And the following "activities" exist:
      | activity    | name          | idnumber     | course |
      | klassenbuch | Klassenbuch 1 | klassenbuch1 | C1     |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Klassenbuch 1"
    And I set the following fields to these values:
      | Chapter title | Chapter 1       |
      | Field 1       | Field 1 content |
      | Field 2       | Field 2 content |
    And I press "Save changes"
    And I follow "Turn editing on"

  Scenario: If everyone is subscribed, the chapter is sent to everyone
    When I follow "Everyone will be subscribed to this book"
    And I should see "This book forces everyone to subscribe"
    And I follow "Allow everyone to choose"
    And I should see "Everyone can choose to subscribe to this book"
    And I follow "Everyone will be subscribed to this book"
    And I click on "Send" "link" in the "Chapter 1" "list_item"
    Then I should see "3 participants sent chapter 'Chapter 1'"

  Scenario: When no one has subscribed, 'show subscribers' shows this correctly
    When I follow "Show subscribers"
    Then I should see "No one has subscribed to this book"

  @javascript
  Scenario: If subscription is optional, then users can subscribe to the book
    Given I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Klassenbuch 1"
    And I follow "I want to subscribe to this book"
    And I follow "I want to unsubscribe from this book"
    And I follow "I want to subscribe to this book"
    And I log out

    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Klassenbuch 1"
    And I follow "Show subscribers"
    Then I should see "Student 1"

    When I follow "Klassenbuch 1"
    And I follow "Turn editing on"
    And I click on "Send" "link" in the "Chapter 1" "list_item"
    Then I should see "1 participants sent chapter 'Chapter 1'"